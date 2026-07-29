<?php

namespace api\wallet\controller;

use api\common\service\Apibase;
use api\common\service\ColumnName;
use cmf\controller\RestUserBaseController;
use think\Db;
use think\Validate;

class AccountController extends RestUserBaseController
{

    public function info()
    {

        $validate = new Validate([
            'currency'     => 'require',                        
            'coin_symbol'     => 'require',
        ]);

        $validate->message([
            'currency.require'  => '货币类型不能为空!',            
            'coin_symbol.require'  => '币种类型不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        //汇率查询
        $rateData = Db::name("rate")->select();
        $rate =array();
        $currency_symbol =array();
        foreach ($rateData as $key => $value) {
              $rate[$value['currency_symbol']] =  $value['usd_rate'] ;
              $currency_symbol[$value['currency_symbol']] =  $value['currency_symbol_char'] ;
        }
      
        if(!isset($rate[$data['currency']])){
            $this->error("请求错误,未知货币参数!");
        }
    
        $userId  = $this->getUserId();
        $this->createCloudWalletIfMissing($userId, $data['coin_symbol']);

        $fieldStr = 'a.id,b.coin_name,a.coin_symbol,a.address,b.coin_type,b.parent_coin,b.min_fee,b.max_fee,b.img_url,cloud_balance,b.usd_price,b.url_baike,b.url_hangqing,b.url_address,b.url_address,b.url_explorer';

        $walletData = Db::name('wallet')
        ->alias('a')
        ->join(config('database.prefix').'coin b',"a.coin_symbol = b.coin_symbol")
        ->field($fieldStr)
        ->where('uid', $userId)
        ->where('type', 1)
        //->where('status', 1)  
        ->where('a.coin_symbol', $data['coin_symbol'])
        ->find();

        if(empty($walletData)){
            $this->error('获取失败');
        }
        //检测exchange开启状态
        // $check = Db::name('exchange')
        // ->where('status',1)
        // ->where('from_coin', $data['coin_symbol'])
        // ->count();

        // if($check>0){
        //     $walletData['exchange_state'] = 1; 

        // }else{
        //     $walletData['exchange_state'] = 0; 
        // }

        $walletData['currency'] = $data['currency'];

        $walletData['currency_symbol'] = $currency_symbol[$data['currency']];

        $walletData['currency_rate'] = $rate[$data['currency']];

        $walletData['price'] =  floatval($rate[$data['currency']] * $walletData['usd_price'] * $walletData['cloud_balance']);
  
        $walletData['cloud_balance'] = sprintf("%01.6f",$walletData['cloud_balance']);
  
        $walletData['price'] = sprintf("%01.2f",$walletData['price']);

        $walletData['img_url']  = cmf_get_image_preview_url($walletData['img_url']) ;  

        // $walletData['url_baike'] =  cmf_get_domain().cmf_get_root()."/portal/index/baike?coin_symbol=". $data['coin_symbol'];      
        
        // $walletData['url_hangqing'] = cmf_get_domain().cmf_get_root()."/portal/index/hangqing?coin_symbol=". $data['coin_symbol'];      


        //如果钱包地址没有生成  生成钱包地址
        if(empty($walletData['address'])){
                //增加生成任务
                $p['uid'] = $userId;
                $p['coin_symbol'] = $data['coin_symbol'];
                $task_data['params'] = json_encode($p);
                $task_data['task_name'] = "generate_user_wallet_address";
                $task_data['uid'] = $userId;
                $task_data['schedule_time'] =0;
                Db::name('cron')->insert($task_data);  
        }

        // TRC20查询ERC20的余额
        $coin_symbol = $data['coin_symbol'];
        if ($coin_symbol === 'USDT-TRC') {
            $walletData['cloud_balance'] = 0;
            $walletData2 = Db::name('wallet')->field('cloud_balance')
                ->where('uid', $userId)
                ->where('type', 1)
                ->where('coin_symbol', 'USDT')
                ->find();
            if(!empty($walletData2)){
                $walletData['cloud_balance'] = sprintf("%01.6f",$walletData2['cloud_balance']);
            }
        }

        $this->success('获取成功！', $walletData);

    }

    private function createCloudWalletIfMissing($userId, $coinSymbol)
    {
        $walletCount = Db::name('wallet')
            ->where('uid', $userId)
            ->where('type', 1)
            ->where('coin_symbol', $coinSymbol)
            ->count();

        if ($walletCount > 0) {
            return;
        }

        $coin = Db::name('coin')->where('coin_symbol', $coinSymbol)->find();
        if (empty($coin)) {
            return;
        }

        Db::name('wallet')->insert([
            'uid' => $userId,
            'coin_symbol' => $coinSymbol,
            'address' => '',
            'type' => 1,
            'add_time' => time(),
        ]);
    }

    //收款二维码
   public function address()
    {

        $validate = new Validate([
            // 'currency'     => 'require',                        
            'coin_symbol'     => 'require',
            // 'amount'    => 'number|between:0,1000000',
        ]);

        $validate->message([
            // 'currency.require'  => '货币类型不能为空!',            
            'coin_symbol.require'  => '币种类型不能为空!',
            // 'amount.number'  => '金额必须为数值!',
            // 'amount.between'  => '金额必须大于0小于1000000!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        // //汇率查询
        // $rateData = Db::name("rate")->select();
        // $rate =array();

        // foreach ($rateData as $key => $value) {
        //       $rate[$value['currency_symbol']] =  $value['usd_rate'] ;
        // }
      
        // if(!isset($rate[$data['currency']])){
        //     $this->error("请求错误,未知货币参数!");
        // }
    
        $userId  = $this->getUserId();

        $fieldStr = 'a.coin_symbol,a.address,b.memo_name,a.memo,b.tips,b.usd_price';

        $walletData = Db::name('wallet')
        ->alias('a')
        ->join(config('database.prefix').'coin b',"a.coin_symbol = b.coin_symbol")
        ->field($fieldStr)
        ->where('uid', $userId)
        ->where('type', 1)
        //->where('status', 1)  
        ->where('a.coin_symbol', $data['coin_symbol'])
        ->find();

        if(empty($walletData)){
            $this->error('获取失败');
        }
        
        //如果钱包地址没有生成  生成钱包地址
        if(empty($walletData['address'])){
            //增加生成任务
            $p['uid'] = $userId;
            $p['coin_symbol'] = $data['coin_symbol'];
            $task_data['params'] = json_encode($p);
            $task_data['task_name'] = "generate_user_wallet_address";
            $task_data['uid'] = $userId;
            $task_data['schedule_time'] =0;
            Db::name('cron')->insert($task_data);
    }
            
        $amount_str = "";

        if(!empty($data['amount'])){
           $data['amount'] = floatval($data['amount']);
           $data['amount'] = sprintf("%01.5f",$data['amount']);
           $amount_str = "?amount=".$data['amount'];
        }
        //$walletData['qr_code_url'] = cmf_get_domain().cmf_get_root() .'/api/wallet/qrcode/make?url='.urlencode($data['coin_symbol'].":". $walletData['address'].$amount_str );     
        $walletData['qr_code_url'] = cmf_get_domain().cmf_get_root() .'/api/wallet/qrcode/make?url='.$walletData['address'];     

        if(!empty($walletData['memo'])){
            $walletData['address'] = $walletData['address']." ".$walletData['memo_name'].":".$walletData['memo'];
        }
        // $walletData['currency'] = $data['currency'];

        // $walletData['amount'] = $data['amount'];

        // $walletData['price'] = floatval($rate[$data['currency']] * $walletData['usd_price'] * $walletData['amount']);
  
        // $walletData['price'] = sprintf("%01.2f",$walletData['price']);

        unset($walletData['usd_price']);    

        $this->success('获取成功！', $walletData);

    }
  
  //转账
   public function transfer()
    {

        $validate = new Validate([
            'coin_symbol'     => 'require',
            'amount'    => 'require|number|between:0.00000001,1000000',
            'to_address'     => 'require',
            'fee'    => 'require|number|between:0.00000001,1000000',
            'passWord_pay' =>  'require',            
        ]);

        $validate->message([
            'coin_symbol.require'  => '币种类型不能为空!',
            'amount.require'  => '转账数量不能为空!',    
            'amount.number'  => '数量必须为数值!',
            'amount.between'  => '数量必须大于0.00000001小于1000000!',
            'fee.require'  => '手续费不能为空!',    
            'fee.number'  => '手续费必须为数值!',
            'fee.between'  => '手续费数值不合理(0.00000001~1000000)',            
            'to_address.require'  => '转账地址不能为空!',
            'passWord_pay.require'  => '支付密码不能为空!',            
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $userId = $this->getUserId();
        $uinfo = $this->user;

        $check_pay = Apibase::check_pay_password($data['passWord_pay'],$uinfo['id']); 
        if($check_pay[0]==0){
            $this->error($check_pay[1]);
        }

        // TRC20
        $coin_symbol2 = $data['coin_symbol'];
        if ($coin_symbol2 === 'USDT-TRC') {
            $data['coin_symbol'] = 'USDT';
        }

        //获取用户钱包余额
        $walletData = Db::name('wallet')
        ->field("id,cloud_balance,address")
        ->where('uid', $userId)
        ->where('type', 1)
        ->where('coin_symbol', $data['coin_symbol'])
        ->find();

        if(empty($walletData)){
            $this->error('钱包不存在');
        }



        // 读取TRC20钱包地址
        $pay_wallet_id = $walletData['id'];
        $from_address = $walletData['address'];

        $fee = Db::name('coin')
                ->where('coin_symbol', $data['coin_symbol'])
                ->value('min_fee');

        if ($coin_symbol2 === 'USDT-TRC') {
            $walletData2 = Db::name('wallet')
                ->field("id,cloud_balance,address")
                ->where('uid', $userId)
                ->where('type', 1)
                ->where('coin_symbol', $coin_symbol2)
                ->find();
            if(empty($walletData2)){
                $this->error('TRC20钱包不存在');
            }
            $pay_wallet_id = $walletData2['id'];
            $from_address = $walletData2['address'];
            $fee = Db::name('coin')
            ->where('coin_symbol', $coin_symbol2)
            ->value('min_fee');            
        }

        $balance = floatval($walletData['cloud_balance']);
        $amount = floatval($data['amount']);
        $fee = floatval($data['fee']);
        // if($amount < 20){
        //     $this->error("最小提币金额为20USDT");
        // }        
        // if($amount <= 100){
        //     $fee = 1;
        // }
        // if($amount > 100){
        //     $fee = $amount*0.07;
        // }
        // //写入转账记录
        // if($balance < $amount + $fee){      
        //     $total = $amount + $fee;
        //     $this->error("钱包可用余额不足(余额:$balance 消耗:$total)");
        // }
        $today = strtotime(date('Y-m-d'));
        $today_count = Db::name('transfer_log')->where("type = 1 and uid= $userId and log_time>= $today")->count();
        if($today_count > 0){
            $this->error("每天最多提现一次");
        }           
        //写入转账记录
        if($balance < $amount + $fee){      
            $total = $amount + $fee;
            $this->error("钱包可用余额不足(余额:$balance 消耗:$total)");
        }
        //开始事务处理
        Db::startTrans();

        $result = Db::name('wallet')
        ->where('id', $walletData['id'] )
        ->setDec('cloud_balance',$amount+$fee );
        if(!$result){
            Db::rollback();
            $this->error('转账提交失败(#1)！');          
        }          
        //获取新余额
        $balance_data = Db::name('wallet')
        ->field('cloud_balance')
        ->where('id', $walletData['id'] )
        ->find();

        //发送交易
        $balance_after =  $balance_data['cloud_balance'];
        $insert_data['uid']  =  $userId ;
        $insert_data['type'] =  1 ; 
        $insert_data['wallet_type'] =  1 ;
        $insert_data['wallet_id'] = $pay_wallet_id;
        $insert_data['coin_symbol'] =  $coin_symbol2;
        $insert_data['from_address'] =  $from_address;
        $insert_data['to_address'] =  $data['to_address'];
        $insert_data['address_memo'] =  (isset($data['address_memo']) && !empty($data['address_memo'])) ? $data['address_memo'] : '';
        $insert_data['amount'] =  -$amount;
        $insert_data['amount_before'] =   $balance;
        $insert_data['amount_after'] =   $balance_after;
        $insert_data['fee'] =   $fee;
        $insert_data['log_time'] =  time();
        $insert_data['memo'] = (isset($data['memo']) && !empty($data['memo'])) ? $data['memo'] : '';
        $insert_data['transfer_status'] =  0;
        $result = Db::name('transfer_log')->insertGetId($insert_data);  
        if($result){
            Db::commit();
            $return_data['id'] = $result;
            $this->success('转账提交成功！',$return_data);
        }else{
            Db::rollback();
            $this->error('转账提交失败(#2)！');          
        }
   
    }   


    //转账记录
   public function transactions()
    {

        $validate = new Validate([
            'coin_symbol'     => 'require',
            'limit_begin'    => 'integer',
            'limit_end'     => 'integer',
        ]);

        $validate->message([
            'coin_symbol.require'  => '币种类型不能为空!',
            'limit_begin.integer'  => 'limit_begin必须为整数!',
            'limit_end.integer'  => 'limit_end必须为整数!',

        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        if(!empty($data['limit_begin'])){
            $limit_begin = $data['limit_begin'];
        }else{
            $limit_begin = 0;       
        }

        if(!empty($data['limit_end'])){
            $limit_end = $data['limit_end'];
        }else{
            $limit_end = 10;       
        }

        $userId = $this->getUserId();
        //获取记录    
        $fieldStr = "id,uid,type,coin_symbol,from_address,to_address,address_memo,amount,amount_before,amount_after,blockhash,fee,log_time,tx_id,memo,transfer_status";
        $transactions = Db::name('transfer_log')
        ->field($fieldStr)
        ->where('uid', $userId)
        ->where('wallet_type', 1)
        //->where('type','in',[1,2])
        ->where('coin_symbol', 'in', [$data['coin_symbol'], 'USDT-TRC'])
        ->order('id desc')
        ->limit($limit_begin,$limit_end)
        ->select()->toArray();
        foreach ($transactions as $key => &$value) {
            $value['type_msg'] = ColumnName::$db_transfer_log['type'][$value['type']];
            $value['amount'] =  sprintf("%01.5f",$value['amount']);
            $value['amount_before'] =  sprintf("%01.5f",$value['amount_before']);
            $value['amount_after'] =  sprintf("%01.5f",$value['amount_after']);
            $value['fee'] =  sprintf("%01.5f",$value['fee']);
            $value['transfer_status_msg'] = ColumnName::$db_transfer_log['transfer_status'][$value['transfer_status']];
            if($data['coin_symbol']=="BTC"){
                $value['url'] =  "https://btc.com/".$value['tx_id'];
                $value['url_tips'] =  "到区块链浏览器查看交易";
            }elseif($data['coin_symbol']=="ETH"){
                $value['url'] =  "https://etherscan.io/tx/".$value['tx_id'];
                $value['url_tips'] =  "到区块链浏览器查看交易";
            }elseif($data['coin_symbol']=="USDT"){
                $value['url'] =  "https://www.omniexplorer.info/tx/".$value['tx_id'];
                $value['url_tips'] =  "到区块链浏览器查看交易";
            }elseif($data['coin_symbol']=="LTC"){
                $value['url'] =  "https://ltc.ihashrate.com/tx/".$value['tx_id'];
                $value['url_tips'] =  "到区块链浏览器查看交易";               
            }elseif($data['coin_symbol']=="BCH"){
                $value['url'] =  "https://bch.btc.com/".$value['tx_id'];
                $value['url_tips'] =  "到区块链浏览器查看交易";                     
            }elseif($data['coin_symbol']=="ETC"){
                $value['url'] =  "http://gastracker.io/tx/".$value['tx_id'];
                $value['url_tips'] =  "到区块链浏览器查看交易";                   
            }elseif($data['coin_symbol']=="EOS"){
                $value['url'] =  "https://eospark.com/tx/".$value['tx_id'];
                $value['url_tips'] =  "到区块链浏览器查看交易";                    
            }else{
                $value['url'] =  "";
                $value['url_tips'] =  "该交易暂不支持浏览器查看";                   
            }

        }        
        $response['count'] = count($transactions);
        $response['list'] = $transactions;
        $this->success('查询成功!',$response);
    }   
    
    public function game(){
        $userId = $this->getUserId();
        
        $coin_symbol = 'USDT';
        $app_conf = cmf_get_option('app_config');
        $game_num = floatval($app_conf['game_num']);  /*每次抽奖消费积分*/
        $game_rate = floatval($app_conf['game_rate']);  /*中奖金额%*/
        
        $current_balance =  Db::name("user")
            ->where('id', $userId)
            ->value('balance');
            
        if ($current_balance < $game_num) {
            $this->error('可用积分不足，当前余额:' . $current_balance,1);
        }
        
        $proSum = 100;  /*中奖总概率*/
        
        $randNum = mt_rand(1, $proSum);  /*随机获取概率*/
        
        $win = 0;
        
        if($randNum<$game_rate){
            /*中奖*/
            
            $win = 1;
        }
       
        if($win){
             //获取用户钱包余额    
            $walletData = Db::name('wallet')
                ->field("id,uid,cloud_balance")
                ->where('uid', intval($uid))
                ->where('type', 1)
                ->where('coin_symbol', $coin_symbol)
                ->find();
            if (!empty($walletData)) {
                
                $user_balance = floatval($walletData['cloud_balance']);
            }else{
                $this->error('抽奖失败！',1);
            }
            
            
            
            $totalnum = $allnum+$game_num;
            
            
            $money = $totalnum*$game_rate/100;
            
            $memo = '中奖: ' . $money;
            $balance_after = $user_balance + $money;
            $insert_data['uid']  = $walletData['uid'];
            $insert_data['type'] = 24; // 分润扣除 quant_profit_reduce
            $insert_data['wallet_type'] = 1; // 云端钱包
            $insert_data['wallet_id'] = $walletData['id'];
            $insert_data['coin_symbol'] =  $coin_symbol;
            $insert_data['from_address'] =  '';
            $insert_data['to_address'] =  'game';
            $insert_data['amount'] =  -$money;
            $insert_data['amount_before'] = $user_balance;
            $insert_data['amount_after'] = $balance_after;
            $insert_data['fee'] = 0;
            $insert_data['log_time'] = time();
            $insert_data['memo'] = $memo;
            $insert_data['blockhash'] = '';
            $insert_data['tx_id'] = '';
            $insert_data['transfer_status'] = 1;
            $result = Db::name('transfer_log')->insert($insert_data);
            
            Db::name('user')->where('id',$userId)->update(['game_score'=>0]);
            
        }else{
            Db::name('user')->where('id',$userId)->setInc('game_score',$game_num);
        }
        
        /*添加中奖记录*/
            
         $log = array(
            'uid' => $userId,
            'amount' => $win?$money:$game_num,
            'ctime' => time(),
            'win' => $win,
        );
        
        $to_uid = Db::name('game_log')->insertGetId($log);
        
         
        /*扣除积分*/
        Apibase::updateBalance(10, $userId, 'balance', -$game_num, $to_uid, 'game', "抽奖消费");
        
        if($win){
            $arr = array('0'=>'一等奖','2'=>'二等奖','4'=>'三等奖','6'=>'四等奖');
            $a = array_rand($arr);
        
            $this->success("恭喜您，中奖了！",$a);
        }else{
            
            $allnum = Db::name('user')->where('id',$userId)->value('game_score');
            $this->success("差一点就中奖了！",1);
        }
        
    }
    
    public function gamelog()
    {

        $validate = new Validate([
            'limit_begin'    => 'integer',
            'limit_end'     => 'integer',
        ]);

        $validate->message([
            'limit_begin.integer'  => 'limit_begin必须为整数!',
            'limit_end.integer'  => 'limit_end必须为整数!',

        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        if(!empty($data['limit_begin'])){
            $limit_begin = $data['limit_begin'];
        }else{
            $limit_begin = 0;       
        }

        if(!empty($data['limit_end'])){
            $limit_end = $data['limit_end'];
        }else{
            $limit_end = 10;       
        }

        $userId = $this->getUserId();
        //获取记录    
        $transactions = Db::name('game_log')
        ->where('uid', $userId)
        ->order('id desc')
        ->limit($limit_begin,$limit_end)
        ->select()->toArray();
        foreach ($transactions as $key => &$value) {
            
            $name = Db::name('user')->where('id',$value['uid'])->value('mobile');
            $value['mobile'] = substr_replace($name, '****', 5, 4);
        }        
        $response['count'] = count($transactions);
        $response['list'] = $transactions;
        $this->success('查询成功!',$response);
    }   
    
    
    public function scorelog()
    {

        $validate = new Validate([
            'limit_begin'    => 'integer',
            'limit_end'     => 'integer',
        ]);

        $validate->message([
            'limit_begin.integer'  => 'limit_begin必须为整数!',
            'limit_end.integer'  => 'limit_end必须为整数!',

        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        if(!empty($data['limit_begin'])){
            $limit_begin = $data['limit_begin'];
        }else{
            $limit_begin = 0;       
        }

        if(!empty($data['limit_end'])){
            $limit_end = $data['limit_end'];
        }else{
            $limit_end = 10;       
        }

        $userId = $this->getUserId();
        //获取记录    
        $transactions = Db::name('balance_log')
        ->where('user_id', $userId)
        ->order('id desc')
        ->limit($limit_begin,$limit_end)
        ->select()->toArray();
        foreach ($transactions as $key => &$value) {
            $value['type_msg'] = $value['extension'];
        }        
        $response['count'] = count($transactions);
        $response['list'] = $transactions;
        $this->success('查询成功!',$response);
    }   
}
