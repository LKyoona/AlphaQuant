<?php

namespace api\common\service;

class KrakenSpotService
{
    const API_URL = 'https://api.kraken.com';

    private $apiKey;
    private $secret;
    private static $lastNonce = 0;

    public function __construct($apiKey, $secret)
    {
        $this->apiKey = trim((string) $apiKey);
        $this->secret = trim((string) $secret);
        if ($this->apiKey === '' || $this->secret === '') {
            throw new \RuntimeException('Kraken API Key 或 Secret 无效');
        }
    }

    public function validateCredentials()
    {
        return $this->privateRequest('GetApiKeyInfo');
    }

    public function fetchBalance()
    {
        try {
            $result = $this->privateRequest('BalanceEx');
            return $this->normalizeBalanceEx($result);
        } catch (\RuntimeException $e) {
            if (stripos($e->getMessage(), 'Unknown method') === false) {
                throw $e;
            }
        }

        return $this->normalizeBalance($this->privateRequest('Balance'));
    }

    public function fetchUsdtPrices(array $symbols)
    {
        $prices = ['USDT' => 1.0];
        $usdtUsd = null;
        foreach (array_values(array_unique(array_map('strtoupper', $symbols))) as $symbol) {
            if ($symbol === '' || isset($prices[$symbol])) {
                continue;
            }
            $krakenSymbol = $symbol === 'BTC' ? 'XBT' : $symbol;
            $price = $this->fetchPublicTickerPrice($krakenSymbol . 'USDT');
            if ($price === null) {
                $assetUsd = $symbol === 'USD' ? 1.0 : $this->fetchPublicTickerPrice($krakenSymbol . 'USD');
                if ($assetUsd !== null) {
                    if ($usdtUsd === null) {
                        $usdtUsd = $this->fetchPublicTickerPrice('USDTUSD');
                    }
                    if ($usdtUsd !== null && $usdtUsd > 0) {
                        $price = $assetUsd / $usdtUsd;
                    }
                }
            }
            if ($price !== null && $price > 0) {
                $prices[$symbol] = $price;
            }
        }
        return $prices;
    }

