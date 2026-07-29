<?php

namespace api\quant\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;
use api\common\service\RedisPackage;

// header("Access-Control-Allow-Origin: *");
// header('Access-Control-Allow-Headers: Content-Type');

class FutureController extends RestBaseController
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
        $robot_list = Db::name("quant_robot_future")
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
            //unset($robot['values_str']);
            if (!empty($robot['values_str'])) {
                $values_str = json_decode($robot['values_str'], true);

                $price = $values_str['base_price'];

                $rate = number_format($robot['revenue'] / $robot['first_order_value'], 4);
                // var_dump($rate);
            } else {
                $price = 0;
                $rate = '-';
            }

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
        $robot_list = Db::name("quant_robot_future")
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

            $param = strtoupper($robot['platform']) . strtoupper($robot['market_name']) . 'USDTICKER';

            $ret = $redis::get($param);

            $ret = json_decode($ret, true);

            if (!empty($robot['values_str']) && isset($ret['last'])) {

                $values_str = json_decode($robot['values_str'], true);

                $price = $values_str['base_price'];

                $now_price = $ret['last'];

                $deal_amount = $values_str['deal_amount'];

                $deal_money = $values_str['deal_money'];



                $rate = number_format(($now_price - $price) / $price * 100, 4);
            } else {
                $price = '-';
                $rate = '-';
                $deal_amount = '-';
                $deal_money = '-';
                $now_price = $ret['last'] ?: 0;
            }
            $robot['deal_amount'] = $deal_amount;
            $robot['deal_money'] = $deal_money;
            $robot['price'] = $price;
            $robot['now_price'] = $ret['last'];
            $robot['rate'] = $rate;
            unset($robot['values_str']);
        }

        $robot_list = array_sort($robot_list, 'now_price');
        $this->success('success', $robot_list);
    }



    public function create()
    {
        $userId = $this->getUserId();


        $platforms = implode(',', Db::name("third_platform")->column('platform'));

        $validate = new Validate([
            'platform'     => 'require|in:' . $platforms,
            'market_id' => 'require|gt:0',
            'pair' => 'require',
            'order_beishu' => 'require|float',
            'deviation_of_orders' => 'require|float',
            'deviation_of_orders_scale' => 'require|float',
            'first_order_value' => 'require|number|gt:0',
            'max_order_count' => 'require|integer|gt:0',
            'leverage_type' => 'require|integer|in:5,10,20',
            'strategy' => 'require|integer|in:1,2'
        ]);

        $validate->message([
            'platform.require'  => '平台不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $platform = $data['platform'];
        $where = array();
        $where['platform'] = $platform;
        $where['uid'] = $userId;
        $where['status'] = 1;
        $findApi = Db::name("future_api")->where($where)->count();
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
        $findRobot =  Db::name("quant_robot_future")->where($where)->count();
        if ($findRobot > 0) {
            $this->error("你已经创建该平台交易区的机器人了:" . $market_id);
        }
        Db::startTrans();
        //创建机器人
        $data['uid'] = $userId;
        $data['status'] = 0; //0启动，1运行
        $data['type'] = 0; //合约
        $field = 'uid,status,type,platform,market_id,pair,first_order_value,max_order_count,strategy,leverage_type,deviation_of_orders,deviation_of_orders_scale,order_beishu';
        $ret = Db::name("quant_robot_future")->field($field)->insertGetId($data);
        if ($ret) {
            Db::commit();
            // $msg='合约机器人创建'.$userId.'---'.$data['pair'];
            // $ret_msg = $this->SendMsg($msg);
            // if ($ret_msg == 0) {
            //     $this->success('创建成功');
            // } else {
            //     $this->success('创建失败');
            // }
            $this->success('创建成功');
        } else {
            Db::rollback();
            $this->error('创建失败，请稍后再试#2');
        }
    }

    function SendMsg($msg)
    {
        $url = 'https://sctapi.ftqq.com/SCT43118T8c2mgK0ttGkcp6MkEMwJdzrJ.send';
        $data = ["title" => "开通合约", "desp" => $msg];
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded",
                'method' => 'POST',
                'content' => http_build_query($data),
            ],
        ];
        $context = stream_context_create($options);
        $result = json_decode(file_get_contents($url, false, $context), true);
        if ($result['code'] == 0) {
            return 0;
        }
        return 1;
    }
    //update一个机器人
    public function edit()
    {
        $userId = $this->getUserId();
        $validate = new Validate([
            'market_id' => 'require|gt:0',
            'pair' => 'require|float',
            'order_beishu' => 'require|float',
            'deviation_of_orders' => 'require|float',
            'deviation_of_orders_scale' => 'require|float',
            'first_order_value' => 'require|number|gt:0',
            'max_order_count' => 'require|integer|gt:0',
            'leverage_type' => 'require|integer|in:5,10,20',
            'strategy' => 'require|integer|in:1,2',
            'rebuild' => 'require|integer|in:1,0',
        ]);
        $data = $this->request->param();
        if ($validate->check($data)) {
            $this->error($validate->getError());
        }
        $where = array();
        $where['uid'] = $userId;
        $where['id'] = $data['robot_id'];

        $findRobot = Db::name("quant_robot_future")->where($where)->find();
        if (empty($findRobot)) {
            $this->error("你无权访问该机器人");
        }
        $field = 'uid,status,type,platform,market_id,pair,first_order_value,max_order_count,number,strategy,leverage_type,deviation_of_orders,deviation_of_orders_scale,order_beishu,rebuild';
        $ret = Db::name("quant_robot_future")
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
        $findRobot = Db::name("quant_robot_future")->where($where)->find();
        if (empty($findRobot)) {
            $this->error("你无权访问该机器人");
        }
        $update_data = array('status' => 0);
        $ret = Db::name("quant_robot_future")->where('id', $findRobot['id'])->update($update_data);
        if ($ret !== false) {
            // $msg='合约机器人禁用'.$userId.'---'.$data['robot_id'];
            // $ret_msg = $this->SendMsg($msg);
            // if ($ret_msg == 0) {
            //     $this->success('禁用成功');
            // } else {
            //     $this->success('禁用失败');
            // }
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
        $findRobot = Db::name("quant_robot_future")->where($where)->find();
        if (empty($findRobot)) {
            $this->error("你无权访问该机器人");
        }
        if ($findRobot['status'] == 0) {
            $this->error("机器人未启动,无法清仓");
        }
        $update_data = array('is_clean' => 1);
        $ret = Db::name("quant_robot_future")->where('id', $findRobot['id'])->update($update_data);
        if ($ret !== false) {
            // $msg='合约机器人清仓'.$userId.'---'.$data['robot_id'];
            // $ret_msg = $this->SendMsg($msg);
            // if ($ret_msg == 0) {
            //     $this->success('清仓成功');
            // } else {
            //     $this->success('清仓失败');
            // }
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
        $findRobot = Db::name("quant_robot_future")->where($where)->find();
        if (empty($findRobot)) {
            $this->error("你无权访问该机器人");
        }

        // $app_conf = cmf_get_option("app_config");
        // if (!isset($app_conf['quant_revenue_type'])) { //|| empty($app_conf['quant_revenue_type']
        //     $this->error("系统未设置计费方式");
        // }
        // if (!isset($app_conf['quant_startup_min'])) {
        //     $this->error("系统未设置启动门槛");
        // }
        // $quant_revenue_type = intval($app_conf['quant_revenue_type']);
        // $amount = floatval($app_conf['quant_startup_min']);



        // $vip_deadline =  Db::name("user")->where('id', $userId)->value('vip_deadline');
        // if ($vip_deadline < time()) { //不是VIP 检测激活码需不需要

        //     // $this->error("您的VIP套餐已到期，请续费");
        // }
        //check api
        $platform = $findRobot['platform'];
        $where = array();
        $where['platform'] = $platform;
        $where['uid'] = $userId;
        $where['status'] = 1;
        $findApi = Db::name("future_api")->where($where)->count();
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
        $count =  Db::name("quant_robot_future")->where($where)->count();
        if ($count > 0) {
            $this->error("你已经创建该平台交易区的机器人了:" . $market_id);
        }
        $update_data = array('status' => 1);
        $ret = Db::name("quant_robot_future")->where('id', $findRobot['id'])->update($update_data);
        if ($ret !== false) {
            // $msg='合约机器人启动'.$userId.'---'.$data['robot_id'];
            // $ret_msg = $this->SendMsg($msg);
            // if ($ret_msg == 0) {
            //     $this->success('启用成功');
            // } else {
            //     $this->success('启用失败');
            // }
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
        $findRobot = Db::name("quant_robot_future")->where($where)->find();
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
        $findRobot = Db::name("quant_robot_future")->where($where)->find();
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
        $findRobot = Db::name("quant_robot_future")->where($where)->find();
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

