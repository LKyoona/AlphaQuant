<?php

namespace api\home\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;

class CronController extends RestBaseController
{

    public function _initialize()
    {
        //因为操作时间会很长，设置php操作超时时间600秒
        set_time_limit(600);
    }

    public function index()
    {
        //查询任务
        $fieldStr = 'id,task_name,params,uid,fail_times';
        $tasks = Db::name('cron')
            ->field($fieldStr)
            ->where('status', 0)
            ->where('schedule_time', 'lt', time())
            ->order('schedule_time asc,id asc')
            ->select()->toArray();
        foreach ($tasks as $key => $task) {
            //执行对应的任务
            $result = array('code' => 0, 'msg' => "");
            $task_id = $task['id'];
            $fail_times = $task['fail_times'];
            $fun_name = $task['task_name'];
            if (!empty($task['params'])) {
                $params = json_decode($task['params'], true);
            } else {
                $params = array();
            }
            if (method_exists($this, $fun_name)) {
                $ret = $this->$fun_name($params);
                $result = array('code' => $ret['code'], 'msg' => $ret['msg']);
            } else {
                $result = array('code' => 0, 'msg' => "function generate_user_wallet_address not exists");
            }
            //标记任务状态
            $update_data = array();
            if ($result['code'] == 0) {
                $fail_times = $fail_times + 1;
                $update_data['result'] = $result['msg'];
                $update_data['fail_times'] = $fail_times;
                if ($fail_times > 3) { //失败超过三次 就
                    $update_data['status'] = -1;
                } else {
                    $update_data['status'] =  0;
                    $update_data['schedule_time'] =  time() + $fail_times * 60;  //延迟再次执行                                   
                }
                $result = Db::name('cron')->where('id', $task_id)->update($update_data);
            } elseif ($result['code'] == 1) {
                $update_data['status'] =  1;
                $update_data['result'] = $result['msg'];
                //$result = Db::name('cron')->where('id', $task_id)->update($update_data);
                $result = Db::name('cron')->where('id', $task_id)->delete();
            } else {
            }
            //进行下一个任务
        }
    }

    //生成钱包地址
    function generate_user_wallet_address($params)
    {
        $userId = $params['uid'];
        $coin_symbol = strtoupper($params['coin_symbol']);
        // if ($coin_symbol == 'IOTA' || $coin_symbol == 'ADA') {
        //     return array('code' => -1, 'msg' => "pass");
        // }
        //检测钱包是否存在
        $fieldStr = 'coin_type,rpc_ip,rpc_port,rpc_user,rpc_pass,b.id,b.address,IFNULL(b.status,-1) as status';
        $coin_data = Db::name('coin')
            ->alias('a')
            ->join(config('database.prefix') . 'wallet b', "a.coin_symbol = b.coin_symbol and uid = $userId and type = 1", "LEFT")
            ->field($fieldStr)
            ->where('a.coin_symbol', $coin_symbol)
            ->find();
        if (empty($coin_data)) { //没有该币种
            return array('code' => 0, 'msg' => "coin symbol not exists");
        }

        if ($coin_data['coin_type'] != 'coin') {
            //return array('code'=>1,'msg'=>"given coin symbol is token"); 
        }
        if ($coin_data['status'] == -1) { //生成钱包
            $insert_data['uid'] =  $userId;
            $insert_data['coin_symbol'] =  $coin_symbol;
            $insert_data['type'] =  1;
            $insert_data['add_time'] =  time();
            $result = Db::name('wallet')->insertGetId($insert_data);
            if (!$result) {
                return array('code' => 0, 'msg' => "user wallet create failed");
            }
            $wallet_id = $result;
        } else {
            $wallet_id = $coin_data['id'];
        }

        if (!empty($coin_data['address'])) {
            return array('code' => 1, 'msg' => "user wallet already exists");
        }
        //币种rpc配置
        if ($coin_data['coin_type'] != 'coin') {
            //$coin = strtolower($coin_data['parent_coin']); 
            //return array('code'=>1,'msg'=>"$coin_symbol is  token"); 
        }

        $vendor_name = "Chain.walletapi";
        Vendor($vendor_name);
        $class_name = "\\walletapi";
        if (!class_exists($class_name)) {
            return array('code' => 0, 'msg' => "$class_name not exists");
        }
        //RPC连接
        $rpc = new $class_name();
        //生成钱包地址钱包  
        if (!method_exists($rpc, "account_create")) {
            return array('code' => 0, 'msg' => "$class_name method account_create not exists");
        }
        $ret = $rpc->account_create($userId, $coin_symbol);

        if ($ret['code'] == 1) {
            $update_data['seed'] = $ret['data']['seed'];
            $update_data['memo'] = $ret['data']['memo'];
            $update_data['address'] = $ret['data']['address'];
            $result = Db::name('wallet')->where('id', $wallet_id)->update($update_data);
            return array('code' => 1, 'msg' => json_encode($update_data));
        } else {
            return array('code' => 0, 'msg' => $ret['data']);
        }
    }

