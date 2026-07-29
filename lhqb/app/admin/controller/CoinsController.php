<?php
namespace app\admin\controller;
use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\CoinModel;
/**
 * Class CoinsController 币种管理
 * @package app\admin\controller
 */
class CoinsController extends AdminBaseController
{

    public function index(){
      $server= $_SERVER['HTTP_HOST'];
      $data=  Db::name('coin')->order('sort desc')->select()->toArray();
      $this->assign('server', $server );
      $this->assign('datas', $data );
      return $this->fetch();
    }

    function uptag(){
      if($_POST){
          $id= $this->request->param('id');
          $tag= $this->request->param('tag');
          $type= $this->request->param('type');

          $isdel= Db::name('coin')->where(['id'=>$id])->update([$tag=>$type]);
          if ($isdel!==false) {
                  echo "修改标签成功！";
              } else {
                  echo "修改标签失败！";
              }
      }
  } 

    public function listorderss() {
        if($_POST){
            foreach ($_POST['sort'] as $k =>$v){
                $status = Db::name('coin')->where(['id'=>$k])->update([
                    'sort'=>$v
                ]);
            }
            $this->success("排序更新成功！");
        }
    }

  public function add(){
    if($_POST){
        $data      = $this->request->param();
      if(  $data['img_url']!=''){
        $data['img_url']= $data['img_url'];
      }
        // print_r($data);die;
        $CoinModel = new CoinModel();
        $result    = $CoinModel->allowField(true)->save($data);
        if ($result === false) {
            $this->error($CoinModel->getError());
        }

        $this->success("添加成功！", url("Coins/index"));
    }
    return $this->fetch();
}
public function edits()
{
    $server= $_SERVER['HTTP_HOST'];
    $id    = $this->request->param('id', 0, 'intval');
    $CoinModel = CoinModel::where('id',$id)->find();
    $this->assign('server', $server );
    $this->assign('coin_data', $CoinModel);
    return $this->fetch();
}
public function editpost()
{
    $data      = $this->request->param();
    if(  $data['img_urls']!=''){
        $data['img_url']= $data['img_urls'];
    }
    $CoinModel = new CoinModel();
    $result    = $CoinModel->allowField(true)->isUpdate(true)->save($data);
    if ($result === false) {
        $this->error($CoinModel->getError());
    }
    $this->success("保存成功！", url("Coins/index"));
}

public function del()
{
    $id = $this->request->param('id', 0, 'intval');
    CoinModel::destroy($id);
    $this->success("删除成功！", url("Coins/index"));
}
  
}