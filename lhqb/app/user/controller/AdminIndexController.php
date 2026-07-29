<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Powerless < wzxaini9@gmail.com>
// +----------------------------------------------------------------------

namespace app\user\controller;

use cmf\controller\AdminBaseController;
use think\Db;

/**
 * Class AdminIndexController
 * @package app\user\controller
 *
 * @adminMenuRoot(
 *     'name'   =>'用户管理',
 *     'action' =>'default',
 *     'parent' =>'',
 *     'display'=> true,
 *     'order'  => 10,
 *     'icon'   =>'group',
 *     'remark' =>'用户管理'
 * )
 *
 * @adminMenuRoot(
 *     'name'   =>'用户组',
 *     'action' =>'default1',
 *     'parent' =>'user/AdminIndex/default',
 *     'display'=> true,
 *     'order'  => 10000,
 *     'icon'   =>'',
 *     'remark' =>'用户组'
 * )
 */
class AdminIndexController extends AdminBaseController
{

    /**
     * 后台本站用户列表
     * @adminMenu(
     *     'name'   => '本站用户',
     *     'parent' => 'default1',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '本站用户',
     *     'param'  => ''
     * )
     */
    public function index()
    {
        $content = hook_one('user_admin_index_view');

        if (!empty($content)) {
            return $content;
        }

        $where   = [];
        $request = input('request.');
       // var_dump($this->request);

        $filterid = session('ADMIN_ID');
      // var_dump($_SESSION); 

        if (!empty($request['uid'])) {
            $where['u.id'] = intval($request['uid']);
        }

        $keywordComplex = [];
        if (!empty($request['keyword'])) {
            $keyword = $request['keyword'];

            $keywordComplex['u.user_login|u.user_nickname|u.user_email|u.mobile'] = ['like', "%$keyword%"];
        }
        $usersQuery = Db::name('user')
            ->alias('u')
            ->join('invitation_code ic', 'u.invite_code_id = ic.id', 'LEFT')
            ->field('u.*,ic.code as source_invitation_code,ic.owner_user_id as source_invitation_owner_id');

        if ($filterid > 1) {
            // New share-code users are all direct members of the proxy team.
            $managedUserIds = Db::name('user')->where('parent_user_id', $filterid)->column('id');
            $where['u.id'] = empty($managedUserIds) ? -1 : ['in', $managedUserIds];
        }

        $list = $usersQuery->whereOr($keywordComplex)->where($where)->order("u.create_time DESC")->paginate(10);
        $items = $list->items();
        $userIds = array_column($items, 'id');
        $exchangeBalanceMap = [];
        $exchangeApiStatsMap = [];
        if (!empty($userIds) && $this->dbTableExists('firm_user')) {
            $firmUsers = Db::name('firm_user')
                ->where('uid', 'in', $userIds)
                ->field('uid,platform,total_assets_usd')
                ->select()
                ->toArray();
            foreach ($firmUsers as $firmUser) {
                $exchangeBalanceMap[(int) $firmUser['uid']] = $firmUser;
            }
        }

        if (!empty($userIds) && $this->dbTableExists('third_api')) {
            $apiStats = Db::name('third_api')
                ->where('uid', 'in', $userIds)
                ->where('status', 1)
                ->field('uid,count(*) as api_count')
                ->group('uid')
                ->select()
                ->toArray();
            foreach ($apiStats as $apiStat) {
                $exchangeApiStatsMap[(int) $apiStat['uid']] = (int) $apiStat['api_count'];
            }
        }


        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
        $this->assign('exchangeBalanceMap', $exchangeBalanceMap);
        $this->assign('exchangeApiStatsMap', $exchangeApiStatsMap);
        // 渲染模板输出
        return $this->fetch();
    }

    private function dbTableExists($name)
    {
        $tableName = config('database.prefix') . $name;
        $tables = Db::query("SHOW TABLES LIKE '" . addslashes($tableName) . "'");
        return !empty($tables);
    }

    private function normalizeExchangeBalance($balance, $currency = 'USDT')
    {
        $free = null;
        $used = null;
        $total = null;
        $resolved = false;

        if (isset($balance[$currency]) && is_array($balance[$currency])) {
            $resolved = true;
            $free = isset($balance[$currency]['free']) ? (float) $balance[$currency]['free'] : $free;
            $used = isset($balance[$currency]['used']) ? (float) $balance[$currency]['used'] : $used;
            $total = isset($balance[$currency]['total']) ? (float) $balance[$currency]['total'] : $total;
        }
        if (isset($balance['free']) && is_array($balance['free']) && isset($balance['free'][$currency])) {
            $resolved = true;
            $free = (float) $balance['free'][$currency];
        }
        if (isset($balance['used']) && is_array($balance['used']) && isset($balance['used'][$currency])) {
            $resolved = true;
            $used = (float) $balance['used'][$currency];
        }
        if (isset($balance['total']) && is_array($balance['total']) && isset($balance['total'][$currency])) {
            $resolved = true;
            $total = (float) $balance['total'][$currency];
        }
        if ($total === null) {
            $total = ($free === null ? 0 : $free) + ($used === null ? 0 : $used);
        }

        return [
            'free' => (float) ($free === null ? 0 : $free),
            'used' => (float) ($used === null ? 0 : $used),
            'total' => (float) $total,
            'resolved' => $resolved,
        ];
    }

    private function getTickerPriceInUsdt(array $tickers, $symbol)
    {
        $symbol = strtoupper(trim((string) $symbol));
        if (in_array($symbol, ['USDT', 'USDC', 'BUSD', 'FDUSD', 'TUSD', 'DAI', 'USDP'], true)) {
            return 1.0;
        }

        foreach ([$symbol . '/USDT', $symbol . '/USDC', $symbol . '/BUSD'] as $market) {
            if (empty($tickers[$market]) || !is_array($tickers[$market])) {
                continue;
            }
            foreach (['last', 'close', 'bid'] as $field) {
                if (isset($tickers[$market][$field]) && (float) $tickers[$market][$field] > 0) {
                    return (float) $tickers[$market][$field];
                }
            }
        }

        $inverseMarket = 'USDT/' . $symbol;
        if (!empty($tickers[$inverseMarket]) && is_array($tickers[$inverseMarket])) {
            foreach (['last', 'close', 'bid'] as $field) {
                if (isset($tickers[$inverseMarket][$field]) && (float) $tickers[$inverseMarket][$field] > 0) {
                    return 1 / (float) $tickers[$inverseMarket][$field];
                }
            }
        }

        foreach (['USDC', 'FDUSD', 'BTC', 'ETH', 'BNB'] as $bridge) {
            $bridgeMarket = $symbol . '/' . $bridge;
            if (empty($tickers[$bridgeMarket]) || !is_array($tickers[$bridgeMarket])) {
                continue;
            }
            $assetBridgePrice = null;
            foreach (['last', 'close', 'bid'] as $field) {
                if (isset($tickers[$bridgeMarket][$field]) && (float) $tickers[$bridgeMarket][$field] > 0) {
                    $assetBridgePrice = (float) $tickers[$bridgeMarket][$field];
                    break;
                }
            }
            if ($assetBridgePrice !== null) {
                $bridgePrice = $this->getTickerPriceInUsdt($tickers, $bridge);
                if ($bridgePrice !== null) {
                    return $assetBridgePrice * $bridgePrice;
                }
            }
        }

        return null;
    }

