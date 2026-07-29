<?php

namespace api\wallet\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;
use api\common\service\InviteRule;
use api\common\service\ColumnName;

class NotifyController extends RestBaseController
{

    protected $_md5_key = 'wallet03020203555';
    
   
    
    public function index()
    {

        $params = $this->request->param();

        if (!isset($params['sign'])) {
            die('sign not set');
        }

        $sign = $params['sign'];

        $params_final = $this->filterPara($params);

        $sign_str =  $this->buildRequestMysign($params_final);

        if ($sign_str !== $sign) {
            die('sign error');   
        }

        $notify_type = $params['notify_type'];
        $from_address = $params['from_address'];
        $to_address = $params['to_address'];
        $amount = $params['amount'];
        $blockhash = '';
        $txid = $params['tx_id']; //链上交易ID
        $fee = $params['fee']; //fee

        $coin_symbol = $params['coin_symbol'];
        if (isset($params['wallet_memo'])) {
            $wallet_memo = $params['wallet_memo'];
        } else {
            $wallet_memo = '';
        }

        $memo = $params['transaction_id'];

        if ($notify_type == 'payment') {

            $this->add_transfer_log($coin_symbol, $from_address, $to_address, $wallet_memo, $memo, $amount, $blockhash, $txid, $fee);
            die('success');
        }

        if ($notify_type == 'confirm') {
            //echo 'confirm';
            //echo '|memo:'.$memo;
            $this->confirm_transfer($coin_symbol, $from_address, $to_address, $wallet_memo, $memo, $amount, $blockhash, $txid, $fee);
            die('success');
        }
    }


    public function add_transfer_log($coin_symbol, $from_address, $to_address, $wallet_memo, $memo, $amount, $blockhash, $txid, $fee)
    {
        //先查找记录是否存在
        $log = Db::name('transfer_log')
            ->where('memo', $memo)
            ->where('tx_id', $txid)
            ->where('transfer_status', 1)
            ->find();
        if ($log) {
            return;
        }
        //获取用户钱包余额    
        $walletData_all = Db::name('wallet')
            ->field("id,uid,type,chain_balance,cloud_balance")
            ->where('coin_symbol', $coin_symbol)
            ->where('address', $to_address)
            ->where('memo', $wallet_memo)
            ->select();
        foreach ($walletData_all as $key => $walletData) {
            if (empty($walletData))
                return;
            if ($walletData['type'] == 1) {  //云端钱包
                $walletId = $walletData['id'];
                if ($coin_symbol === 'USDT-TRC') {
                    $coin_symbol = 'USDT';
                    $usdtWallet = Db::name('wallet')
                        ->field("id,uid,type,chain_balance,cloud_balance")
                        ->where('coin_symbol', 'USDT')
                        ->where('uid', $walletData['uid'])
                        ->find();
                    if ($usdtWallet) {
                        $walletId = $usdtWallet['id'];
                        $walletData = $usdtWallet;
                    }
                }

                //开始事务处理
                Db::startTrans();
                $result = Db::name('wallet')
                    ->where('id', $walletId)
                    ->setInc('cloud_balance', $amount);
                if (!$result) {
                    Db::rollback();
                    //$this->error('转账提交失败(#1)！'); 
                    return;
                }
                $balance =  $walletData['cloud_balance'];
                //获取新余额
                $balance_data = Db::name('wallet')
                    ->field('cloud_balance')
                    ->where('id', $walletId)
                    ->find();
                //写入交易日志
                $balance_after =  $balance_data['cloud_balance'];
                $insert_data['uid']  = $walletData['uid'];
                $insert_data['type'] =  2; //云端充值
                $insert_data['wallet_type'] =  1;
                $insert_data['wallet_id'] = $walletId;
                $insert_data['coin_symbol'] =  $coin_symbol;
                $insert_data['from_address'] =  $from_address;
                $insert_data['to_address'] =  $to_address;
                $insert_data['amount'] =  $amount;
                $insert_data['amount_before'] =   $balance;
                $insert_data['amount_after'] =   $balance_after;
                $insert_data['fee'] =   $fee;
                $insert_data['log_time'] =  time();
                $insert_data['memo'] = $memo;
                $insert_data['blockhash'] = $blockhash;
                $insert_data['tx_id'] = $txid;
                $insert_data['transfer_status'] = 1;
                $result = Db::name('transfer_log')->insert($insert_data);
                if ($result) {
                    //增加任务
                    $p['uid'] =  $walletData['uid'];
                    $p['coin_symbol'] = $coin_symbol;
                    $p['type'] = 1;
                    $task_data['params'] = json_encode($p);
                    $task_data['task_name'] = "update_wallet_balance";
                    $task_data['uid'] = $walletData['uid'];
                    $task_data['schedule_time'] = time() + 30;
                    Db::name('cron')->insert($task_data);
                    Db::commit();
                    //邀请奖励
                    return;
                } else {
                    Db::rollback();
                    return;
                }
            }
        }
    }

