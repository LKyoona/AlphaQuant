<?php

namespace api\third\controller;

use api\common\service\KrakenSpotService;
use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;
// header("Access-Control-Allow-Origin: *");

class AccountController extends RestBaseController
{
    protected function createExchange($exchangeClass, $apiKey, $secret, $password)
    {
        $className = "\\ccxt\\" . $exchangeClass;
        if (!class_exists($className)) {
            throw new \RuntimeException('交易所驱动不存在:' . $exchangeClass);
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

    protected function loadCcxt()
    {
        $ccxtFile = CMF_ROOT . 'public/ccxt/ccxt.php';
        if (!is_file($ccxtFile)) {
            throw new \RuntimeException('CCXT库文件不存在:' . $ccxtFile);
        }

        require_once $ccxtFile;
    }

    public function _initialize()
    {
        // CCXT loads large exchange metadata; keep the higher limit scoped to this controller.
        ini_set('memory_limit', '512M');
        //因为操作时间会很长，设置php操作超时时间600秒
        set_time_limit(600);
        date_default_timezone_set("Etc/GMT");
    }

    public function validateAccount($exchange_class, $apiKey, $secret, $password)
    {
        try {
            if (strtolower((string) $exchange_class) === 'kraken') {
                $service = new KrakenSpotService($apiKey, $secret);
                return array(1, $service->validateCredentials());
            }
            $this->loadCcxt();
            $exchange = $this->createExchange($exchange_class, $apiKey, $secret, $password);

            $result = $exchange->fetch_balance();
        } catch (\Throwable $e) {
            return array(0, $e->getMessage());
        }
        return array(1, $result);
    }

    protected function binanceRequest($method, $path, $apiKey, $secret, $params = [])
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

    protected function httpRequest($method, $url, $params = [], $headers = [])
    {
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

    protected function normalizeBalance($balance, $currency = 'USDT')
    {
        $free = null;
        $used = null;
        $total = null;

        if (isset($balance[$currency]) && is_array($balance[$currency])) {
            $free = isset($balance[$currency]['free']) ? (float) $balance[$currency]['free'] : $free;
            $used = isset($balance[$currency]['used']) ? (float) $balance[$currency]['used'] : $used;
            $total = isset($balance[$currency]['total']) ? (float) $balance[$currency]['total'] : $total;
        }

        if (isset($balance['free']) && is_array($balance['free']) && isset($balance['free'][$currency])) {
            $free = (float) $balance['free'][$currency];
        }
        if (isset($balance['used']) && is_array($balance['used']) && isset($balance['used'][$currency])) {
            $used = (float) $balance['used'][$currency];
        }
        if (isset($balance['total']) && is_array($balance['total']) && isset($balance['total'][$currency])) {
            $total = (float) $balance['total'][$currency];
        }

        if ($total === null) {
            $freeValue = $free === null ? 0 : $free;
            $usedValue = $used === null ? 0 : $used;
            $total = $freeValue + $usedValue;
        }

        return [
            'currency' => $currency,
            'free' => round((float) ($free === null ? 0 : $free), 8),
            'used' => round((float) ($used === null ? 0 : $used), 8),
            'total' => round((float) $total, 8),
        ];
    }

    protected function fetchBestBalance($exchange, $platform)
    {
        $types = ['spot', 'funding', 'margin'];
        if (in_array($platform, ['binance', 'okex', 'okex3', 'bitget'], true)) {
            $types = array_merge($types, ['future', 'swap']);
        }

        $bestBalance = null;
        $bestSummary = null;
        $errors = [];
        foreach (array_unique($types) as $type) {
            try {
                $params = $type === 'default' ? [] : ['type' => $type];
                $balance = $exchange->fetch_balance($params);
                if (empty($balance) || !is_array($balance)) {
                    $errors[] = $type . ': empty balance response';
                    continue;
                }
                $summary = $this->normalizeBalance($balance, 'USDT');
                $summary['account_type'] = $type;
                if ($bestBalance === null) {
                    $bestBalance = $balance;
                    $bestSummary = $summary;
                }
                if ($summary['total'] > 0 || $summary['free'] > 0) {
                    return [$balance, $summary, $errors];
                }
            } catch (\Throwable $e) {
                $errors[] = $type . ': ' . $e->getMessage();
            }
        }

        return [$bestBalance, $bestSummary, $errors];
    }

    protected function fetchBinanceFundingBalance($apiKey, $secret, $currency = 'USDT')
    {
        $data = $this->binanceRequest('POST', '/sapi/v1/asset/get-funding-asset', $apiKey, $secret, [
            'asset' => $currency,
            'needBtcValuation' => 'false',
        ]);

        if (empty($data) || empty($data[0])) {
            return [
                'info' => $data,
                $currency => [
                    'currency' => $currency,
                    'free' => 0,
                    'used' => 0,
                    'total' => 0,
                    'account_type' => 'funding',
                ],
                'free' => [$currency => 0],
                'used' => [$currency => 0],
                'total' => [$currency => 0],
            ];
        }

        $item = $data[0];
        $free = (float) ($item['free'] ?? 0);
        $used = (float) ($item['locked'] ?? ($item['freeze'] ?? ($item['withdrawing'] ?? 0)));
        $total = $free + $used;

        return [
            'info' => $data,
            $currency => [
                'currency' => $currency,
                'free' => round($free, 8),
                'used' => round($used, 8),
                'total' => round($total, 8),
                'account_type' => 'funding',
            ],
            'free' => [$currency => round($free, 8)],
            'used' => [$currency => round($used, 8)],
            'total' => [$currency => round($total, 8)],
        ];
    }

    /*
     * 绑定api
     */
    public function addAccount()
    {

        $userId = $this->getUserId();
        $platforms = implode(',', Db::name("third_platform")->column('platform'));
        $validate = new Validate([
            'platform'     => 'require|in:' . $platforms,
            'api_key'     => 'require',
            'secret_key' => 'require',
        ]);

        $validate->message([
            'platform.require'  => '平台不能为空!',
            'api_key.require'  => 'API Key不能为空!',
            'secret_key.require'  => 'Secret Key不能为空',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $platform = $data['platform'];
        $apiKey = $data['api_key'];
        $secretKey = $data['secret_key'];
        $passphrase = isset($data['passphrase']) ? trim((string) $data['passphrase']) : '';
        if (in_array(strtolower((string) $platform), ['okex', 'okx', 'okex3'], true) && $passphrase === '') {
            $this->error('Passphrase不能为空!');
        }
        $platforms = Db::name("third_platform")->select()->toArray();
        $exchange_class = array_column($platforms, 'class', 'platform');
        //$exchange_class = array('okex' => 'okex3', 'binance' => 'binance', 'huobi' => 'hbdm', 'gateio' => 'gateio');
        if (!isset($exchange_class[$platform])) {
            $this->error('无法验证该平台API有效性:', $platform);
        }
        //测试api信息是否正确
        $ret = $this->validateAccount($exchange_class[$platform], $apiKey, $secretKey, $passphrase);
        if ($ret[0] != 1) {
            $this->error('API信息校验错误:' . $ret[1]);
        }

        $where = array();
        $where['platform'] = $platform;
        $where['uid'] = $userId;

        $findApi = Db::name("third_api")->where($where)->find();
        if ($findApi) {
            $api_info = [
                'api_key' => $apiKey,
                'secret_key' => $secretKey,
                'passphrase' => $passphrase,
                'update_time' => time(),
                'status' => 1,
            ];
            $ret = Db::name("third_api")->where('id', $findApi['id'])->update($api_info);
        } else {
            //添加到数据库
            $api_info = [
                'platform' => $platform,
                'uid' => $userId,
                'api_key' => $apiKey,
                'secret_key' => $secretKey,
                'passphrase' => $passphrase,
                'create_time' => time(),
                'update_time' => time(),
                'status' => 1,
            ];
            $ret = Db::name("third_api")->insert($api_info);
        }
        if ($ret) {
            $this->success($findApi ? '更新API成功' : '绑定API成功');
        } else {
            $this->error('绑定API失败');
        }
    }

    /*
     * 删除api
     */
    public function removeAccount()
    {

        $userId = $this->getUserId();
        $platforms = implode(',', Db::name("third_platform")->column('platform'));
        $validate = new Validate([
            'platform'     => 'require|in:' . $platforms,
        ]);

        $validate->message([
            'platform.require'  => '平台不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $platform = $data['platform'];

        $where = array();
        $where['platform'] = $platform;
        $where['uid'] = $userId;
        $findApi = Db::name("third_api")->where($where)->find();
        if ($findApi) {
            $ret = Db::name("third_api")->where('id', $findApi['id'])->update(array('status' => 0));
            if ($ret) {
                $this->success('删除成功');
            } else {
                $this->error('删除失败');
            }
        } else {
            $this->error('您没有设置该平台的API:' . $platform);
        }
    }



    public function accountInfo()
    {
        $userId = $this->getUserId();
        $platforms = implode(',', Db::name("third_platform")->column('platform'));
        $validate = new Validate([
            'platform'     => 'require|in:' . $platforms,
        ]);

        $validate->message([
            'platform.require'  => '平台不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $platform = $data['platform'];
        $where = array();
        $where['platform'] = $platform;
        $where['uid'] = $userId;
        $findApi = Db::name("third_api")->where($where)->find();
        if ($findApi) {
            if ($findApi['status'] == 0) { //已经删除
                $this->error('您还未添加该平台的API:' . $platform);
            } else {
                $this->success('success', $findApi);
            }
        } else {
            $this->error('您还未添加该平台的API:' . $platform);
        }
    }

    public function accountBalance()
    {
        $userId = $this->getUserId();
        $platforms = implode(',', Db::name("third_platform")->column('platform'));
        $validate = new Validate([
            'platform'     => 'require|in:' . $platforms,
        ]);

        $validate->message([
            'platform.require'  => '平台不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $platform = $data['platform'];
        $where = array();
        $where['platform'] = $platform;
        $where['uid'] = $userId;
        $findApi = Db::name("third_api")->where($where)->find();
        if ($findApi) {
            if ($findApi['status'] == 0) { //已经删除
                $this->error('您还未添加该平台的API:' . $platform);
            } else {
                $platform = $findApi['platform'];
                $apiKey = $findApi['api_key'];
                $secretKey = $findApi['secret_key'];
                $passphrase = $findApi['passphrase'];
                $platforms = Db::name("third_platform")->select()->toArray();
                $exchange_class = array_column($platforms, 'class', 'platform');
                if (!isset($exchange_class[$platform])) {
                    $this->error('无法验证该平台API有效性:', $platform);
                }
                $balance = null;
                $summary = null;
                $errors = [];
                if (strtolower((string) $platform) === 'kraken') {
                    try {
                        $balance = (new KrakenSpotService($apiKey, $secretKey))->fetchBalance();
                        $summary = $this->normalizeBalance($balance, 'USDT');
                        $summary['account_type'] = 'spot';
                    } catch (\Throwable $e) {
                        $errors[] = 'kraken: ' . $e->getMessage();
                    }
                } else {
                    try {
                        $this->loadCcxt();
                        $exchange = $this->createExchange($exchange_class[$platform], $apiKey, $secretKey, $passphrase);
                        list($balance, $summary, $errors) = $this->fetchBestBalance($exchange, $platform);
                    } catch (\Throwable $e) {
                        $errors[] = 'ccxt: ' . $e->getMessage();
                    }
                }

                if (strtolower($platform) === 'binance' && (empty($summary) || ((float) $summary['total'] <= 0 && (float) $summary['free'] <= 0))) {
                    try {
                        $fundingBalance = $this->fetchBinanceFundingBalance($apiKey, $secretKey, 'USDT');
                        $fundingSummary = $this->normalizeBalance($fundingBalance, 'USDT');
                        $fundingSummary['account_type'] = 'funding';
                        if ($fundingSummary['total'] > 0 || $fundingSummary['free'] > 0 || $balance === null) {
                            $balance = $fundingBalance;
                            $summary = $fundingSummary;
                        }
                    } catch (\Throwable $e) {
                        $errors[] = 'binance funding: ' . $e->getMessage();
                    }
                }

                if ($balance === null || $summary === null) {
                    $message = implode('；', array_filter(array_slice($errors, 0, 3)));
                    $this->error('API信息校验错误:' . ($message !== '' ? $message : '余额接口无返回'));
                }
                $balance['USDT'] = $summary;
                $balance['_summary'] = $summary;
                $balance['_errors'] = $errors;
                $this->success('success', $balance);
            }
        } else {
            $this->error('您还未添加该平台的API:' . $platform);
        }
    }




    /*
     * 绑定合约api
     */
    public function addFutrueAccount()
    {

        $userId = $this->getUserId();
        $platforms = implode(',', Db::name("third_platform")->column('platform'));
        $validate = new Validate([
            'platform'     => 'require|in:' . $platforms,
            'api_key'     => 'require',
            'secret_key' => 'require',
            'passphrase' => 'require',
        ]);

        $validate->message([
            'platform.require'  => '平台不能为空!',
            'api_key.require'  => 'API Key不能为空!',
            'secret_key.require'  => 'Secret Key不能为空',
            'passphrase.require'  => 'Passphrase不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $platform = $data['platform'];
        $apiKey = $data['api_key'];
        $secretKey = $data['secret_key'];
        $passphrase = $data['passphrase'];
        $platforms = Db::name("third_platform")->select()->toArray();
        $exchange_class = array_column($platforms, 'class', 'platform');
        //$exchange_class = array('okex' => 'okex3', 'binance' => 'binance', 'huobi' => 'hbdm', 'gateio' => 'gateio');
        if (!isset($exchange_class[$platform])) {
            $this->error('无法验证该平台API有效性:', $platform);
        }
        //测试api信息是否正确
        $ret = $this->validateFutrueAccount($exchange_class[$platform], $data['api_key'], $data['secret_key'], $data['passphrase']);
        // $this->error("add".json_encode($ret[1]));

        if ($ret[0] != 1) {
            $this->error('API信息校验错误:' . $ret[1]);
        }

        $where = array();
        $where['platform'] = $platform;
        $where['uid'] = $userId;

        $findApi = Db::name("future_api")->where($where)->find();
        if ($findApi) {
            $api_info = [
                'api_key' => $apiKey,
                'secret_key' => $secretKey,
                'passphrase' => $passphrase,
                'update_time' => time(),
                'status' => 1,
            ];
            $ret = Db::name("future_api")->where('id', $findApi['id'])->update($api_info);
        } else {
            //添加到数据库
            $api_info = [
                'platform' => $platform,
                'uid' => $userId,
                'api_key' => $apiKey,
                'secret_key' => $secretKey,
                'passphrase' => $passphrase,
                'create_time' => time(),
                'update_time' => time(),
                'status' => 1,
            ];
            $ret = Db::name("future_api")->insert($api_info);
        }
        if ($ret) {
            $this->success($findApi ? '更新API成功' : '绑定API成功');
        } else {
            $this->error('绑定API失败');
        }
    }
    public function accountFutureBalance()
    {
        $userId = $this->getUserId();
        $platforms = implode(',', Db::name("third_platform")->column('platform'));
        $validate = new Validate([
            'platform'     => 'require|in:' . $platforms,
        ]);

        $validate->message([
            'platform.require'  => '平台不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $platform = $data['platform'];
        $where = array();
        $where['platform'] = $platform;
        $where['uid'] = $userId;
        $findApi = Db::name("future_api")->where($where)->find();
        if ($findApi) {
            if ($findApi['status'] == 0) { //已经删除
                $this->error('您还未添加该平台的API:' . $platform);
            } else {
                $platform = $findApi['platform'];
                $apiKey = $findApi['api_key'];
                $secretKey = $findApi['secret_key'];
                $passphrase = $findApi['passphrase'];
                $platforms = Db::name("third_platform")->select()->toArray();
                $exchange_class = array_column($platforms, 'class', 'platform');
                if (!isset($exchange_class[$platform])) {
                    $this->error('无法验证该平台API有效性:', $platform);
                }
                //测试api信息是否正确
                $ret = $this->validateFutrueAccount($exchange_class[$platform], $apiKey, $secretKey, $passphrase);

                // $this->success(json_decode($ret[1]));

                if ($ret[0] != 1) {
                    $this->error('API信息校验错误:' . $ret[1]);
                }
                $summary = $this->normalizeBalance($ret[1], 'USDT');
                $this->success('success', $summary['total']);
            }
        } else {
            $this->error('您还未添加该平台的API:' . $platform);
        }
    }

    public function futureAccountInfo()
    {
        $userId = $this->getUserId();
        $platforms = implode(',', Db::name("third_platform")->column('platform'));
        $validate = new Validate([
            'platform' => 'require|in:' . $platforms,
        ]);

        $validate->message([
            'platform.require' => '平台不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $platform = $data['platform'];
        $where = array();
        $where['platform'] = $platform;
        $where['uid'] = $userId;
        $findApi = Db::name("future_api")->where($where)->find();
        if ($findApi) {
            if ($findApi['status'] == 0) {
                $this->error('您还未添加该平台的API:' . $platform);
            } else {
                $this->success('success', $findApi);
            }
        } else {
            $this->error('您还未添加该平台的API:' . $platform);
        }
    }

    public function validateFutrueAccount($exchange_class, $apiKey, $secret, $password)
    {
        try {
            $this->loadCcxt();
            $className = "\\ccxt\\" . $exchange_class;
            if (!class_exists($className)) {
                return array(0, '交易所驱动不存在:' . $exchange_class);
            }

            $exchange = new $className(array(
                'apiKey' => $apiKey,
                'secret' => $secret,
                'password' => $password,
            ));

            $para = array(
                'type' => 'future'
            );

            $result = $exchange->fetch_balance($para);
        } catch (\Throwable $e) {
            return array(0, $e->getMessage());
        }
        return array(1, $result);
    }
}