    private function normalizeExchangeAssets(array $balance, $source)
    {
        $freeMap = isset($balance['free']) && is_array($balance['free']) ? $balance['free'] : [];
        $usedMap = isset($balance['used']) && is_array($balance['used']) ? $balance['used'] : [];
        $totalMap = isset($balance['total']) && is_array($balance['total']) ? $balance['total'] : [];
        $symbols = array_unique(array_merge(array_keys($freeMap), array_keys($usedMap), array_keys($totalMap)));
        $assets = [];
        foreach ($symbols as $rawSymbol) {
            $symbol = strtoupper((string) $rawSymbol);
            $free = isset($freeMap[$rawSymbol]) ? (float) $freeMap[$rawSymbol] : 0.0;
            $used = isset($usedMap[$rawSymbol]) ? (float) $usedMap[$rawSymbol] : 0.0;
            $total = isset($totalMap[$rawSymbol]) ? (float) $totalMap[$rawSymbol] : 0.0;
            if ($total <= 0) {
                $total = $free + $used;
            }
            if ($free > 0 || $used > 0 || $total > 0) {
                $assets[$symbol] = compact('free', 'used', 'total', 'source');
            }
        }
        return $assets;
    }

    private function fetchBinanceFundingAssets($apiKey, $secret)
    {
        $data = $this->binanceRequest('POST', '/sapi/v1/asset/get-funding-asset', $apiKey, $secret, [
            'needBtcValuation' => 'false',
        ]);
        $assets = [];
        foreach ($data as $item) {
            $symbol = strtoupper((string) ($item['asset'] ?? ''));
            if ($symbol === '') {
                continue;
            }
            $free = (float) ($item['free'] ?? 0);
            $used = (float) ($item['locked'] ?? 0);
            if ($used <= 0) {
                $used = (float) ($item['freeze'] ?? 0) + (float) ($item['withdrawing'] ?? 0);
            }
            $total = $free + $used;
            if ($total > 0) {
                $source = 'funding';
                $assets[$symbol] = compact('free', 'used', 'total', 'source');
            }
        }
        return $assets;
    }