    //更新钱包余额
    function update_wallet_balance($params)
    {
        $type = $params['type'];
        $userId = $params['uid'];
        $coin_symbol = $params['coin_symbol'];
        if ($coin_symbol == 'IOTA' || $coin_symbol == 'ADA') {
            return array('code' => -1, 'msg' => "pass");
        }
        //检测钱包是否存在
        $fieldStr = 'coin_type,contract,decimals,parent_coin,rpc_ip,rpc_port,rpc_user,rpc_pass,b.id,b.address,b.seed,IFNULL(b.status,-1) as status';
        $coin_data = Db::name('coin')
            ->alias('a')
            ->join(config('database.prefix') . 'wallet b', "a.coin_symbol = b.coin_symbol and uid = $userId and type = $type", "LEFT")
            ->field($fieldStr)
            ->where('a.coin_symbol', $coin_symbol)
            ->find();
        if (empty($coin_data)) { //没有该币种
            return array('code' => 1, 'msg' => "coin symbol not exists");
        }
        if (empty($coin_data['address'])) {
            return array('code' => 1, 'msg' => "user wallet not exists");
        }
        $wallet_id = $coin_data['id'];
        $seed = $coin_data['seed'];

        if ($type == 1) {
            $vendor_name = "Chain.walletapi";
            Vendor($vendor_name);
            $class_name = "\\walletapi";
            if (!class_exists($class_name)) {
                return array('code' => 0, 'msg' => "$class_name not exists");
            }
            //RPC连接
            $rpc = new $class_name();
            //查询余额
            if (!method_exists($rpc, "account_info")) {
                return array('code' => 0, 'msg' => "$class_name method account_info not exists");
            }
            $ret = $rpc->account_info($seed);
            if ($ret['code'] == 1) {
                $update_data = array();
                $update_data['chain_balance'] = $ret['data']['balance'];
                $result = Db::name('wallet')->where('id', $wallet_id)->update($update_data);
                $balance = $update_data['chain_balance'];
                return array('code' => 1, 'msg' => "$class_name method account_info ok : $userId  $coin_symbol $balance");
            } else {
                return array('code' => 0, 'msg' => "$class_name account_info error:" . $ret['data']);
            }
        } else {

            //币种rpc配置
            if ($coin_data['coin_type'] == 'coin') {
                $coin = strtolower($params['coin_symbol']);
                $rpc_ip =   $coin_data['rpc_ip'];
                $rpc_port = $coin_data['rpc_port'];
                $rpc_user = $coin_data['rpc_user'];
                $rpc_pass = $coin_data['rpc_pass'];
            } else {
                $coin = strtolower($coin_data['parent_coin']);
                $parent_coin_data = Db::name('coin')
                    ->field('rpc_ip,rpc_port,rpc_user,rpc_pass')
                    ->where('coin_symbol', $coin_data['parent_coin'])
                    ->find();
                $rpc_ip =   $parent_coin_data['rpc_ip'];
                $rpc_port = $parent_coin_data['rpc_port'];
                $rpc_user = $parent_coin_data['rpc_user'];
                $rpc_pass = $parent_coin_data['rpc_pass'];
            }
            if (empty($rpc_ip) || empty($rpc_port)) {
                return array('code' => 0, 'msg' => "rpc ip or port not set");
            }
            $vendor_name = "Chain." . $coin . "rpc";
            Vendor($vendor_name);
            $class_name = "\\" . $coin . "rpc";
            if (!class_exists($class_name)) {
                return array('code' => 0, 'msg' => "$class_name not exists");
            }

            //RPC连接
            $rpc = new $class_name($rpc_ip, $rpc_port, $rpc_user, $rpc_pass);
            //查询余额
            if ($coin_data['coin_type'] == 'coin') {
                $api_method = "get_Balance";
                if (!method_exists($rpc, $api_method)) {
                    return array('code' => 0, 'msg' => "$class_name method $api_method not exists");
                }
                $ret = $rpc->$api_method($coin_data['address']);
                if ($ret['code'] == 1) {
                    $update_data = array();
                    $update_data['chain_balance'] = $ret['data']['balance'];
                    $result = Db::name('wallet')->where('id', $wallet_id)->update($update_data);
                    $balance = $update_data['chain_balance'];
                    return array('code' => 1, 'msg' => "$class_name method $api_method ok : $userId  $coin_symbol $balance");
                } else {
                    return array('code' => 0, 'msg' => "$class_name $api_method error:" . $ret['data']);
                }
            } else {
                if ($coin_data['parent_coin'] == 'ETH') {
                    $api_method = "get_TokenBalance";
                    if (!method_exists($rpc, $api_method)) {
                        return array('code' => 0, 'msg' => "$class_name method $api_method not exists");
                    }
                    $ret = $rpc->$api_method($coin_data['address'], $coin_data['contract'], $coin_data['decimals']);
                    //$ret = $rpc->$api_method("0x54f3e53bea04a3989114b8885ac16cc0fadcf2ec",$coin_data['contract'],$coin_data['decimals']); 
                    //var_dump($ret);
                    if ($ret['code'] == 1) {
                        $update_data = array();
                        $update_data['chain_balance'] = $ret['data']['balance'];
                        $result = Db::name('wallet')->where('id', $wallet_id)->update($update_data);
                        $balance = $update_data['chain_balance'];
                        return array('code' => 1, 'msg' => "$class_name method $api_method ok : $userId  $coin_symbol $balance");
                    } else {
                        return array('code' => 0, 'msg' => "$class_name $api_method error:" . $ret['data']);
                    }
                } else {
                    $parent_coin = $coin_data['parent_coin'];
                    return array('code' => 1, 'msg' => "$parent_coin token : $coin_symbol not support now");
                }
            }
        }
    }

