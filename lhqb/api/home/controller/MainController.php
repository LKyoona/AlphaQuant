<?php

namespace api\home\controller;

use api\common\service\ColumnName;
use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;

class MainController extends RestBaseController
{
    /**
     * 获取初始化信息
     */
    public function init()
    {
        $app_conf = cmf_get_option("app_config");
        if (!is_array($app_conf)) {
            $app_conf = [];
        }

        $defaults = [
            'quant_revenue_type' => 1,
            'quant_startup_min' => 0,
            'cdkey_price' => 0,
            'quant_usdt_to_balance_rate' => 1,
            'system_customer_service' => '',
            'system_user_agreement' => '',
            'system_privacy_policy' => '',
            'system_help_center' => '',
            'google_auth_help' => '',
            'share_activity_rule' => '',
            'official_telegram_group_chat' => '',
            'official_whatsapp_group_chat' => '',
        ];
        $app_conf = array_merge($defaults, $app_conf);

        // $currency_rate = cmf_get_option("currency_rate");
        // if (empty($currency_rate)){
        $currency_rate = Db::name("Rate")->select();
        $currency_rate = $currency_rate ? $currency_rate->toArray() : [];
        //cmf_set_option('currency_rate', $currency_rate);
        // }
        $data = array(
            'system_score_name' => ColumnName::$system_info['score']['name'],
            'system_score_symbol' => ColumnName::$system_info['score']['symbol'],
            'system_balance_name' => ColumnName::$db_balance_log['balance_type']['balance'],
            'quant_revenue_type' => $app_conf['quant_revenue_type'],
            'quant_revenue_type_type' => $app_conf['quant_revenue_type'] == 1 ? "USDT" : ColumnName::$db_balance_log['balance_type']['balance'],
            'quant_startup_min' => $app_conf['quant_startup_min'],
            'cdkey_price' => $app_conf['cdkey_price'],
            'quant_usdt_to_balance_rate' => $app_conf['quant_usdt_to_balance_rate'],
            'system_customer_service' => $app_conf['system_customer_service'],
            'system_user_agreement' => $app_conf['system_user_agreement'],
            'system_privacy_policy' => $app_conf['system_privacy_policy'],
            'system_help_center' => $app_conf['system_help_center'],
            'google_auth_help' => $app_conf['google_auth_help'],
            'share_activity_rule' => $app_conf['share_activity_rule'],
            'currency_rate' => $currency_rate,
            'group_chat' => [
                [
                    'name' => 'Telegram: ',
                    'addr' => $app_conf['official_telegram_group_chat'],
                ], [
                    'name' => 'Whatsapp: ',
                    'addr' => $app_conf['official_whatsapp_group_chat'],
                ]
            ]
        );

        $this->success('请求成功!', $data);
    }

    /**
     * 检查更新
     */
    public function checkUpdate()
    {

        $validate = new Validate([
            'platform'     => 'require',
            'version'     => 'require',
        ]);

        $validate->message([
            'platform.require'  => '平台不能为空!',
            'version.require'  => '版本号不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $platform = $data['platform'];
        $version = $data['version'];

        $app_config = cmf_get_option("app_config");

        if ($data['platform'] == 'iphone') {
            if (isset($app_config['ios_version']) && isset($app_config['ios_download_url'])) {
                if (version_compare($version, $app_config['ios_version'], '<')) {
                    $ret =  array();
                    $ret['download_url'] = $app_config['ios_download_url'];
                    $ret['update_nodes'] = $app_config['ios_version_description'];
                    $ret['version'] = $app_config['ios_version'];
                    $this->success('请求成功!', $ret);
                } else {
                    $this->error('已经是最新版本!');
                }
            } else {
                $this->error('已经是最新版本!');
            }
        }

        if ($data['platform'] == 'android') {
            if (isset($app_config['android_version']) && isset($app_config['android_download_url'])) {
                if (version_compare($version, $app_config['android_version'], '<')) {
                    $ret =  array();
                    $ret['download_url'] = $app_config['android_download_url'];
                    $ret['update_nodes'] = $app_config['android_version_description'];
                    $ret['version'] = $app_config['android_version'];
                    $this->success('请求成功!', $ret);
                } else {
                    $this->error('已经是最新版本!');
                }
            } else {
                $this->error('已经是最新版本!');
            }
        }
    }

    /**
     * 获取Banner
     */
    public function banner()
    {
        $slide = 'home';
        $slide_obj = Db::name("SlideItem");
        $data = $slide_obj->alias('a')->join('slide b', 'a.slide_id = b.id', 'LEFT')->where(['a.status' => 1, 'b.status' => 1, 'b.delete_time' => 0, 'b.name' => $slide])
            ->field('a.title,a.image,a.url')->order('list_order desc')->select();
        if ($data->isEmpty()) {
            $count = 0;
        } else {
            $data = $data->toArray();
            $count = count($data);
            foreach ($data as &$x) {
                $x['image'] = cmf_get_image_url($x['image']);
            }
        }
        $response = ['count' => $count, 'list' => $data];

        $this->success('请求成功!', $response);
    }


    /*
     * 联系客服提交反馈
     */
    public function help()
    {
        $validate = new Validate([
            'email'     => 'require',
            'mobile'     => 'require',
            'title'     => 'require',
            'content'     => 'require',
            //'image'     => 'require',
        ]);

        $validate->message([
            'email.require'  => '邮箱地址不能为空!',
            'mobile.require'  => '手机号码不能为空!',
            'title.require'  => '标题不能为空!',
            'content.require'  => '描述不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        if (Validate::is($data['email'], 'email')) {
        } else {
            $this->error("请输入正确的邮箱格式!");
        }
        if (cmf_check_mobile($data['mobile'])) {
        } else {
            $this->error("请输入正确的手机格式!");
        }
        $data['ctime'] = time();
        Db::name("Help")->insert($data);
        $this->success("提交成功");
    }

    /*
     * 用户登录后请求上传推送sdk：registration_id、os
     */
    public function updateDevice()
    {
        $userId = $this->getUserId();
        $validate = new Validate([
            'registration_id'     => 'require',
            'os'     => 'require',
        ]);

        $validate->message([
            'registration_id.require'  => '设备编码不能为空!',
            'os.require'  => '系统类型不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $registration_id = $data['registration_id'];
        $os = strtolower($data['os']);
        if (!in_array($os, ['ios', 'android'])) {
            $this->error("系统类型只支持ios/android");
        }
        $find = Db::name("UserDeviceRegistration")->where(['user_id' => $userId])->find();
        if ($find) {
            $up = Db::name("UserDeviceRegistration")->where(['user_id' => $userId])->update([
                'registration_id' => $registration_id,
                'os' => $os
            ]);
        } else {
            $up = Db::name("UserDeviceRegistration")->insert([
                'user_id' => $userId,
                'registration_id' => $registration_id,
                'os' => $os
            ]);
        }
        if ($up) {
            $this->success("更新成功");
        } else {
            $this->error("更新失败");
        }
    }

    /*
     * 用户更新推送模式 1 正常推送 2夜间不推送 3从不推送
     */
    public function setNotify()
    {
        $userId = $this->getUserId();
        $validate = new Validate([
            'notify_type'     => 'require|in:1,2,3',
        ]);

        $validate->message([
            'notify_type.require'  => 'notify_type不能为空!',
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $notify_type = $data['notify_type'];

        $up = Db::name("UserDeviceRegistration")->where(['user_id' => $userId])->update([
            'notify_type' => $notify_type,
        ]);

        if ($up) {
            $this->success("操作成功");
        } else {
            $this->error("操作失败");
        }
    }
}
