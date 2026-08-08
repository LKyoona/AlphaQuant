<?php

namespace api\common\service;

/**
 * Minimal Coinbase Advanced Trade client for the legacy PHP CCXT runtime.
 * Coinbase uses an ES256 JWT, while the bundled CCXT version predates it.
 */
class CoinbaseAdvancedService
{
    private $apiKey;
    private $privateKey;
    private $host = 'api.coinbase.com';

    public function __construct($apiKey, $privateKey)
    {
        $this->apiKey = trim((string) $apiKey);
        $this->privateKey = str_replace('\\n', "\n", trim((string) $privateKey));
    }

    public function validateCredentials()
    {
        $response = $this->request('GET', '/api/v3/brokerage/accounts');
        return isset($response['accounts']) && is_array($response['accounts']);
    }

    public function fetchBalance()
    {
        // Coinbase signs the resource path without the query string.
        $response = $this->request(
            'GET',
            '/api/v3/brokerage/accounts?limit=250',
            true,
            '/api/v3/brokerage/accounts'
        );
        $balance = ['free' => [], 'used' => [], 'total' => []];
        foreach ((array) ($response['accounts'] ?? []) as $account) {
            $currency = strtoupper((string) ($account['currency'] ?? ''));
            if ($currency === '') {
                continue;
            }
            $free = $this->number(($account['available_balance']['value'] ?? 0));
            $used = $this->number(($account['hold']['value'] ?? 0));
            $total = $free + $used;
            $balance[$currency] = [
                'currency' => $currency,
                'free' => $free,
                'used' => $used,
                'total' => $total,
            ];
            $balance['free'][$currency] = $free;
            $balance['used'][$currency] = $used;
            $balance['total'][$currency] = $total;
        }
        return $balance;
    }

    public function fetchTicker($symbol)
    {
        $product = strtoupper(str_replace('/', '-', trim((string) $symbol)));
        $response = $this->request('GET', '/api/v3/brokerage/market/products/' . rawurlencode($product), false);
        $price = $response['price'] ?? ($response['price_percentage_change_24h'] ?? 0);
        return ['last' => $this->number($price), 'symbol' => str_replace('-', '/', $product)];
    }

    private function request($method, $path, $authenticated = true, $jwtPath = null)
    {
        $headers = ['Accept: application/json', 'Content-Type: application/json'];
        if ($authenticated) {
            $headers[] = 'Authorization: Bearer ' . $this->createJwt($method, $jwtPath ?: $path);
        }
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP cURL 扩展未启用');
        }
        $curl = \curl_init('https://' . $this->host . $path);
        \curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = \curl_exec($curl);
        $error = \curl_error($curl);
        $status = (int) \curl_getinfo($curl, CURLINFO_HTTP_CODE);
        \curl_close($curl);
        if ($body === false) {
            throw new \RuntimeException('Coinbase network error: ' . $error);
        }
        $data = json_decode($body, true);
        if ($status < 200 || $status >= 300 || !is_array($data)) {
            throw new \RuntimeException('Coinbase API HTTP ' . $status . ': ' . $body);
        }
        return $data;
    }

    private function createJwt($method, $path)
    {
        if ($this->apiKey === '' || $this->privateKey === '') {
            throw new \RuntimeException('Coinbase API Key 或 EC Private Key 为空');
        }
        $header = ['alg' => 'ES256', 'kid' => $this->apiKey, 'nonce' => bin2hex(random_bytes(16)), 'typ' => 'JWT'];
        $now = time();
        $payload = ['sub' => $this->apiKey, 'iss' => 'cdp', 'nbf' => $now, 'exp' => $now + 120, 'uri' => strtoupper($method) . ' ' . $this->host . $path];
        $encodedHeader = $this->base64url(json_encode($header));
        $encodedPayload = $this->base64url(json_encode($payload));
        $signingInput = $encodedHeader . '.' . $encodedPayload;
        $privateKey = openssl_pkey_get_private($this->privateKey);
        if ($privateKey === false || !openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Coinbase EC Private Key 无效');
        }
        // ES256 uses two 32-byte integers (r and s), joined into 64 bytes.
        return $signingInput . '.' . $this->base64url($this->derToJose($signature, 32));
    }

    private function derToJose($der, $partLength)
    {
        $offset = 2;
        if (ord($der[1]) & 0x80) {
            $offset = 2 + (ord($der[1]) & 0x7f);
        }
        $rLength = ord($der[$offset + 1]);
        $r = substr($der, $offset + 2, $rLength);
        $offset += 2 + $rLength;
        $sLength = ord($der[$offset + 1]);
        $s = substr($der, $offset + 2, $sLength);
        $r = str_pad(ltrim($r, "\x00"), $partLength, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), $partLength, "\x00", STR_PAD_LEFT);
        return substr($r, -$partLength) . substr($s, -$partLength);
    }

    private function base64url($value)
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function number($value)
    {
        return round((float) $value, 12);
    }
}
