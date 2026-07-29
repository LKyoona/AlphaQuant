<?php
namespace app\admin\controller;
use cmf\controller\AdminBaseController;
use think\Db;
/**
 * Class UserSeoreLogController 用户积分奖励
 * @package app\admin\controller
 */
class UserSeoreLogController extends AdminBaseController
{
    public function index(){
        $size=20;
        $data=  
        Db::name('UserScoreLog')->alias('r')
        ->join('jl_user u','r.user_id = u.id')
        ->field('r.*,u.mobile as uname')
        ->paginate($size , false, [  'query' =>request()->param()  ]    );

        $this->assign('datas', $data->items() );
        $this->assign('num', $data->total());
        $this->assign('page',$data->render() );
    
        return $this->fetch();
    }
  
}