    //初始化用户钱包
    function init_user_wallet($params)
    {
        $userId = $params['uid'];
        $wallet_count = Db::name('wallet')
            ->where('uid', $userId)
            ->where('type', 1)
            ->count();
        //初始化生成钱包
        if ($wallet_count == 0) {
            $param['uid'] = $userId;
            $result = hook_one("generate_wallet", $param);
            if ($result !== false && !empty($result['error'])) {
                return array('code' => 0, 'msg' => $result['message']);
            }
            if ($result === false) {
                return array('code' => 0, 'msg' => 'hook generate_wallet not exists');
            }
            return array('code' => 1, 'msg' => 'init_user_wallet ok');
        } else {
            return array('code' => 1, 'msg' => 'wallet_count already > 1');
        }
    }
    //初始化用户行情
    function init_user_ticker($params)
    {
        $userId = $params['uid'];

        $ticker_count = Db::name('user_ticker')
            ->where('uid', $userId)
            ->count();

        //初始化生成默认行情
        if ($ticker_count == 0) {
            $param['uid'] = $userId;
            $result = hook_one("generate_ticker", $param);
            if ($result !== false && !empty($result['error'])) {
                return array('code' => 0, 'msg' => $result['message']);
            }
            if ($result === false) {
                return array('code' => 0, 'msg' => 'hook generate_ticker not exists');
            }
            return array('code' => 1, 'msg' => 'init_user_ticker ok');
        } else {
            return array('code' => 1, 'msg' => 'ticker_count already > 1');
        }
    }

