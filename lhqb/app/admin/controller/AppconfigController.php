<?php

namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use api\common\service\ColumnName;

class AppconfigController extends AdminBaseController
{


	function index()
	{
		$this->assign("balance_name", ColumnName::$db_balance_log['balance_type']['balance']);
		$this->assign("app_config", cmf_get_option("app_config"));
		return $this->fetch();
	}

	function indexPost()
	{
		$post = $this->request->post("app_config/a");

		cmf_set_option('app_config', $post);

		$this->success("保存成功！");
	}
}
