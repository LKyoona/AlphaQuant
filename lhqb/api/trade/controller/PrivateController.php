<?php
namespace api\trade\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;

class PrivateController extends RestBaseController
{
    private $exchange_name = "";
    private $market = "";
    private $exchange_class = "";
    private $className = "";

    private $apiKey = "";
    private $secret = "";
    private $password = "";

    public function _initialize()
    {
        date_default_timezone_set("Etc/GMT");
        //ini_set('date.timezone','Asia/Shanghai');

        $validate = new Validate([
            'exchange'     => 'require',
            'market'     => 'require',
            'api_key'     => 'require',
            'api_secret'     => 'require',
            'api_password'     => 'require',
        ]);

        $validate->message([
            'exchange.require'  => '交易所不能为空!',
            'market.require'  => '交易对不能为空!',
            'api_key.require'  => 'ApiKey不能为空!',
            'api_secret.require'  => 'ApiSecret不能为空!',
            'api_password.require'  => 'ApiPassword不能为空!',
        ]);

        $data = $this->request->param();

        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        
        $this->apiKey =  $data['api_key'];
        $this->secret =  $data['api_secret'];
        $this->password = $data['api_password'];

        $this->exchange_name = $data['exchange'];

        $this->market = $data['market'];

        $exchange_data = Db::name('ticker')
        ->field('id,exchange_class,market,coin,currency')
        ->where('exchange_name', $this->exchange_name)
        ->where('market', $this->market)
        ->where('status', 1)
        ->find();  

        if(empty($exchange_data)){
            $this->error('指定交易所交易所不存在');
        }      

        $this->exchange_class = $exchange_data['exchange_class'];

        $vendor_name = "ccxt.ccxt";
        Vendor($vendor_name);

        $this->className = "\ccxt\\".$this->exchange_class;
    }

    /* 账户余额 */
    public function balance(){

        $exchange  = new $this->className (array (
            'apiKey' => $this->apiKey,
            'secret' => $this->secret ,
            'password'=>$this->password,
        ));

        //$this->market
        try{
            $result = $exchange->fetch_balance();
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }        

        $data = array();

        $coin = explode('/', $this->market)[0];
        $currency = explode('/', $this->market)[1];
        if(isset($result['free'][$coin])){
            $data[$coin] = $result['free'][$coin];
        }else{
            $data[$coin] = 0;
        }
        if(isset($result['free'][$currency])){
            $data[$currency] = $result['free'][$currency];
        }else{
            $data[$currency] = 0;
        } 

        $this->success('查询成功',$data);
    }

    /* 下单 */
    public function order(){

        $validate = new Validate([
            'side'     => 'require|in:buy,sell',
            'amount'     => 'require|gt:0',
            'price'     => 'require|gt:0',
        ]);

        $validate->message([
            'side.require'  => 'side不能为空!',
            'side.in'  => 'side不正确!',
            'amount.require'  => '数量不能为空!',
            'price.require'  => '价格不能为空!',
        ]);

        $data = $this->request->param();

        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $side     = $data['side'];

        $amount     = $data['amount'];

        $price     = $data['price'];

        $exchange  = new $this->className(array (
            'apiKey' => $this->apiKey,
            'secret' => $this->secret ,
            'password'=>$this->password,
        ));

        try{
            $result = $exchange->create_order ($this->market, 'limit', $side, $amount, $price);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('下单成功',$result);
    }

    /* 取消订单 */
    public function cancelOrder(){

        $validate = new Validate([
            'order_id'     => 'require',
        ]);

        $validate->message([
            'order_id.require'  => '订单号不能为空!',
        ]);

        $data = $this->request->param();

        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $order_id     = $data['order_id'];


        $exchange  = new $this->className (array (
            'apiKey' => $this->apiKey,
            'secret' => $this->secret ,
            'password'=>$this->password,
        ));

        //
        try{
            $result = $exchange->cancel_order($order_id,$this->market);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }        

        $this->success('取消成功',$result);

    }

    /* 当前委托 */
    public function openOrders(){

        $exchange  = new $this->className (array (
            'apiKey' => $this->apiKey,
            'secret' => $this->secret ,
            'password'=>$this->password,
        ));

        //
        try{
            $result = $exchange->fetch_open_orders($this->market);
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }        

        $this->success('查询成功',$result);

    }

    /* 历史委托 */
    public function historyOrders(){

        $exchange  = new $this->className (array (
            'apiKey' => $this->apiKey,
            'secret' => $this->secret ,
            'password'=>$this->password,
        ));

        //
        try{
            if(method_exists($exchange,"fetch_closed_orders")){
                $result = $exchange->fetch_closed_orders($this->market);
            }else{
                $result = $exchange->fetch_my_trades($this->market);
            }
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }        

        $this->success('查询成功',$result);

    }
}