    //更新行情
    function update_ticker($params)
    {


        $ticker_id_list = array();
        $ticker_data = Db::name('ticker')
            ->field('id')
            ->order('update_time asc')
            ->limit(0, 6)
            ->select()
            ->toArray();

        $platforms = Db::name("third_platform")->select()->toArray();
        $exchange_class = array_column($platforms, 'class', 'platform');
        //$exchange_class = array('okex' => 'okex3', 'binance' => 'binance', 'huobi' => 'hbdm', 'gateio' => 'gateio');


        foreach ($ticker_data as $key => $value) {
            array_push($ticker_id_list, $value['id']);
        }
        $tickers = array();
        foreach ($ticker_id_list as $ticker_id) {
            $tickerData = Db::name('ticker')
                ->field('id,exchange_name,exchange_class,market,coin,currency')
                ->where('id', $ticker_id)
                ->where('status', 1)
                ->find();

            if (!empty($tickerData)) {
                $exchange_class_name = $tickerData['exchange_class'];

                if (!isset($exchange_class[$exchange_class_name])) {
                    continue;
                }
                $market = $tickerData['market'];
                //ccxt lib
                $vendor_name = "ccxt.ccxt";
                Vendor($vendor_name);
                date_default_timezone_set('Etc/GMT-8'); 
                $className = "\ccxt\\" . $exchange_class[$exchange_class_name];
                $exchange  = new $className();
                if(!isset($tickers[$exchange_class_name])){
                     $tickers = $exchange->fetch_tickers();
                }else{
                     $tickers = $tickers[$exchange_class_name];
                }
                if(!isset($tickers[$market])){
                    continue;
                }
                $result = $tickers[$market];
                $update_data['price'] = $result['last'];
                $update_data['change'] = $result['percentage']; //intval(($result['last']-$result['open'])*10000/$result['open'])/100;
                $update_data['volume'] = $result['baseVolume'];
                $update_data['update_time'] = time();
                Db::name('ticker')->where('id', $ticker_id)->update($update_data);
                //更新币种价格
                if ($tickerData['currency'] == 'USDT' && $tickerData['exchange_class'] = 'huobipro') {
                    $coin_data = array();
                    $coin_data['usd_price'] = $update_data['price'];
                    $coin_data['price_change'] = $update_data['change'];
                    $coin_data['price_change_week'] = $update_data['change'];
                    Db::name('coin')->where('coin_symbol', $tickerData['coin'])->update($coin_data);
                }
            }
        }
        // //更新闪对汇率
        // //读取币种价格
        // $coin_price_data = Db::name('coin')
        // ->field("coin_symbol,usd_price")
        // ->select();
        // $coin_price = array();      
        // foreach ($coin_price_data as $key => $value) {
        //     $coin_price[$value['coin_symbol']] = floatval($value['usd_price']); 
        // }
        // //查找闪对表
        // $exchange_data = Db::name('exchange')
        // ->field("id,from_coin,to_coin")
        // ->select(); 
        // foreach ($exchange_data as $key => $value) {
        //     if(isset($coin_price[$value['from_coin']])&&isset($coin_price[$value['to_coin']])){
        //         if($coin_price[$value['from_coin']]>0&&$coin_price[$value['to_coin']]>0){
        //             $update_rate_data = array();

        //             $update_rate_data['rate'] =sprintf("%01.6f",$coin_price[$value['from_coin']]/$coin_price[$value['to_coin']]);
        //             Db::name('exchange')->where('id', $value['id'])->update($update_rate_data); 
        //         }
        //     }
        // }       
        //写入下一个任务


        //检测是否已经有任务没有执行
        $task_check =  Db::name('cron')
            ->where('task_name', "update_ticker")
            ->where('status', 0)
            ->count();
        if ($task_check <= 1) {
            //写入下一个任务
            $task_data['params'] = "";
            $task_data['task_name'] = "update_ticker";
            $task_data['uid'] = 0;
            $task_data['schedule_time'] = time() + 5;
            Db::name('cron')->insert($task_data);
        }
        return array('code' => 1, 'msg' => 'update_ticker ok');
    }


