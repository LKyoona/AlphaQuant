<?php

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\ExchangeModel;

/**
 * Class WalletController 钱包
 * @package app\admin\controller
 */
class WalletController extends AdminBaseController
{
    public function index()
    {
        $size = 20;
        $where = [];
        $requ = request()->param();
        !empty($requ['uid']) ? $where['uid'] = $requ['uid'] : '';
        !empty($requ['coin_symbol']) ? $where['coin_symbol'] = $requ['coin_symbol'] : '';
        $where['coin_symbol'] = 'USDT';
        $type = [
            "1" => "云端钱包",
            "2" => "HD钱包",
        ];

        $data =
            Db::name('Wallet')->alias('r')
            ->join('jl_user u', 'r.uid = u.id')
            ->field('r.*,u.mobile as uname')
            ->where($where)
            ->order('r.id desc')
            ->paginate($size, false, ['query' => request()->param()]);

        $total_balance =
            Db::name('Wallet')->alias('r')
            ->join('jl_user u', 'r.uid = u.id')
            ->field('r.*,u.mobile as uname')
            ->where($where)
            ->sum('cloud_balance');

        $bizhong =  Db::name('Wallet')->group('coin_symbol')->column('coin_symbol');  //有效币种

        $this->assign('total_balance', $total_balance);
        $this->assign('type', $type);
        $this->assign('bizhong', $bizhong);
        $this->assign('datas', $data->items());
        $this->assign('num', $data->total());
        $this->assign('page', $data->render());

        return $this->fetch();
    }

    public function add()
    {

        $id = $this->request->param('id');
        if (empty($id)) {
            $this->error("参数有误");
        }


        $where['r.id'] = $id;
        $data =
            Db::name('Wallet')->alias('r')
            ->join('jl_user u', 'r.uid = u.id')
            ->field('r.*,u.mobile as uname')
            ->where($where)
            ->find();
        //var_dump($data);die();
        $this->assign('data', $data);
        return $this->fetch();
    }

    public function add_post()
    {

        $id = $this->request->param('id');
        $type = $this->request->param('type');
        $num = $this->request->param('num');

        if (empty($id)) {
            $this->error("参数有误");
        }

        if (empty($type)) {
            $this->error("参数有误");
        }

        if (($num < 0) || ($num == 0)) {
            $this->error("请输入正确的数值");
        }

        $where['id'] = $id;
        $Wallet_info =
            Db::name('Wallet')
            ->field('*')
            ->where($where)
            ->find();

        if (empty($Wallet_info)) {
            $this->error("参数有误");
        }

        if ($type == 'add') {
            Db::startTrans();
            $result =   Db::name('Wallet')
                ->where($where)
                ->setInc('cloud_balance', $num);
            if (!$result) {
                Db::rollback();
                $this->error('操作失败(#1)！');
            }
            $balance =  $Wallet_info['cloud_balance'];
            //获取新余额
            $new_balance = Db::name('wallet')
                ->where($where)
                ->value('cloud_balance');
            //写入交易日志
            $insert_data['uid']  = $Wallet_info['uid'];
            $insert_data['type'] =  21; //系统增加
            $insert_data['wallet_type'] =  1;
            $insert_data['wallet_id'] =  $Wallet_info['id'];
            $insert_data['coin_symbol'] =  $Wallet_info['coin_symbol'];
            $insert_data['from_address'] = "system";
            $insert_data['to_address'] =  $Wallet_info['address'];
            $insert_data['amount'] =  $num;
            $insert_data['amount_before'] =   $balance;
            $insert_data['amount_after'] =   $new_balance;
            $insert_data['fee'] =  0;
            $insert_data['log_time'] =  time();
            $insert_data['memo'] = "系统增加";
            $insert_data['blockhash'] =  "";
            $insert_data['tx_id'] = "";
            $insert_data['transfer_status'] = 1;
            $ret = Db::name('transfer_log')->insert($insert_data);
            if (!$ret) {
                Db::rollback();
                $this->error('操作失败(#2)！');
            }
            Db::commit();
        } else {
            $where['id'] = $id;
            Db::startTrans();
            $result =   Db::name('Wallet')
                ->where($where)
                ->setDec('cloud_balance', $num);
            if (!$result) {
                Db::rollback();
                $this->error('操作失败(#1)！');
            }
            $balance =  $Wallet_info['cloud_balance'];
            //获取新余额
            $new_balance = Db::name('wallet')
                ->where($where)
                ->value('cloud_balance');
            //写入交易日志
            $insert_data['uid']  = $Wallet_info['uid'];
            $insert_data['type'] =  22; //系统扣除
            $insert_data['wallet_type'] =  1;
            $insert_data['wallet_id'] =  $Wallet_info['id'];
            $insert_data['coin_symbol'] =  $Wallet_info['coin_symbol'];
            $insert_data['from_address'] =  $Wallet_info['address'];
            $insert_data['to_address'] = "system";
            $insert_data['amount'] =  $num;
            $insert_data['amount_before'] =   $balance;
            $insert_data['amount_after'] =   $new_balance;
            $insert_data['fee'] =  0;
            $insert_data['log_time'] =  time();
            $insert_data['memo'] = "系统扣除";
            $insert_data['blockhash'] =  "";
            $insert_data['tx_id'] = "";
            $insert_data['transfer_status'] = 1;
            $ret = Db::name('transfer_log')->insert($insert_data);
            if (!$ret) {
                Db::rollback();
                $this->error('操作失败(#2)！');
            }
            Db::commit();
        }
        $this->success("操作成功");
    }
}
