<?php


namespace api\user\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;
use api\common\service\Apibase;
use api\common\service\InviteRule;

class CdkeyController extends RestBaseController
{
    public function list()
    {
        $userId = $this->getUserId();
        $where = array();
        $where['uid'] = $userId;
        $keyList = Db::name("cdkey_logs")->where($where)->order('id desc')->select();
        if ($keyList) {
            $keyList = $keyList->toArray();
        }
        $this->success('success', $keyList);
    }

    public function buy()
    {
        $userId = $this->getUserId();


        $validate = new Validate([
            'password'     => 'require',
        ]);

        $validate->message([
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

        $app_conf = cmf_get_option('app_config');
        $amount = floatval($app_conf['cdkey_price']);

        $coin_symbol = 'USDT';
        //查询用户对应币种余额
        $cloud_wallet = Db::name("Wallet")->where(['uid' => $userId, 'coin_symbol' => $coin_symbol, 'type' => 1])->field('id,coin_symbol,cloud_balance,address')->find();
        if (empty($cloud_wallet)) {
            $this->error("查询不到云端钱包账户信息，请稍后重试");
        }
        $cloud_balance = $cloud_wallet['cloud_balance'];
        if ($amount > $cloud_balance) {
            $this->error("钱包账户{$coin_symbol}余额不足");
        }
        Db::startTrans();

        $temp = [
            'cloud_balance' => Db::raw("cloud_balance-" . $amount)
        ];
        $set = Db::name("Wallet")->where(['id' => $cloud_wallet['id']])->update($temp);

        if ($set > 0) {

            $transfer_log = [
                'uid' => $userId,
                'type' => 3,  //3 购买激活码
                'wallet_type' => 1,
                'related_id' => 0,
                'wallet_id' => $cloud_wallet['id'],
                'coin_symbol' => $coin_symbol,
                'from_address' => $cloud_wallet['address'],
                'to_address' => 'cdkey',
                'amount' => 0 - $amount,
                'amount_before' => $cloud_balance,
                'amount_after' => $cloud_balance - $amount,
                'fee' => 0,
                'log_time' => time(),
                'tx_id' => '',
                'memo' => '购买激活码',
                'transfer_status' => 1,
            ];

            $log_id = intval(Db::name("TransferLog")->insertGetId($transfer_log));

            if ($log_id < 1) {
                Db::rollback();
                $this->error("资金账户记录写入失败,请稍后重试");
            }

            $find_cdkey = Db::name("cdkey")->where(array('status' => 1, 'selled' => 0))->find();
            if (!$find_cdkey) {
                Db::rollback();
                $this->error("系统当前无可售激活码,请稍后再来吧");
            }
            $update_info = array('selled' => 1, 'sell_time' => time(), 'uid' => $userId, 'username' => $this->user['mobile'] ? $this->user['mobile'] : $this->user['user_email'], 'price' => $amount, 'coin_symbol' => $coin_symbol);
            $update = Db::name("cdkey")->where('id', $find_cdkey['id'])->update($update_info);
            if (!$update) {
                Db::rollback();
                $this->error("购买激活码失败,请稍后重试#1");
            }
            $insert_info = array('keys' =>  $find_cdkey['keys'], 'ctime' => time(), 'uid' => $userId, 'used' => 0, 'qrobot_id' => 0);
            $ret = Db::name("cdkey_logs")->insert($insert_info);
            if ($ret) {
                Db::commit();
                InviteRule::buyCdkey($userId, $coin_symbol, $amount);
                $this->success("购买激活码成功");
            } else {
                Db::rollback();
                $this->error("购买激活码失败,请稍后重试#2");
            }
        } else {
            Db::rollback();
            $this->error("余额更新失败,请稍后重试");
        }
    }

    public function input()
    {
        $userId = $this->getUserId();

        $validate = new Validate([
            'keys'     => 'require',
        ]);

        $validate->message([
            'keys.require'  => '激活码必须填写',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $keys = $data['keys'];

        Db::startTrans();

        $amount = 0;
        $coin_symbol = 'USDT';

        $find_cdkey = Db::name("cdkey")->where(array('status' => 1, 'selled' => 0, 'keys' => $keys))->find();
        if (!$find_cdkey) {
            Db::rollback();
            $this->error("激活码不存在或者已经被领取");
        }
        $update_info = array('selled' => 1, 'sell_time' => time(), 'uid' => $userId, 'username' => $this->user['mobile'] ? $this->user['mobile'] : $this->user['user_email'], 'price' => $amount, 'coin_symbol' => $coin_symbol);
        $update = Db::name("cdkey")->where('id', $find_cdkey['id'])->update($update_info);
        if (!$update) {
            Db::rollback();
            $this->error("领取激活码失败,请稍后重试#1");
        }
        $insert_info = array('keys' =>  $find_cdkey['keys'], 'ctime' => time(), 'uid' => $userId, 'used' => 0, 'qrobot_id' => 0);
        $ret = Db::name("cdkey_logs")->insert($insert_info);
        if ($ret) {
            Db::commit();
            $this->success("领取激活码成功");
        } else {
            Db::rollback();
            $this->error("领取激活码失败,请稍后重试#2");
        }
    }
}
