<?php

namespace api\trade\controller;

use api\common\service\RedisPackage;
use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;

class PublicController extends RestBaseController
{
    private $exchange_name = "";
    private $market = "";
    private $exchange_class = "";
    private $className = "";
    private $currency_rate = 0;
    private $currency_char = "";

    public function _initialize()
    {
        date_default_timezone_set("Etc/GMT");
        //ini_set('date.timezone','Asia/Shanghai');

        $validate = new Validate([
            'exchange'     => 'require',
            'market'     => 'require',
            'currency'     => 'require',
        ]);

        $validate->message([
            'exchange.require'  => '交易所不能为空!',
            'market.require'  => '交易对不能为空!',
            'currency.require'  => '货币类型不能为空!',
        ]);


        $data = $this->request->param();

        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $this->exchange_name = $data['exchange'];

        $this->market = $data['market'];

        //汇率查询
        $rateData = Db::name("rate")->select();
        $rate = array();
        $currency_symbol = array();
        foreach ($rateData as $key => $value) {
            $rate[$value['currency_symbol']] =  $value['usd_rate'];
            $currency_symbol[$value['currency_symbol']] =  $value['currency_symbol_char'];
        }

        if (!isset($rate[$data['currency']])) {
            $this->error("未知货币参数!");
        }

        $this->currency_rate = floatval($rate[$data['currency']]);
        $this->currency_char = $currency_symbol[$data['currency']];

        // $exchange_data = Db::name('ticker')
        // ->field('id,exchange_class,market,coin,currency')
        // ->where('exchange_name', $this->exchange_name)
        // ->where('market', $this->market)
        // ->where('status', 1)
        // ->find();  

        // if(empty($exchange_data)){
        //     $this->error('指定交易所交易所不存在');
        // }      

        // $this->exchange_class = $exchange_data['exchange_class'];

        $platforms = Db::name("third_platform")->select()->toArray();
        $exchange_class = array_column($platforms, 'class', 'platform');
        //$exchange_class = array('okex' => 'okex3', 'binance' => 'binance', 'huobi' => 'hbdm', 'gateio' => 'gateio');
        if (!isset($exchange_class[$this->exchange_name])) {
            $this->error('无法获取指定交易所行情:',  $this->exchange_name);
        }

        $this->exchange_class = $exchange_class[$this->exchange_name];
        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);

