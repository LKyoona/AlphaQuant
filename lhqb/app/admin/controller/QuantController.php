<?php

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use think\Request;

class QuantController extends AdminBaseController
{


    public function robot()
    {

        $where = [];
        $requ = request()->param();
        !empty($requ['uid']) ? $where['uid'] = $requ['uid'] : '';
        !empty($requ['platform']) ? $where['platform'] = $requ['platform'] : '';
        //!empty($requ['qrobot_id']) ? $where['qrobot_id'] = $requ['qrobot_id'] : '';

        $size = 15;
        $data =  Db::name('quant_robot')->where($where)->order('id desc')->paginate($size, false, ['query' => request()->param()]); //->where(['status' => 1])
        $this->assign('datas', $data->items());
        $this->assign('num', $data->total());
        $this->assign('page', $data->render());
        return $this->fetch();
    }
    
    public function strategy()
    {
        $defaults = [
            'max_order_count' => '',
            'stop_profit_rate' => '',
            'stop_profit_callback_rate' => '',
            'cover_rate' => '',
            'cover_callback_rate' => '',
        ];
        $strategy = Db::name('quant_strategy')->find();
        $data = array_merge($defaults, $strategy ?: []);
        
        $this->assign('data', $data);
        return $this->fetch();
    }
    
    public function addcl()
    {
        $data = $this->request->only([
            'max_order_count',
            'stop_profit_rate',
            'stop_profit_callback_rate',
            'cover_rate',
            'cover_callback_rate',
        ]);
        $strategyQuery = Db::name('quant_strategy');
        if ($strategyQuery->where('id', 1)->find()) {
            $result = $strategyQuery->where('id', 1)->update($data);
        } else {
            $data['id'] = 1;
            $result = $strategyQuery->insert($data);
        }
        if ($result === false) {
            $this->error(Db::name('quant_strategy')->getError());
        }
        $this->success("保存成功！", url("Quant/strategy"));
    }
    
    
    

    public function order()
    {
        $where = [];
        $requ = request()->param();
        !empty($requ['uid']) ? $where['uid'] = $requ['uid'] : '';
        !empty($requ['platform']) ? $where['platform'] = $requ['platform'] : '';
        !empty($requ['qrobot_id']) ? $where['qrobot_id'] = $requ['qrobot_id'] : '';

        $size = 15;
        $data =  Db::name('quant_robot_order')->where($where)->order('id desc')->paginate($size, false, ['query' => request()->param()]); //->where(['status' => 1])
        $this->assign('datas', $data->items());
        $this->assign('num', $data->total());
        $this->assign('page', $data->render());
        return $this->fetch();
    }

    public function log()
    {
        $where = [];
        $requ = request()->param();
        !empty($requ['uid']) ? $where['uid'] = $requ['uid'] : '';
        !empty($requ['platform']) ? $where['platform'] = $requ['platform'] : '';
        !empty($requ['qrobot_id']) ? $where['qrobot_id'] = $requ['qrobot_id'] : '';        

        $size = 15;
        $data =  Db::name('quant_robot_log')->where($where)->order('id desc')->paginate($size, false, ['query' => request()->param()]); //->where(['status' => 1])
        $this->assign('datas', $data->items());
        $this->assign('num', $data->total());
        $this->assign('page', $data->render());
        return $this->fetch();
    }

    public function revenue()
    {
        $where = [];
         $filterid = session('ADMIN_ID');
         $data;$total_amount;
        $requ = request()->param();
        !empty($requ['uid']) ? $where['uid'] = $requ['uid'] : '';
        !empty($requ['platform']) ? $where['platform'] = $requ['platform'] : '';
        !empty($requ['qrobot_id']) ? $where['qrobot_id'] = $requ['qrobot_id'] : '';
                
        $size = 15;
        if( $filterid >1 ){
            $where['B.parent_user_id'] = $filterid;
        $data =  Db::name('quant_robot_revenue' )->alias('A')->join('user B', 'A.uid = B.id' )->where($where)->order('A.id desc')->paginate($size, false, ['query' => request()->param()]); //->where(['status' => 1])
        $total_amount = Db::name('quant_robot_revenue')->alias('A')->join('user B', 'A.uid = B.id' )->where($where)->sum('revenue');

        }else {
        $data =  Db::name('quant_robot_revenue')->where($where)->order('id desc')->paginate($size, false, ['query' => request()->param()]); //->where(['status' => 1])
        $total_amount = Db::name('quant_robot_revenue')->where($where)->sum('revenue');
         }

        $this->assign('total_amount',$total_amount);
        $this->assign('datas', $data->items());
        $this->assign('num', $data->total());
        $this->assign('page', $data->render());
        return $this->fetch();
    }
}
