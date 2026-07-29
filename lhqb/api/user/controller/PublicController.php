<?php

namespace api\user\controller;

use api\common\service\Apibase;
use think\Db;
use think\Validate;
use cmf\controller\RestBaseController;

class PublicController extends RestBaseController
{
    // 用户注册
    public function register()
    {
        $validate = new Validate([
            'username'          => 'require',
            'password'          => 'require',
            'verification_code' => 'require',
        ]);

        $validate->message([
            'username.require'          => '请输入手机号,邮箱!',
            'password.require'          => '请输入您的密码!',
            'verification_code.require' => '请输入数字验证码!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $data['username'] = cmf_normalize_verification_account($data['username']);
        $data['verification_code'] = trim((string) $data['verification_code']);

        $user = [];

        $findUserWhere = [];

        if (Validate::is($data['username'], 'email')) {
            $user['user_email']          = $data['username'];
            $findUserWhere['user_email'] = $data['username'];
        } else {
            if (cmf_check_mobile($data['username'])) {
                $user['mobile']          = $data['username'];
                $findUserWhere['mobile'] = $data['username'];
            } else {
                $this->error("请输入正确的手机格式!");
            }
        }

        $findUserCount = Db::name("user")->where($findUserWhere)->count();

        if ($findUserCount > 0) {
            $this->error("此账号已存在!");
        }
        $user['avatar'] = 'avatar.png';
        $user['signature'] = 'N/A';
        $ip = $this->request->ip(0, true);
        $user['last_login_ip'] = $ip;
        $user['create_time'] = time();
        $user['user_status'] = 1;
        $user['user_type']   = 2;
        $user['user_pass']   = cmf_password($data['password']);
       
        #wuqi修改后台配置关闭注册不生效bug
        cmf_clear_cache();
        $app_config = cmf_get_option('app_config');
        // var_dump($app_config);
        if (isset($app_config['close_reg']) && $app_config['close_reg'] == "1") {
            $this->error('已经关闭注册，敬请期待开放O(∩_∩)O');
        }
        if (empty($data['invitation_code'])) {
            $this->error('请填写注册邀请码');
        }

        $invitation_code = trim($data['invitation_code']);
        $inviteInfo = $this->resolveInvitationCode($invitation_code);
        $parent_user = $inviteInfo['user'];

        if (empty($parent_user)) {
            $this->error('请填写正确的邀请码');
        }

        // 先完成账号与邀请码校验，注册写入成功后再销毁验证码。
        $errMsg = cmf_check_verification_code($data['username'], $data['verification_code']);
        if (!empty($errMsg)) {
            $this->error($errMsg);
        }

        Db::startTrans();
        try {
            // Fetch the ID from the same insert operation. A new query object
            // can return 0 here under MySQL 8, leaving the user unassociated.
            $rst = (int) Db::name("user")->insertGetId($user);
            if ($rst <= 0) {
                throw new \RuntimeException('用户写入失败');
            }

            $new['invitation_code'] = null;
            $new['user_nickname']   = 'User' . $rst;
            if (!empty($inviteInfo['code_id'])) {
                $new['invite_code_id'] = $inviteInfo['code_id'];
            }

            if (isset($data['invitation_code']) && !empty($data['invitation_code'])) { //注册时填写邀请码，查询邀请码用户
                $invitation_code = $data['invitation_code'];
                $parent_user = $inviteInfo['user'];
                if (!empty($parent_user)) {
                    $parent_uid = $parent_user['id'];
                    Db::name("user")->where(['id' => $parent_uid])->setInc('invitation_count');
                    if (!empty($inviteInfo['code_id'])) {
                        Db::name('invitation_code')->where('id', $inviteInfo['code_id'])->setInc('used_count');
                    }
                    $new['parent_user_id'] = $parent_uid;
                    $new['parent_tree'] = empty($parent_user['parent_tree'])
                        ? (string) $parent_uid
                        : $parent_uid . '|' . $parent_user['parent_tree'];
                    Apibase::addActionLog($parent_uid, $rst, 'invite_user_reg');
                }
            }
            Apibase::addActionLog($rst, "", 'newbie_reg');
            if (Db::name("user")->where(['id' => $rst])->update($new) === false) {
                throw new \RuntimeException('用户关系写入失败');
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error("注册失败,请重试!");
        }

        // Consume the code only after the user and invitation relation commit.
        cmf_clear_verification_code($data['username']);
        // if (isset($parent_tree) && !empty($parent_tree)) {
        //     Apibase::invite_reward($rst, $parent_tree, 'reg');
        // }
        //注册后钩子
        $param  = ['uid' => $rst];
        $result = hook_one("after_register", $param);
        if ($result !== false && !empty($result['error'])) {
            $this->success("注册并激活成功,请登录!!");
        }

        if ($result === false) {
            $this->success("注册并激活成功,请登录!!!");
        }
        $this->success("注册并激活成功,请登录!");
    }

    // 用户验证码注册 适用于红包场景
    public function vcode_login()
    {
        $validate = new Validate([
            'username'          => 'require',
            'verification_code' => 'require'
        ]);

        $validate->message([
            'username.require'          => '请输入手机号!',
            'verification_code.require' => '请输入数字验证码!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $user = [];

        $findUserWhere = [];

        if (Validate::is($data['username'], 'email')) {
            $user['user_email']          = $data['username'];
            $findUserWhere['user_email'] = $data['username'];
        } else if (cmf_check_mobile($data['username'])) {
            $user['mobile']          = $data['username'];
            $findUserWhere['mobile'] = $data['username'];
        } else {
            $this->error("请输入正确的手机或者邮箱格式!");
        }

        $errMsg = cmf_check_verification_code($data['username'], $data['verification_code']);
        if (!empty($errMsg)) {
            $this->error($errMsg);
        }

        $findUserCount = Db::name("user")->where($findUserWhere)->count();

        if ($findUserCount > 0) {

            //登录开始
            $findUser = Db::name("user")->field("id")->where($findUserWhere)->find();
            $device_type = "web";
            $userTokenQuery = Db::name("user_token")
                ->where('user_id', $findUser['id'])
                ->where('device_type', $device_type);
            $findUserToken  = $userTokenQuery->find();
            $currentTime    = time();
            $expireTime     = $currentTime + 24 * 3600 * 180;
            $token          = md5(uniqid()) . md5(uniqid());
            if (empty($findUserToken)) {
                $result = $userTokenQuery->insert([
                    'token'       => $token,
                    'user_id'     => $findUser['id'],
                    'expire_time' => $expireTime,
                    'create_time' => $currentTime,
                    'device_type' => $device_type
                ]);
            } else {
                $result = $userTokenQuery
                    ->where('user_id', $findUser['id'])
                    ->where('device_type', $device_type)
                    ->update([
                        'token'       => $token,
                        'expire_time' => $expireTime,
                        'create_time' => $currentTime
                    ]);
            }
            if (empty($result)) {
                $this->error("登录失败!");
            }
            cmf_clear_verification_code($data['username']);
            //$update = ['last_login_time'=>time()];
            //Db::name("user")->where(['id'=>$findUser['id']])->update($update);
            $this->success("登录成功!", ['token' => $token, 'device_type' =>  $device_type]);
            //登录结束
        } else {
            $site_info = cmf_get_option('site_info');
            $app_name = $site_info['site_name'];
            $this->error("请先注册成为" . $app_name . "会员,才能继续哦！");
        }
    }

    // 用户登录 TODO 增加最后登录信息记录,如 ip
    public function login()
    {
        $validate = new Validate([
            'username' => 'require',
            'password' => 'require'
        ]);
        $validate->message([
            'username.require' => '请输入手机号,邮箱或用户名!',
            'password.require' => '请输入您的密码!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $findUserWhere = [];

        if (Validate::is($data['username'], 'email')) {
            $findUserWhere['user_email'] = $data['username'];
        } else if (cmf_check_mobile($data['username'])) {
            $findUserWhere['mobile'] = $data['username'];
        } else {
            $findUserWhere['user_login'] = $data['username'];
        }
        $fieldStr = 'id,mobile,user_email,user_nickname,avatar,signature,user_pass,score,balance,user_status,create_time,invitation_code,has_paypwd,auth_id,is_google_check,last_login_time,last_login_ip,level_id,is_partner,vip_deadline';
        $findUser = Db::name("user")->field($fieldStr)->where($findUserWhere)->find();

        if (empty($findUser)) {
            $this->error("用户不存在!");
        } else {

            switch ($findUser['user_status']) {
                case 0:
                    $this->error('您已被拉黑!');
                case 2:
                    $this->error('账户还没有验证成功!');
            }

            if (!cmf_compare_password($data['password'], $findUser['user_pass'])) {
                $this->error("密码不正确!");
            }
            unset($findUser['user_pass']);
        }

        $allowedDeviceTypes = $this->allowedDeviceTypes;

        if (empty($data['device_type']) || !in_array($data['device_type'], $allowedDeviceTypes)) {
            $this->error("请求错误,未知设备!");
        }

        $userTokenQuery = Db::name("user_token")
            ->where('user_id', $findUser['id'])
            ->where('device_type', $data['device_type']);
        $findUserToken  = $userTokenQuery->find();
        $currentTime    = time();
        $expireTime     = $currentTime + 24 * 3600 * 180;
        $token          = md5(uniqid()) . md5(uniqid());
        if (empty($findUserToken)) {
            $result = $userTokenQuery->insert([
                'token'       => $token,
                'user_id'     => $findUser['id'],
                'expire_time' => $expireTime,
                'create_time' => $currentTime,
                'device_type' => $data['device_type']
            ]);
        } else {
            $result = $userTokenQuery
                ->where('user_id', $findUser['id'])
                ->where('device_type', $data['device_type'])
                ->update([
                    'token'       => $token,
                    'expire_time' => $expireTime,
                    'create_time' => $currentTime
                ]);
        }


        if (empty($result)) {
            $this->error("登录失败!");
        }
        $ip = $this->request->ip(0, true);
        $update = ['last_login_time' => time(), 'last_login_ip' => $ip];
        Db::name("user")->where(['id' => $findUser['id']])->update($update);
        //$findUser['invitation_url'] = Apibase::sp_get_host(). '/#/register?code='.$findUser['invitation_code'];
        $findUser['invitation_url'] = Apibase::sp_get_host() . '/app/sign/register?invitation_code=' . $findUser['invitation_code'];

        $findUser['avatar'] = cmf_get_image_preview_url($findUser['avatar']);
        $this->success("登录成功!", ['token' => $token, 'user' => $findUser]);
    }

    // 用户退出
    public function logout()
    {
        $userId = $this->getUserId();
        Db::name('user_token')->where([
            'token'       => $this->token,
            'user_id'     => $userId,
            'device_type' => $this->deviceType
        ])->update(['token' => '']);

        $this->success("退出成功!");
    }

    // 用户密码重置
    public function passwordReset()
    {
        $validate = new Validate([
            'username'          => 'require',
            'password'          => 'require',
            'verification_code' => 'require'
        ]);

        $validate->message([
            'username.require'          => '请输入手机号,邮箱!',
            'password.require'          => '请输入您的密码!',
            'verification_code.require' => '请输入数字验证码!'
        ]);

        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $userWhere = [];
        if (Validate::is($data['username'], 'email')) {
            $userWhere['user_email'] = $data['username'];
        } else if (cmf_check_mobile($data['username'])) {
            $userWhere['mobile'] = $data['username'];
        } else {
            $this->error("请输入正确的手机或者邮箱格式!");
        }

        $errMsg = cmf_check_verification_code($data['username'], $data['verification_code']);
        if (!empty($errMsg)) {
            $this->error($errMsg);
        }

        $userPass = cmf_password($data['password']);
        if (Db::name("user")->where($userWhere)->update(['user_pass' => $userPass]) === false) {
            $this->error("密码重置失败,请重试!");
        }
        cmf_clear_verification_code($data['username']);

        $this->success("密码重置成功,请使用新密码登录!");
    }

    protected function resolveInvitationCode($code)
    {
        $code = trim((string) $code);
        $invite = Db::name('invitation_code')
            ->where(['code' => $code, 'status' => 1])
            ->find();
        if (!empty($invite)) {
            if ((int) $invite['max_use_count'] > 0 && (int) $invite['used_count'] >= (int) $invite['max_use_count']) {
                $this->error('该邀请码使用人数已达上限');
            }
            $user = Db::name('user')->where('id', $invite['owner_user_id'])->field('id,user_type,parent_user_id,parent_tree')->find();
            return ['user' => $user, 'code_id' => (int) $invite['id']];
        }

        return ['user' => null, 'code_id' => 0];
    }
}
