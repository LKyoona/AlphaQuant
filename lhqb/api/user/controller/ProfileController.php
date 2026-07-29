<?php

namespace api\user\controller;

use api\common\service\Apibase;
use cmf\controller\RestUserBaseController;
use think\Db;
use think\Validate;
use api\common\service\InviteRule;

class ProfileController extends RestUserBaseController
{
    // 用户密码修改
    public function changePassword()
    {
        $validate = new Validate([
            'old_password'     => 'require',
            'password'         => 'require',
            'confirm_password' => 'require|confirm:password'
        ]);

        $validate->message([
            'old_password.require'     => '请输入您的旧密码!',
            'password.require'         => '请输入您的新密码!',
            'confirm_password.require' => '请输入确认密码!',
            'confirm_password.confirm' => '两次输入的密码不一致!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $userId       = $this->getUserId();
        $userPassword = Db::name("user")->where('id', $userId)->value('user_pass');

        if (!cmf_compare_password($data['old_password'], $userPassword)) {
            $this->error('旧密码不正确!');
        }

        Db::name("user")->where('id', $userId)->update(['user_pass' => cmf_password($data['password'])]);

        $this->success("密码修改成功!");
    }

    // 用户绑定邮箱
    public function bindingEmail()
    {
        $validate = new Validate([
            'email'             => 'require|email|unique:user,user_email',
            'verification_code' => 'require'
        ]);

        $validate->message([
            'email.require'             => '请输入您的邮箱!',
            'email.email'               => '请输入正确的邮箱格式!',
            'email.unique'              => '邮箱账号已存在!',
            'verification_code.require' => '请输入数字验证码!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $userId    = $this->getUserId();
        $userEmail = Db::name("user")->where('id', $userId)->value('user_email');

        if (!empty($userEmail)) {
            $this->error("您已经绑定邮箱!");
        }

        $errMsg = cmf_check_verification_code($data['email'], $data['verification_code']);
        if (!empty($errMsg)) {
            $this->error($errMsg);
        }

        if (Db::name("user")->where('id', $userId)->update(['user_email' => $data['email']]) === false) {
            $this->error("绑定失败,请重试!");
        }
        cmf_clear_verification_code($data['email']);

        $this->success("绑定成功!");
    }

    // 用户绑定手机号
    public function bindingMobile()
    {
        $validate = new Validate([
            'mobile'            => 'require|unique:user,mobile',
            'verification_code' => 'require'
        ]);

        $validate->message([
            'mobile.require'            => '请输入您的手机号!',
            'mobile.unique'             => '手机号已经存在！',
            'verification_code.require' => '请输入数字验证码!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        if (!cmf_check_mobile($data['mobile'])) {
            $this->error("请输入正确的手机格式!");
        }


        $userId = $this->getUserId();
        $mobile = Db::name("user")->where('id', $userId)->value('mobile');

        if (!empty($mobile)) {
            $this->error("您已经绑定手机!");
        }

        $errMsg = cmf_check_verification_code($data['mobile'], $data['verification_code']);
        if (!empty($errMsg)) {
            $this->error($errMsg);
        }

        if (Db::name("user")->where('id', $userId)->update(['mobile' => $data['mobile']]) === false) {
            $this->error("绑定失败,请重试!");
        }
        cmf_clear_verification_code($data['mobile']);

        $this->success("绑定成功!");
    }

    /**
     * 用户基本信息获取及修改
     * @param 请求为GET 获取信息
     * @param [string] $[field] [要获取的一个或多个字段名] 可选
     * @return 带参数,返回某个或多个字段信息。不带参数，返回所有信息
     * @param 请求为POST 修改信息
     */
    public function userInfo($field = '')
    {
        $userId   = $this->getUserId();
        // InviteRule::checkUserLevelCanUpgrade($userId);
        //判断请求为GET，获取信息
        if ($this->request->isGet()) {
            //$fieldStr = 'user_type,user_login,mobile,user_email,user_nickname,avatar,signature,user_url,balance,sex,birthday,score,coin,user_status,user_activation_key,create_time,last_login_time,last_login_ip';
            $fieldStr = 'id,mobile,user_email,user_nickname,avatar,signature,score,balance,user_status,create_time,invitation_code,has_paypwd,auth_id,is_google_check,last_login_time,last_login_ip,level_id,is_partner,vip_deadline';
            if (empty($field)) {
                $userData = Db::name("user")->field($fieldStr)->find($userId);
            } else {
                $fieldArr     = explode(',', $fieldStr);
                $postFieldArr = explode(',', $field);
                $mixedField   = array_intersect($fieldArr, $postFieldArr);
                if (empty($mixedField)) {
                    $this->error('您查询的信息不存在！');
                }
                if (count($mixedField) > 1) {
                    $fieldStr = implode(',', $mixedField);
                    $userData = Db::name("user")->field($fieldStr)->find($userId);
                } else {
                    $userData = Db::name("user")->where('id', $userId)->value($mixedField);
                }
            }

            $site_info = cmf_get_option('site_info');
            $app_name = $site_info['site_name'];

            $userData['today_score'] = $userData['score'];
            $userData['avatar'] =  cmf_get_image_preview_url($userData['avatar']);
            $userData['invitation_url'] = Apibase::sp_get_host() . '/app/sign/register?invitation_code=' . $userData['invitation_code'];
            $userData['invitation_title'] = $app_name;
            $userData['invitation_desc'] = '您的好友邀请您注册' . $app_name;
            $this->success('获取成功！', $userData);
        }
        //判断请求为POST,修改信息
        if ($this->request->isPost()) {
            $userId   = $this->getUserId();
            $fieldStr = 'user_nickname,avatar,signature,user_url,sex,birthday';
            $data     = $this->request->post();
            if (empty($data)) {
                $this->error('修改失败，提交表单为空！');
            }

            //if (!empty($data['birthday'])) {
            // $data['birthday'] = strtotime($data['birthday']);
            // }

            $upData = Db::name("user")->where('id', $userId)->field($fieldStr)->update($data);
            if ($upData !== false) {
                $this->success('修改成功！');
            } else {
                $this->error('修改失败！');
            }
        }
    }

    // 用户V认证
    public function vAuth()
    {
        $validate = new Validate([
            'real_name' => 'require',
            'v_title' => 'require',
            'v_reason' => 'require',
        ]);

        $validate->message([
            'real_name.require' => '请输入真实姓名',
            'v_title.unique'   => '请输入认证头衔',
            'v_reason.require' => '请输入申请理由'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $userId = $this->getUserId();

        $v_status = Db::name("user")->where('id', $userId)->value('v_status');

        if ($v_status == 1) {
            $this->error("您已经通过大V认证了,请勿重复提交!");
        }

        $info = Db::name("user_vauth")->where('uid', $userId)->where('auth_status', 0)->find();

        if ($info) {
            $this->error("您的申请正在审核中，请勿重复提交！");
        }
        $insert_data = array();
        $insert_data['uid'] = $userId;
        $insert_data['real_name'] = $data['real_name'];
        $insert_data['v_title'] = $data['v_title'];
        $insert_data['v_reason'] = $data['v_reason'];
        $insert_data['auth_status'] = 0;

        $ret = Db::name("user_vauth")->insert($insert_data);
        if ($ret) {
            $this->success("提交申请成功!");
        } else {
            $this->error("提交申请失败,请重试!");
        }
    }
}