    //更新汇率
    function  update_rate($params)
    {

        $currency_list = array();
        $rate_data = Db::name('rate')
            ->field('currency_symbol')
            ->where('currency_symbol', 'neq', "USD")
            ->select()->toArray();
        foreach ($rate_data as $key => $value) {
            array_push($currency_list, $value['currency_symbol']);
        }

        $url = "http://op.juhe.cn/onebox/exchange/currency";

        foreach ($currency_list as $value) {
            $params = array(
                "from" => "USD", //转换汇率前的货币代码
                "to" => $value, //转换汇率成的货币代码
                "key" => "6441bc5cc96078b6621b839da7c68520", //应用APPKEY(应用详细页查询)
            );
            $paramstring = http_build_query($params);
            $content = $this->curl_get($url, $paramstring);
            $result = json_decode($content, true);
            if ($result) {
                if ($result['error_code'] == '0') {

                    $currency_symbol = $value;
                    $usd_rate =  $result['result'][0]['exchange'];
                    //var_dump($currency_symbol);
                    //var_dump($usd_rate);
                    $update_rate_data['usd_rate'] = $usd_rate;
                    Db::name('rate')->where('currency_symbol', $currency_symbol)->update($update_rate_data);

                    if ($currency_symbol == "CNY") {
                        Db::name('coin')->where("coin_symbol = 'CNY'")->update(["usd_price" => 1 / $usd_rate]);
                        // var_dump(1/$usd_rate);die();
                    }
                } else {
                    return array('code' => 0, 'msg' => $result['error_code'] . ":" . $result['reason']);
                }
            } else {
                return array('code' => 0, 'msg' => 'request failed');
            }
        }
        $task_check =  Db::name('cron')
            ->where('task_name', "update_rate")
            ->where('status', 0)
            ->count();
        if ($task_check <= 1) {
            //写入下一个任务
            $task_data['params'] = "";
            $task_data['task_name'] = "update_rate";
            $task_data['uid'] = 0;
            $task_data['schedule_time'] = time() + 3600 * 6;
            Db::name('cron')->insert($task_data);
        }
        return array('code' => 1, 'msg' => 'update_rate ok');
    }


    //检测订阅是否到期
    function  check_follow($params)
    {
        $time_now =  time();
        $expire_info = Db::name("follower")
            ->where("deadline < $time_now and deadline<>0 and status = 1")
            ->field("id")
            ->select();
        if (!empty($expire_info)) {
            $ids = array();
            foreach ($expire_info as $key => $value) {
                $ids[] = $value['id'];
            }
            $update_info = array('status' => 0);
            Db::name("follower")->where('id', 'in', $ids)->update($update_info);
        }
        //写入下一个任务
        //检测是否已经有任务没有执行
        $task_check =  Db::name('cron')
            ->where('task_name', "check_follow")
            ->where('status', 0)
            ->count();
        if ($task_check <= 1) {
            $task_data['params'] = "";
            $task_data['task_name'] = "check_follow";
            $task_data['uid'] = 0;
            $task_data['schedule_time'] = time() + 3600;
            Db::name('cron')->insert($task_data);
        }
        return array('code' => 1, 'msg' => 'check_follow ok');
    }


