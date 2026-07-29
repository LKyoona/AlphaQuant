<?php
namespace app\admin\controller;
use cmf\controller\AdminBaseController;
use think\Db;
/**
 * Class ShopController 商品管理
 * @package app\admin\controller
 */
class ShopController extends AdminBaseController
{

    public function index(){
      $server= $_SERVER['HTTP_HOST'];
      $data=  Db::name('user_shop')->order('sort desc')->select()->toArray();
      $this->assign('server', $server );
      $this->assign('datas', $data );
      return $this->fetch();
    }

    function uptag(){
      if($_POST){
          $id= $this->request->param('id');
          $tag= $this->request->param('tag');
          $type= $this->request->param('type');

          $isdel= Db::name('user_shop')->where(['id'=>$id])->update([$tag=>$type]);
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
                $status = Db::name('user_shop')->where(['id'=>$k])->update([
                    'sort'=>$v
                ]);
            }
            $this->success("排序更新成功！");
        }
    }

  public function add(){
    if($_POST){
        $data      = $this->request->param();

        $result    = Db::name('user_shop')->insert($data);
        if ($result === false) {
            $this->error(Db::name('user_shop')->getError());
        }

        $this->success("添加成功！", url("Shop/index"));
    }
    return $this->fetch();
}
public function edits()
{
    $server= $_SERVER['HTTP_HOST'];
    $id    = $this->request->param('id', 0, 'intval');
    $good_data =  Db::name('user_shop')->where('id',$id)->find();
    $this->assign('server', $server );
    $this->assign('good_data', $good_data);
    return $this->fetch();
}
public function editpost()
{
    $data      = $this->request->param();

    $result    = Db::name('user_shop')->update($data);
    if ($result === false) {
        $this->error(Db::name('user_shop')->getError());
    }
    $this->success("保存成功！", url("Shop/index"));
}

public function del()
{
    $id = $this->request->param('id', 0, 'intval');
     Db::name('user_shop')->where("id = $id")->delete();
    $this->success("删除成功！", url("Shop/index"));
}

}