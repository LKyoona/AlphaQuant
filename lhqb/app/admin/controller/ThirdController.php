<?php

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use think\Request;

class ThirdController extends AdminBaseController
{

    public function market()
    {
        $size = 15;
        $data =  Db::name('spot_market')->where(['status' => 1])->order('sort desc,id desc')->paginate($size, false, ['query' => request()->param()]);
        $this->assign('datas', $data->items());
        $this->assign('num', $data->total());
        $this->assign('page', $data->render());
        return $this->fetch();
    }


    public function api()
    {
        $size = 15;
        $data =  Db::name('third_api')->where(['status' => 1])->whereOr(['status' => -1])->order('id desc')->paginate($size, false, ['query' => request()->param()]);
        $this->assign('datas', $data->items());
        $this->assign('num', $data->total());
        $this->assign('page', $data->render());
        return $this->fetch();
    }

    function addMarket()
    {

        $bizhong = Db::name('coin')->group('coin_symbol')->column('coin_symbol');
        $platforms =  Db::name("third_platform")->column('platform');
        $this->assign('platforms', $platforms);        
        $this->assign('coin', $bizhong);
        return $this->fetch();
    }

    function addpost()
    {
        $data      = $this->request->param();
        $data['update_time'] = time();
        $result    = Db::name('spot_market')->field('platform,market,market_name,stock,money,update_time')->insert($data);
        if ($result === false) {
            $this->error(Db::name('spot_market')->getError());
        }
        $this->success("添加成功！", url("Third/market"));
    }

    public function editMarket()
    {
        $id        = $this->request->param('id', 0, 'intval');
        $data =  Db::name('spot_market')->where('id', $id)->find();
        $bizhong = Db::name('coin')->group('coin_symbol')->column('coin_symbol');

        $platforms =  Db::name("third_platform")->column('platform');
        $this->assign('platforms', $platforms);
        $this->assign('coin', $bizhong);
        $this->assign('data', $data);
        return $this->fetch();
    }
    public function editpost()
    {
        $data      = $this->request->param();
        $data['update_time'] = time();
        $result    = Db::name('spot_market')->field('platform,market,market_name,stock,money,update_time')->update($data);
        if ($result === false) {
            $this->error(Db::name('spot_market')->getError());
        }
        $this->success("保存成功！", url("Third/market"));
    }


    public function delMarket()
    {
        $id = $this->request->param('id', 0, 'intval');

        $result    = Db::name('spot_market')->where('id', $id)->update([
            'status' => 0
        ]);
        if ($result === false) {
            $this->error('删除失败，请重试！', url("Third/market"));
        } else {
            $this->success('删除成功！', url("Third/market"));
        }
    }



    public function listorderss()
    {
        if ($_POST) {
            foreach ($_POST['sort'] as $k => $v) {
                $status = Db::name('spot_market')->where(['id' => $k])->update([
                    'sort' => $v
                ]);
            }
            $this->success("排序更新成功！");
        }
    }

    function uptag()
    {
        if ($_POST) {
            $id = $this->request->param('id');
            $tag = $this->request->param('tag');
            $type = $this->request->param('type');
            $isdel = Db::name('spot_market')->where(['id' => $id])->update([$tag => $type]);
            if ($isdel !== false) {
                echo "修改成功！";
            } else {
                echo "修改失败！";
            }
        }
    }
}
