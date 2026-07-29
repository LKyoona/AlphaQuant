<?php

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use api\common\service\Pool;

/**
 * Class Pool管理
 * @package app\admin\controller
 */
class PoolController extends AdminBaseController
{

    public function index()
    {
        $size = 20;
        $where = [];
        $requ = request()->param();
        !empty($requ['user_id']) ? $where['uid'] = $requ['user_id'] : '';
        !empty($requ['type']) ? $where['pool_type'] = $requ['type'] : '';

        $type = [
            "3" => "月度分红池",
            "4" => "年度分红池",
        ];

        $data =
            Db::name('pool_log')->alias('r')
            ->join('user u', 'r.uid = u.id')
            ->field('r.*,u.mobile as uname')
            ->where($where)
            ->order('r.id desc')
            ->paginate($size, false, ['query' => request()->param()]);

        $type_sql =  Db::name('pool_log')->group('pool_type')->column('pool_type');  //pool分类

        $pool_1_balance = Pool::getBalance(3);
        $pool_2_balance = Pool::getBalance(4);

        $this->assign('pool_1_balance', $pool_1_balance);
        $this->assign('pool_2_balance', $pool_2_balance);

        $this->assign('type_sql', $type_sql);
        $this->assign('type', $type);
        $this->assign('datas', $data->items());
        $this->assign('num', $data->total());
        $this->assign('page', $data->render());

        return $this->fetch();
    }

    public function users()
    {


        $app_conf = cmf_get_option("app_config");

        $total_num =  intval($app_conf['partner_num']);

        $size = 20;
        $where = [];
        $requ = request()->param();
        !empty($requ['user_id']) ? $where['id'] = $requ['user_id'] : '';

        $where['is_partner'] = 1;

        $data =  Db::name('user')
            ->field('id,mobile')
            ->where($where)
            ->paginate($size, false, ['query' => request()->param()]);


        $pool_1_balance = Pool::getBalance(3);
        $pool_2_balance = Pool::getBalance(4);

        $this->assign('pool_1_balance', $pool_1_balance);
        $this->assign('pool_2_balance', $pool_2_balance);
        $this->assign('total', $total_num);
        $this->assign('datas', $data->items());
        $this->assign('num', $data->total());
        $this->assign('page', $data->render());

        return $this->fetch();
    }

    //月度分红
    public function pool1_edit()
    {

        $pool_balance = Pool::getBalance(3);

        $this->assign('pool_balance', $pool_balance);

        return $this->fetch();
    }


    //年度分红
    public function pool2_edit()
    {

        $pool_balance = Pool::getBalance(4);

        $this->assign('pool_balance', $pool_balance);

        return $this->fetch();
    }



    //月度分红
    public function revenue_post1()
    {
        $num = $this->request->param('num', 0, 'floatval');

        $app_conf = cmf_get_option("app_config");

        if (!empty($num)) {
            $pool_1_balance = Pool::getBalance(3);
            if ($num > $pool_1_balance) {
                $this->error('分红额度超过分红池剩余额度');
            }
            $where = array();
            $where['is_partner'] = 1;

            $data =  Db::name('user')
                ->where($where)
                ->field('id')
                ->select();

            $total_num =  Db::name('user')
                ->where($where)
                ->count();

            if ($total_num <= 0) {
                $this->error('没有可分红的人');
            }
            //开始分红
            Db::startTrans();
            $revenue = $num  / $total_num;

            foreach ($data as $item) {
                $uid = $item['id'];
                $coin_symbol = 'USDT';
                $related_id = 0;
                $flag = "revenue";
                $memo = "月度分红";
                Pool::change($uid, $related_id, -$revenue, $flag, $memo, 3);

                //查询用户对应币种余额
                $cloud_wallet = Db::name("Wallet")->where(['uid' => $uid, 'coin_symbol' => $coin_symbol, 'type' => 1])->field('id,coin_symbol,cloud_balance,address')->find();
                if (empty($cloud_wallet)) {
                    Db::rollback();
                    $this->error("查询不到{$uid}云端钱包账户信息，请稍后重试");
                }

                $temp = [
                    'cloud_balance' => Db::raw("cloud_balance+" . $revenue)
                ];
                $set = Db::name("Wallet")->where(['id' => $cloud_wallet['id']])->update($temp);

                if ($set <= 0) {
                    Db::rollback();
                    $this->error("用户{$uid}云端钱包余额变更失败，请稍后重试");
                }

                $cloud_balance = $cloud_wallet['cloud_balance'];

                $transfer_log = [
                    'uid' => $uid,
                    'type' => 8,
                    'wallet_type' => 1,
                    'related_id' => 0,
                    'wallet_id' => $cloud_wallet['id'],
                    'coin_symbol' => $coin_symbol,
                    'from_address' => 'partner_revenue',
                    'to_address' => $cloud_wallet['address'],
                    'amount' => $revenue,
                    'amount_before' => $cloud_balance,
                    'amount_after' => $cloud_balance + $revenue,
                    'fee' => 0,
                    'log_time' => time(),
                    'tx_id' => '',
                    'memo' => '月度分红',
                    'transfer_status' => 1,
                ];

                $log_id = intval(Db::name("TransferLog")->insertGetId($transfer_log));

                if (empty($log_id)) {
                    Db::rollback();
                    $this->error("用户{$uid}钱包日志写入失败，请稍后重试");
                }
            }

            Db::commit();
            $this->success("分红成功！", url("pool/index"));
        } else {
            $this->error('数据传入失败！');
        }
    }


