<?php
namespace app\admin\controller;

use api\wallet\service\ColumnName;
use cmf\controller\AdminBaseController;
use think\Db;
use think\Validate;

class HelpController extends AdminBaseController
{
    public function index(){
        $data=  Db::name('Help')->where(['status'=>['gt',0]])->order('id desc')->paginate(15);
        // 获取分页显示
        $page = $data->render();
        $this->assign('status', ['已删除','未处理','已处理'] );

        $this->assign('datas', $data->toArray()['data'] );
        $this->assign("page", $page);
        return $this->fetch();
    }
    public function status(){
        $id= $this->request->param('id');
        $status= $this->request->param('status');
        $is_del=  Db::name('Help')->where(['id' => $id])->update(['status'=>$status,'update_time'=>time()]);
        if( $is_del ){
            $this->success('更新成功！');
        }else{
            $this->error('更新失败，请重试！');
        }
    }
}