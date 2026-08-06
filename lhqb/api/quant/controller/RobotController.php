<?php

namespace api\quant\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;
use api\common\service\RedisPackage;

class RobotController extends RestBaseController
{
    //可用的market list
    public function marketList()
    {
        $platforms = implode(',', Db::name("third_platform")->column('platform'));
        $validate = new Validate([
            'platform'     => 'require|in:' . $platforms,
            'type'     => 'require|in:spot',
        ]);

        $validate->message([
            'platform.require'  => '平台不能为空!',
            'type.require'  => '市场类型不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $platform = $data['platform'];
        $where = array();
        $where['platform'] = $platform;
        $where['status'] = 1;
        $marketList = Db::name("spot_market")->where($where)->select();
        if ($marketList) {
            $marketList =  $marketList->toArray();
        }
        $this->success('success', $marketList);
    }

    //机器人列表
    public function robotList()
    {
        $userId = $this->getUserId();
        //机器人列表
        $where = array();
        $where['uid'] = $userId;
        $fieldStr = 'a.*,b.market_name,b.stock,b.money';
        $robot_list = Db::name("quant_robot")
            ->alias('a')
            ->join(config('database.prefix') . 'spot_market b', "a.market_id = b.id", "LEFT")
            ->field($fieldStr)
            ->where($where)
            ->order('a.id desc')
            ->select();
        if ($robot_list) {
            $robot_list = $robot_list->toArray();
        }
        foreach ($robot_list as &$robot) {
            $values = [];
            if (!empty($robot['values_str'])) {
                $decoded = json_decode($robot['values_str'], true);
                $values = is_array($decoded) ? $decoded : [];
            }
            $price = isset($values['base_price']) ? $values['base_price'] : 0;
            $firstOrderValue = (float) $robot['first_order_value'];
            $rate = $firstOrderValue > 0
                ? number_format((float) $robot['revenue'] / $firstOrderValue, 4, '.', '')
                : '0.0000';

            $robot['values'] = $values;
            $robot['price'] = $price;
            $robot['rate'] = $rate;
        }
        $this->success('success', $robot_list);
    }
    
    //机器人列表
    public function robotpai()
    {
        $userId = $this->getUserId();
        //机器人列表
        $where = array();
        $where['uid'] = $userId;
        $where['a.status'] = 1;
        $fieldStr = 'a.id,a.platform,a.revenue,a.values_str,a.first_order_value,b.market_name,b.stock,b.money';
        $robot_list = Db::name("quant_robot")
            ->alias('a')
            ->join(config('database.prefix') . 'spot_market b', "a.market_id = b.id", "LEFT")
            ->field($fieldStr)
            ->where($where)
            ->order('a.id desc')
            ->select();
        if ($robot_list) {
            $robot_list = $robot_list->toArray();
        }
        $redis = new RedisPackage();
        
        // $ret = $redis::get('HUOBIXRP/USDTUSDTICKER');
        // $ret = json_decode($ret,true);
        
        foreach ($robot_list as &$robot) {
            
            $param = strtoupper($robot['platform']).strtoupper($robot['market_name']).'USDTICKER';
           
            $ret = $redis::get($param);
            
            $ret = json_decode($ret,true);
            
            if(!empty($robot['values_str']) && isset($ret['last'])){
                
                $values_str = json_decode($robot['values_str'],true);
                
                $price = $values_str['base_price'];
                
                $now_price = $ret['last'];
                
                $deal_amount = $values_str['deal_amount'];
                
                $deal_money = $values_str['deal_money'];
                
                
                
                $rate = number_format(($now_price-$price)/$price*100,4);
            }else{
                $price = '-';
                $rate = '-';
                $deal_amount = '-';
                $deal_money = '-';
                $now_price = $ret['last']?:0;
            }
            $robot['deal_amount'] = $deal_amount;
            $robot['deal_money'] = $deal_money;
            $robot['price'] = $price;
            $robot['now_price'] = $ret['last'];
            $robot['rate'] = $rate;
             unset($robot['values_str']);
        }
        
        $robot_list = array_sort($robot_list,'now_price');
        $this->success('success', $robot_list);
    }

    //创建机一个机器人
    public function create()
    {
        $userId = $this->getUserId();
        $platforms = implode(',', Db::name("third_platform")->column('platform'));
        $validate = new Validate([
            'platform'     => 'require|in:' . $platforms,
            'market_id' => 'require|gt:0',
            'first_order_value' => 'require|number|gt:0',
            'max_order_count' => 'require|integer|gt:0',
            'stop_profit_rate' => 'require|number|gt:0',
            'stop_profit_callback_rate' => 'require|number|gt:0',
            'cover_rate' => 'require',
            'cover_callback_rate' => 'require|number|gt:0',
            'recycle_status' => 'require|integer|in:0,1',
            'c_type' => 'require|integer|in:1,2,3',
            'number'=>'require|integer|egt:0'
        ]);

        $validate->message([
            'platform.require'  => '平台不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        
        $cover_ratenum = array_column($data['cover_rate'],'input');
        
        if(empty($cover_ratenum)){
            $this->error('补仓跌幅不能为空');
        }
        
        $data['cover_rate'] = json_encode(array_column($data['cover_rate'],'input','count'));
        if(empty($data['cover_rate'])){
            $this->error('补仓跌幅不能为空');
        }
        
        // print_r($data);exit;
        //check api
        $platform = $data['platform'];
        $where = array();
        $where['platform'] = $platform;
        $where['uid'] = $userId;
        $where['status'] = 1;
        $findApi = Db::name("third_api")->where($where)->count();
        if ($findApi < 1) {
            $this->error("当前无可用的API，请先绑定:" . $platform);
        }
        //check market_id
        $market_id = $data['market_id'];
        $where = array();
        $where['platform'] = $platform;
        $where['id'] = $market_id;
        $where['status'] = 1;
        $findMarket = Db::name("spot_market")->where($where)->count();
        if ($findMarket < 1) {
            $this->error("指定的市场ID不可用:" . $market_id);
        }
        $where = array();
        $where['uid'] = $userId;
        $where['market_id'] = $market_id;
        $findRobot =  Db::name("quant_robot")->where($where)->count();
        if ($findRobot > 0) {
            $this->error("你已经创建该平台交易区的机器人了:" . $market_id);
        }
        //check cd key
        $app_conf = cmf_get_option('app_config');
        $cdkey_price = floatval($app_conf['cdkey_price']);
        $need_cdkey = 0;
        //检测用户是不是VIP套餐有效期内
        $vip_deadline =  Db::name("user")->where('id', $userId)->value('vip_deadline');
        if ($vip_deadline < time()) { //不是VIP 检测激活码需不需要
            // /if ($cdkey_price > 0) { //cdkey 启用了
            
                // $this->error("您的VIP套餐已到期，请续费");
                // $need_cdkey = 1;
                // if (!isset($data['cd_key']) || empty($data['cd_key'])) {
                //     $this->error("请输入激活码创建机器人");
                // }
                // $cd_key = $data['cd_key'];
                // $where = array();
                // $where['keys'] = $cd_key;
                // $where['uid'] = $userId;
                // $where['used'] = 0;
                // $findKey = Db::name("cdkey_logs")->where($where)->find();
                // if (empty($findKey)) {
                //     $this->error("cdkey不存在或者已经失效:" . $cd_key);
                // }
            // }
        }

        Db::startTrans();
        //创建机器人
        $data['uid'] = $userId;
        $data['status'] = 0;
        $data['type'] = 1; //1 spot
        $field = 'uid,status,type,cd_key,platform,market_id,first_order_value,max_order_count,stop_profit_rate, stop_profit_callback_rate, cover_rate, cover_callback_rate, recycle_status,c_type,number';
        $ret = Db::name("quant_robot")->field($field)->insertGetId($data);
        if ($ret) {
            //标记cd_key
            if ($need_cdkey) {
                $ret2 = Db::name("cdkey_logs")->where('id', $findKey['id'])->update(array('used' => 1, 'qrobot_id' => $ret));
            } else {
                $ret2 = 1;
            }
            if ($ret2) {
                Db::commit();
                $this->success('创建成功');
            } else {
                Db::rollback();
                $this->error('创建失败，请稍后再试#2');
            }
        } else {
            Db::rollback();
            $this->error('创建失败，请稍后再试#1');
        }
    }

    //创建机一个机器人
    public function edit()
    {
        $userId = $this->getUserId();
        $validate = new Validate([
            'robot_id' => 'require|integer|gt:0',
            'first_order_value' => 'number|gt:0',
            'max_order_count' => 'integer|gt:0',
            'stop_profit_rate' => 'number|gt:0',
            'stop_profit_callback_rate' => 'number|gt:0',
            'cover_rate' => 'require',
            'cover_callback_rate' => 'number|gt:0',
            'recycle_status' => 'integer|in:0,1',
            'c_type' => 'require|integer|in:1,2,3',
            'number'=>'require|integer|egt:0'
        ]);
        $validate->message([
            'robot_id.require'  => 'robot_id不能为空!',
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        
        $cover_ratenum = array_column($data['cover_rate'],'input');
        
        if(empty($cover_ratenum)){
            $this->error('补仓跌幅不能为空');
        }
        
        $data['cover_rate'] = json_encode(array_column($data['cover_rate'],'input','count'));
        //check robot
        $where = array();
        $where['uid'] = $userId;
        $where['id'] = $data['robot_id'];
        $findRobot = Db::name("quant_robot")->where($where)->find();
        if (empty($findRobot)) {
            $this->error("你无权访问该机器人");
        }
        $field = 'first_order_value,max_order_count,stop_profit_rate, stop_profit_callback_rate, cover_rate, cover_callback_rate, recycle_status,c_type,number';
        $ret = Db::name("quant_robot")
            ->field($field)
            ->where('id', $findRobot['id'])
            ->update($data);
        if ($ret !== false) {
            $this->success('更改成功');
        } else {
            $this->error('更改失败');
        }
    }
    //禁用机器人
    public function disable()
    {

        $userId = $this->getUserId();

        $validate = new Validate([
            'robot_id' => 'require|integer|gt:0',

        ]);

        $validate->message([
            'robot_id.require'  => 'robot_id不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        //check robot
        $where = array();
        $where['uid'] = $userId;
        $where['id'] = $data['robot_id'];
        $findRobot = Db::name("quant_robot")->where($where)->find();
        if (empty($findRobot)) {
            $this->error("你无权访问该机器人");
        }
        $update_data = array('status' => 0, 'values_str' => '', 'show_msg' => '');
        $ret = Db::name("quant_robot")->where('id', $findRobot['id'])->update($update_data);
        if ($ret !== false) {
            $this->success('禁用成功');
        } else {
            $this->error('禁用失败');
        }
    }

    //清仓卖出
    public function clean()
    {

        $userId = $this->getUserId();

        $validate = new Validate([
            'robot_id' => 'require|integer|gt:0',

        ]);

        $validate->message([
            'robot_id.require'  => 'robot_id不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        //check robot
        $where = array();
        $where['uid'] = $userId;
        $where['id'] = $data['robot_id'];
        $findRobot = Db::name("quant_robot")->where($where)->find();
        if (empty($findRobot)) {
            $this->error("你无权访问该机器人");
        }
        if ($findRobot['status'] == 0) {
            $this->error("机器人未启动,无法清仓");
        }
        $update_data = array('is_clean' => 1);
        $ret = Db::name("quant_robot")->where('id', $findRobot['id'])->update($update_data);
        if ($ret !== false) {
            $this->success('清仓成功,即将执行');
        } else {
            $this->error('清仓失败');
        }
    }

    //启用机器人
    public function enable()
    {

        $userId = $this->getUserId();

        $validate = new Validate([
            'robot_id' => 'require|integer|gt:0',

        ]);

        $validate->message([
            'robot_id.require'  => 'robot_id不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        //check robot
        $where = array();
        $where['uid'] = $userId;
        $where['id'] = $data['robot_id'];
        $findRobot = Db::name("quant_robot")->where($where)->find();
        if (empty($findRobot)) {
            $this->error("你无权访问该机器人");
        }

        $app_conf = cmf_get_option("app_config");
        if (!isset($app_conf['quant_revenue_type'])) { //|| empty($app_conf['quant_revenue_type']
            $this->error("系统未设置计费方式");
        }
        if (!isset($app_conf['quant_startup_min'])) {
            $this->error("系统未设置启动门槛");
        }
        $quant_revenue_type = intval($app_conf['quant_revenue_type']);
        $amount = floatval($app_conf['quant_startup_min']);

        // if ($quant_revenue_type == 1) {
        //     //check usdt balance 
        //     $coin_symbol = 'USDT';
        //     //查询用户对应币种余额
        //     $cloud_wallet = Db::name("Wallet")->where(['uid' => $userId, 'coin_symbol' => $coin_symbol, 'type' => 1])->field('id,coin_symbol,cloud_balance,address')->find();
        //     if (empty($cloud_wallet)) {
        //         $this->error("查询不到云端钱包账户信息，请稍后重试");
        //     }
        //     $cloud_balance = $cloud_wallet['cloud_balance'];
        //     if ($amount > $cloud_balance) {
        //         $this->error("启动机器人需要满足：{$coin_symbol}钱包余额大于" . $amount);
        //     }
        // } else {
        //     $user_balance = Db::name("user")->where(['id' => $userId])->value('balance');
        //     if ($amount > $user_balance) {
        //         $this->error("启动机器人需要满足：燃料余额大于" . $amount);
        //     }
        // }
        
        $vip_deadline =  Db::name("user")->where('id', $userId)->value('vip_deadline');
        if ($vip_deadline < time()) { //不是VIP 检测激活码需不需要
        
            // $this->error("您的VIP套餐已到期，请续费");
        }
        //check api
        $platform = $findRobot['platform'];
        $where = array();
        $where['platform'] = $platform;
        $where['uid'] = $userId;
        $where['status'] = 1;
        $findApi = Db::name("third_api")->where($where)->count();
        if ($findApi < 1) {
            $this->error("当前无可用的API，请先绑定:" . $platform);
        }
        //check market_id
        $market_id = $findRobot['market_id'];
        $where = array();
        $where['platform'] = $platform;
        $where['id'] = $market_id;
        $where['status'] = 1;
        $findMarket = Db::name("spot_market")->where($where)->count();
        if ($findMarket < 1) {
            $this->error("指定的市场ID不可用:" . $market_id);
        }
        $where = array();
        $where['uid'] = $userId;
        $where['market_id'] = $market_id;
        $where['status'] = 1;
        $count =  Db::name("quant_robot")->where($where)->count();
        if ($count > 0) {
            $this->error("你已经创建该平台交易区的机器人了:" . $market_id);
        }
        $update_data = array('status' => 1);
        $ret = Db::name("quant_robot")->where('id', $findRobot['id'])->update($update_data);
        if ($ret !== false) {
            $this->success('启用成功');
        } else {
            $this->error('启用失败');
        }
    }
    //日志
    public function log()
    {
        $userId = $this->getUserId();

        $validate = new Validate([
            'robot_id' => 'integer|gt:0',
            'limit_begin'    => 'integer',
            'limit_end'     => 'integer'
        ]);

        $validate->message([
            //'robot_id.require'  => 'robot_id不能为空!',
            'limit_begin.integer'  => 'limit_begin必须为整数!',
            'limit_end.integer'  => 'limit_end必须为整数!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        if (!empty($data['limit_begin'])) {
            $limit_begin = $data['limit_begin'];
        } else {
            $limit_begin = 0;
        }

        if (!empty($data['limit_end'])) {
            $limit_end = $data['limit_end'];
        } else {
            $limit_end = 10;
        }
        //check robot
        $where = array();
        $where['uid'] = $userId;
        if (isset($data['robot_id'])) {
            $where['id'] = $data['robot_id'];
        }
        $findRobot = Db::name("quant_robot")->where($where)->find();
        if (empty($findRobot)) {
            $this->error("你无权访问该机器人");
        }

        $where = array();
        $where['uid'] = $userId;
        if (isset($data['robot_id'])) {
            $where['qrobot_id'] = $data['robot_id'];
        }
        $log_list =  Db::name("quant_robot_log")
            ->where($where)
            ->order('id desc')
            ->limit($limit_begin, $limit_end)
            ->select();

        $total_count =  Db::name("quant_robot_log")
            ->where($where)
            ->order('id desc')
            ->count();

        $return_data['data'] = $log_list;
        $return_data['total_count'] = $total_count;
        $this->success('success', $return_data);
    }
    //机器人订单
    public function order()
    {
        $userId = $this->getUserId();

        $validate = new Validate([
            'robot_id' => 'integer|gt:0',
            'limit_begin'    => 'integer',
            'limit_end'     => 'integer'
        ]);

        $validate->message([
            //'robot_id.require'  => 'robot_id不能为空!',
            'limit_begin.integer'  => 'limit_begin必须为整数!',
            'limit_end.integer'  => 'limit_end必须为整数!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        if (!empty($data['limit_begin'])) {
            $limit_begin = $data['limit_begin'];
        } else {
            $limit_begin = 0;
        }

        if (!empty($data['limit_end'])) {
            $limit_end = $data['limit_end'];
        } else {
            $limit_end = 10;
        }
        //check robot
        $where = array();
        $where['uid'] = $userId;
        if (isset($data['robot_id'])) {
            $where['id'] = $data['robot_id'];
        }
        $findRobot = Db::name("quant_robot")->where($where)->find();
        if (empty($findRobot)) {
            $this->error("你无权访问该机器人");
        }

        $where = array();
        if (isset($data['robot_id'])) {
            $where['qrobot_id'] = $data['robot_id'];
        }
        $where['uid'] = $userId;
        $order_list =  Db::name("quant_robot_order")
            ->where($where)
            ->order('id desc')
            ->limit($limit_begin, $limit_end)
            ->select();

        $total_count =  Db::name("quant_robot_order")
            ->where($where)
            ->order('id desc')
            ->count();

        $return_data['data'] = $order_list;
        $return_data['total_count'] = $total_count;
        $this->success('success', $return_data);
    }

    //机器人收益
    public function revenue()
    {
        $userId = $this->getUserId();

        $validate = new Validate([
            'robot_id' => 'integer|gt:0',
            'limit_begin'    => 'integer',
            'limit_end'     => 'integer'
        ]);

        $validate->message([
            //'robot_id.require'  => 'robot_id不能为空!',
            'limit_begin.integer'  => 'limit_begin必须为整数!',
            'limit_end.integer'  => 'limit_end必须为整数!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        if (!empty($data['limit_begin'])) {
            $limit_begin = $data['limit_begin'];
        } else {
            $limit_begin = 0;
        }

        if (!empty($data['limit_end'])) {
            $limit_end = $data['limit_end'];
        } else {
            $limit_end = 10;
        }
        //check robot
        $where = array();
        $where['uid'] = $userId;
        if (isset($data['robot_id'])) {
            $where['id'] = $data['robot_id'];
        }
        $findRobot = Db::name("quant_robot")->where($where)->find();
        if (empty($findRobot)) {
            //$this->error("你无权访问该机器人");
            $return_data['data'] = array();
            $return_data['total_count'] = 0;
            $return_data['total_revenue'] = 0;
            $return_data['today_revenue'] = 0;
            $this->success('success', $return_data);            
        }

        $where = array();
        if (isset($data['robot_id'])) {
            $where['qrobot_id'] = $data['robot_id'];
        }
        $where['uid'] = $userId;
        $revenue_list =  Db::name("quant_robot_revenue")
            ->where($where)
            ->order('id desc')
            ->limit($limit_begin, $limit_end)
            ->select();

        $total_count =  Db::name("quant_robot_revenue")
            ->where($where)
            ->order('id desc')
            ->count();

        $total_revenue =  Db::name("quant_robot_revenue")
            ->where($where)
            ->order('id desc')
            ->sum('revenue');
        $today = date("Y-m-d H:i:s", strtotime(date('Y-m-d')));

        $today_revenue =  Db::name("quant_robot_revenue")
            ->where($where)
            ->where("ctime > '{$today}'")
            ->order('id desc')
            ->sum('revenue');
        $return_data['data'] = $revenue_list;
        $return_data['total_count'] = $total_count;
        $return_data['total_revenue'] = $total_revenue;
        $return_data['today_revenue'] = $today_revenue;
        $this->success('success', $return_data);
    }
}