    //检测红包是否到期
    function  check_redenvelope($params)
    {
        $time_now =  time();
        $expire_info = Db::name("user_red_envelope")
            ->where("deadline_time < $time_now and status = 1")
            ->field("id,uid,coin_symbol")
            ->select();
        if (!empty($expire_info)) {
            foreach ($expire_info as $key => $value) {
                $id = $value['id'];
                $userId = $value['uid'];
                $coin_symbol = $value['coin_symbol'];
                //子红包剩余余额
                $child_sum = Db::name('red_envelope')
                    ->where('envelope_id', $id)
                    ->where('status', 1)
                    ->sum('amount');
                //退还余额
                Db::startTrans();
                $update_info = array('status' => 0);
                Db::name("user_red_envelope")->where('id', $id)->update($update_info);
                Db::name("red_envelope")->where('envelope_id', $id)->update($update_info);
                if ($child_sum > 0) {
                    //var_dump($child_sum);var_dump($id);die();
                    //获取用户钱包余额    
                    $walletData = Db::name('wallet')
                        ->field("id,cloud_balance")
                        ->where('uid', $userId)
                        ->where('coin_symbol', $coin_symbol)
                        ->find();

                    if (empty($walletData)) { //生成钱包
                        $insert_data = array();
                        $insert_data['uid'] =  $userId;
                        $insert_data['coin_symbol'] = $coin_symbol;
                        $insert_data['type'] =  1;
                        $insert_data['add_time'] =  time();
                        $result = Db::name('wallet')->insertGetId($insert_data);
                        $wallet_id = $result;
                        $balance = 0;
                    } else {
                        $wallet_id = $walletData['id'];
                        $balance = $walletData['cloud_balance'];
                    }

                    $result = Db::name('wallet')
                        ->where('id', $wallet_id)
                        ->setInc('cloud_balance', $child_sum);

                    //获取新余额
                    $balance_data = Db::name('wallet')
                        ->field('cloud_balance')
                        ->where('id', $wallet_id)
                        ->find();
                    //写入交易
                    $balance_after =  $balance_data['cloud_balance'];
                    $insert_data = array();
                    $insert_data['uid']  =  $userId;
                    $insert_data['type'] =  11; //兑换
                    $insert_data['wallet_type'] =  1;
                    $insert_data['related_id'] = $id; //红包ID
                    $insert_data['wallet_id'] = $wallet_id;
                    $insert_data['coin_symbol'] =  $coin_symbol;
                    $insert_data['from_address'] =  '';
                    $insert_data['to_address'] =  '';
                    $insert_data['amount'] =  $child_sum;
                    $insert_data['amount_before'] =   $balance;
                    $insert_data['amount_after'] =   $balance_after;
                    $insert_data['fee'] =   0;
                    $insert_data['log_time'] =  time();
                    $insert_data['memo'] = "红包过期退还:#$id";
                    $insert_data['transfer_status'] =  1;
                    $result = Db::name('transfer_log')->insert($insert_data);
                    if ($result) {
                        Db::commit();
                    } else {
                        Db::rollback();
                    }
                } else {
                    Db::commit();
                }
            }
        }
        //检测是否已经有任务没有执行
        $task_check =  Db::name('cron')
            ->where('task_name', "check_redenvelope")
            ->where('status', 0)
            ->count();
        if ($task_check <= 1) {
            //写入下一个任务
            $task_data['params'] = "";
            $task_data['task_name'] = "check_redenvelope";
            $task_data['uid'] = 0;
            $task_data['schedule_time'] = time() + 60;
            Db::name('cron')->insert($task_data);
        }
        return array('code' => 1, 'msg' => 'check_redenvelope ok');
    }


    function curl_get($url, $params = false, $ispost = 0)
    {
        $httpInfo = array();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if ($ispost) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            if ($params) {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        $response = curl_exec($ch);
        if ($response === FALSE) {
            //echo "cURL Error: " . curl_error($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
        curl_close($ch);
        return $response;
    }




    public function test()
    {
        /*$vendor_name = "Chain.walletapi";
        Vendor($vendor_name);
        $class_name = "\\walletapi";
        if(!class_exists($class_name)){
            return array('code'=>0,'msg'=>"$class_name not exists");
        }                    
        //RPC连接
        $rpc = new $class_name();    
        //生成钱包地址钱包  
        if (!method_exists($rpc,"account_create")) {
            return array('code'=>0,'msg'=>"$class_name method account_create not exists");   
        }        
        $ret = $rpc->account_create(80,"BTC");

        if($ret['code']==1){
            $update_data['seed'] = $ret['data']['seed'];
            $update_data['memo'] =$ret['data']['memo'] ;
            $update_data['address'] = $ret['data']['address'] ;
            return array('code'=>1,'msg'=>json_encode($update_data));                 
        }else{
            return array('code'=>0,'msg'=>$ret['data']);            
        }*/

        $seed = 470;
        $vendor_name = "Chain.walletapi";
        Vendor($vendor_name);
        $class_name = "\\walletapi";
        if (!class_exists($class_name)) {
            return array('code' => 0, 'msg' => "$class_name not exists");
        }
        //RPC连接
        $rpc = new $class_name();
        //查询余额
        if (!method_exists($rpc, "account_info")) {
            return array('code' => 0, 'msg' => "$class_name method account_info not exists");
        }
        $ret = $rpc->account_info($seed);
        if ($ret['code'] == 1) {
            $update_data = array();
            $update_data['chain_balance'] = $ret['data']['balance'];
            //$result = Db::name('wallet')->where('id',$wallet_id)->update($update_data);
            $balance = $update_data['chain_balance'];
            return array('code' => 1, 'msg' => "$class_name method account_info ok :  $balance");
        } else {
            return array('code' => 0, 'msg' => "$class_name account_info error:" . $ret['data']);
        }
    }
}
