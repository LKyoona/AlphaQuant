<?php

namespace api\home\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;

class TickerController extends RestBaseController
{

    // 行情服务读取的启用交易对配置
    public function config()
    {
        $rows = Db::name('spot_market')
            ->field('platform as exchange,market')
            ->where('status', 1)
            ->group('platform,market')
            ->order('platform asc,market asc')
            ->select();

        $this->success('获取成功！', [
            'list' => $rows ? $rows->toArray() : [],
        ]);
    }

    public function lists()
    {


        $validate = new Validate([
            'currency'     => 'require',
            'order'     => 'in:coin,volume,price,change',
            'order_type'     => 'in:asc,desc',
        ]);

        $validate->message([
            'currency.require'  => '货币类型不能为空!',
            'order.in'  => 'order参数有误!',
            'order_type.in'  => 'order_type设置有误!',
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
        //游客模式
        if (empty($this->user)) {
           $userId  = 3;   
        }else{
            $userId  = $this->getUserId();           
        }

        $fieldStr = 'a.id,b.exchange_name,b.market,coin,currency,volume,price,change,c.img_url';


        $ticker_count = Db::name('user_ticker')
        ->where('uid', $userId)
        ->count();

        //初始化生成默认行情
        if($ticker_count == 0){    
            $param['uid'] = $userId;
            $result = hook_one("generate_ticker", $param);
            if ($result !== false && !empty($result['error'])) {
                $this->error($result['message']);
            }
            if ($result === false) {
                $this->generateDefaultTicker($userId);
            }               
        }
        if(empty($data['order'])||empty($data['order_type'])){
            $order_str = 'a.sort asc,a.ticker_id desc';
        }else{
            $order_str = $data['order']." ".$data['order_type'];
        }
        $tickerData = Db::name('user_ticker')
        ->alias('a')
        ->join(config('database.prefix').'ticker b',"a.ticker_id = b.id")
        ->join(config('database.prefix').'coin c',"b.coin = c.coin_symbol")
        ->field($fieldStr)
        ->where('uid', $userId)
        ->where('a.status', 1)
        ->where('b.status', 1)
        ->order($order_str)
        ->select();
        
        $tickerData = $tickerData->toArray();

        foreach ($tickerData as &$ticker) {
            $liveTicker = cmf_market_ticker($ticker['exchange_name'], $ticker['market']);
            if ($liveTicker !== null) {
                $ticker['price'] = (float) $liveTicker['last_price'];
                $ticker['change'] = isset($liveTicker['change_percent_24h'])
                    ? (float) $liveTicker['change_percent_24h']
                    : 0;
                $ticker['volume'] = isset($liveTicker['base_volume_24h'])
                    ? (float) $liveTicker['base_volume_24h']
                    : 0;
                $ticker['updated_at'] = isset($liveTicker['received_at'])
                    ? (int) $liveTicker['received_at']
                    : 0;
                $ticker['stale'] = false;
            } else {
                $ticker['updated_at'] = 0;
                $ticker['stale'] = true;
            }
            $ticker['currency_symbol'] = $currency_symbol[$data['currency']];  
            $ticker['price_usd'] = round($ticker['price'],2);  
            $ticker['price'] = floatval($rate[$data['currency']] * $ticker['price']);  
            $ticker['price'] = sprintf("%01.2f",$ticker['price']); 
            $ticker['price_cny'] = sprintf("%01.2f",$ticker['price']*6.4); 
            $ticker['change'] = floatval($ticker['change']);  
            $ticker['change'] = sprintf("%01.2f",$ticker['change']); 
            $ticker['volume'] = $this->formatNum($ticker['volume']);
            $ticker['img_url'] = cmf_get_image_preview_url($ticker['img_url']);

        }

        $response['count'] = count($tickerData);
        $response['list'] = $tickerData;
        $this->success('获取成功！', $response);

    }

    //所有行情列表
    public function all()
    {

        if (empty($this->user)) {
            $this->error(['code' => 10001, 'msg' => '登录已失效!']);
        }

        $userId = $this->getUserId();

        //查询行情
        $fieldStr = 'a.id as ticker_id,exchange_name,coin,img_url,coin_name,currency,IFNULL(b.status,0) as status';
        
        $tickerData = Db::name('ticker')
        ->alias('a')
        ->join(config('database.prefix').'user_ticker b',"a.id = b.ticker_id and uid = $userId","LEFT")
        ->join(config('database.prefix').'coin c',"a.coin = c.coin_symbol")        
        ->field($fieldStr)
        ->where('a.status', 1)
        ->order('a.sort asc')        
        ->select();
        
        $tickerData = $tickerData->toArray();

        $ticker_list  = array();

        foreach ($tickerData as $key => $ticker) {
            if(!isset($ticker_list[$ticker['exchange_name']])){
                $ticker_list[$ticker['exchange_name']] = array();
            }
            $ticker['img_url'] = cmf_get_image_preview_url($ticker['img_url']);
            array_push($ticker_list[$ticker['exchange_name']], $ticker);    
        }
        $i = 0;
        $ticker_data = array();
        foreach ($ticker_list as $key => $value) {
            $ticker_count = count($value);
            $ticker_data[$i]['exchange_name'] = $key;
            $ticker_data[$i]['ticker_list']['count'] = $ticker_count;
            $ticker_data[$i]['ticker_list']['list'] = $value;
            $i++;
        }
        $response['count'] = count($ticker_data);
        $response['list'] = $ticker_data;
        $this->success('获取成功！', $response);

    }

    //增加行情
    public function add()
    {
        if (empty($this->user)) {
            $this->error(['code' => 10001, 'msg' => '登录已失效!']);
        }

         $validate = new Validate([
            'ticker_id'     => 'require|number',
        ]);

        $validate->message([
            'ticker_id.require'  => 'ticker_id不能为空!',
            'ticker_id.number'  => 'ticker_id必须为数值!',
        ]);


        $data = $this->request->param();

        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        
        $userId = $this->getUserId();

        //查询行情是否存在
        $fieldStr = 'b.id,IFNULL(b.status,-1) as status';

        $ticker_data = Db::name('ticker')
        ->alias('a')
        ->join(config('database.prefix').'user_ticker b',"a.id = b.ticker_id and uid = $userId","LEFT")
        ->field($fieldStr)
        ->where('a.status', 1)
        ->where('a.id', $data['ticker_id'])
        ->find();
        if(empty($ticker_data)){ //没有该行情
             $this->error('指定行情不存在');
        }
        if($ticker_data['status'] == -1){//生成行情
            $insert_data['uid'] =  $userId ;
            $insert_data['ticker_id'] =  $data['ticker_id'];
            $insert_data['sort'] =  0;
            $insert_data['status'] =  1;
            $result = Db::name('user_ticker')->insert($insert_data);
            if($result){
               $this->success('添加成功！'); 
            }else{
               $this->error('添加失败(#1)！');               
            }
        }     
        //更新行情显示状态
        $save_data['status'] = 1;
        $id = $ticker_data['id'];
        $result = Db::name('user_ticker')->where('id',$id)->update($save_data);      
        if($result!==false){
           $this->success('添加成功！'); 
        }else{
           $this->error('添加失败(#2)！');               
        }
    }

    //移除币种
    public function delete()
    {
        if (empty($this->user)) {
            $this->error(['code' => 10001, 'msg' => '登录已失效!']);
        }        

         $validate = new Validate([
            'ticker_id'     => 'require|number',
        ]);

        $validate->message([
            'ticker_id.require'  => 'ticker_id不能为空!',
            'ticker_id.number'  => 'ticker_id必须为数值!',
        ]);


        $data = $this->request->param();

        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        
        $userId = $this->getUserId();
        //更新钱包状态
        $save_data['status'] = 0;
        $ticker_id = $data['ticker_id'];        
        $result = Db::name('user_ticker')->where('ticker_id',$ticker_id)->where('uid',$userId)->update($save_data);
        if($result!=false){
           $this->success('移除成功！'); 
        }else{
           $this->error('移除失败！');               
        }
    }


    //币种排序
    public function sort(){
        if (empty($this->user)) {
            $this->error(['code' => 10001, 'msg' => '登录已失效!']);
        }

         $validate = new Validate([
            'ids'     => 'require',
        ]);
        $validate->message([
            'ids.require'  => 'ids不能为空!',
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }      
        $userId = $this->getUserId();
        $ids = $data['ids'];
        $ids =  preg_split('/[,]+/s', $ids);
        foreach ($ids as $key => $value) {
            $sort = $key;
            $id = $value;
            $save_data['sort'] = $sort;
            $result = Db::name('user_ticker')->where('id',$id)->where('uid',$userId)->where('status',1)->update($save_data);  
        }
        $this->success('排序成功！');         
    }

    function formatNum($num) { 
        $units = array('','k', 'm'); 
        for ($i = 0; $num >= 1000 && $i < 2; $i++) 
            $num /= 1000; 
        return round($num, 2).$units[$i]; 
    }

    private function generateDefaultTicker($userId)
    {
        $tickerList = Db::name('ticker')
            ->field('id')
            ->where('status', 1)
            ->order('sort asc,id desc')
            ->select();

        if ($tickerList) {
            $tickerList = $tickerList->toArray();
        }

        if (empty($tickerList)) {
            $this->error('行情数据为空，请先在后台添加币种行情!');
        }

        $insertData = [];
        foreach ($tickerList as $sort => $ticker) {
            $insertData[] = [
                'uid' => $userId,
                'ticker_id' => $ticker['id'],
                'sort' => $sort,
                'status' => 1,
            ];
        }

        Db::name('user_ticker')->insertAll($insertData);
    }


}