        $this->className = "\ccxt\\" . $this->exchange_class;
    }

    private function setRedisValue($function_name, $value)
    {
        $data = $this->request->param();
        $item_name = "";
        if (isset($data['exchange'])) {
            $item_name = $item_name . strtoupper($data['exchange']);
        }
        if (isset($data['market'])) {
            $item_name = $item_name . strtoupper($data['market']);
        }
        if (isset($data['currency'])) {
            $item_name = $item_name . strtoupper($data['currency']);
        }
        if (isset($data['timeframe'])) {
            $item_name = $item_name . strtoupper($data['timeframe']);
        }
        $item_name = $item_name . strtoupper($function_name);
        $redis = new RedisPackage();
        $redis::set($item_name, json_encode($value));
        $item_name = $item_name . "TIME";
        $redis::set($item_name, time());
    }


    private function getRedisValue($function_name, $force = false)
    {
        $data = $this->request->param();
        $item_name = "";
        if (isset($data['exchange'])) {
            $item_name = $item_name . strtoupper($data['exchange']);
        }
        if (isset($data['market'])) {
            $item_name = $item_name . strtoupper($data['market']);
        }
        if (isset($data['currency'])) {
            $item_name = $item_name . strtoupper($data['currency']);
        }
        if (isset($data['timeframe'])) {
            $item_name = $item_name . strtoupper($data['timeframe']);
        }
        $item_name = $item_name . strtoupper($function_name);
        $item_name2 = $item_name . "TIME";
        //  print_r($item_name);exit;
        //var_dump($item_name);
        //var_dump($item_name2);
        $redis = new RedisPackage();

        $ret = $redis::get($item_name2);
        if ($ret == false) {
            return false;
        }
        $ret = intval($ret);
        if ((time() - $ret > 5) && !$force) {
            return false;
        }
        $ret = $redis::get($item_name);
        if ($ret == false) {
            return false;
        } else {
            return json_decode($ret, true);
        }
    }

    private function fetchBinancePublicTicker($market)
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('PHP curl 扩展未启用');
        }
        $symbol = strtoupper(str_replace(['/', '-', '_'], '', (string) $market));
        if ($symbol === '') {
            throw new \RuntimeException('交易对格式错误');
        }
        $url = 'https://api.binance.com/api/v3/ticker/24hr?symbol=' . rawurlencode($symbol);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false || $status < 200 || $status >= 300) {
            throw new \RuntimeException($error !== '' ? $error : 'Binance HTTP ' . $status);
        }
        $response = json_decode($body, true);
        if (!is_array($response) || !isset($response['lastPrice'])) {
            throw new \RuntimeException('Binance 行情返回格式异常');
        }

        return [
            'symbol' => $this->market,
            'open' => (float) ($response['openPrice'] ?? 0),
            'high' => (float) ($response['highPrice'] ?? 0),
            'low' => (float) ($response['lowPrice'] ?? 0),
            'last' => (float) $response['lastPrice'],
            'percentage' => (float) ($response['priceChangePercent'] ?? 0),
            'baseVolume' => (float) ($response['volume'] ?? 0),
        ];
    }

    public function redisTest()
    {


        $cache = $this->getRedisValue(__FUNCTION__);
        if ($cache != false) {
            $this->success('查询成功.', $cache);
        }

        $exchange  = new $this->className();

        $result = $exchange->fetch_ticker($this->market);

        $data = array();

        $data['symbol'] = $result['symbol'];

        $data['open'] = $result['open'];

        $data['hign'] = $result['high'];

        $data['low'] = $result['low'];

        //$data['bid'] = $result['bid'];

        //$data['ask'] = $result['ask'];

        $data['last'] = $result['last'];

        $data['price'] = intval($result['last'] * $this->currency_rate * 100) / 100;


        $data['currency_symbol'] = $this->currency_char;

        $data['percentage'] = intval(($data['last'] - $data['open']) * 10000 / $data['open']) / 100;

        $data['volume'] = $result['baseVolume'];

        //var_dump($result);
        $this->setRedisValue(__FUNCTION__, $data);

        $this->success('查询成功', $data);
    }


    /* 行情 */
    public function ticker()
    {
        
        $cache = $this->getRedisValue(__FUNCTION__);
        if ($cache != false) {
            $this->success('查询成功.', $cache);
        }

        try {
            if (strtolower($this->exchange_name) === 'binance') {
                $result = $this->fetchBinancePublicTicker($this->market);
            } else {
                $exchange = new $this->className();
                $result = $exchange->fetch_ticker($this->market);
            }
        } catch (\Throwable $e) {
            $cache = $this->getRedisValue(__FUNCTION__, true);
            if ($cache != false) {
                $this->success('查询成功.', $cache);
                return;
            } else {
                $this->error('行情获取失败：' . $e->getMessage());
                return;
            }
        }

        $data = array();

        $data['symbol'] = $result['symbol'];

        $data['open'] = $result['open'];

        $data['hign'] = $result['high'];

        $data['low'] = $result['low'];

        //$data['bid'] = $result['bid'];

        //$data['ask'] = $result['ask'];

        $data['last'] = $result['last'];

        $data['price'] = intval($result['last'] * $this->currency_rate * 100) / 100;


        $data['currency_symbol'] = $this->currency_char;

        $data['percentage'] = isset($result['percentage'])
            ? (float) $result['percentage']
            : ($data['open'] > 0 ? intval(($data['last'] - $data['open']) * 10000 / $data['open']) / 100 : 0);

        $data['volume'] = $result['baseVolume'];

        //var_dump($result);
        $this->setRedisValue(__FUNCTION__, $data);

        $this->success('查询成功', $data);
    }

    /* 交易深度挂单 */
    public function orderBook()
    {

        $cache = $this->getRedisValue(__FUNCTION__);
        if ($cache != false) {
            $this->success('查询成功.', $cache);
        }

        $exchange  = new $this->className();

        try {
            $result = $exchange->fetch_order_book($this->market, 10);
        } catch (\Exception $e) {
            $cache = $this->getRedisValue(__FUNCTION__, true);
            if ($cache != false) {
                $this->success('查询成功.', $cache);
            } else {
                $this->success('查询成功.', array());
            }
        }
        $this->setRedisValue(__FUNCTION__, $result);

        $this->success('查询成功', $result);
    }

    /* 成交记录 */
    public function trades()
    {

        $cache = $this->getRedisValue(__FUNCTION__);
        if ($cache != false) {
            $this->success('查询成功.', $cache);
        }

        $exchange  = new $this->className();

        try {
            $result = $exchange->fetch_trades($this->market, 0, 20);
        } catch (\Exception $e) {
            $cache = $this->getRedisValue(__FUNCTION__, true);
            if ($cache != false) {
                $this->success('查询成功.', $cache);
            } else {
                $this->success('查询成功.', array());
            }
        }
        $data = array();

        foreach ($result as $key => $value) {
            $record = array();
            $record['symbol'] = $value['symbol'];
            $record['side'] = $value['side'];
            $record['price'] = $value['price'];
            $record['amount'] = $value['amount'];
            $record['time'] = date('H:i:s', intval($value['timestamp'] / 1000) + 8 * 3600);
            $data[] = $record;
        }
        $data = array_reverse($data);

        $this->setRedisValue(__FUNCTION__, $data);

        $this->success('查询成功', $data);
    }

    /*K线数据*/

    public function kline()
    {

        $cache = $this->getRedisValue(__FUNCTION__);
        if ($cache != false) {
            $this->success('查询成功.', $cache);
        }

        $timeframe     = $this->request->param('timeframe', '1m');

        $exchange  = new $this->className();
        /*
            $ohlcv['Timestamp'],
            $ohlcv['Open'],
            $ohlcv['High'],
            $ohlcv['Low'],
            $ohlcv['Close'],
            $ohlcv['Volume'],
        */
        try {
            $result = $exchange->fetch_ohlcv($this->market, $timeframe);
        } catch (\Exception $e) {
            $cache = $this->getRedisValue(__FUNCTION__, true);
            if ($cache != false) {
                $this->success('查询成功.', $cache);
            } else {
                $this->success('查询成功.', array());
            }
        }

        $this->success('查询成功', $result);
    }

    // public function kline2(){

    //     for ($i=1; $i < 574; $i++) { 
    //         $date_str = date("Y-m-d",$i*3600*24 + 1514736000 );
    //         echo "$date_str<br>";

    //         $result = $this->read_csv("D:\www\test\public\csv\huobipro_ETH_BTC_".$date_str."_1T.csv");

    //         //var_dump($result);die();

    //         $insert_data = array();


    //         foreach ($result as $key => $value) {
    //             $item = array();

    //             $item['market'] =  "ETH/BTC";
    //             $item['ctime'] = strtotime($value['1']);            
    //             $item['open'] = $value['2'];
    //             $item['high'] = $value['3'];
    //             $item['low'] = $value['4'];
    //             $item['close'] = $value['5'];
    //             $item['volume'] = $value['6'];

    //             $insert_data[] = $item;
    //         }

    //         //var_dump($insert_data);die();

    //         Db::name("history_ticker")->insertAll($insert_data);     
    //     }

    // }


    // function read_csv($file)
    // {
    //     setlocale(LC_ALL,'zh_CN');//linux系统下生效
    //     $data = null;//返回的文件数据行
    //     if(!is_file($file)&&!file_exists($file))
    //     {
    //         die('文件错误');
    //     }
    //     $cvs_file = fopen($file,'r'); //开始读取csv文件数据
    //     $i = 0;//记录cvs的行
    //     while ($file_data = fgetcsv($cvs_file))
    //     {
    //         $i++;
    //         if($i==1)
    //         {
    //             continue;//过滤表头
    //         }
    //         if($file_data[0]!='')
    //         {
    //             $data[$i] = $file_data;
    //         }

    //     }
    //     fclose($cvs_file);
    //     return $data;
    // }

}
