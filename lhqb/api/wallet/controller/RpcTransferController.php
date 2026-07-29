<?php

namespace api\wallet\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;

class RpcTransferController extends RestBaseController
{


    public function index(){
        //写入交易日志
        $logs = Db::name('transfer_log')
        ->field('id,wallet_id,coin_symbol,from_address,to_address,address_memo,amount,fee')
        ->where('type',1)
        ->where('transfer_status',0)
        ->select();  
        foreach ($logs as $key => $value) {
            $log_id = $value['id'];
            $wallet_id = $value['wallet_id'];
            $coin_symbol = $value['coin_symbol'];
            $to_address = $value['to_address'];
            $amount = abs($value['amount']);
            $address_memo = $value['address_memo'];     
                   
            $ret = $this->rpc_send_transactions($wallet_id,$coin_symbol,$to_address,$amount,$log_id,$address_memo);
            if($ret['code']==1){
                $update_data['tx_id'] = $ret['data'];
                $update_data['memo'] = $ret['data'];
                $update_data['transfer_status'] = 2;
                Db::name('transfer_log')->where('id',$log_id)->update($update_data);               
            }else{
                $update_data['tx_id'] = "";
                $update_data['memo'] = $ret['msg'];
                $update_data['transfer_status'] = -1;
                Db::name('transfer_log')->where('id',$log_id)->update($update_data);                  
            }
        }

    }

    function rpc_send_transactions($wallet_id,$coin_symbol,$to_address,$amount,$transaction_no,$memo=""){
        if($coin_symbol=='IOTA'||$coin_symbol=='ADA'){
            return array('code'=>-1,'msg'=>"pass");  
        }        
        
        $vendor_name = "Chain.walletapi";
        Vendor($vendor_name);
        $class_name = "\\walletapi";
        //检测钱包是否存在
        $fieldStr = 'rpc_ip,rpc_port,rpc_user,rpc_pass,rpc_last_pos';
        $coin_data = Db::name('coin')
        ->field($fieldStr)
        ->where('coin_symbol', $coin_symbol)
        ->find();
        if(empty($coin_data)){ //没有该币种
            return array('code'=>0,'msg'=>"coin symbol not exists");  
        }
        //RPC连接
        $rpc = new $class_name();    
        if (!method_exists($rpc,"account_transfer")) {
            return array('code'=>0,'msg'=>"$class_name method account_transfer not exists");   
        }
        //获取钱包
        $wallet_data = Db::name('wallet')
        ->field("id,chain_balance,address,memo,seed")
        ->where('coin_symbol', $coin_symbol)
        ->where('id',$wallet_id)
        ->find(); 
        if(empty($wallet_data)){
            return array('code'=>0,'msg'=>"wallet not exists");               
        }

        $from_address = $wallet_data['address'];
        $seed = $wallet_data['seed'];
    
        $ret = $rpc->account_transfer($seed,$from_address,$amount,$to_address,$transaction_no,$memo); 

        if($ret['code'] == 1){
             return array('code'=>1,'msg'=>"$class_name method: send_Transactions ok",'data'=>$ret['data']['tx_id']);     
        }else{
             return array('code'=>0,'msg'=>"$class_name send_Transactions error:".json_encode($ret),'data'=>"");              
        }  
                         
    }



}
