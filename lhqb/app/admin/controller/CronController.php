<?php
namespace app\admin\controller;
use cmf\controller\AdminBaseController;
use think\Db;
use think\Request;
/**
 * Class CronController 任务日志
 * @package app\admin\controller
 */
class CronController extends AdminBaseController
{

    public function index(){
        $status=["0"=> "未处理", "1"=>"处理成功",  "-1"=> "处理失败"];
        $size=30;
        $data=  Db::name('Cron')->alias('c')
        ->join('user u',"c.uid = u.id",'left')
        ->order('id desc')//schedule_time
        ->field('u.mobile as uname,c.*')
        ->paginate($size , false, [  'query' =>request()->param()  ]    );
        $this->assign('datas', $data->items() );
        $this->assign('num', $data->total());
        $this->assign('page',$data->render() );
        $this->assign('status',$status );
        return $this->fetch();
    }
    function add(){
        // $bizhong = Db::name('Cron')->group('coin_symbol')->column('coin_symbol');
        // $this->assign('coin', $bizhong );
        $user_data = Db::name("user")->where(['user_status'=>['neq',0]])->field('id,mobile as uname')->select();
        $this->assign('user_data', $user_data );
        return $this->fetch();
    } 
    function addpot(){
        $count_1= count($_POST['params_name']);
        $count_2= count($_POST['params']);
        $count_pa= ( $count_1 > $count_2)?$count_1:$count_2;
        $arr=[];
        for( $i=0;$i<$count_pa ;$i++){
            if( empty($_POST['params_name'][$i]) ){
                continue;
            }else{
               $arr[$_POST['params_name'][$i]] = $_POST['params'][$i];
            }
        }
        $arr=  json_encode( array_unique($arr) );
        $int_data['params']= $arr;
        $int_data['task_name']= $_POST['task_name'];
        $int_data['uid']= $_POST['uid'];
        $int_data['schedule_time']= strtotime($_POST['schedule_time'] );
        $result    = Db::name('Cron')->insert($int_data);
        if ($result ) {
            $this->success("保存成功！", url("Cron/index"));
        }else{
            $this->error('保存失败！');
        }
    }

    function edit(){

        $id        = $this->request->param('id', 0, 'intval');
        $result    = Db::name('Cron')->where('id',$id)->find();
        $user_data = Db::name("user")->where(['user_status'=>['neq',0]])->field('id,mobile as uname')->select();
        $renwu    = json_decode($result['params'],true);
        $this->assign('user_data', $user_data );
        $this->assign('data', $result );
        $this->assign('renwu', $renwu );
        return $this->fetch();
    } 

    function editpot(){
        $count_1= count($_POST['params_name']);
        $count_2= count($_POST['params']);
        $count_pa= ( $count_1 > $count_2)?$count_1:$count_2;
        $arr=[];
        for( $i=0;$i<$count_pa ;$i++){
            if( empty($_POST['params_name'][$i]) ){
                continue;
            }else{
                $arr[$_POST['params_name'][$i]] = $_POST['params'][$i];
            }
        }
        $arr=  json_encode( array_unique($arr) );
        $int_data['params']= $arr;
        $int_data['task_name']= $_POST['task_name'];
        $int_data['uid']= $_POST['uid'];
        $int_data['schedule_time']= strtotime($_POST['schedule_time'] );
        $result    = Db::name('Cron')->where('id',$_POST['this_id'])->update($int_data);
        if ($result ) {
            $this->success("保存成功！", url("Cron/index"));
        }else{
            $this->error('保存失败！');
        }
    }




    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');
        $result    = Db::name('Cron')->where('id',$id)->delete();
        if ($result === false) {
            $this->error('删除失败，请重试！',url("Cron/index"));
        }else{
            $this->success('删除成功！',url("Cron/index"));
        }
    }




    
}