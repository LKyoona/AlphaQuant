<?php
namespace api\home\controller;

use cmf\controller\RestUserBaseController;
use think\Db;
use think\Validate;
use api\common\service\Apibase;
use api\common\service\ColumnName;

class TaskController extends RestUserBaseController
{


    public function list(){

        $userId  = $this->getUserId();

        Apibase::addActionLog($userId,"",'daily_sign');


        $task_list_data = Db::name('user_task')->where("status = 1")
            ->field("id as task_id,task_name,task_icon,task_description,task_identifier,task_type,rewards")->order('sort desc')->select()->toArray();

        if (!empty($task_list_data)){
            foreach ($task_list_data as &$x){
                $x['task_icon'] = cmf_get_image_preview_url($x['task_icon']);
                $x['task_status'] = $this->check_task_status($userId,$x['task_identifier'],$x['task_type']) ;
            }
        }

        $response = ['count'=>count($task_list_data),'list' => $task_list_data];


        $this->success('请求成功!', $response);
    }

    public function finish(){

        $validate = new Validate([
            'task_id'     => 'require',
        ]);

        $validate->message([
            'task_id.require'  => '任务ID不能为空!',
        ]);

        $data = $this->request->param();

        $userId  = $this->getUserId();

        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $task_id = $data['task_id'];

        $task_data =  Db::name('user_task')->where("status = 1")->where("id = $task_id")
            ->field("task_identifier,task_type,rewards")->find();

        $task_status = $this->check_task_status($userId,$task_data['task_identifier'],$task_data['task_type']);

        if($task_status == 2){
            Apibase::updateBalance(1,$userId,'score',(int)$task_data['rewards'],$userId,$task_data['task_identifier'],json_encode($task_data));
            $this->success('任务奖励领取成功!');
        }else{
            $this->error('任务完成失败!');
        }
    }

    //检测任务是否完成
    private function check_task_status($uid,$task_identifier,$task_type){

        if($task_type=='single'){
            $find_task_count= Db::name("BalanceLog")->where(['user_id'=>$uid,'balance_type'=>'score','detial_type'=>$task_identifier])->count();
            if($find_task_count > 0){
                return 1;
            }else{
                $find_action_count= Db::name("user_action_log")->where(['user_id'=>$uid,'action'=>$task_identifier])->count();
                if($find_action_count>0){
                    return 2;
                }else{
                    return 0;
                }
            }
        }

        if($task_type == "daily"){
            $today = strtotime(date("Y-m-d"),time());
            $find_task_count= Db::name("BalanceLog")->where(['user_id'=>$uid,'balance_type'=>'score','detial_type'=>$task_identifier])->where("ctime  > $today ")->count();            
            if($find_task_count > 0){
                return 1;
            }else{
                $find_action_count= Db::name("user_action_log")->where(['user_id'=>$uid,'action'=>$task_identifier])->where("last_visit_time > $today ")->count();
                if($find_action_count>0){
                    return 2;
                }else{
                    return 0;
                }
            }

        }

        return 0;

    }

    public function log(){

        $limit_begin     = $this->request->param('limit_begin', 0, 'intval');
        
        $limit_num     = $this->request->param('limit_num', 20, 'intval');

        $userId  = $this->getUserId();

        $detial_types = [];

        $task_list_data = Db::name('user_task')->field("task_identifier")->select()->toArray();

        foreach ($task_list_data as $key => $value) {
            $detial_types[] = $value['task_identifier'];
        }

        $where = [
            'detial_type' => ['in',$detial_types],
        ];

        $self_score_info = Db::name("user")->where(['id'=>$userId])->field('score')->find();

        $time_today = strtotime(date("Y-m-d",time()));

        $log_data = Db::name("BalanceLog")->where(['user_id'=>$userId,'balance_type'=>'score'])->where($where)->field('type,change,detial_type,ctime')->order('id desc')->limit($limit_begin.','.$limit_num)->select()->toArray();

        foreach ($log_data as $key => &$value) {
             $value['change'] = abs($value['change']);
             $value['ctime'] = date('Y-m-d H:i:s',$value['ctime']);
             $value['type'] =  ColumnName::$db_balance_log['type'][$value['type']];
             $value['detial_type'] =  ColumnName::$db_balance_log['detial_type'][$value['detial_type']];

         }


        $this->success('请求成功!', $log_data);
    }
}