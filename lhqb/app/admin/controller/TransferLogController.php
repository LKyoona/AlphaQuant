<?php

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\ExchangeModel;
use api\common\service\ColumnName;

/**
 * Class TransferLogController 转账记录
 * @package app\admin\controller
 */
class TransferLogController extends AdminBaseController
{
    public function index()
    {
        $size = 20;
        $where = [];

        $where2 = '';
        $where3 = '';

        $requ = request()->param();
        $where['wallet_type'] = 1;
        !empty($requ['uid']) ? $where['uid'] = $requ['uid'] : '';
        !empty($requ['type']) ? $where['type'] = $requ['type'] : '';
        !empty($requ['wallet_type']) ? $where['wallet_type'] = $requ['wallet_type'] : 1;

        !empty($requ['coin_symbol']) ? $where['r.coin_symbol'] = $requ['coin_symbol'] : '';

        if (!empty($requ['start_time'])) {
            $where2 = "r.log_time >= " . strtotime($requ['start_time']);
        }

        if (!empty($requ['end_time'])) {
            $where3 = "r.log_time < " . strtotime($requ['end_time']);
        }

        !empty($requ['transfer_status']) ? $where['transfer_status'] = $requ['transfer_status'] : '';
        if (isset($requ['transfer_status'])) {
            if ($requ['transfer_status'] === "0") {
                $where['transfer_status'] = 0;
            }
        }
        $type =  ColumnName::$db_transfer_log['type'];
        $wallet_type = [
            "1" => "云端钱包",
            "2" => "HD钱包",
        ];
        $transfer_status = [
            "-1" => "失败",
            "0"  => "等待处理",
            "1"  => "转账成功",
            "2"  => "转账中",
        ];

        if ($where['wallet_type'] == 1) {
            $data =
                Db::name('transfer_log')->alias('r')
                ->join('jl_user u', 'r.uid = u.id', 'LEFT')
                ->field('r.*,u.mobile as uname')
                ->where($where)
                ->where($where2)
                ->where($where3)
                ->order("id desc")
                ->paginate($size, false, ['query' => request()->param()]);
        } else {
            $data =
                Db::name('transfer_log')->alias('r')
                ->join('jl_hd_user u', 'r.uid = u.id', 'LEFT')
                ->field('r.*,u.primary_key as uname')
                ->where($where)
                ->where($where2)
                ->where($where3)
                ->order("id desc")
                ->paginate($size, false, ['query' => request()->param()]);
        }


        $total_fee = Db::name('transfer_log')->alias('r')
            ->join('jl_user u', 'r.uid = u.id', 'LEFT')
            ->field('r.*,u.mobile as uname')
            ->where($where)
            ->where($where2)
            ->where($where3)
            ->sum('r.fee');

        $total_amount = Db::name('transfer_log')->alias('r')
            ->join('jl_user u', 'r.uid = u.id', 'LEFT')
            ->field('r.*,u.mobile as uname')
            ->where($where)
            ->where($where2)
            ->where($where3)
            ->sum('r.amount');

        $type_sql =  Db::name('transfer_log')->group('type')->column('type');  //转账分类
        $wallet_type_sql =  Db::name('transfer_log')->group('wallet_type')->column('wallet_type');        //钱包类型
        $transfer_status_sql =  Db::name('transfer_log')->group('transfer_status')->column('transfer_status');  //状态

        $this->assign('total_fee', $total_fee);
        $this->assign('total_amount', $total_amount);
        $this->assign('type_sql', $type_sql);
        $this->assign('wallet_type_sql', $wallet_type_sql);
        $this->assign('transfer_status_sql', $transfer_status_sql);
        $this->assign('type_arr', $type);
        $this->assign('wallet_type_arr', $wallet_type);
        $this->assign('transfer_status_arr', $transfer_status);
        $this->assign('datas', $data->items());
        $this->assign('num', $data->total());
        $this->assign('page', $data->render());

        return $this->fetch();
    }
    
    public function score()
    {
        $size = 20;
        $where = [];

        $where2 = '';
        $where3 = '';

        $requ = request()->param();
        $where['balance_type'] = 'balance';
        !empty($requ['uid']) ? $where['user_id'] = $requ['uid'] : '';
        !empty($requ['type']) ? $where['type'] = $requ['type'] : '';
        
        if (!empty($requ['start_time'])) {
            $where2 = "r.ctime >= " . strtotime($requ['start_time']);
        }

        if (!empty($requ['end_time'])) {
            $where3 = "r.ctime < " . strtotime($requ['end_time']);
        }

        $data =Db::name('balance_log')->alias('r')
        ->join('jl_user u', 'r.user_id = u.id', 'LEFT')
        ->field('r.*,u.mobile as uname')
        ->where($where)
        ->where($where2)
        ->where($where3)
        ->order("id desc")
        ->paginate($size, false, ['query' => request()->param()]);
        
        
         $total_amount = Db::name('balance_log')->alias('r')
            ->join('jl_user u', 'r.user_id = u.id', 'LEFT')
            ->field('r.*,u.mobile as uname')
            ->where($where)
            ->where($where2)
            ->where($where3)
            ->sum('r.amount');
            
        $this->assign('total_amount', $total_amount);
        $this->assign('datas', $data->items());
        $this->assign('num', $data->total());
        $this->assign('page', $data->render());

        return $this->fetch();
    }
    
    
    public function game()
    {
        $size = 20;
        $where = [];

        $where2 = '';
        $where3 = '';

        $requ = request()->param();
        !empty($requ['uid']) ? $where['user_id'] = $requ['uid'] : '';
        !empty($requ['win']) ? $where['win'] = $requ['win'] : '';
        
        if (!empty($requ['start_time'])) {
            $where2 = "r.ctime >= " . strtotime($requ['start_time']);
        }

        if (!empty($requ['end_time'])) {
            $where3 = "r.ctime < " . strtotime($requ['end_time']);
        }

        $data =Db::name('game_log')->alias('r')
        ->join('jl_user u', 'r.uid = u.id', 'LEFT')
        ->field('r.*,u.mobile as uname')
        ->where($where)
        ->where($where2)
        ->where($where3)
        ->order("id desc")
        ->paginate($size, false, ['query' => request()->param()]);
        
        
         $total_amount = Db::name('game_log')->alias('r')
            ->join('jl_user u', 'r.uid = u.id', 'LEFT')
            ->field('r.*,u.mobile as uname')
            ->where($where)
            ->where($where2)
            ->where($where3)
            ->sum('r.amount');
            
        $this->assign('total_amount', $total_amount);
        $this->assign('datas', $data->items());
        $this->assign('num', $data->total());
        $this->assign('page', $data->render());

        return $this->fetch();
    }
}
