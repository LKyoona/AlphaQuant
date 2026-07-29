<?php


namespace api\common\service;

use think\Controller;
use think\Db;
use Think\Exception;

class Pool extends Controller
{
    //$pool_type 3 月度
    //$pool_type 4 年度
    public static function change($uid,$related_id,$amount,$flag,$memo,$pool_type){
        
        $find_last_log = Db::name("pool_log")->where(['pool_type'=>$pool_type])->order('id desc')->limit(0,1)->select()->toArray();
        if(!empty($find_last_log)){
            $amount_before = $find_last_log[0]['amount_after'];
        }else{
            $amount_before = 0;
        }
        $log = array(
            'uid' => $uid,
            'related_id'=>$related_id,
            'amount_before' => $amount_before,
            'amount' => $amount,
            'amount_after' => $amount_before+$amount,
            'flag' => $flag,
            'memo' => $memo,
            'pool_type' => $pool_type,
            'ctime'=>time()
        );        
        Db::name("pool_log")->insert($log);         
    }

    public static function getBalance($pool_type){
        $find_last_log = Db::name("pool_log")->where(['pool_type'=>$pool_type])->order('id desc')->limit(0,1)->select()->toArray();
        if($find_last_log){
            return $find_last_log[0]['amount_after'];
        }else{
            return 0;
        }
    }

}