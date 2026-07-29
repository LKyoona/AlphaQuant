<?php

namespace api\wallet\controller;

use cmf\controller\RestUserBaseController;
use think\Db;
use think\Validate;

class CloudController extends RestUserBaseController
{

    public function lists()
    {

        $validate = new Validate([
            'currency'     => 'require',
        ]);

        $validate->message([
            'currency.require'  => '货币类型不能为空!',
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

        $fieldStr = 'a.id,b.coin_name,a.coin_symbol,b.img_url,cloud_balance,b.usd_price';


        $wallet_count = Db::name('wallet')
        ->where('uid', $userId)
        ->where('type', 1)
        ->count();

        //初始化生成钱包
        if($wallet_count < 2){
            $param['uid'] = $userId;
            $result = hook_one("generate_wallet", $param);
            if ($result !== false && !empty($result['error'])) {
                $this->error($result['message']);
            }
            if ($result === false) {
                $this->error('钱包生成失败，请联系管理员!');
            }            
        }

        $walletData = Db::name('wallet')
        ->alias('a')
        ->join(config('database.prefix').'coin b',"a.coin_symbol = b.coin_symbol and b.cloud_status = 1")
        ->field($fieldStr)
        ->where('uid', $userId)
        ->where('type', 1)
        ->where('status', 1)
        ->order('add_time asc,id asc')
        ->select();
        
        $walletData = $walletData->toArray();

        foreach ($walletData as &$wallet) {
            $wallet['currency'] = $data['currency'];
            $wallet['currency_symbol'] = $currency_symbol[$data['currency']];
            $wallet['coin_price'] = floatval($rate[$data['currency']] * $wallet['usd_price']);    
            $wallet['coin_price'] = sprintf("%01.2f",$wallet['coin_price']);            
            $wallet['price'] = floatval($rate[$data['currency']] * $wallet['usd_price'] * $wallet['cloud_balance']);    
            $wallet['price'] = sprintf("%01.2f",$wallet['price']);
            unset($wallet['usd_price']);
            $wallet['img_url']  =cmf_get_image_preview_url($wallet['img_url']) ;     
            $wallet['cloud_balance'] = sprintf("%01.6f",$wallet['cloud_balance']);                   
        }

        $walletPrice = Db::name('wallet')
            ->alias('a')
            ->join(config('database.prefix').'coin b',"a.coin_symbol = b.coin_symbol and b.cloud_status = 1")
            ->field("a.coin_symbol,a.cloud_balance,b.usd_price")
            ->where('uid', $userId)
            ->where('type', 1)
            ->select()->toArray();

        //var_dump($walletPrice);

        $total_price = 0;
        foreach ($walletPrice as $x){
            $total_price += $rate[$data['currency']] * $x['usd_price'] * $x['cloud_balance'];
        }

        $btc_info =  Db::name('coin')->where("coin_symbol = 'BTC'")->field('usd_price')->find();

        $response['count'] = count($walletData);
        $response['btc_value'] = 0;
        if($btc_info){
            $response['btc_value'] =  sprintf("%.6f",$total_price / $rate[$data['currency']] / $btc_info['usd_price'],$total_price);
        }
        $response['total_price'] = sprintf("%.2f",$total_price);        
        $response['currency'] = $data['currency'];
        $response['currency_symbol'] = $currency_symbol[$data['currency']];
        $response['list'] = $walletData;
        $this->success('获取成功！', $response);

    }

    //所有币种列表
    public function coins()
    {

        $validate = new Validate([
            'status'     => 'require|in:all,display,none',
        ]);

        $validate->message([
            'status.require'  => '筛选类型不能为空!',
            'status.in'  => '筛选类型不正确!',
        ]);


        $data = $this->request->param();

        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        
        $userId = $this->getUserId();

        //查询币种

        $fieldStr = 'a.id,a.coin_name,a.coin_symbol,a.coin_type,a.parent_coin,a.contract,a.img_url,IFNULL(b.status,0) as status';

        $where = '';
        if($data['status'] == 'display'){
            $where = ' status = 1 ';
        }

        if($data['status'] == 'none'){
            $where = ' status = 0 ';
        }      
        $coinData = Db::name('coin')
        ->alias('a')
        ->join(config('database.prefix').'wallet b',"a.coin_symbol = b.coin_symbol and uid = $userId and type = 1","LEFT")
        ->field($fieldStr)
        ->where('cloud_status', 1)
        ->where($where)
        ->order('sort asc')        
        ->select();
        
        $coinData = $coinData->toArray();

        $coin_index  = array();
        $token_list  = array();

        foreach ($coinData as $key => &$coin) {
            $coin['img_url']  = cmf_get_image_preview_url($coin['img_url']) ;    
            if($coin['coin_type'] == 'coin'){
                $coin_index[$coin['coin_symbol']] = $key;
                $coin['token_list'] = ['count'=>0,'data'=>array()];
            } 
            if($coin['coin_type'] == 'token'){
                if(!isset($token_list[$coin['parent_coin']])){
                    $token_list[$coin['parent_coin']] = array();
                }
                array_push($token_list[$coin['parent_coin']], $coin);
                unset($coinData[$key]);
            }
        }
        foreach ($token_list as $key => $value) {
            $token_count = count($value);
            if(isset($coin_index[$key])){
                $coinData[$coin_index[$key]]['token_list']['count'] = $token_count;
                $coinData[$coin_index[$key]]['token_list']['data'] = $value;
            }
        }
        $response['count'] = count($coinData);
        $final_list = array();
        foreach ($coinData as $key => $value) {
           $final_list[] =  $value;
        }
        $response['list'] = $final_list;
        $this->success('获取成功！', $response);

    }

    //增加币种
    public function add()
    {
         $validate = new Validate([
            'coin_symbol'     => 'require',
        ]);

        $validate->message([
            'coin_symbol.require'  => '币种类型不能为空!',
        ]);


        $data = $this->request->param();

        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        
        $userId = $this->getUserId();

        //查询币种钱包是否存在
        $fieldStr = 'b.id,a.coin_type,a.parent_coin,IFNULL(b.status,-1) as status';

        $coin_data = Db::name('coin')
        ->alias('a')
        ->join(config('database.prefix').'wallet b',"a.coin_symbol = b.coin_symbol and uid = $userId and type = 1","LEFT")
        ->field($fieldStr)
        ->where('cloud_status', 1)
        ->where('a.coin_symbol', $data['coin_symbol'])
        ->find();
        if(empty($coin_data)){ //没有该币种
             $this->error('指定币种不存在');
        }
        if($coin_data['status'] == -1){//生成钱包
            //如果是代币 就要检测父币种的地址
            $insert_data = array();
            if($coin_data['coin_type'] == 'token'){
                $parent_coin_symbol = $coin_data['parent_coin'];
                //查询父币种地址
                $parent_coin_data = Db::name('wallet')
                ->field("id,address,memo,seed")
                ->where('type', 1)
                ->where('uid', $userId )                
                ->where('coin_symbol',$parent_coin_symbol)
                ->find();
                if(!empty($parent_coin_data)){
                    $insert_data['address'] =  $parent_coin_data['address'];
                    $insert_data['memo'] =  $parent_coin_data['memo'];
                    $insert_data['seed'] = $parent_coin_data['seed'];
                }               
            }     
            $insert_data['uid'] =  $userId ;
            $insert_data['coin_symbol'] =  $data['coin_symbol'];
            $insert_data['type'] =  1;
            $insert_data['add_time'] =  time();
            $result = Db::name('wallet')->insert($insert_data);
            //增加任务
            $p['uid'] = $userId;
            $p['coin_symbol'] =  $data['coin_symbol'];
            $p['type'] = 1;
            $task_data['params'] = json_encode($p);
            $task_data['task_name'] = "update_wallet_balance";
            $task_data['uid'] = $userId;
            $task_data['schedule_time'] =0;
            Db::name('cron')->insert($task_data);               
            if($result){
               $this->success('添加成功！'); 
            }else{
               $this->error('添加失败(#1)！');               
            }
        }     
        //更新钱包状态
        $save_data['status'] = 1;
        $save_data['add_time'] =  time();
        $wallet_id = $coin_data['id'];
        $result = Db::name('wallet')->where('id',$wallet_id)->update($save_data);      
        if($result!=false){
           $this->success('添加成功！'); 
        }else{
           $this->error('添加失败(#2)！');               
        }
    }

    //移除币种
    public function delete()
    {
        $validate = new Validate([
            'coin_symbol'     => 'require',
        ]);

        $validate->message([
            'coin_symbol.require'  => '币种类型不能为空!',
        ]);


        $data = $this->request->param();

        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        
        $userId = $this->getUserId();
        
        //更新钱包状态
        $save_data['status'] = 0;
        $result = Db::name('wallet')
        ->where('coin_symbol',$data['coin_symbol'])
        ->where("uid",$userId)
        ->where("type",1)        
        ->update($save_data);      
        if($result!=false){
           $this->success('移除成功！'); 
        }else{
           $this->error('移除失败！');               
        }
    }

    //搜索币种
    public function search()
    {
        $validate = new Validate([
            'keyword'     => 'require',
        ]);

        $validate->message([
            'keyword.require'  => '搜索词不能为空!',
        ]);


        $data = $this->request->param();

        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        
        $userId = $this->getUserId();
   
        //查询币种

        $fieldStr = 'a.id,a.coin_name,a.coin_symbol,a.coin_type,a.parent_coin,a.contract,a.img_url,IFNULL(b.status,0) as status';
 
        $coinData = Db::name('coin')
        ->alias('a')
        ->join(config('database.prefix').'wallet b',"a.coin_symbol = b.coin_symbol and uid = $userId and type = 1","LEFT")
        ->field($fieldStr)
        ->where('cloud_status', 1)
        ->where('a.coin_symbol|contract','like','%'.$data['keyword'].'%')
        ->order('sort asc')       
        ->select()->toArray();

        foreach ($coinData as &$coin) {
            $coin['img_url']  =cmf_get_image_preview_url($coin['img_url']) ;     
        }
        $response['count'] = count($coinData);
        $response['list'] = $coinData;
        $this->success('搜索成功！', $response);
    }
}
