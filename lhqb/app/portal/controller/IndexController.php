<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 老猫 <thinkcmf@126.com>
// +----------------------------------------------------------------------
namespace app\portal\controller;

use cmf\controller\HomeBaseController;
use think\Db;

class IndexController extends HomeBaseController
{
    public function index()
    {
        return redirect('/app/');
    }

    public function baike()
    {
        $coin_symbol = $this->request->param('coin_symbol');
        $coin_info = Db::name("coin")
        ->where(['coin_symbol'=>$coin_symbol])
        ->field("coin_symbol,coin_name,img_url,usd_price,price_change,price_change_week,chain_type,coin_amount,coin_value,coin_value_rank,ico_time,ico_price,coin_homepage,coin_whitepaper,coin_introduction")
        ->find();        

        $this->assign($coin_info);      
        return $this->fetch(':baike');
    }


    public function hangqing()
    {
        $coin_symbol = $this->request->param('coin_symbol');
        $coin_info = Db::name("coin")
        ->where(['coin_symbol'=>$coin_symbol])
        ->field("coin_symbol,coin_name,img_url,usd_price,price_change,price_change_week")
        ->find();        

        $ticker_info = Db::name("ticker")
        ->where(['coin'=>$coin_symbol,'status'=>1])
        ->field("coin,currency,exchange_name,exchange_class,market,volume,price,change")
        ->select(); 

        if(!empty($ticker_info )){
            $ticker_info = $ticker_info->toArray();
        }
        foreach ($ticker_info as &$ticker) {
            $ticker['price'] = round($ticker['price'],2);  
            $ticker['change'] = floatval($ticker['change']);  
            $ticker['change'] = sprintf("%01.2f",$ticker['change']); 
            $ticker['volume'] = $this->formatNum($ticker['volume']);
        }

        $this->assign($coin_info);    
        $this->assign('ticker_info',$ticker_info);
        return $this->fetch(':hangqing');
    }


    public function sendEnvelope()
    {
    	$id = $this->request->param('red_envelope_id');
    	$key = $this->request->param('red_envelope_key');
        $code = $this->request->param('invitation_code');
        $red_envelope = Db::name("UserRedEnvelope")->alias('a')
        ->join(config('database.prefix').'coin b',"a.coin_symbol = b.coin_symbol ")
        ->join(config('database.prefix').'red_envelope_nickname c',"a.nickname = c.nickname ", 'LEFT')
        ->where(['a.id'=>$id])
        ->field("a.id,a.coin_symbol,a.total_amount,a.total_num,a.nickname,a.wish,b.img_url,c.avatar")
        ->find();
        
        if(empty($red_envelope['avatar'])){
            $red_envelope['avatar'] = '/upload/icon/1.png';
        }else{
            $red_envelope['avatar'] = '/upload/'.$red_envelope['avatar'];
        }

        $app_config = cmf_get_option('app_config');
        $download_url = "#";

        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            if(isset($app_config['ios_download_url'])){
                $download_url = $app_config['ios_download_url'];
            } 
        }else {
            if(isset($app_config['android_download_url'])){
                $download_url = $app_config['android_download_url'];
            } 
        }


        $this->assign('download_url',$download_url);
        
        $this->assign('red_envelope',$red_envelope);
    	$this->assign('id',$id);
    	$this->assign('key',$key);
        $this->assign('code',$code);
        return $this->fetch(':SendEnvelope');
    }
    
    public function invite()
    {
        $code = $this->request->param('invitation_code');
        $this->assign('app_config', cmf_get_option('app_config'));        
        $this->assign('code',$code);
        return $this->fetch(':invite');
    }
    
    function formatNum($num) { 
        $units = array('','k', 'm'); 
        for ($i = 0; $num >= 1000 && $i < 2; $i++) 
            $num /= 1000; 
        return round($num, 2).$units[$i]; 
    }
}
