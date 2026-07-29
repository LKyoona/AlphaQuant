<?php

namespace api\home\controller;

use think\Db;
use think\Validate;
use cmf\controller\RestBaseController;

class IndexController extends RestBaseController
{
    // api 首页
    public function index()
    {
        $this->success("恭喜您,API访问成功!", [
            'version' => '1.1.0',
            'doc'     => ''
        ]);
    }

}