    private function calculateAssetsValueInUsdt($exchange, array $assetGroups)
    {
        $tickers = $exchange->fetch_tickers();
        $totalUsdt = 0.0;
        $valuedAssets = 0;
        $unpricedAssets = [];
        foreach ($assetGroups as $assets) {
            foreach ($assets as $symbol => $item) {
                $amount = (float) ($item['total'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $price = $this->getTickerPriceInUsdt($tickers, $symbol);
                if ($price === null) {
                    $unpricedAssets[] = $symbol;
                    continue;
                }
                $totalUsdt += $amount * $price;
                $valuedAssets++;
            }
        }
        return [
            'total_assets' => round($totalUsdt, 8),
            'valued_assets' => $valuedAssets,
            'unpriced_assets' => array_values(array_unique($unpricedAssets)),
        ];
    }

    private function fetchExchangeAccountOverview($exchange, $platform, $apiKey, $secret)
    {
        $errors = [];
        $spotAssets = [];
        $fundingAssets = [];
        try {
            $spotAssets = $this->normalizeExchangeAssets($exchange->fetch_balance(['type' => 'spot']), 'spot');
        } catch (\Throwable $e) {
            $errors[] = 'spot: ' . $e->getMessage();
        }
        try {
            $fundingAssets = $this->normalizeExchangeAssets($exchange->fetch_balance(['type' => 'funding']), 'funding');
        } catch (\Throwable $e) {
            $errors[] = 'funding: ' . $e->getMessage();
        }
        if (strtolower($platform) === 'binance' && empty($fundingAssets)) {
            try {
                $fundingAssets = $this->fetchBinanceFundingAssets($apiKey, $secret);
            } catch (\Throwable $e) {
                $errors[] = 'binance funding: ' . $e->getMessage();
            }
        }
        if (empty($spotAssets) && empty($fundingAssets)) {
            return [null, $errors];
        }

        $spotUsdt = $spotAssets['USDT'] ?? ['free' => 0, 'used' => 0, 'total' => 0];
        $fundingUsdt = $fundingAssets['USDT'] ?? ['free' => 0, 'used' => 0, 'total' => 0];
        $withdrawAsset = ((float) $spotUsdt['free'] > 0 || (float) $spotUsdt['used'] > 0 || (float) $spotUsdt['total'] > 0)
            ? $spotUsdt
            : $fundingUsdt;
        try {
            $valuation = $this->calculateAssetsValueInUsdt($exchange, [$spotAssets, $fundingAssets]);
        } catch (\Throwable $e) {
            $errors[] = '行情: ' . $e->getMessage();
            return [null, $errors];
        }
        $valuation['withdrawable'] = round((float) ($withdrawAsset['free'] ?? 0), 8);
        $valuation['resolved'] = true;
        return [$valuation, $errors];
    }

    private function calculateBalanceValueInUsdt($exchange, array $balance)
    {
        $usdtBalance = $this->normalizeExchangeBalance($balance, 'USDT');
        $originalUsdt = 0.0;
        if (!empty($usdtBalance['resolved'])) {
            $usdtAvailableAndFrozen = (float) $usdtBalance['free'] + (float) $usdtBalance['used'];
            $originalUsdt = max((float) $usdtBalance['total'], $usdtAvailableAndFrozen);
        }
        $totals = isset($balance['total']) && is_array($balance['total']) ? $balance['total'] : [];
        if (empty($totals)) {
            foreach ($balance as $symbol => $item) {
                if (is_array($item) && isset($item['total'])) {
                    $totals[$symbol] = $item['total'];
                }
            }
        }

        $tickers = [];
        try {
            $tickers = $exchange->fetch_tickers();
        } catch (\Throwable $e) {
            // USDT and stablecoin balances can still be valued without market data.
        }

        $convertedUsdt = 0.0;
        $valuedAssets = !empty($usdtBalance['resolved']) ? 1 : 0;
        $unpricedAssets = [];
        foreach ($totals as $symbol => $amount) {
            $symbol = strtoupper(trim((string) $symbol));
            if ($symbol === 'USDT') {
                continue;
            }
            $amount = (float) $amount;
            if ($amount <= 0) {
                continue;
            }
            $price = $this->getTickerPriceInUsdt($tickers, $symbol);
            if ($price === null) {
                $unpricedAssets[] = strtoupper((string) $symbol);
                continue;
            }
            $convertedUsdt += $amount * $price;
            $valuedAssets++;
        }

        $totalUsdt = $originalUsdt + $convertedUsdt;

        return [
            'total' => round($totalUsdt, 8),
            'original_usdt' => round($originalUsdt, 8),
            'converted_usdt' => round($convertedUsdt, 8),
            'valued_assets' => $valuedAssets,
            'unpriced_assets' => array_values(array_unique($unpricedAssets)),
            'resolved' => $valuedAssets > 0,
        ];
    }

    private function createExchangeClient($exchangeClass, $apiKey, $secret, $password)
    {
        $className = "\\ccxt\\" . $exchangeClass;
        if (!class_exists($className)) {
            throw new \RuntimeException('交易所类不存在');
        }

        return new $className([
            'apiKey' => $apiKey,
            'secret' => $secret,
            'password' => $password,
            'timeout' => 10000,
            'enableRateLimit' => true,
            'options' => [
                'defaultType' => 'spot',
                'adjustForTimeDifference' => true,
                'recvWindow' => 10000,
            ],
        ]);
    }

    private function httpRequest($method, $url, $params = [], $headers = [])
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP curl 扩展未启用');
        }

        $ch = curl_init();
        $method = strtoupper($method);
        if ($method === 'GET' && !empty($params)) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params, '', '&');
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params, '', '&'));
        }

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new \RuntimeException('网络请求失败:' . $error);
        }
        $data = json_decode($body, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $body;
            throw new \RuntimeException('Binance API 请求失败 HTTP ' . $status . ':' . $message);
        }

        return is_array($data) ? $data : [];
    }

    private function binanceRequest($method, $path, $apiKey, $secret, $params = [])
    {
        $timeResponse = $this->httpRequest('GET', 'https://api.binance.com/api/v3/time', [], []);
        if (empty($timeResponse['serverTime'])) {
            throw new \RuntimeException('同步 Binance 时间失败');
        }

        $params['timestamp'] = (int) $timeResponse['serverTime'];
        $params['recvWindow'] = 10000;
        $query = http_build_query($params, '', '&');
        $params['signature'] = hash_hmac('sha256', $query, $secret);

        return $this->httpRequest($method, 'https://api.binance.com' . $path, $params, [
            'X-MBX-APIKEY: ' . $apiKey
        ]);
    }

    private function fetchBinanceFundingExchangeBalance($apiKey, $secret, $currency = 'USDT')
    {
        $data = $this->binanceRequest('POST', '/sapi/v1/asset/get-funding-asset', $apiKey, $secret, [
            'asset' => $currency,
            'needBtcValuation' => 'false',
        ]);

        if (empty($data) || empty($data[0])) {
            return [
                'free' => 0,
                'used' => 0,
                'total' => 0,
                'account_type' => 'funding',
                'resolved' => false,
            ];
        }

        $item = $data[0];
        $free = (float) ($item['free'] ?? 0);
        $used = (float) ($item['locked'] ?? ($item['freeze'] ?? ($item['withdrawing'] ?? 0)));

        return [
            'free' => round($free, 8),
            'used' => round($used, 8),
            'total' => round($free + $used, 8),
            'account_type' => 'funding',
            'resolved' => true,
        ];
    }

    private function fetchBestExchangeBalance($exchange, $platform)
    {
        $types = ['spot', 'funding', 'margin'];
        if (in_array($platform, ['binance', 'okex', 'okex3', 'bitget'], true)) {
            $types = array_merge($types, ['future', 'swap']);
        }

        $best = ['free' => 0, 'used' => 0, 'total' => 0, 'resolved' => false];
        $errors = [];
        foreach (array_unique($types) as $type) {
            try {
                $balance = $exchange->fetch_balance(['type' => $type]);
                if (empty($balance) || !is_array($balance)) {
                    $errors[] = $type . ': empty balance response';
                    continue;
                }
                $summary = $this->calculateBalanceValueInUsdt($exchange, $balance);
                $summary['account_type'] = $type;
                if (!empty($summary['resolved']) && $summary['total'] > 0) {
                    return [$summary, $errors];
                }
                if (!empty($summary['resolved'])) {
                    $best = $summary;
                }
            } catch (\Throwable $e) {
                $errors[] = $type . ': ' . $e->getMessage();
            }
        }

        return [$best, $errors];
    }


    private function normalizeExchangeErrors(array $errors)
    {
        $normalizedErrors = [];
        $binanceErrorMap = [
            -1002 => 'Binance API 未授权，请检查 API 读取权限',
            -1021 => '服务器时间与 Binance 时间不同步',
            -1022 => 'Binance API 签名无效，请检查 Secret Key',
            -2014 => 'Binance API Key 格式不正确',
            -2015 => 'Binance API Key 无效、服务器IP未授权或API读取权限不足',
            -2017 => 'Binance API Key 已被锁定'
        ];
        foreach ($errors as $error) {
            $error = trim((string) $error);
            $normalizedError = $error;
            if (stripos($error, 'binance') !== false) {
                $errorCode = null;
                if (preg_match('/["\\\\]*code["\\\\]*\s*:\s*(-?\d+)/i', $error, $matches)) {
                    $errorCode = (int) $matches[1];
                }
                if ($errorCode !== null && isset($binanceErrorMap[$errorCode])) {
                    $normalizedError = $binanceErrorMap[$errorCode];
                } elseif (stripos($error, 'You are not authorized to execute this request') !== false) {
                    $normalizedError = $binanceErrorMap[-1002];
                } elseif (stripos($error, 'Timestamp for this request') !== false) {
                    $normalizedError = $binanceErrorMap[-1021];
                } elseif (stripos($error, 'Signature for this request is not valid') !== false) {
                    $normalizedError = $binanceErrorMap[-1022];
                } elseif (stripos($error, 'API-key format invalid') !== false) {
                    $normalizedError = $binanceErrorMap[-2014];
                } elseif (stripos($error, 'Invalid API-key, IP, or permissions for action') !== false) {
                    $normalizedError = $binanceErrorMap[-2015];
                } elseif (stripos($error, 'API Keys are locked on this account') !== false) {
                    $normalizedError = $binanceErrorMap[-2017];
                }
            }
            if ($normalizedError !== '' && !in_array($normalizedError, $normalizedErrors, true)) {
                $normalizedErrors[] = $normalizedError;
            }
        }
        return $normalizedErrors;
    }

    public function queryExchangeBalancePost()
    {
        // Loading the bundled CCXT market metadata peaks above PHP's default
        // 128 MB limit. Scope the higher limit to this explicit admin action.
        ini_set('memory_limit', '512M');

        $uid = $this->request->param('uid', 0, 'intval');
        if ($uid <= 0) {
            $this->error('用户ID错误');
        }

        $adminId = (int) session('ADMIN_ID');
        if ($adminId > 1) {
            $isManaged = Db::name('user')
                ->where('id', $uid)
                ->where('parent_user_id', $adminId)
                ->count();
            if (!$isManaged) {
                $this->error('无权查询该用户');
            }
        }

        $cacheKey = 'admin_exchange_balance_query_' . $uid;
        $lastQueryTime = cache($cacheKey);
        if (!empty($lastQueryTime) && time() - (int) $lastQueryTime < 300) {
            $leftSeconds = 300 - (time() - (int) $lastQueryTime);
            $this->error('查询太频繁，请 ' . ceil($leftSeconds / 60) . ' 分钟后再试');
        }

        $apis = Db::name('third_api')
            ->where('uid', $uid)
            ->where('status', 1)
            ->select()
            ->toArray();
        if (empty($apis)) {
            $this->error('该用户没有有效授权API');
        }

        $platformRows = Db::name('third_platform')->select()->toArray();
        $exchangeClassMap = array_column($platformRows, 'class', 'platform');
        $ccxtFile = CMF_ROOT . 'public/ccxt/ccxt.php';
        if (!is_file($ccxtFile)) {
            $this->error('CCXT库文件不存在');
        }
        require_once $ccxtFile;

        $totalUsdt = 0;
        $withdrawableUsdt = 0;
        $successCount = 0;
        $errors = [];
        foreach ($apis as $api) {
            $platform = $api['platform'];
            if (empty($exchangeClassMap[$platform])) {
                $errors[] = $platform . ': 交易所驱动不存在';
                continue;
            }
            $summary = null;
            try {
                $exchange = $this->createExchangeClient($exchangeClassMap[$platform], trim((string) $api['api_key']), trim((string) $api['secret_key']), trim((string) $api['passphrase']));
                list($summary, $queryErrors) = $this->fetchExchangeAccountOverview(
                    $exchange,
                    $platform,
                    trim((string) $api['api_key']),
                    trim((string) $api['secret_key'])
                );
                foreach ($queryErrors as $queryError) {
                    $errors[] = $platform . ': ' . $queryError;
                }
            } catch (\Throwable $e) {
                $errors[] = $platform . ' ccxt: ' . $e->getMessage();
            }

            if (!empty($summary) && !empty($summary['resolved'])) {
                $totalUsdt += (float) $summary['total_assets'];
                $withdrawableUsdt += (float) $summary['withdrawable'];
                $successCount++;
            } else {
                $errors[] = $platform . ': 未查询到可折算为 USDT 的资产，请检查 API 权限或账户类型';
            }
        }

        $errors = $this->normalizeExchangeErrors($errors);
        cache($cacheKey, time(), 300);
        if ($successCount === 0) {
            $this->error('查询失败：' . ($errors[0] ?? '未知错误'));
        }

        $result = [
            'total' => round($totalUsdt, 4),
            'total_assets' => round($totalUsdt, 4),
            'withdrawable' => round($withdrawableUsdt, 4),
            'unit' => 'USDT',
            'api_count' => count($apis),
            'success_count' => $successCount,
            'query_time' => date('Y-m-d H:i:s')
        ];
        $this->success('查询成功', '', $result);
    }

    public function childs()
    {
        $content = hook_one('user_admin_index_view');

        if (!empty($content)) {
            return $content;
        }

        $uid = $this->request->param('uid', 0, 'intval');
        $adminId = (int) session('ADMIN_ID');
        if ($adminId > 1) {
            if ($uid !== $adminId && !$this->isUserDescendantOf($uid, $adminId)) {
                $this->error('您没有权限查看该用户的邀请关系');
            }
        }

        $rootUser = Db::name('user')->where('id', $uid)->find();
        if (empty($rootUser)) {
            $this->error('用户不存在');
        }

        // The root user was already checked against the administrator's scope
        // above. From here, show the root user's own three levels. Otherwise a
        // proxy viewing a direct child would lose that child's third level
        // because it becomes depth four from the proxy account.
        $levelOneQuery = Db::name('user')->where('parent_user_id', $uid);
        $list = $levelOneQuery->order('create_time DESC')->select()->toArray();
        $levelOneIds = array_column($list, 'id');
        if (empty($levelOneIds)) {
            $list2 = [];
        } else {
            $levelTwoQuery = Db::name('user')->where('parent_user_id', 'in', $levelOneIds);
            $list2 = $levelTwoQuery->order('create_time DESC')->select()->toArray();
        }
        $levelTwoIds = array_column($list2, 'id');
        if (empty($levelTwoIds)) {
            $list3 = [];
        } else {
            $levelThreeQuery = Db::name('user')->where('parent_user_id', 'in', $levelTwoIds);
            $list3 = $levelThreeQuery->order('create_time DESC')->select()->toArray();
        }

        $this->assign('uid', $uid);
        $this->assign('rootUser', $rootUser);
        $this->assign('list', $list);
        $this->assign('list2', $list2);
        $this->assign('list3', $list3);
        $this->assign('inviteCounts', [
            1 => count($list),
            2 => count($list2),
            3 => count($list3)
        ]);
        // 渲染模板输出
        return $this->fetch();
    }

    /**
     * 邀请码及三级邀请关系管理
     * @adminMenu(
     *     'name'   => '分享码管理',
     *     'parent' => 'default1',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 90,
     *     'icon'   => '',
     *     'remark' => '查看和管理用户邀请码及三级邀请关系',
     *     'param'  => ''
     * )
     */
    public function invitationManagement()
    {
        $keyword = $this->request->param('keyword', '', 'trim');
        $adminId = (int) session('ADMIN_ID');
        $query = Db::name('user')
            ->alias('u')
            ->join('user p', 'u.parent_user_id = p.id', 'LEFT')
            ->field('u.id,u.user_login,u.user_nickname,u.mobile,u.invitation_code,u.invitation_count,u.parent_user_id,u.parent_tree,u.create_time,p.user_login as parent_login,p.user_nickname as parent_nickname,p.mobile as parent_mobile');

        if ($adminId > 1) {
            // Keep the shared backend focused on the proxy's direct team.
            $managedUserIds = Db::name('user')->where('parent_user_id', $adminId)->column('id');
            $managedUserIds[] = $adminId;
            $managedUserIds = array_values(array_unique(array_map('intval', $managedUserIds)));
            $query->where('u.id', 'in', $managedUserIds);
        }

        if ($keyword !== '') {
            if (ctype_digit($keyword)) {
                $query->where(function ($where) use ($keyword) {
                    $where->where('u.id', (int) $keyword)
                        ->whereOr('u.mobile', 'like', '%' . $keyword . '%')
                        ->whereOr('u.invitation_code', $keyword);
                });
            } else {
                $query->where(function ($where) use ($keyword) {
                    $where->where('u.user_login|u.user_nickname|u.mobile|u.invitation_code', 'like', '%' . $keyword . '%');
                });
            }
        }

        $list = $query->order('u.id desc')->paginate(20, false, [
            'query' => ['keyword' => $keyword]
        ]);

        $codeMap = [];
        $items = $list->items();
        $ownerIds = array_column($items, 'id');
        if (!empty($ownerIds)) {
            $codes = Db::name('invitation_code')->where('owner_user_id', 'in', $ownerIds)->order('id desc')->select()->toArray();
            foreach ($codes as $code) {
                $codeMap[(int) $code['owner_user_id']][] = $code;
            }
        }

        $this->assign('list', $list);
        $this->assign('page', $list->render());
        $this->assign('keyword', $keyword);
        $this->assign('canManageInvitation', (int) session('ADMIN_ID') === 1);
        $this->assign('codeMap', $codeMap);

        return $this->fetch();
    }

    public function createInvitationCodePost()
    {
        if ((int) session('ADMIN_ID') !== 1) {
            $this->error('只有超级管理员可以生成分享码');
        }
        $ownerId = $this->request->param('owner_user_id', 0, 'intval');
        $maxUseCount = max(0, min(1000000, $this->request->param('max_use_count', 0, 'intval')));
        if (empty(Db::name('user')->where('id', $ownerId)->find())) {
            $this->error('用户不存在');
        }
        do {
            $code = strtoupper(substr(md5(uniqid((string) $ownerId, true)), 0, 8));
        } while (Db::name('invitation_code')->where('code', $code)->find());
        Db::name('invitation_code')->insert([
            'owner_user_id' => $ownerId,
            'code' => $code,
            'max_use_count' => $maxUseCount,
            'used_count' => 0,
            'status' => 1,
            'is_self_generated' => 0,
            'create_time' => time(),
            'update_time' => time()
        ]);
        $this->success('分享码已生成', url('AdminIndex/invitationManagement'));
    }

    public function toggleInvitationCodePost()
    {
        if ((int) session('ADMIN_ID') !== 1) {
            $this->error('只有超级管理员可以修改分享码状态');
        }
        $codeId = $this->request->param('id', 0, 'intval');
        $code = Db::name('invitation_code')->where('id', $codeId)->find();
        if (empty($code)) {
            $this->error('分享码不存在');
        }
        Db::name('invitation_code')->where('id', $codeId)->update([
            'status' => (int) $code['status'] === 1 ? 0 : 1,
            'update_time' => time()
        ]);
        $this->success('分享码状态已更新', url('AdminIndex/invitationManagement'));
    }

    public function updateInvitationPost()
    {
        if ((int) session('ADMIN_ID') !== 1) {
            $this->error('只有超级管理员可以修改邀请关系');
        }

        $userId = $this->request->param('id', 0, 'intval');
        $parentUserId = $this->request->param('parent_user_id', 0, 'intval');
        $invitationCode = strtoupper($this->request->param('invitation_code', '', 'trim'));

        $user = Db::name('user')->where('id', $userId)->find();
        if (empty($user)) {
            $this->error('用户不存在');
        }
        if (!preg_match('/^[A-Z0-9]{4,32}$/', $invitationCode)) {
            $this->error('邀请码只能使用4-32位英文字母或数字');
        }

        $duplicate = Db::name('user')
            ->where('invitation_code', $invitationCode)
            ->where('id', '<>', $userId)
            ->find();
        if (!empty($duplicate)) {
            $this->error('邀请码已被其他用户使用');
        }
        if ($parentUserId === $userId) {
            $this->error('用户不能成为自己的上级');
        }
        if ($parentUserId > 0 && empty(Db::name('user')->where('id', $parentUserId)->find())) {
            $this->error('新的上级用户不存在');
        }

        $oldParentUserId = (int) $user['parent_user_id'];
        Db::startTrans();
        try {
            // Building the chain before writing also rejects descendant-to-parent cycles.
            $this->buildParentTree($parentUserId, $userId);

            Db::name('user')->where('id', $userId)->update([
                'invitation_code' => $invitationCode,
                'parent_user_id'  => $parentUserId
            ]);

            $this->rebuildInvitationSubtree($userId);
            $this->refreshInvitationCount($oldParentUserId);
            $this->refreshInvitationCount($parentUserId);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage() ?: '邀请码关系更新失败');
        }

        $this->success('邀请码和邀请关系已更新', url('AdminIndex/invitationManagement'));
    }

    protected function buildParentTree($parentUserId, $subjectUserId = 0)
    {
        $parentIds = [];
        $visited = [];
        $currentId = (int) $parentUserId;

        while ($currentId > 0) {
            if ($currentId === (int) $subjectUserId || isset($visited[$currentId])) {
                throw new \Exception('不能把用户移动到自己的下级关系中');
            }

            $visited[$currentId] = true;
            $parentIds[] = $currentId;
            if (count($parentIds) > 100) {
                throw new \Exception('邀请关系层级异常，请检查历史数据');
            }

            $currentId = (int) Db::name('user')->where('id', $currentId)->value('parent_user_id');
        }

        return implode('|', $parentIds);
    }

    protected function rebuildInvitationSubtree($rootUserId)
    {
        $queue = [(int) $rootUserId];
        $visited = [];

        while (!empty($queue)) {
            $userId = array_shift($queue);
            if (isset($visited[$userId])) {
                throw new \Exception('检测到循环邀请关系，更新已取消');
            }
            $visited[$userId] = true;

            $user = Db::name('user')->where('id', $userId)->field('id,parent_user_id')->find();
            if (empty($user)) {
                continue;
            }

            $parentTree = $this->buildParentTree((int) $user['parent_user_id'], $userId);
            Db::name('user')->where('id', $userId)->update(['parent_tree' => $parentTree]);

            $childIds = Db::name('user')->where('parent_user_id', $userId)->column('id');
            foreach ($childIds as $childId) {
                $queue[] = (int) $childId;
            }
        }
    }

    protected function refreshInvitationCount($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return;
        }

        $count = Db::name('user')->where('parent_user_id', $userId)->count();
        Db::name('user')->where('id', $userId)->update(['invitation_count' => $count]);
    }

    protected function getManagedUserIds($adminId, $maxDepth = 3)
    {
        $adminId = (int) $adminId;
        $maxDepth = max(1, min(10, (int) $maxDepth));
        $parentIds = [$adminId];
        $managedUserIds = [];

        for ($depth = 1; $depth <= $maxDepth; $depth++) {
            $childIds = Db::name('user')->where('parent_user_id', 'in', $parentIds)->column('id');
            if (empty($childIds)) {
                break;
            }

            $parentIds = array_values(array_unique(array_map('intval', $childIds)));
            $managedUserIds = array_merge($managedUserIds, $parentIds);
        }

        return array_values(array_unique($managedUserIds));
    }

    protected function isUserDescendantOf($targetUserId, $ancestorUserId)
    {
        $targetUserId = (int) $targetUserId;
        $ancestorUserId = (int) $ancestorUserId;
        if ($targetUserId <= 0 || $ancestorUserId <= 0 || $targetUserId === $ancestorUserId) {
            return $targetUserId === $ancestorUserId;
        }

        $currentId = $targetUserId;
        $visited = [];
        for ($depth = 0; $depth < 100; $depth++) {
            if (isset($visited[$currentId])) {
                break;
            }
            $visited[$currentId] = true;

            $parentUserId = (int) Db::name('user')->where('id', $currentId)->value('parent_user_id');
            if ($parentUserId <= 0) {
                return false;
            }
            if ($parentUserId === $ancestorUserId) {
                return true;
            }

            $currentId = $parentUserId;
        }

        return false;
    }

    protected function assertManagedUserAccess($targetUserId)
    {
        $adminId = (int) session('ADMIN_ID');
        if ($adminId <= 1) {
            return;
        }

        if (!$this->isUserDescendantOf($targetUserId, $adminId)) {
            $this->error('您没有权限操作该用户');
        }
    }

    public function exportExcel()
    {
        vendor("PHPExcel.PHPExcel");

        $className = "\PHPExcel";

        $objPHPExcel  = new $className();

        $result = Db::name('user')->field("id,user_nickname ,mobile ,score ,FROM_UNIXTIME(last_login_time) 'last_login_time',last_login_ip ,FROM_UNIXTIME(create_time) 'create_time',invitation_code ,invitation_count ,max_count ,parent_user_id ")->select();

        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', 'ID编号')
            ->setCellValue('B1', '昵称')
            ->setCellValue('C1', '手机号')
            ->setCellValue('D1', 'HH余额')
            ->setCellValue('E1', '上次登录时间')
            ->setCellValue('F1', '上次登录IP')
            ->setCellValue('G1', '账号创建时间')
            ->setCellValue('H1', '邀请码')
            ->setCellValue('I1', '邀请次数')
            ->setCellValue('J1', '最大邀请次数')
            ->setCellValue('K1', '推荐人ID');

        /*--------------开始从数据库提取信息插入Excel表中------------------*/
        $i = 2;  //定义一个i变量，目的是在循环输出数据是控制行数
        /*$i = 2,因为第一行是表头，所以写到表格时候只能从第二行开始写。*/
        $count = count($result);  //计算有多少条数据
        for ($i = 2; $i <= $count + 1; $i++) {
            $objPHPExcel->getActiveSheet(0)->setCellValue('A' . $i, $result[$i - 2]['id']);
            $objPHPExcel->getActiveSheet(0)->setCellValue('B' . $i, $result[$i - 2]['user_nickname']);
            $objPHPExcel->getActiveSheet(0)->setCellValue('C' . $i, $result[$i - 2]['mobile']);
            $objPHPExcel->getActiveSheet(0)->setCellValue('D' . $i, $result[$i - 2]['score']);
            $objPHPExcel->getActiveSheet(0)->setCellValue('E' . $i, $result[$i - 2]['last_login_time']);
            $objPHPExcel->getActiveSheet(0)->setCellValue('F' . $i, $result[$i - 2]['last_login_ip']);
            $objPHPExcel->getActiveSheet(0)->setCellValue('G' . $i, $result[$i - 2]['create_time']);
            $objPHPExcel->getActiveSheet(0)->setCellValue('H' . $i, $result[$i - 2]['invitation_code']);
            $objPHPExcel->getActiveSheet(0)->setCellValue('I' . $i, $result[$i - 2]['invitation_count']);
            $objPHPExcel->getActiveSheet(0)->setCellValue('J' . $i, $result[$i - 2]['max_count']);
            $objPHPExcel->getActiveSheet(0)->setCellValue('K' . $i, $result[$i - 2]['parent_user_id']);
        }
        /**接下来就是设置导入表的名称等内容了**/
        /*--------------下面是设置其他信息------------------*/

        $objPHPExcel->getActiveSheet()->setTitle('user');      //设置sheet的名称
        $objPHPExcel->setActiveSheetIndex(0);                   //设置sheet的起始位置


        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="用户信息' . date('Ymd-His') . '.xls"');
        header('Cache-Control: max-age=0');

        //$PHPWriter = \PHPExcel_IOFactory::createWriter( $objPHPExcel,"Excel2007");

        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');   //通过PHPExcel_IOFactory的写函数将上面数据写出来
        $objWriter->save("php://output"); //表示在$path路径下面生成demo.xlsx文件

    }


    public function add()
    {

        $id = $this->request->param('id');
        $type = $this->request->param('type');
        if (empty($id)) {
            $this->error("参数有误");
        }
        if (empty($type)) {
            $this->error("参数有误");
        }


        $where['id'] = $id;
        $data =
            Db::name('user')
            ->where($where)
            ->find();
        //var_dump($data);die();
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function add_post()
    {

        $id = $this->request->param('id');
        $type = $this->request->param('type');
        $num = $this->request->param('num');
        $pwd = $this->request->param('pwd');

        if ($pwd !== '666888') {
            $this->error("操作密码有误");
        }

        if (empty($id)) {
            $this->error("参数有误");
        }
        if (empty($type)) {
            $this->error("参数有误");
        }

        if (($num < 0) || ($num == 0)) {
            $this->error("请输入正确的数值");
        }

        $where['id'] = $id;
        $uinfo =
            Db::name('user')
            ->where($where)
            ->find();

        if (empty($uinfo)) {
            $this->error("参数有误");
        }


        if ($type == 'add') {
            $data = ['type' => 1, 'user_id' => $uinfo['id'], 'balance_type' => 'score', 'change' => $num, 'amount' => $num + $uinfo['score'], 'detial' => $uinfo['id'], 'detial_type' => 'system_add', 'ctime' => time(), 'extension' => json_encode($uinfo)];
            Db::name('balance_log')
                ->data($data)
                ->insert();
            $data =
                Db::name('user')
                ->where($where)
                ->setInc('score', $num);
        } else {
            $data = ['type' => 1, 'user_id' => $uinfo['id'], 'balance_type' => 'score', 'change' => $num, 'amount' => $uinfo['score'] - $num, 'detial' => $uinfo['id'], 'detial_type' => 'system_reduce', 'ctime' => time(), 'extension' => json_encode($uinfo)];
            Db::name('balance_log')
                ->data($data)
                ->insert();
            $data =
                Db::name('user')
                ->where($where)
                ->setDec('score', $num);
        }
        $this->success("操作成功");
    }


    /*积分记录*/
    public function score()
    {


        $where   = [];
        $request = input('request.');
        //var_dump($request);

        if (!empty($request['uid'])) {
            $where['user_id'] = intval($request['uid']);
        }
        if (!empty($request['oid'])) {
            $where['detial'] = intval($request['oid']);
        }
        $keywordComplex = [];
        if (!empty($request['keyword'])) {
            $keyword = $request['keyword'];

            $keywordComplex['detial_type']    = ['like', "%$keyword%"];
        }
        $usersQuery = Db::name('BalanceLog');

        $list = $usersQuery->where($keywordComplex)->where($where)->order("id DESC")->paginate(100, false, ['query' => request()->param()]);
        //var_dump($where);
        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
        $this->assign('request', $request);
        // 渲染模板输出
        return $this->fetch();
    }



    /*实盘用户*/
    public function firm()
    {
        $content = hook_one('user_admin_index_view');

        if (!empty($content)) {
            return $content;
        }

        $where   = [];
        $request = input('request.');

        if (!empty($request['uid'])) {
            $where['b.id'] = intval($request['uid']);
        }
        $keywordComplex = [];
        if (!empty($request['keyword'])) {
            $keyword = $request['keyword'];

            $keywordComplex['user_login|user_nickname|user_email|mobile']    = ['like', "%$keyword%"];
        }
        $usersQuery = Db::name('firm_user')->alias('a')->join("user b", "a.uid = b.id", "LEFT");

        $auth_status = [1 => 'I', 2 => 'Ⅱ', 3 => 'Ⅲ', 4 => 'Ⅳ', 5 => 'Ⅴ'];
        $list = $usersQuery->whereOr($keywordComplex)->where($where)->order("create_time DESC")->paginate(100);
        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
        // 渲染模板输出
        return $this->fetch();
    }


    /*实盘用户*/
    public function charge_apply()
    {

        $Query = Db::name('firm_user_charge_apply')->alias('a')->join("user b", "a.uid = b.id", "LEFT");

        $list = $Query->order("a.id DESC")->field("a.*,b.user_nickname")->paginate(10);
        // 获取分页显示
        $page = $list->render();
        $this->assign('list', $list);
        $this->assign('page', $page);
        // 渲染模板输出
        return $this->fetch();
    }


    public function agree()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            $result = Db::name("firm_user_charge_apply")->where(["id" => $id, "status" => 2])->setField('status', 1);
            if ($result) {
                $this->success("审批已通过", "adminIndex/charge_apply");
            } else {
                $this->error('审批失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }


    public function editUser()
    {
        $id     = $this->request->param('id', 0, 'intval');
        $user = Db::name('user')->where('id', $id)->find();
        $this->assign($user);

        return $this->fetch();
    }

    public function editUserPost()
    {
        $id = $this->request->param('id', 0, 'intval');

        $data = $this->request->param();

        Db::name('user')->where('id', $id)
            ->strict(false)
            ->field('has_paypwd,is_google_check')
            ->update($data);

        $this->success('保存成功！');
    }

    public function simulateDisk()
    {
        $id = $this->request->param('id', 0, 'intval');
        if (empty($id)) {
            $this->error('用户ID不能为空');
        }
        $this->assertManagedUserAccess($id);

        $user = Db::name('user')->where('id', $id)->find();
        if (empty($user)) {
            $this->error('用户不存在');
        }

        $robots = $this->getSimulatedRobots($id);
        $markets = $this->getSimulatedMarkets();
        $hasRobots = !empty($robots) && count($robots) > 0;
        $summary = $this->getRevenueSummary($id);
        $recentRecords = Db::name('quant_robot_revenue')
            ->where('uid', $id)
            ->order('id desc')
            ->limit(20)
            ->select();

        $this->assign('user', $user);
        $this->assign('robots', $robots);
        $this->assign('markets', $markets);
        $this->assign('hasRobots', $hasRobots);
        $this->assign('summary', $summary);
        $this->assign('recentRecords', $recentRecords);

        return $this->fetch();
    }

    public function simulateDiskPost()
    {
        $id = $this->request->param('id', 0, 'intval');
        $actionType = $this->request->param('action_type', '', 'trim');

        if (empty($id)) {
            $this->error('用户ID不能为空');
        }
        $this->assertManagedUserAccess($id);

        $user = Db::name('user')->where('id', $id)->find();
        if (empty($user)) {
            $this->error('用户不存在');
        }

        $robots = $this->getSimulatedRobots($id);
        Db::startTrans();
        try {
            switch ($actionType) {
                case 'create_virtual_robot':
                    $platform = $this->request->param('virtual_platform', '', 'trim');
                    $marketId = $this->request->param('virtual_market_id', 0, 'intval');
                    $firstOrderValue = round((float) $this->request->param('virtual_first_order_value', 300), 8);

                    if (empty($platform) || empty($marketId)) {
                        throw new \Exception('请选择平台和交易对');
                    }

                    $robotId = $this->createVirtualRobot($id, $platform, $marketId, $firstOrderValue);
                    $message = '模拟机器人已创建';
                    break;

                case 'set_target':
                case 'add_record':
                case 'generate_demo':
                    if (empty($robots)) {
                        throw new \Exception('当前用户还没有模拟机器人，请先创建一台模拟机器人');
                    }

                    $robotId = $this->request->param('robot_id', 0, 'intval');
                    $robot = null;
                    foreach ($robots as $robotItem) {
                        if ((int) $robotItem['id'] === $robotId) {
                            $robot = $robotItem;
                            break;
                        }
                    }
                    if (empty($robot)) {
                        $robot = $robots[0];
                    }

                    if ($actionType === 'set_target') {
                    $targetToday = round((float) $this->request->param('target_today_revenue', 0), 8);
                    $targetTotal = round((float) $this->request->param('target_total_revenue', 0), 8);
                    $todayRecordCount = max(1, min(100, (int) $this->request->param('target_today_count', 3)));

                    if ($targetTotal + 0.00000001 < $targetToday) {
                        throw new \Exception('累计盈利不能小于今日盈利');
                    }

                    $summary = $this->getRevenueSummary($id);
                    $deltaToday = round($targetToday - $summary['today_revenue'], 8);
                    $deltaHistory = round($targetTotal - $summary['total_revenue'] - $deltaToday, 8);

                    if (abs($deltaHistory) > 0.00000001) {
                        $this->insertSplitRevenueRecords($robot, $id, $deltaHistory, 1, strtotime('-1 day 12:00:00'));
                    }

                    if (abs($deltaToday) > 0.00000001) {
                        $this->insertSplitRevenueRecords($robot, $id, $deltaToday, $todayRecordCount, time());
                    }
                    $message = '模拟盘收益已同步到目标值';
                    break;
                    }

                    if ($actionType === 'add_record') {
                    $revenue = round((float) $this->request->param('record_revenue', 0), 8);
                    $platform = $this->request->param('record_platform', '', 'trim');
                    $market = $this->request->param('record_market', '', 'trim');
                    $ctime = $this->request->param('record_time', '', 'trim');

                    if ($revenue == 0.0) {
                        throw new \Exception('收益金额不能为0');
                    }

                    $this->insertSimulatedRevenueRecord($robot, $id, [
                        'platform' => $platform ?: $robot['platform'],
                        'market'   => $market ?: $robot['market_name'],
                        'revenue'  => $revenue,
                        'ctime'    => $this->normalizeRevenueTime($ctime)
                    ]);
                    $message = '模拟盘交易记录已新增';
                    break;
                    }

                    if ($actionType === 'generate_demo') {
                    $days = max(1, min(30, (int) $this->request->param('demo_days', 7)));
                    $countPerDay = max(1, min(100, (int) $this->request->param('demo_count_per_day', 3)));
                    $minRevenue = (float) $this->request->param('demo_min_revenue', 8);
                    $maxRevenue = (float) $this->request->param('demo_max_revenue', 80);

                    if ($minRevenue <= 0 || $maxRevenue <= 0 || $minRevenue > $maxRevenue) {
                        throw new \Exception('示例收益区间不正确');
                    }

                    for ($day = $days - 1; $day >= 0; $day--) {
                        $records = mt_rand(max(1, $countPerDay - 1), $countPerDay + 1);
                        for ($i = 0; $i < $records; $i++) {
                            $sign = mt_rand(1, 100) <= 82 ? 1 : -1;
                            $value = mt_rand((int) ($minRevenue * 100), (int) ($maxRevenue * 100)) / 100;
                            $dayStart = strtotime(date('Y-m-d 00:00:00', strtotime("-{$day} day")));
                            $dayEnd = $day === 0 ? time() : $dayStart + 86399;
                            $timestamp = mt_rand($dayStart, max($dayStart, $dayEnd));

                            $this->insertSimulatedRevenueRecord($robot, $id, [
                                'platform' => $robot['platform'],
                                'revenue'  => round($sign * $value, 8),
                                'ctime'    => date('Y-m-d H:i:s', min($timestamp, time()))
                            ]);
                        }
                    }
                    $message = '模拟盘示例数据已生成';
                    break;
                    }

                default:
                    throw new \Exception('不支持的操作类型');
            }

            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $message = trim((string) $e->getMessage());
            if ($message === '') {
                $message = '模拟盘操作失败，请查看运行日志';
            }
            trace('模拟盘操作失败: ' . $message, 'error');
            $this->error($message);
        }

        $this->success($message, url('AdminIndex/simulateDisk', ['id' => $id]));
    }

    /**
     * 本站用户拉黑
     * @adminMenu(
     *     'name'   => '本站用户拉黑',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '本站用户拉黑',
     *     'param'  => ''
     * )
     */
    public function ban()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            $result = Db::name("user")->where(["id" => $id, "user_type" => 2])->setField('user_status', 0);
                    Db::name('user_token')->where([
                             'user_id'     => $id,
                             'device_type' => 'web'
                    ])->update(['token' => '']);



            if ($result) {
                $this->success("会员拉黑成功！", "adminIndex/index");
            } else {
                $this->error('会员拉黑失败,会员不存在,或者是管理员！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }


    /**
     * 后台修改认证等级
     */
    public function up_auth()
    {
        if ($_POST) {
            $id = $_POST['id'];
            $num = $_POST['num'];

            if ($num < 1 || $num > 5) {
                echo "要修改的等级错误,请重试！";
                return;
            }
            $data = Db::name("user")->where('id', $id)->update([
                'auth_id' => $num
            ]);
            if ($data) {
                echo "操作成功！";
            } else {
                echo "操作失败！";
            }
        } else {
            echo "请求类型有误！";
        }
    }

    /**
     * 本站用户启用
     * @adminMenu(
     *     'name'   => '本站用户启用',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '本站用户启用',
     *     'param'  => ''
     * )
     */
    public function cancelBan()
    {
        $id = input('param.id', 0, 'intval');
        if ($id) {
            Db::name("user")->where(["id" => $id, "user_type" => 2])->setField('user_status', 1);
            $this->success("会员启用成功！", '');
        } else {
            $this->error('数据传入失败！');
        }
    }

    public function edit()
    {
        $id     = $this->request->param('id', 0, 'intval');
        $firm_user = Db::name('firm_user')->where('uid', $id)->find();
        $this->assign($firm_user);

        return $this->fetch();
    }

    public function editPost()
    {
        $id = $this->request->param('id', 0, 'intval');

        $data = $this->request->param();

        Db::name('firm_user')->where('uid', $id)
            ->strict(false)
            ->field('is_free,subscribe_coin_symbol,subscribe_price,subscribe_days')
            ->update($data);

        $this->success('保存成功！');
    }

    protected function getSimulatedRobots($userId)
    {
        $field = 'a.id,a.uid,a.platform,a.market_id,a.type,b.market_name,b.stock,b.money';

        return Db::name('quant_robot')
            ->alias('a')
            ->join('spot_market b', 'a.market_id = b.id', 'LEFT')
            ->field($field)
            ->where('a.uid', $userId)
            ->order('a.id desc')
            ->select()
            ->toArray();
    }

    protected function getSimulatedMarkets()
    {
        return Db::name('spot_market')
            ->where('status', 1)
            ->field('id,platform,market_name,stock,money')
            ->order('platform asc,id asc')
            ->select()
            ->toArray();
    }

    protected function getRevenueSummary($userId)
    {
        $today = date('Y-m-d 00:00:00');
        $totalRevenue = (float) Db::name('quant_robot_revenue')
            ->where('uid', $userId)
            ->sum('revenue');
        $todayRevenue = (float) Db::name('quant_robot_revenue')
            ->where('uid', $userId)
            ->where('ctime', '>', $today)
            ->sum('revenue');
        $recordCount = (int) Db::name('quant_robot_revenue')
            ->where('uid', $userId)
            ->count();

        return [
            'total_revenue' => round($totalRevenue, 8),
            'today_revenue' => round($todayRevenue, 8),
            'record_count'  => $recordCount
        ];
    }

    protected function insertSimulatedRevenueRecord($robot, $userId, array $data)
    {
        $insertData = [
            'platform'    => !empty($data['platform']) ? $data['platform'] : $robot['platform'],
            'qrobot_id'   => $robot['id'],
            'pid'         => !empty($data['pid']) ? $data['pid'] : strtoupper(substr(md5(uniqid((string) $userId, true)), 0, 24)),
            'uid'         => $userId,
            'market'      => !empty($data['market']) ? $data['market'] : $robot['market_name'],
            'stock'       => !empty($data['stock']) ? $data['stock'] : $robot['stock'],
            'money'       => !empty($data['money']) ? $data['money'] : $robot['money'],
            'revenue'     => round((float) $data['revenue'], 8),
            'deal_status' => 1,
            'type'        => !empty($robot['type']) ? $robot['type'] : 1,
            'ctime'       => $this->normalizeRevenueTime(isset($data['ctime']) ? $data['ctime'] : '')
        ];

        $result = Db::name('quant_robot_revenue')->insert($insertData);
        if ($result === false) {
            throw new \Exception('模拟盘收益记录写入失败');
        }
    }

    /**
     * Write a target amount as several records whose rounded sum is exact.
     * The timestamps are distributed through the available period and never
     * exceed the current server time.
     */
    protected function insertSplitRevenueRecords($robot, $userId, $amount, $count, $endTimestamp)
    {
        $amount = round((float) $amount, 8);
        $count = max(1, min(100, (int) $count));
        $endTimestamp = min((int) $endTimestamp, time());
        $startTimestamp = strtotime(date('Y-m-d 00:00:00', $endTimestamp));
        $availableSeconds = max(0, $endTimestamp - $startTimestamp);
        $unit = round($amount / $count, 8);

        for ($index = 0; $index < $count; $index++) {
            $revenue = $index === $count - 1
                ? round($amount - ($unit * ($count - 1)), 8)
                : $unit;
            $timestamp = $startTimestamp + (int) floor($availableSeconds * ($index + 1) / ($count + 1));

            $this->insertSimulatedRevenueRecord($robot, $userId, [
                'revenue' => $revenue,
                'ctime'   => date('Y-m-d H:i:s', min($timestamp, time()))
            ]);
        }
    }

    protected function normalizeRevenueTime($time)
    {
        if (empty($time)) {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($time);
        if ($timestamp === false) {
            throw new \Exception('时间格式不正确');
        }

        return date('Y-m-d H:i:s', min($timestamp, time()));
    }

    protected function createVirtualRobot($userId, $platform, $marketId, $firstOrderValue)
    {
        $market = Db::name('spot_market')
            ->where([
                'id' => $marketId,
                'platform' => $platform,
                'status' => 1
            ])
            ->find();

        if (empty($market)) {
            throw new \Exception('选中的交易对不存在或已停用');
        }

        $exists = Db::name('quant_robot')
            ->where([
                'uid' => $userId,
                'platform' => $platform,
                'market_id' => $marketId
            ])
            ->find();

        if (!empty($exists)) {
            throw new \Exception('该用户已经存在同交易对机器人，请直接使用现有挂载');
        }

        $insertData = [
            'uid' => $userId,
            'status' => 1,
            'type' => 1,
            'cd_key' => '',
            'platform' => $platform,
            'market_id' => $marketId,
            'first_order_value' => $firstOrderValue > 0 ? $firstOrderValue : 300,
            'max_order_count' => 6,
            'stop_profit_rate' => 1.8,
            'stop_profit_callback_rate' => 0.3,
            'cover_rate' => json_encode(['1' => '2', '2' => '4', '3' => '6', '4' => '8', '5' => '10', '6' => '12']),
            'cover_callback_rate' => 0.3,
            'recycle_status' => 1,
            'c_type' => 2,
            'number' => 0
        ];

        $robotId = Db::name('quant_robot')
            ->field('uid,status,type,cd_key,platform,market_id,first_order_value,max_order_count,stop_profit_rate,stop_profit_callback_rate,cover_rate,cover_callback_rate,recycle_status,c_type,number')
            ->insertGetId($insertData);

        if (empty($robotId)) {
            throw new \Exception('模拟机器人创建失败');
        }

        return $robotId;
    }
}