    public function confirm_transfer($coin_symbol, $from_address, $to_address, $wallet_memo, $memo, $amount, $blockhash, $txid, $fee)
    {
        //写入交易日志
        $logs = Db::name('transfer_log')
            ->field('id,wallet_type,uid,coin_symbol')
            ->where('memo', $memo)
            ->where('transfer_status', 2)
            ->select()->toArray();
        //echo '|logs:'.count($logs);
        foreach ($logs as $key => $value) {
            var_dump($value);
            $log_id = $value['id'];
            $update_data['tx_id'] = $txid;
            $update_data['blockhash'] = $blockhash;
            //$update_data['fee'] = $fee;
            $update_data['transfer_status'] = 1;
            Db::name('transfer_log')->where('id', $log_id)->update($update_data);
            $p['uid'] =  $value['uid'];
            $p['coin_symbol'] = $value['coin_symbol'];
            $p['type'] = $value['wallet_type'];
            $task_data['params'] = json_encode($p);
            $task_data['task_name'] = "update_wallet_balance";
            $task_data['uid'] = $value['uid'];
            $task_data['schedule_time'] = time() + 30;
            Db::name('cron')->insert($task_data);
        }
    }

    /**
     * 除去数组中的空值和签名参数
     * @param $para 签名参数组
     * return 去掉空值与签名参数后的新签名参数组
     */
    public function paraFilter($para)
    {
        $para_filter = array();
        foreach ($para as $key => $val) {
            if ($key == "sign" || $val == "") continue;
            else    $para_filter[$key] = $para[$key];
        }
        return $para_filter;
    }
    /**
     * 对数组排序
     * @param $para 排序前的数组
     * return 排序后的数组
     */
    public function argSort($para)
    {
        ksort($para);
        reset($para);
        return $para;
    }
    /**
     * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
     * @param $para 需要拼接的数组
     * return 拼接完成以后的字符串
     */
    public function createLinkstring($para)
    {
        $arg  = "";
        foreach ($para as $key => $val) {
            $arg .= $key . "=" . $val . "&";
        }
        //去掉最后一个&字符
        $arg = substr($arg, 0, strlen($arg) - 1);
        //如果存在转义字符，那么去掉转义
        if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
            $arg = stripslashes($arg);
        }
        return $arg;
    }
    /**
     * 生成md5签名字符串
     * @param $prestr 需要签名的字符串
     * @param $key 私钥
     * return 签名结果
     */
    public function md5Sign($prestr, $key)
    {
        $prestr = $prestr . $key;
        return md5($prestr);
    }

    public function filterPara($para_temp)
    {
        $para_filter = $this->paraFilter($para_temp); //除去待签名参数数组中的空值和签名参数
        return $this->argSort($para_filter); //对待签名参数数组排序
    }
    /**
     * 生成签名结果
     * @param $para_sort 已排序要签名的数组
     * @return string 签名结果字符串
     */
    public function buildRequestMysign($para_sort)
    {
        //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
        $prestr = $this->createLinkstring($para_sort);
        $mysign = "";
        $mysign = $this->md5Sign($prestr, $this->_md5_key);

        return $mysign;
    }
    /**
     * 生成要发送的参数数组
     * @param $para_temp 请求前的参数数组
     * @return 要请求的参数数组
     */
    public function buildRequestPara($para_temp)
    {
        $para_sort = $this->filterPara($para_temp); //对待签名参数进行过滤
        $para_sort['sign'] = $this->buildRequestMysign($para_sort); //生成签名结果，并与签名方式加入请求提交参数组中
        return $para_sort;
    }
}
