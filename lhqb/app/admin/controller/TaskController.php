<?php
namespace app\admin\controller;
use cmf\controller\AdminBaseController;
use think\Db;
/**
 * Class TaskController 任务管理
 * @package app\admin\controller
 */
class TaskController extends AdminBaseController
{

    public function index(){
      $server= $_SERVER['HTTP_HOST'];
      $data=  Db::name('user_task')->order('sort desc')->select()->toArray();
      $this->assign('server', $server );
      $this->assign('datas', $data );
      return $this->fetch();
    }

    function uptag(){
      if($_POST){
          $id= $this->request->param('id');
          $tag= $this->request->param('tag');
          $type= $this->request->param('type');

          $isdel= Db::name('user_task')->where(['id'=>$id])->update([$tag=>$type]);
          if ($isdel!==false) {
                  echo "修改状态成功！";
              } else {
                  echo "修改状态失败！";
              }
      }
  }

    public function listorderss() {
        if($_POST){
            foreach ($_POST['sort'] as $k =>$v){
                $status = Db::name('user_task')->where(['id'=>$k])->update([
                    'sort'=>$v
                ]);
            }
            $this->success("排序更新成功！");
        }
    }

  public function add(){
    if($_POST){
        $data      = $this->request->param();
        if(  $data['task_icon']!=''){
          $data['task_icon']= $data['task_icon'];
        }
        $result    = Db::name('user_task')->insert($data);
        if ($result === false) {
            $this->error(Db::name('user_task')->getError());
        }

        $this->success("添加成功！", url("Task/index"));
    }
    return $this->fetch();
}
public function edits()
{
    $server= $_SERVER['HTTP_HOST'];

    $id    = $this->request->param('id', 0, 'intval');
    $task_data =  Db::name('user_task')->where('id',$id)->find();
    $this->assign('server', $server );
    $this->assign('task_data', $task_data);
    return $this->fetch();
}
public function editpost()
{
    $data      = $this->request->param();
    if($data['task_icon']!=''){
      $data['task_icon']= $data['task_icon'];
    }
    $result    = Db::name('user_task')->update($data);
    if ($result === false) {
        $this->error(Db::name('user_task')->getError());
    }
    $this->success("保存成功！", url("Task/index"));
}

public function del()
{
    $id = $this->request->param('id', 0, 'intval');
     Db::name('user_task')->where("id = $id")->delete();
    $this->success("删除成功！", url("Task/index"));
}

}