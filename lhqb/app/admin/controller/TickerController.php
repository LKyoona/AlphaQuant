<?php
namespace app\admin\controller;
use cmf\controller\AdminBaseController;
use think\Db;
use think\Request;
use app\admin\model\TickerModel;

class TickerController extends AdminBaseController
{

    public function index(){
        $size=15;
        $data=  Db::name('Ticker')->where(['status'=>1])->order('sort desc,id desc')->paginate($size , false, [  'query' =>request()->param()  ]    );
        $this->assign('datas', $data->items() );
        $this->assign('num', $data->total());
        $this->assign('page',$data->render() );
        return $this->fetch();
    }

    function add(){

        $bizhong = Db::name('coin')->group('coin_symbol')->column('coin_symbol');
        $this->assign('coin', $bizhong );
        return $this->fetch();
    } 
    function addpot(){
        $data      = $this->request->param();
        $data['update_time'] = time();
        $TickerModel = new TickerModel();
        $result    = $TickerModel->allowField(true)->save($data);
        if ($result === false) {
            $this->error($TickerModel->getError());
        }
        $this->success("添加成功！", url("Ticker/index"));
    }

    public function edit()
    {
        $id        = $this->request->param('id', 0, 'intval');
        $TickerModel = TickerModel::get($id);
        $bizhong = Db::name('coin')->group('coin_symbol')->column('coin_symbol');
        $this->assign('coin', $bizhong );
        $this->assign('data', $TickerModel);
        return $this->fetch();
    }
    public function editpost()
    {
        $data      = $this->request->param();
        $data['update_time'] = time();
        $TickerModel = new TickerModel();
        $result    = $TickerModel->allowField(true)->isUpdate(true)->save($data);
        if ($result === false) {
            $this->error($TickerModel->getError());
        }
        $this->success("保存成功！", url("Ticker/index"));
    }

    function uptag(){
        if($_POST){
            $id= $this->request->param('id');
            $tag= $this->request->param('tag');
            $type= $this->request->param('type');
            $isdel= Db::name('Ticker')->where(['id'=>$id])->update([$tag=>$type]);
            if ($isdel!==false) {
                    echo "修改标签成功！";
                } else {
                    echo "修改标签失败！";
                }
        }
    } 
    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');
      
        $result    = Db::name('Ticker')->where('id',$id)->update([
            'status'=>0
        ]);
        if ($result === false) {
            $this->error('删除失败，请重试！',url("Ticker/index"));
        }else{
            $this->success('删除成功！',url("Ticker/index"));
        }
    }



    public function listorderss() {
        if($_POST){
            foreach ($_POST['sort'] as $k =>$v){
                $status = Db::name('Ticker')->where(['id'=>$k])->update([
                    'sort'=>$v
                ]);
            }
            $this->success("排序更新成功！");
        }
    }




    
}