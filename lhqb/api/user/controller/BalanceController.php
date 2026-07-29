<?php

namespace api\user\controller;

use cmf\controller\RestUserBaseController;
use think\Db;
use think\Validate;
use api\common\service\ColumnName;
use api\common\service\Apibase;

class BalanceController extends RestUserBaseController
{
    // 燃料明细
    public function log()
    {
        $userId = $this->getUserId();

        $validate = new Validate([
            'limit_begin'    => 'integer',
            'limit_end'     => 'integer'
        ]);

        $validate->message([
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

        $where = array();
        $where['balance_type'] = 'balance';
        $where['user_id'] = $userId;
        $list =  Db::name("balance_log")
            ->where($where)
            ->order('id desc')
            ->limit($limit_begin, $limit_end)
            ->select();
        if ($list) {
            $list = $list->toArray();
            foreach ($list as &$item) {
                $item['detial_type_name'] = isset(ColumnName::$db_balance_log['detial_type'][$item['detial_type']]) ? ColumnName::$db_balance_log['detial_type'][$item['detial_type']] : "其他类型";
            }
        }
        $total_count =  Db::name("balance_log")
            ->where($where)
            ->order('id desc')
            ->count();
        $return_data['data'] = $list;
        $return_data['total_count'] = $total_count;
        $this->success('success', $return_data);
    }


    //转账
    public function transfer()
    {

        $userId = $this->getUserId();

        $validate = new Validate([
            'to_uid'    => 'integer|require',
            'amount'     => 'float|require|gt:0',
            'password'     => 'require',            
        ]);

        $validate->message([
            'to_uid.require'  => 'to_uid必须!',
            'amount.require'  => 'amount必须!',
            'password.require'  => '支付密码必须填写',            
        ]);


        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $check_pay = Apibase::check_pay_password($data['password'], $userId);
        if ($check_pay[0] == 0) {
            $this->error($check_pay[1]);
        }
                
        $to_uid = intval($data['to_uid']);
        $amount = floatval($data['amount']);
        $count = Db::name("user")
            ->where('id', $to_uid)
            ->count();
        if ($count == 0) {
            $this->error('用户不存在');
        }
        if ($userId == $to_uid) {
            $this->error('您不能转账给自己');
        }
        $current_balance =  Db::name("user")
            ->where('id', $userId)
            ->value('balance');
        if ($current_balance < $amount) {
            $this->error('可用余额不足，当前余额:' . $current_balance);
        }
        //扣除
        Apibase::updateBalance(10, $userId, 'balance', -$amount, $to_uid, 'transfer', "转账给用户UID:" . $to_uid);
        //增加
        Apibase::updateBalance(1, $to_uid, 'balance', $amount, $userId, 'transfer', "来自用户UID:" . $userId . "的转账");

        $this->success('转账成功');
    }
}
