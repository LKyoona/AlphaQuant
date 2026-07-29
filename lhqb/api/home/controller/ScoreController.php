<?php
namespace api\home\controller;

use cmf\controller\RestUserBaseController;
use think\Db;
use think\Validate;
use api\common\service\Apibase;
use api\common\service\ColumnName;

class ScoreController extends RestUserBaseController
{

    public function info(){

        $userId  = $this->getUserId();

        $self_score_info = Db::name("user")->where(['id'=>$userId])->field('score')->find();

        $time_today = strtotime(date("Y-m-d",time()));

        $log_data = Db::name("BalanceLog")->where(['user_id'=>$userId,'balance_type'=>'score','type'=>1])->where("ctime > $time_today")->field('change')->select()->toArray();

        $today_amount = 0;

        foreach ($log_data as $key => $value) {
             $today_amount += $value['change'];
         }

        $data['score'] =  $self_score_info['score'];
        $data['today_amount'] =  $today_amount;

        $this->success('请求成功!', $data);
    }


    public function log(){

        $limit_begin     = $this->request->param('limit_begin', 0, 'intval');
        
        $limit_num     = $this->request->param('limit_num', 20, 'intval');

        $userId  = $this->getUserId();

        $self_score_info = Db::name("user")->where(['id'=>$userId])->field('score')->find();

        $time_today = strtotime(date("Y-m-d",time()));

        $log_data = Db::name("BalanceLog")->where(['user_id'=>$userId,'balance_type'=>'score'])->field('type,change,detial_type,ctime')->order('id desc')->limit($limit_begin.','.$limit_num)->select()->toArray();

        foreach ($log_data as $key => &$value) {
             $value['change'] = abs($value['change']);
             $value['ctime'] = date('Y-m-d H:i:s',$value['ctime']);
             $value['type'] =  ColumnName::$db_balance_log['type'][$value['type']];
             if(isset(ColumnName::$db_balance_log['detial_type'][$value['detial_type']])){
                $value['detial_type'] =  ColumnName::$db_balance_log['detial_type'][$value['detial_type']];
             }else{
                $value['detial_type'] = "其他类型";
             }

         }


        $this->success('请求成功!', $log_data);
    }


}