<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/30
 * Time: 12:07
 */

namespace api\quant\service;

use think\Controller;
use think\Db;
use Think\Exception;

class Apibase extends Controller
{
    /**
     * 判断是否SSL协议
     * @return boolean
     */
    public static function is_ssl() {
        if(isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))){
            return true;
        }elseif(isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'] )) {
            return true;
        }
        return false;
    }
    /**
     * 返回带协议的域名
     */
    public static function sp_get_host(){
        $host=$_SERVER["HTTP_HOST"];
        $protocol=self::is_ssl()?"https://":"http://";
        return $protocol.$host;
    }
    /**
     * @param $num         科学计数法字符串  如 2.1E-5
     * @param int $double 小数点保留位数 默认18位
     * @return string
     */

    public static function sctonum($num, $double = 18){
        if(false !== stripos($num, "e")){
            $a = explode("e",strtolower($num));
            $b = bcmul($a[0], bcpow(10, $a[1], $double), $double);
            $c = rtrim($b, '0');
            return $c;
        }else{
            return $num;
        }
    }

    public static function addActionLog($uid,$object,$action){
        
        $find_log = Db::name("user_action_log")->where(['user_id'=>$uid,'object'=>$object,'action'=>$action])->find();
        if($find_log){
            Db::name("user_action_log")->where(['user_id'=>$uid,'object'=>$object,'action'=>$action])->setInc('count',1);
            $update_data['last_visit_time'] = time();
            Db::name("user_action_log")->where(['user_id'=>$uid,'object'=>$object,'action'=>$action])->update($update_data);
            return $find_log['count']+1;
        }else{
            $log = array(
                'user_id' => $uid,
                'count'=>1,
                'last_visit_time' => time(),
                'object' => $object,
                'action' => $action,
            );        
            Db::name("user_action_log")->insert($log); 
            return 1;
        }
    }

    public static function updateBalance($type,$uid,$balance_type,$change,$detial,$detial_type,$extension){
        $model = Db::name("user");
        $amount = $model->where(['id'=>$uid])->value($balance_type);
        Db::startTrans();
        try {
            $ff = ($change < 0) ? '-' : '+';
            $temp = [
                $balance_type => Db::raw("{$balance_type}{$ff}".abs($change))
            ];
            $set = $model->where(['id'=>$uid])->update($temp);
            $new_balance = $change + $amount;

            if ($set){
                $log = array(
                    'type' => $type,
                    'user_id' => $uid,
                    'balance_type' => $balance_type,
                    'change' => $change,
                    'amount' => $new_balance,
                    'detial' => $detial,
                    'detial_type' => $detial_type,
                    'ctime' => time(),
                    'extension' => $extension,
                );
                $log_id = Db::name("BalanceLog")->insert($log);
                if ($log_id){
                    // 提交事务
                    Db::commit();
                }
            }
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return false;
        }
    }

    /**
     * 获得随机字符串
     * @param $len             需要的长度
     * @param $special        是否需要特殊符号
     * @return string       返回随机字符串
     */
    public static function getRandomStr($len, $special=false){
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );

        if($special){
            $chars = array_merge($chars, array(
                "!", "@", "#", "$", "?", "|", "{", "/", ":", ";",
                "%", "^", "&", "*", "(", ")", "-", "_", "[", "]",
                "}", "<", ">", "~", "+", "=", ",", "."
            ));
        }

        $charsLen = count($chars) - 1;
        shuffle($chars);                            //打乱数组顺序
        $str = '';
        for($i=0; $i<$len; $i++){
            $str .= $chars[mt_rand(0, $charsLen)];    //随机取出一位
        }
        return $str;
    }

    //根据ID计算唯一邀请码
    public static function createCode($Id){
        static $sourceString = [
            0,1,2,3,4,5,6,7,8,9,10,
            'a','b','c','d','e','f',
            'g','h','i','j','k','l',
            'm','n','o','p','q','r',
            's','t','u','v','w','x',
            'y','z'
        ];

        $num = $Id;
        $code = '';
        while($num)
        {
            $mod = $num % 36;
            $num = (int)($num / 36);
            $code = "{$sourceString[$mod]}{$code}";
        }

        //判断code的长度
        if( empty($code[4]))
            str_pad($code,5,'0',STR_PAD_LEFT);

        return $code;
    }

    /*
     * 依据邀请奖励规则发放奖励
     * uid  自身uid
     * parent_tree 上级用户ID树
     * type 场景类型    reg：注册/deposit:存入
     * detial
     */
    public static function invite_reward($uid,$parent_tree,$type,$detial=''){
        $uids = explode('|',$parent_tree);
        if (empty($uids)){
            return false;
        }
        //查询邀请奖励规则
        $reward_rule = Db::name("UserInviteReward")->where(['status'=>1])->select()->toArray();
        if (!empty($reward_rule)){
            $reward_rule = array_column($reward_rule,NULL,'type');
        }
        foreach ($uids as $k => $x){
            $x = (int)$x;
            if ($x > 0){
                if (isset($reward_rule[$k+1]) && !empty($reward_rule[$k+1])){
                    $rule = $reward_rule[$k+1];
                    $parent_need_allocation = $rule["{$type}_parent_reward"];
                    $offspring_need_allocation = $rule["{$type}_offspring_reward"];
                    $rule['parent_tree'] = $parent_tree;
                    if ($parent_need_allocation > 0){
                        $need_detial = empty($detial) ? $uid : $detial;
                        self::updateBalance(1,$x,'score',(int)$parent_need_allocation,$need_detial,'invite_reward_'.(string)$rule['type'].'_'.$type.'_parent_score_income',json_encode($rule));
                    }
                    if ($offspring_need_allocation > 0){
                        self::updateBalance(1,$uid,'score',(int)$offspring_need_allocation,$uid,'invite_reward_'.(string)$rule['type'].'_'.$type.'_offspring_score_income',json_encode($rule));
                    }
                }
            }
        }
        return true;
    }

    /**
     * 获取随机字符串
     *
     * @param $length
     * @param bool $numeric
     * @return string
     */
    public static function random($length, $numeric = false)
    {
        $seed = base_convert(md5(microtime() . $_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
        $seed = $numeric ? (str_replace('0', '', $seed) . '012340567890') : ($seed . 'zZ' . strtoupper($seed));

        if ($numeric)
        {
            $hash = '';
        }
        else
        {
            $hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
            $length--;
        }

        $max = strlen($seed) - 1;
        for ($i = 0; $i < $length; $i++)
        {
            $hash .= $seed[mt_rand(0, $max)];
        }

        return $hash;
    }

    /**
     * 获取数字随机字符串
     *
     * @param bool $prefix 判断是否需求前缀
     * @param int $length 长度
     * @return string
     */
    public static function randomNum($prefix = false, $length = 8)
    {
        $str = $prefix ? $prefix : '';
        return $str . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, $length);
    }

    public static function timetodate($c){
        if($c < 86400){
            $time = explode(' ',gmstrftime('%H %M %S',$c));
            $duration = $time[0].'小时'.$time[1].'分'.$time[2].'秒';
        }else{
            $time = explode(' ',gmstrftime('%j %H %M %S',$c));
            $duration = ($time[0]-1).'天'.$time[1].'小时'.$time[2].'分'.$time[3].'秒';
        }
        return $duration;
    }

    /*
     * 推送方法
     */
    public static function send_push($type,$title,$content,$url='',$uids=[]){
        Db::startTrans();
        $code = 0;
        try{
            $log = [
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'url' => $url,
                'status' => 1,
                'ctime' => time(),
            ];
            $data = [];
            if (!empty($uids)){
                foreach ($uids as $x){
                    $log['user_id'] = $x;
                    array_push($data,$log);
                }
            }else{
                $log['user_id'] = 0;
                array_push($data,$log);
            }
            if (!empty($data)){
                $log_id = Db::name('PushLog')->insertAll($data);
                if ($log_id){
                    Db::commit();
                    $code = 1;
                }
            }
        }catch (\Exception $e) {

        }
        return $code;
    }

    /*
     * 具体发送推送函数
     */
    public static function jpush_send_push($title,$content,$type,$url,$registration_id = 0,$os='android'){
        $code = 0;
        $message = '';
        $app_conf = cmf_get_option("app_config");
        if (empty($app_conf['jpush_app_key']) || empty($app_conf['jpush_master_secret'])){
            $message = '请先设置推送配置';
        }else {
            $client = new \JPush\Client($app_conf['jpush_app_key'], $app_conf['jpush_master_secret']);

            $msg = array(
                'title' => $title,
                'extras' => array(
                    'type' => $type,
                    'content' => $content,
                    'url' => $url,
                ),
            );

            $pusher = $client->push()
                ->setPlatform('all')
                ->options(array(
                    // apns_production: 表示APNs是否生产环境，
                    // True 表示推送生产环境，False 表示要推送开发环境；如果不指定则默认为推送生产环境
                    'apns_production' => true,//APP_DEBUG ? false : true,
                ));
            if (!empty($registration_id)){
                if (strtolower($os) == 'android'){
                    $pusher->androidNotification($content, $msg);
                }else{
                    $msg['alert'] = $title;
                    unset($msg['title']);
                    $pusher->iosNotification(['title'=>$title,'body'=>$content], $msg);
                }
                $pusher->addRegistrationId($registration_id);
            }else{
                $pusher->addAllAudience();
                $pusher->androidNotification($content, $msg);
                $msg['alert'] = $title;
                unset($msg['title']);
                $pusher->iosNotification(['title'=>$title,'body'=>$content], $msg);
            }
            try {
                $rst = $pusher->send();
                if (!empty($rst['body']['msg_id'])){
                    $code = 1;
                    $message = '成功';
                }else{
                    $message = '推送失败';
                }
            } catch (\JPush\Exceptions\JPushException $e) {
                $message = '推送失败';
            }
        }
        return ['code'=>$code,'msg'=>$message];
    }

    /*
     * 极光推送实盘
     */
    public static function jpush_send_push_firm($title,$content,$type,$url,$uids){
        $code = 0;
        $message = '';
        $app_conf = cmf_get_option("app_config");
        if (empty($app_conf['jpush_app_key']) || empty($app_conf['jpush_master_secret'])){
            $message = '请先设置推送配置';
        }else {
            $client = new \JPush\Client($app_conf['jpush_app_key'], $app_conf['jpush_master_secret']);

            $msg = array(
                'title' => $title,
                'extras' => array(
                    'type' => $type,
                    'content' => $content,
                    'url' => $url,
                ),
            );
            $pusher = $client->push()
                ->setPlatform('all')
                ->options(array(
                    // apns_production: 表示APNs是否生产环境，
                    // True 表示推送生产环境，False 表示要推送开发环境；如果不指定则默认为推送生产环境
                    'apns_production' => true,//APP_DEBUG ? false : true,
                ));


            $pusher->androidNotification($content, $msg);

            $msg['alert'] = $title;
            unset($msg['title']);
            $pusher->iosNotification(['title'=>$title,'body'=>$content], $msg);

            //写入推送用户
            $regs = Db::name("UserDeviceRegistration")->where(['user_id'=>['in',$uids]])->field('user_id,registration_id,os,notify_type')->select();
            if ($regs->isEmpty()){
                $code = 0;
                $message = 'no user can push';                
                return ['code'=>$code,'msg'=>$message];
            }
            
            $registration_id = array();

            foreach ($regs as $key => $value) {
                if($value['notify_type'] == 1){
                    $registration_id[] = $value['registration_id'];
                }
                if($value['notify_type'] == 2){
                    if (date('H') > 8 ){//|| date('H') > 23
                        $registration_id[] = $value['registration_id'];
                    }                    
                }                
            }
            
            $pusher->addRegistrationId($registration_id);

            try {
                $rst = $pusher->send();
                if (!empty($rst['body']['msg_id'])){
                    $code = 1;
                    $message = '成功';
                }else{
                    $message = '推送失败';
                }
            } catch (\JPush\Exceptions\JPushException $e) {
                $message = '推送失败';
            }
        }
        return ['code'=>$code,'msg'=>$message];
    }

    /*
     * 获取extension配置项
     */
    public static function get_extension($type){
        $rst = Db::name("Extension")
            ->where(['type'=>$type])
            ->value('detial');
        return empty($rst) ? null : $rst;
    }

    public static function eth_rpc($NODE_HOST,$method,$params=[]){
        try{
            $data = array(
                'jsonrpc' => "2.0",
                'method' => $method,
                'params' => $params,
                'id' => 1
            );
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $NODE_HOST);
            curl_setopt($ch, CURLOPT_POST, 1);
            $httpHeader[] = 'Content-Type:Application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data) );

            curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false); //处理http证书问题
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $ret = curl_exec($ch);

            if (false === $ret) {
                $code = 0;
                $rst = curl_errno($ch);
            }else{
                $ret = json_decode($ret);
                if(!empty($ret->error->code)){
                    $code = 0;
                    $rst = $ret->error->message;
                }else{
                    $code = 1;
                    $rst = $ret->result;
                }
            }
            curl_close($ch);
        }catch(Exception $e){
            $code = 0;
            $rst = $e->getMessage();
        }
        return array(
            'code' => $code,
            'data' => $rst
        );
    }

    /*
     *
     */
    public static function chain_rpc($coin,$userId,$wallet_type,$method,$params=[]){
        $coin = strtolower($coin);
//        $vendor_name = "Chain.".$coin."rpc";
//        Vendor($vendor_name);
//        $class_name = "\\".$coin."rpc";
//        if(!class_exists($class_name)){
//            return array('code'=>0,'msg'=>"$class_name not exists");
//        }
        //检测钱包是否存在
        $fieldStr = 'rpc_ip,rpc_port,rpc_user,rpc_pass,b.id,b.address,IFNULL(b.status,-1) as status';
        $coin_data = Db::name('coin')
            ->alias('a')
            ->join(config('database.prefix').'wallet b',"a.coin_symbol = b.coin_symbol and uid = $userId and type = $wallet_type","LEFT")
            ->field($fieldStr)
            ->where('a.coin_symbol', strtoupper($coin))
            ->find();
        if(empty($coin_data)){ //没有该币种
            return array('code'=>0,'msg'=>"coin symbol not exists");
        }
        if(empty($coin_data['address'])){
            return array('code'=>0,'msg'=>"user wallet not exists");
        }
        //币种rpc配置
        $rpc_ip = $coin_data['rpc_ip'];
        $rpc_port = $coin_data['rpc_port'];
        $rpc_user = $coin_data['rpc_user'];
        $rpc_pass = $coin_data['rpc_pass'];
        if(empty($rpc_ip)||empty($rpc_port)){
            return array('code'=>0,'msg'=>"rpc ip or port not set");
        }
        //RPC连接
        $rpc = self::eth_rpc("http://{$rpc_ip}:{$rpc_port}",$method,$params);
        return $rpc;
        var_dump($rpc);exit();
        $rpc = new $class_name($rpc_ip,$rpc_port,$rpc_user,$rpc_pass);
        if (!method_exists($rpc,$method)) {
            return array('code'=>0,'msg'=>"$class_name method $method not exists");
        }
        $ret = $rpc->$method($params);
        $code = 0;
        $msg = '';
        $data = $ret;
        try {
            $ret = json_decode($ret);
            $code = 1;
            $data = $ret->result;
        }catch (Exception $e){

        }
        return ['code'=>$code,'msg'=>$msg,'data'=>$data];
    }

    /*
     * BTC查unspent
     */
    public static function btc_unspent($addr){
        try{
            $NODE_HOST = "https://blockchain.info/unspent";
            $url = $NODE_HOST . '?active=' . $addr;
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Blockchain-PHP/1.0');
            //curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false); //处理http证书问题
            curl_setopt($ch, CURLOPT_CAINFO, VENDOR_PATH.'/Chain/blockchain-ca-bundle.crt');
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $ret = curl_exec($ch);
            curl_close($ch);

            if (false === $ret) {
                $code = 0;
                $rst = curl_errno($ch);
            }else{
                if(strpos($ret, 'unspent_outputs') !== false){
                    $ret = json_decode($ret, true);
                    if(!empty($ret['unspent_outputs'])){
                        $code = 1;
                        $rst = $ret['unspent_outputs'];
                        foreach ($rst as $k => &$v){
                            if($v['confirmations'] < 6){
                                unset($rst[$k]);
                            }
                        }
                    }else{
                        $code = 0;
                        $rst = 'unspent error';
                    }
                }else{
                    if($ret == "No free outputs to spend"){
                        $code = 1;
                        $rst = 0;
                    }else{
                        $code = 0;
                        $rst = $ret;
                    }
                }
            }
        }catch(Exception $e){
            $code = 0;
            $rst = $e->getMessage();
        }
        return array(
            'code' => $code,
            'data' => $rst
        );
    }

    public static function btc_unspent_v2($addr){
        try{
            $NODE_HOST = "https://chain.api.btc.com/v3/address/";
            $url = $NODE_HOST . $addr . '/unspent';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Blockchain-PHP/1.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false); //处理http证书问题
            //curl_setopt($ch, CURLOPT_CAINFO, VENDOR_PATH.'/Chain/blockchain-ca-bundle.crt');
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $ret = curl_exec($ch);
            curl_close($ch);

            if (false === $ret) {
                $code = 0;
                $rst = curl_errno($ch);
            }else{
                $ret = json_decode($ret,true);
                if (empty($ret['data']['list'])){
                    $code = 1;
                    $rst = 0;
                }else{
                    $code = 1;
                    $rst = $ret['data']['list'];
                    foreach ($rst as $k => &$v){
                        if($v['confirmations'] < 6){
                            unset($rst[$k]);
                        }
                    }
                }
            }
        }catch(Exception $e){
            $code = 0;
            $rst = $e->getMessage();
        }
        return array(
            'code' => $code,
            'data' => $rst
        );
    }

    public static function calcbtcbalance($unspent){
        $values = array_column($unspent,'value');

        $total = array_sum($values);
        return $total / 100000000;
    }

   public static function checkSensitiveword($word)
    {
        $cmf_settings = cmf_get_option('sensitiveword');
        $ret = true;
        if (!empty($cmf_settings) && isset($cmf_settings['sensitiveword'])) {
            if (!empty($cmf_settings['sensitiveword'])) {
                $sensitiveword = explode(",", $cmf_settings['sensitiveword']);
                if (in_array($word, $sensitiveword)) {
                    $ret = false;
                }
                foreach ($sensitiveword as $x) {
                    if (strpos($word, $x) !== false) {
                        $ret = false;
                    }
                }
            }
        }
        return $ret;
    }

    /**
     * 根据最近在线时间，计算直观描述，如：10分钟前，1分钟前，1天前
     */
   public static  function calcViewTime($time = 0)
    {
        if ($time == 0) {
            return '1000' . lang("天前");
        }
        $c = time() - $time;
        if ($c < 86400) {
            if ($c < 3600) {
                $t = sprintf("%.d", $c / 60);
                if($t==0){
                    $duration = "刚刚";
                }else{
                    $duration = $t . lang("分钟前");
                } 
            } else {
                $t = sprintf("%.d", $c / 3600);
                $duration = $t . lang("小时前");
            }
        } else {
            $t = sprintf("%.d", $c / 86400);
            $duration = $t . lang("天前");
        }
        return $duration;
    } 

    /**
     * 根据最近在线时间，计算直观描述，如：10分钟，1分钟，1天
     */
   public static  function calcViewTime2($time = 0)
    {
        if ($time == 0) {
            return '1000' . lang("天");
        }
        $c = time() - $time;
        if ($c < 86400) {
            if ($c < 3600) {
                $t = sprintf("%.d", $c / 60);
                if($t==0){
                    $duration = "刚刚";
                }else{
                    $duration = $t . lang("分钟");
                }                
            } else {
                $t = sprintf("%.d", $c / 3600);
                $duration = $t . lang("小时");
            }
        } else {
            $t = sprintf("%.d", $c / 86400);
            $duration = $t . lang("天");
        }
        return $duration;
    }
    /**
     * 给用户发送消息提醒  type 1 评论 2点赞
     */    
   public static  function add_msg_alert($from_uid,$uid,$moment_id,$type,$msg,$object_id=0)
    {
        $insert_data = array();
        $insert_data['from_uid'] = $from_uid;
        $insert_data['uid'] = $uid;
        $insert_data['moment_id'] = $moment_id;
        $insert_data['object_id'] = $object_id;
        $insert_data['type'] = $type;
        $insert_data['msg'] = $msg;
        $insert_data['read_status'] = 0;
        $insert_data['status'] = 1;
        $insert_data['ctime'] = time();
        Db::name('user_moment_alert')->insert($insert_data);
    } 

    /**
     * 支付密码检测 
     */    
   public static  function check_pay_password($password,$uid)
    {
        $user_info = Db::name("user")
                ->where("id = $uid and user_status = 1")
                ->field('paypwd,paypwd_count,paypwd_last_time')
                ->find();
        if(empty($user_info)){
            $ret = [0,'用户不存在'];
            return $ret ;
        }
        $paypwd_count = $user_info['paypwd_count'];
        if( $paypwd_count >=5){
            if(time()-$user_info['paypwd_last_time'] < 3600*2 ){
                $ret = [0,'支付密码因多次输入错误已被冻结两个小时,请稍后重试'];
                return $ret ;                
            }else{
                $update_data = array('paypwd_count'=>0);
                $user_info = Db::name("user")->where("id = $uid")->update($update_data); 
                $paypwd_count = 0;               
            }
        }

        if (!cmf_compare_password('pay'.$password, $user_info['paypwd'])) {
            $update_data = array('paypwd_count'=>$user_info['paypwd_count']+1,'paypwd_last_time'=>time());
            $user_info = Db::name("user")->where("id = $uid")->update($update_data);               
            $ret = [0,'支付密码输入不正确'];
            return $ret ;              
        }else{
            $update_data = array('paypwd_count'=>0,'paypwd_last_time'=>time());
            $user_info = Db::name("user")->where("id = $uid")->update($update_data); 
            $ret = [1,''];
            return $ret ;                           
        }
    } 

    /**
     * 实盘增加新的instrument类型
     */    
   public static  function firm_add_new_instrument($platform,$instrument_id,$type,$coin="")
    {

        //查询合约是否存在
        $count = Db::name('firm_instrument')->where("platform = '$platform' and instrument_id = '$instrument_id'")->count(); 
        if($count == 0){
            if($platform=='huobi'){
                $type_name = array("this_week"=>"当周","next_week"=>"下周","quarter"=>"季度");
                if(isset($type_name[$type])){
                    $instrument_name= $coin.$type_name[$type];
                }else{
                    $instrument_name= $coin."合约";
                }
                $stock = $coin;
                $money = "USDT";                
            }
            $insert_data = array();
            $insert_data['platform'] = $platform;
            $insert_data['instrument_id'] = $instrument_id;
            $insert_data['instrument_type'] = $type;
            $insert_data['instrument_name'] = $instrument_name;
            $insert_data['stock'] = $stock;
            $insert_data['money'] = $money;
            $insert_data['price'] = 0;
            $insert_data['update_time'] = 0;
            $insert_data['status'] = 1;
            //var_dump($insert_data);
            Db::name('firm_instrument')->insert($insert_data);            
        }
    } 
    /**
     * 实盘插入交易记录
     */   
   public static  function firm_add_new_transaction($platform,$api_id,$uid,$total_assets_usd,$old_positions,$positions,$transactions,$mark)
    {


        //判断是否今日存储过仓位
        $date_today = date("Y-m-d",time()+3600*8);
        $count = Db::name("firm_position_log")->where("remark_date ='$date_today' and uid = $uid and platform = '$platform'")->count();
        if($count ==0){
            $insert_data =array('api_id'=>$api_id,'platform'=>$platform,'uid'=>$uid,'total_assets_usd'=>$total_assets_usd,'positions'=> json_encode($positions,JSON_UNESCAPED_UNICODE),'remark_date'=>$date_today,'remark_time'=>time());
            Db::name("firm_position_log")->insert($insert_data);
        } 

        $insert_data_array = array();
        $push_uids = array();

        $uids =  Db::name("follower")->where(['uid'=>$uid,"status"=>1])->select();

        foreach ($uids as $key => $value) {
            $push_uids[] = $value['follower_uid'];
        }


        $instruments_data = Db::name("firm_instrument")->field('*')->select()->toArray();
        $instruments_info = array_column($instruments_data,NULL,'instrument_id');

        $coin_symbol_price = Db::name("coin")->field('coin_symbol,usd_price')->select()->toArray();
        $coin_prices = array_column($coin_symbol_price,'usd_price','coin_symbol');

        $ls_total = 0;
        $ls_total_week = 0;
        $ls_change = 0;
        $ls_change_week = 0;
        //当前仓位收益
        $total_value = 0;
        foreach ($positions as $key => $value) {
            $price = $instruments_info[$value['instrument_id']]['price'];
            $money = $instruments_info[$value['instrument_id']]['money'];
            $contract_val =  $instruments_info[$value['instrument_id']]['value'];
            if($money=='XBT'){
                $money = "BTC";
            }
            if($money=='USD'){
                $money = "USDT";
            }              
            if($value['side'] =="long"){
                $ls_change = $ls_change + ($price - $value['avg_cost']) * $value['num']  *($contract_val/$value['avg_cost'] )  * $coin_prices[$money] ; 

            }else{
                $ls_change = $ls_change + ($value['avg_cost'] - $price  ) * $value['num']  * ($contract_val/$value['avg_cost'] ) * $coin_prices[$money];       
            } 
            $total_value =  $total_value + $value['num']  * $contract_val/ $value['leverage'] * $coin_prices[$money];
        }        

        $ls_total = $total_value;
        $ls_total_week = $total_value;

        $ls_total_paiwei = $total_value;

        $ls_change_week = $ls_change;
        $ls_change_paiwei = $ls_change;

        //计算历史总收益
        $history_position = Db::name("firm_log")
            ->where("uid = $uid and platform = '$platform' and op_type > 2")
            ->field('ctime,positions_old,positions,op_type,instrument_id,price_avg,value,num,num2,total_assets_usd')
            ->order('ctime desc')
            ->select();

        $history_position_array = array();
        if(count($history_position)>0){
            $history_position = $history_position->toArray();
            foreach ($history_position as $key => &$value) {
                $old_positions_array = json_decode($value['positions_old'],true);
                foreach ($old_positions_array as $k => &$v) {
                    $v['instrument_mark'] = $v['instrument_id'].$v['side'];
                }                
                $positions_array = json_decode($value['positions'],true);
                foreach ($positions_array as $kk => &$vv) {
                    $vv['instrument_mark'] = $vv['instrument_id'].$vv['side'];
                }                
                $instrument_id = $value['instrument_id'];
                if($value['op_type']==3){
                    $side = "long";
                }
                 if($value['op_type']==4){
                    $side = "short";
                }               
                if($old_positions_array){

                    $old_positions_array = array_column($old_positions_array, NULL,'instrument_mark');
                    $positions_array = array_column($positions_array, NULL,'instrument_mark');
                    if(isset($old_positions_array[$instrument_id.$side])){//&&!isset($positions_array[$instrument_id])
                        //var_dump($value);
                        $old_positions_array[$instrument_id.$side]['now_price'] = round($value['price_avg'],6);
                        $old_positions_array[$instrument_id.$side]['num'] = $value['num2'];                       
                        $old_positions_array[$instrument_id.$side]['ctime'] = $value['ctime'];
                        $old_positions_array[$instrument_id.$side]['total_assets_usd'] = $value['total_assets_usd'];
                        $old_positions_array[$instrument_id.$side]['op_type'] = $value['op_type'];
                        $history_position_array[] = $old_positions_array[$instrument_id.$side];

                    }
                }
            }
        }

        foreach ($history_position_array as $key => &$value) {
            $value['value'] = round($value['value'],6);
            $value['avg_cost'] = round($value['avg_cost'],6);

            $value['revenue']= round($value['num']*10 * $value['change']/100/ $value['leverage'],6) ;/// 
            $price = $value['now_price'] ;
            $money = $instruments_info[$value['instrument_id']]['money'];                        
            $contract_val =  $instruments_info[$value['instrument_id']]['value'];

            if($money=='XBT'){
                $money = "BTC";
            }
            if($money=='USD'){
                $money = "USDT";
            }  
            
            if($value['side']=='long'){
                //$value['change']= intval(10000*$value['leverage']*($price-$value['avg_cost'])/$value['avg_cost'])/100;
                $value['revenue']= round($coin_prices[$money]*$value['num']*$contract_val/$value['avg_cost']*($price-$value['avg_cost']),6) ;/// =
            }else{
                //$value['change']= intval(10000*$value['leverage']*($value['avg_cost']-$price)/$value['avg_cost'])/100;
                $value['revenue']= round($coin_prices[$money]*$value['num']*$contract_val/$value['avg_cost']*($value['avg_cost']-$price),6) ;/// =
            }
            $position_value = $value['num']  * $contract_val/ $value['leverage'] * $coin_prices[$money];
            if($value['ctime']>(time()-7*24*3600)){
                $ls_total_week = $ls_total_week + $position_value;
                $ls_change_week = $ls_change_week + $value['revenue'];
            }   

            if($value['ctime']>1569340800){
                $ls_total_paiwei = $ls_total_paiwei + $position_value;
                $ls_change_paiwei = $ls_change_paiwei + $value['revenue'];
            } 

            $ls_total = $ls_total + $position_value;
            $ls_change = $ls_change + $value['revenue'];
            
        }       
        //计算收益

        if($ls_total > 0){
            $profit_usd = $ls_change;
            $profit_rate = intval($ls_change*10000/$ls_total)/100;            
        }else{
            $profit_usd = 0;
            $profit_rate = 0;             
        }
        //--paiwei
        $positions_paiwei = array();
        $date_paiwei = date("Y-m-d",1569340800+8*3600);
        $paiwei_info = Db::name("firm_position_log")->where("remark_date ='$date_paiwei' and uid = $uid and platform = '$platform'")->find();
        //var_dump($date_paiwei);
        $instruments_price_log = Db::name("firm_instrumen_price_log")->where("remark_date ='$date_paiwei'")->select()->toArray();
        $instruments_price_log = array_column($instruments_price_log,NULL,"ins_id");

        if($paiwei_info){
            $positions_paiwei = json_decode($paiwei_info['positions'],true);
        } 

        $ls_change_init = 0;
        foreach ($positions_paiwei as $key => $value) {
            //$price = $instruments_info[$value['instrument_id']]['price'];
            $price = $instruments_price_log[$instruments_info[$value['instrument_id']]['id']]['price'];

            $money = $instruments_info[$value['instrument_id']]['money'];
            $contract_val =  $instruments_info[$value['instrument_id']]['value'];
            if($money=='XBT'){
                $money = "BTC";
            }
            if($money=='USD'){
                $money = "USDT";
            }              
            if($value['side'] =="long"){
                $ls_change_init = $ls_change_init + ($price - $value['avg_cost']) * $value['num']  *($contract_val/$value['avg_cost'] )  * $coin_prices[$money] ; 

            }else{
                $ls_change_init = $ls_change_init + ($value['avg_cost'] - $price  ) * $value['num']  * ($contract_val/$value['avg_cost'] ) * $coin_prices[$money];       
            }
            
            $ls_total_paiwei =  $ls_total_paiwei + $value['num']  * $contract_val/ $value['leverage'] * $coin_prices[$money];
 
        }  

        if($ls_total_paiwei > 0){
            $profit_usd2 = $ls_change_paiwei - $ls_change_init;
            $profit_rate2 = intval($profit_usd2*10000/$ls_total_paiwei)/100;            
        }else{
            $profit_usd2 = 0;
            $profit_rate2 = 0;             
        }

        //排位初始本金+充值
        $paiwei_info = Db::name("firm_position_log")->where("remark_date ='$date_paiwei' and uid = $uid and platform = '$platform'")->find();

        $paiwei_first_momey = 0;
        if($paiwei_info){
            $paiwei_first_momey = $paiwei_info['total_assets_usd'];
        }else{
            $paiwei_first_momey = 0;
        }

        if($paiwei_first_momey > 0){
            $profit_rate2_new = intval($profit_usd2*10000/$paiwei_first_momey)/100;            
        }else{
            $profit_rate2_new = 0;             
        }
        //---------------------------------------

        if($ls_total_week > 0){
            $week_profit_usd = $ls_change_week;
            $week_profit_rate = intval($ls_change_week*10000/$ls_total_week)/100;            
        }else{
            $week_profit_usd = 0;
            $week_profit_rate = 0;             
        }
        //初始本金+充值
        $first_info = Db::name("firm_log")->where("uid = $uid and platform = '$platform'")->order('id asc')->limit(0,1)->find();
        $total_recharge = 0;
        if($first_info){
            $total_recharge = $first_info['total_assets_usd'];
        }else{
            $total_recharge = 0;
        }
        $profit_usd_new = $profit_usd ;
        if($total_recharge>0){
             $profit_rate_new = intval($profit_usd_new*10000/$total_recharge)/100;
        }else{
            $profit_rate_new = 0;
        }

        $transaction_value_usd = 0;
        foreach ($transactions as $key => $value) {
            $insert_data = array();
            $insert_data['platform'] = $platform;
            $insert_data['api_id'] = $api_id;
            $insert_data['uid'] = $uid;
            $insert_data['push_uids'] = json_encode($push_uids);
            $insert_data['total_assets_usd'] = $total_assets_usd;
            $insert_data['positions_old'] =  json_encode($old_positions,JSON_UNESCAPED_UNICODE);
            $insert_data['positions'] =  json_encode($positions,JSON_UNESCAPED_UNICODE);
            $insert_data['positions_count'] =  count($positions);
            $num = 0;
            foreach ($positions as $k => $v) {
                $num = $num + $v['num'];
            }
            $insert_data['positions_num'] = $num;

            $insert_data['op_type'] =$value['op_type'];
            $insert_data['op_type_name'] =$value['op_type_name'];
            $insert_data['instrument_id'] =$value['instrument_id'];
            //$insert_data['type'] =$value['type'];
            $insert_data['name'] =$value['name'];
            $insert_data['coin_symbol'] =$value['coin_symbol'];
            $insert_data['num'] =$value['num'];
            $insert_data['num2'] =$value['num2'];
            $insert_data['price'] =$value['price'];
            $insert_data['price_avg'] =$value['price_avg'];
            $insert_data['fee'] =$value['fee'];
            $insert_data['value'] =$value['value'];
            $insert_data['order_id'] =$value['order_id'];
            $insert_data['extra'] =json_encode($value['extra'],JSON_UNESCAPED_UNICODE);
            $insert_data['ctime'] = time();
            $insert_data['push_status'] = 0;
            if(isset($value['op_type'])){
                $insert_data['type'] = $value['op_type'];
                if($value['op_type']==1 || $value['op_type']==4 ){
                    $insert_data['transaction_value_usd'] = -$value['num2']*$value['price_avg'];
                }else{
                    $insert_data['transaction_value_usd'] = $value['num2']*$value['price_avg'];
                }
                if($value['op_type'] <3 ){
                     $insert_data['profit_usd'] = $profit_usd;
                     $insert_data['profit_rate'] = $profit_rate;                                        
                }else{
                     $insert_data['profit_usd'] = $profit_usd;
                     $insert_data['profit_rate'] = $profit_rate;                                                     
                }
                $insert_data_array[] = $insert_data;
            }            
        }
        //var_dump($insert_data_array);
        //插入交易
        if(count($insert_data_array)>0){
            Db::name("firm_log")->insertAll($insert_data_array);
        }
        //更新实盘信息
        //$week_profit_usd = $profit_usd;
        //$week_profit_rate = $profit_rate;        
        $update_info = [
            'positions' => json_encode($positions,JSON_UNESCAPED_UNICODE),
            'mark' => json_encode($mark),
            'total_assets_usd' => $total_assets_usd,
            'profit_usd' => $profit_usd_new,//$profit_usd,
            'profit_rate' => $profit_rate_new,//$profit_rate, 
            'week_profit_usd'=> $week_profit_usd,
            'week_profit_rate'=> $week_profit_rate,                       
        ];
        Db::name("firm_".$platform)->where(['id'=>$api_id])->update($update_info); 
        //更新实盘用户信息
        $firm_user = Db::name("firm_user")->where(['uid'=>$uid])->find();

        if($firm_user){
            if($firm_user['platform'] == $platform){ //检测默认平台
                $update_info = [
                    'total_assets_usd' => $total_assets_usd,
                    'profit_usd' =>$profit_usd_new,// $profit_usd,
                    'profit_rate' => $profit_rate_new,// $profit_rate,       
                    'profit_usd2' => $profit_usd2,
                    'profit_rate2' => $profit_rate2_new,//$profit_rate2,    
                    'profit_usd_new' => $profit_usd_new,
                    'profit_rate_new' => $profit_rate_new,                                          
                ];
                Db::name("firm_user")->where(['uid'=>$uid])->update($update_info);                     
            }else{
                if($total_assets_usd >$firm_user['total_assets_usd'] ){ //如果金额大于默认平台的金额，就更新当前平台为默认平台
                     $update_info = [
                        'platform' => $platform,
                        'total_assets_usd' => $total_assets_usd,
                        'profit_usd' =>$profit_usd_new,// $profit_usd,
                        'profit_rate' =>$profit_rate_new, // $profit_rate, 
                        'profit_usd2' => $profit_usd2,
                        'profit_rate2' => $profit_rate2_new,//$profit_rate2,  
                        'profit_usd_new' => $profit_usd_new,
                        'profit_rate_new' => $profit_rate_new,                                                          
                    ];
                    Db::name("firm_user")->where(['uid'=>$uid])->update($update_info);                         
                }
            
            }
        } 

    }  



}