    //年度分红
    public function revenue_post2()
    {
        $num = $this->request->param('num', 0, 'floatval');

        $app_conf = cmf_get_option("app_config");

        if (!empty($num)) {
            $pool_1_balance = Pool::getBalance(4);
            if ($num > $pool_1_balance) {
                $this->error('分红额度超过分红池剩余额度');
            }
            $where = array();
            $where['is_partner'] = 1;

            $data =  Db::name('user')
                ->where($where)
                ->field('id')
                ->select();

            $total_num =  Db::name('user')
                ->where($where)
                ->count();

            if ($total_num <= 0) {
                $this->error('没有可分红的人');
            }
            //开始分红
            Db::startTrans();
            $revenue = $num  / $total_num;

            foreach ($data as $item) {
                $uid = $item['id'];
                $coin_symbol = 'USDT';
                $related_id = 0;
                $flag = "revenue";
                $memo = "年度分红";
                Pool::change($uid, $related_id, -$revenue, $flag, $memo, 4);

                //查询用户对应币种余额
                $cloud_wallet = Db::name("Wallet")->where(['uid' => $uid, 'coin_symbol' => $coin_symbol, 'type' => 1])->field('id,coin_symbol,cloud_balance,address')->find();
                if (empty($cloud_wallet)) {
                    Db::rollback();
                    $this->error("查询不到{$uid}云端钱包账户信息，请稍后重试");
                }

                $temp = [
                    'cloud_balance' => Db::raw("cloud_balance+" . $revenue)
                ];
                $set = Db::name("Wallet")->where(['id' => $cloud_wallet['id']])->update($temp);

                if ($set <= 0) {
                    Db::rollback();
                    $this->error("用户{$uid}云端钱包余额变更失败，请稍后重试");
                }

                $cloud_balance = $cloud_wallet['cloud_balance'];

                $transfer_log = [
                    'uid' => $uid,
                    'type' => 8,
                    'wallet_type' => 1,
                    'related_id' => 0,
                    'wallet_id' => $cloud_wallet['id'],
                    'coin_symbol' => $coin_symbol,
                    'from_address' => 'partner_revenue',
                    'to_address' => $cloud_wallet['address'],
                    'amount' => $revenue,
                    'amount_before' => $cloud_balance,
                    'amount_after' => $cloud_balance + $revenue,
                    'fee' => 0,
                    'log_time' => time(),
                    'tx_id' => '',
                    'memo' => '年度分红',
                    'transfer_status' => 1,
                ];

                $log_id = intval(Db::name("TransferLog")->insertGetId($transfer_log));

                if (empty($log_id)) {
                    Db::rollback();
                    $this->error("用户{$uid}钱包日志写入失败，请稍后重试");
                }
            }

            Db::commit();
            $this->success("分红成功！", url("pool/index"));
        } else {
            $this->error('数据传入失败！');
        }
    }    
}
