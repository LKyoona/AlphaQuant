<?php


namespace api\user\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;
use api\common\service\Apibase;
use api\common\service\InviteRule;

class PackageController extends RestBaseController
{
    public function list()
    {
        //$userId = $this->getUserId();
        $where = array();
        $where['status'] = 1;
        $list = Db::name("vip_package")->where($where)->order('period asc')->select();
        if ($list) {
            $list = $list->toArray();
        }
        $this->success('success', $list);
    }

    public function buy()
    {
        $userId = $this->getUserId();


        $validate = new Validate([
            'package_id'     => 'require',
            'password'     => 'require',
        ]);

        $validate->message([
            'package_id.require'  => '套餐ID必须填写',
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
        //查询套餐
        $package_id = $data['package_id'];
        $package_info = Db::name("vip_package")->where(['id' => $package_id, 'status' => 1])->field('*')->find();
        if (empty($package_info)) {
            $this->error('套餐不存在或者已经失效');
        }
        $coin_symbol = $package_info['coin_symbol'];
        $amount = $package_info['price'];
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
                'type' => 9,  //9 购买套餐
                'wallet_type' => 1,
                'related_id' => $package_id,
                'wallet_id' => $cloud_wallet['id'],
                'coin_symbol' => $coin_symbol,
                'from_address' => $cloud_wallet['address'],
                'to_address' => 'package',
                'amount' => 0 - $amount,
                'amount_before' => $cloud_balance,
                'amount_after' => $cloud_balance - $amount,
                'fee' => 0,
                'log_time' => time(),
                'tx_id' => '',
                'memo' => '购买套餐',
                'transfer_status' => 1,
            ];
            $log_id = intval(Db::name("TransferLog")->insertGetId($transfer_log));
            if ($log_id < 1) {
                Db::rollback();
                $this->error("资金账户记录写入失败,请稍后重试");
            }

            $period = intval($package_info['period']) * 24 * 3600;
            $old_deadline =  Db::name("user")->where('id', $userId)->value('vip_deadline');
            if ($old_deadline < time()) {
                $vip_deadline = time() + $period;
            } else {
                $vip_deadline = $old_deadline + $period;
            }
            $update_info = array('vip_deadline' => $vip_deadline);
            $update = Db::name("user")->where('id', $userId)->update($update_info);
            if (!$update) {
                Db::rollback();
                $this->error("购买套餐失败,请稍后重试");
            }
            $datalog = [
                
                'uid' =>$userId,
                'pid' =>$package_id,
                
            ];
            $plog_id = intval(Db::name("package_log")->insertGetId($datalog));
            if ($plog_id < 1) {
                Db::rollback();
                $this->error("购买套餐记录写入失败,请稍后重试");
            }
            Db::commit();
            // InviteRule::buyPackage($userId, $coin_symbol, $amount);
            InviteRule::buyCdkey($userId, $coin_symbol, $amount);
            $this->success("购买套餐成功");
        } else {
            Db::rollback();
            $this->error("余额更新失败,请稍后重试");
        }
    }
}
