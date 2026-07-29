<?php

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;

/**
 * @package app\admin\controller
 */
class CdkeyController extends AdminBaseController
{

    public function index()
    {
        $server = $_SERVER['HTTP_HOST'];
        $data =  Db::name('cdkey')->order('id desc')->select()->toArray();
        $this->assign('server', $server);
        $this->assign('datas', $data);
        return $this->fetch();
    }

    function uptag()
    {
        if ($_POST) {
            $id = $this->request->param('id');
            $tag = $this->request->param('tag');
            $type = $this->request->param('type');

            $isdel = Db::name('cdkey')->where(['id' => $id])->update([$tag => $type]);
            if ($isdel !== false) {
                echo "修改状态成功！";
            } else {
                echo "修改状态失败！";
            }
        }
    }
    
    public function package()
    {
        $server = $_SERVER['HTTP_HOST'];
        $data =  Db::name('vip_package')->order('id desc')->select()->toArray();
        $this->assign('server', $server);
        $this->assign('datas', $data);
        return $this->fetch();
    }
    
    function uppack()
    {
        if ($_POST) {
            $id = $this->request->param('id');
            $tag = $this->request->param('tag');
            $type = $this->request->param('type');

            $isdel = Db::name('vip_package')->where(['id' => $id])->update([$tag => $type]);
            if ($isdel !== false) {
                echo "修改状态成功！";
            } else {
                echo "修改状态失败！";
            }
        }
    }


    /**
     * 获得随机字符串
     * @param $len             需要的长度
     * @param $special        是否需要特殊符号
     * @return string       返回随机字符串
     */
    private function getRandomStr($len, $special = true)
    {
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );

        if ($special) {
            $chars = array_merge($chars, array(
                "!", "@", "#", "$", "?", "|", "{", "/", ":", ";",
                "%", "^", "&", "*", "(", ")", "-", "_", "[", "]",
                "}", "<", ">", "~", "+", "=", ",", "."
            ));
        }

        $charsLen = count($chars) - 1;
        shuffle($chars);                            //打乱数组顺序
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $charsLen)];    //随机取出一位
        }
        return $str;
    }


    public function add()
    {
        if ($_POST) {


            //$data      = $this->request->param();
            $num    = $this->request->param('num', 0, 'intval');
            if ($num < 1) {
                $this->error('数量不正确');
            }
            $memo    = $this->request->param('memo','');
            $data = array();
            for ($i = 0; $i < $num; $i++) {
                $item = array();
                $item['keys'] = $this->getRandomStr(8, false);
                $item['memo'] = $memo;
                $item['selled'] = 0;
                $item['ctime'] = time();
                $item['status'] = 1;
                $data[] = $item;
            }

            //var_dump($data);
            $result    = Db::name('cdkey')->insertAll($data);
            if ($result === false) {
                $this->error(Db::name('cdkey')->getError());
            }

            $this->success("添加成功！", url("Cdkey/index"));
        }
        return $this->fetch();
    }
    public function edits()
    {
        $server = $_SERVER['HTTP_HOST'];
        $id    = $this->request->param('id', 0, 'intval');
        $good_data =  Db::name('cdkey')->where('id', $id)->find();
        $this->assign('server', $server);
        $this->assign('good_data', $good_data);
        return $this->fetch();
    }

    public function editpost()
    {
        $data      = $this->request->param();

        $result    = Db::name('cdkey')->update($data);
        if ($result === false) {
            $this->error(Db::name('cdkey')->getError());
        }
        $this->success("保存成功！", url("Cdkey/index"));
    }

    public function del()
    {
        $id = $this->request->param('id', 0, 'intval');
        Db::name('cdkey')->where("id = $id")->delete();
        $this->success("删除成功！", url("Cdkey/index"));
    }

    public function log()
    {


        $where   = [];
        $request = $this->request->param();

        if (!empty($request['keys'])) {
            $where['keys'] = htmlspecialchars($request['keys']);
        }
        $query = Db::name('CdkeyLogs');

        $datas = $query->where($where)->order("id DESC")->paginate(20);


        // 获取分页显示
        $page = $datas->render();


        $this->assign('datas', $datas);
        $this->assign('page', $page);
        // 渲染模板输出
        return $this->fetch();
    }
    
    
    public function addpack()
    {
        if ($_POST) {


            $data      = $this->request->param();
            
            $data['coin_symbol'] = 'USDT';

            $result    = Db::name('vip_package')->insert($data);
            if ($result === false) {
                $this->error(Db::name('vip_package')->getError());
            }

            $this->success("添加成功！", url("Cdkey/package"));
        }
        return $this->fetch();
    }
    public function editpack()
    {
        $server = $_SERVER['HTTP_HOST'];
        $id    = $this->request->param('id', 0, 'intval');
        $good_data =  Db::name('vip_package')->where('id', $id)->find();
        $this->assign('server', $server);
        $this->assign('data', $good_data);
        return $this->fetch();
    }

    public function editposttpack()
    {
        $data      = $this->request->param();

        $result    = Db::name('vip_package')->update($data);
        if ($result === false) {
            $this->error(Db::name('vip_package')->getError());
        }
        $this->success("保存成功！", url("Cdkey/package"));
    }

    public function delpack()
    {
        $id = $this->request->param('id', 0, 'intval');
        Db::name('vip_package')->where("id = $id")->delete();
        $this->success("删除成功！", url("Cdkey/package"));
    }
    
     public function logpack()
    {
        
        $id = $this->request->param('id', 0, 'intval');

        $request = $this->request->param();

        $query = Db::name('PackageLog');

        $datas = $query->where("pid = $id")->order("id DESC")->paginate(20);


        // 获取分页显示
        $page = $datas->render();


        $this->assign('datas', $datas);
        $this->assign('page', $page);
        // 渲染模板输出
        return $this->fetch();
    }
    
}