    private function privateRequest($method, array $params = [])
    {
        $lastException = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return $this->privateRequestOnce($method, $params);
            } catch (\RuntimeException $e) {
                $lastException = $e;
                if (stripos($e->getMessage(), 'Nonce') === false || $attempt === 2) {
                    throw $e;
                }
                usleep(100000 * ($attempt + 1));
            }
        }

        throw $lastException ?: new \RuntimeException('Kraken API 请求失败');
    }

    private function privateRequestOnce($method, array $params = [])
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Kraken API 连接失败:PHP curl 扩展未启用');
        }

        $nonceLock = $this->acquireNonceLock();
        try {
            $path = '/0/private/' . $method;
            $params['nonce'] = $this->nextNonce($nonceLock);
            $postData = http_build_query($params, '', '&');
            $decodedSecret = base64_decode($this->secret, true);
            if ($decodedSecret === false) {
                throw new \RuntimeException('Kraken API Secret 格式无效');
            }
            $digest = hash('sha256', $params['nonce'] . $postData, true);
            $signature = base64_encode(hash_hmac('sha512', $path . $digest, $decodedSecret, true));

            $ch = curl_init(self::API_URL . $path);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postData,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => [
                    'API-Key: ' . $this->apiKey,
                    'API-Sign: ' . $signature,
                    'Accept: application/json',
                    'Content-Type: application/x-www-form-urlencoded',
                    'User-Agent: lhqb-kraken/1.0',
                ],
            ]);
            $body = curl_exec($ch);
            $curlError = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false) {
                throw new \RuntimeException('Kraken API 连接失败:' . $curlError);
            }
            $payload = json_decode($body, true);
            if (!is_array($payload)) {
                throw new \RuntimeException('Kraken API 返回格式异常');
            }
            if ($status < 200 || $status >= 300) {
                throw new \RuntimeException('Kraken API 请求失败:HTTP ' . $status);
            }
            if (!empty($payload['error'])) {
                $this->throwApiError((array) $payload['error']);
            }
            if (!isset($payload['result']) || !is_array($payload['result'])) {
                throw new \RuntimeException('Kraken API 返回格式异常');
            }

            return $payload['result'];
        } finally {
            flock($nonceLock, LOCK_UN);
            fclose($nonceLock);
        }
    }

    private function fetchPublicTickerPrice($pair)
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Kraken API 连接失败:PHP curl 扩展未启用');
        }
        $pair = strtoupper(trim((string) $pair));
        $cacheKey = 'kraken_public_ticker_' . md5($pair);
        $cachedPrice = function_exists('cache') ? (float) cache($cacheKey) : 0.0;
        $url = self::API_URL . '/0/public/Ticker?' . http_build_query(['pair' => $pair], '', '&');

        // Kraken's TLS handshake can occasionally be slow from overseas servers.
        // Retry transient network failures and use the latest successful quote as a
        // fallback so one ticker request cannot invalidate the whole balance query.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_NOSIGNAL => true,
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Connection: close',
                    'User-Agent: lhqb-kraken/1.0',
                ],
            ]);
            $body = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body !== false && $status >= 200 && $status < 300) {
                $payload = json_decode($body, true);
                if (is_array($payload)) {
                    if (!empty($payload['error'])) {
                        return null;
                    }
                    if (!empty($payload['result']) && is_array($payload['result'])) {
                        $ticker = reset($payload['result']);
                        $price = is_array($ticker) && isset($ticker['c'][0]) ? (float) $ticker['c'][0] : 0.0;
                        if ($price > 0) {
                            if (function_exists('cache')) {
                                cache($cacheKey, $price, 1800);
                            }
                            return $price;
                        }
                    }
                }
            }

            if ($attempt < 2) {
                usleep(200000 * ($attempt + 1));
            }
        }

        return $cachedPrice > 0 ? $cachedPrice : null;
    }

    private function normalizeBalanceEx(array $result)
    {
        $assets = [];
        foreach ($result as $rawSymbol => $item) {
            if (!is_array($item)) {
                continue;
            }
            $balance = (float) ($item['balance'] ?? 0);
            $credit = (float) ($item['credit'] ?? 0);
            $creditUsed = (float) ($item['credit_used'] ?? 0);
            $used = max(0, (float) ($item['hold_trade'] ?? 0));
            $total = max(0, $balance + $credit - $creditUsed);
            $free = max(0, $total - $used);
            $this->appendAsset($assets, $rawSymbol, $free, $used, $total);
        }
        return $this->toCcxtBalance($assets, $result);
    }

    private function normalizeBalance(array $result)
    {
        $assets = [];
        foreach ($result as $rawSymbol => $amount) {
            $total = max(0, (float) $amount);
            $this->appendAsset($assets, $rawSymbol, $total, 0, $total);
        }
        return $this->toCcxtBalance($assets, $result);
    }

    private function appendAsset(array &$assets, $rawSymbol, $free, $used, $total)
    {
        $symbol = $this->normalizeAssetSymbol($rawSymbol);
        if ($symbol === '') {
            return;
        }
        if (!isset($assets[$symbol])) {
            $assets[$symbol] = ['free' => 0.0, 'used' => 0.0, 'total' => 0.0];
        }
        $assets[$symbol]['free'] += (float) $free;
        $assets[$symbol]['used'] += (float) $used;
        $assets[$symbol]['total'] += (float) $total;
    }

    private function toCcxtBalance(array $assets, array $info)
    {
        $balance = ['info' => $info, 'free' => [], 'used' => [], 'total' => []];
        foreach ($assets as $symbol => $item) {
            foreach (['free', 'used', 'total'] as $field) {
                $item[$field] = round((float) $item[$field], 12);
                $balance[$field][$symbol] = $item[$field];
            }
            $balance[$symbol] = $item;
        }
        return $balance;
    }

    private function normalizeAssetSymbol($rawSymbol)
    {
        $symbol = strtoupper(trim((string) $rawSymbol));
        $symbol = preg_replace('/\.(F|B|T)$/', '', $symbol);
        $aliases = [
            'XXBT' => 'BTC', 'XBT' => 'BTC',
            'XETH' => 'ETH', 'XXRP' => 'XRP',
            'ZUSD' => 'USD', 'ZEUR' => 'EUR',
            'ZGBP' => 'GBP', 'ZJPY' => 'JPY',
            'ZCAD' => 'CAD', 'ZAUD' => 'AUD',
        ];
        if (isset($aliases[$symbol])) {
            return $aliases[$symbol];
        }
        if (strlen($symbol) === 4 && ($symbol[0] === 'X' || $symbol[0] === 'Z')) {
            return substr($symbol, 1);
        }
        return $symbol;
    }

    private function throwApiError(array $errors)
    {
        $message = implode('; ', array_map('strval', $errors));
        if (stripos($message, 'Invalid key') !== false || stripos($message, 'Invalid signature') !== false) {
            throw new \RuntimeException('Kraken API Key 或 Secret 无效');
        }
        if (stripos($message, 'Invalid nonce') !== false) {
            throw new \RuntimeException('Kraken API Nonce 无效，请稍后重试');
        }
        if (stripos($message, 'Permission denied') !== false) {
            throw new \RuntimeException('Kraken API 权限不足');
        }
        throw new \RuntimeException('Kraken API 请求失败:' . $message);
    }

    private function acquireNonceLock()
    {
        $nonceDir = trim((string) getenv('KRAKEN_NONCE_DIR'));
        if ($nonceDir === '') {
            $nonceDir = DIRECTORY_SEPARATOR === '\\'
                ? sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lhqb-kraken-nonce'
                : '/data/lhqb/shared/runtime/kraken-nonce';
        }
        if (!is_dir($nonceDir) && !mkdir($nonceDir, 0777, true) && !is_dir($nonceDir)) {
            throw new \RuntimeException('Kraken API Nonce 目录创建失败，请稍后重试');
        }
        @chmod($nonceDir, 0777);
        $lockPath = $nonceDir . DIRECTORY_SEPARATOR . 'lhqb-kraken-nonce-' . hash('sha256', $this->apiKey) . '.lock';
        $handle = fopen($lockPath, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new \RuntimeException('Kraken API Nonce 锁定失败，请稍后重试');
        }
        @chmod($lockPath, 0666);
        return $handle;
    }

    private function nextNonce($nonceLock = null)
    {
        $time = gettimeofday();
        $nonce = (int) ((string) $time['sec'] . str_pad((string) $time['usec'], 6, '0', STR_PAD_LEFT) . '000');
        $previous = self::$lastNonce;

        if (is_resource($nonceLock)) {
            rewind($nonceLock);
            $storedNonce = trim((string) stream_get_contents($nonceLock));
            if ($storedNonce !== '' && ctype_digit($storedNonce)) {
                $previous = max($previous, (int) $storedNonce);
            }
        }
        if ($nonce <= $previous) {
            $nonce = $previous + 1;
        }
        self::$lastNonce = $nonce;

        if (is_resource($nonceLock)) {
            rewind($nonceLock);
            ftruncate($nonceLock, 0);
            fwrite($nonceLock, (string) $nonce);
            fflush($nonceLock);
        }
        return (string) $nonce;
    }
}
