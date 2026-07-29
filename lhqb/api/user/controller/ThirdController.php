<?php

namespace api\user\controller;

use api\quant\service\Apibase;
use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;

class ThirdController extends RestBaseController
{

    public function openAuthLogin()
    {
        $validate = new Validate([
            'token'     => 'require',
        ]);
        $validate->message([
            'token.require'  => 'token不能为空!',
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }

        $user = [];

        $findUserWhere = [];

        /*检测token是否合法*/
        $platform = 'sinance';
        //$validate_api_url = "https://{host}/third-party/validate_user.php";
        $validate_api_url = "";
        $params['token'] = $data['token'];
        $result = $this->curl_get($validate_api_url, $params, true);
        $result = json_decode($result, true);
        if (!isset($result['data']['user_id'])) {
            $this->error('身份校验失败');
        } else {
            $uid = $result['data']['user_id'];
            $last_member =  $result['data']['last_member'];
            if ($uid <= 0) {
                $this->error('身份校验失败#2');
            }
        }
        $user['user_login']        = $uid;
        $user['open_uid']          = $uid;
        $user['parent_user_id']    = $last_member;
        $findUserWhere['user_login'] = $uid;
        $findUserWhere['open_uid'] = $uid;

        /*检测token是否合法*/

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
            $update = ['last_login_time' => time(), 'parent_user_id' => $last_member];
            Db::name("user")->where(['id' => $findUser['id']])->update($update);

            $where = array();
            $where['platform'] = $platform;
            $where['uid'] = $findUser['id'];
            Db::name("third_api")->where($where)->update([
                'secret_key' => $data['token'],
                'update_time' => time()
            ]);

            $this->success("登录成功!", ['token' => $token, 'device_type' =>  $device_type]);
            //登录结束
        } else {
            //$site_info = cmf_get_option('site_info');
            //$app_name = $site_info['site_name'];            
            //$this->error("请先注册成为".$app_name."会员,才能继续哦！");
        }

        $password = strval(mt_rand(100000, 999999));
        $user['avatar'] = 'avatar.png';
        $user['signature'] = 'N/A';
        $user['create_time'] = time();
        $user['user_status'] = 1;
        $user['user_type']   = 2;
        $user['user_pass']   = cmf_password($password);
        $inviteInfo = $this->resolveInvitationCode(isset($data['invitation_code']) ? $data['invitation_code'] : '');
        Db::startTrans();
        try {
            $rst = (int) Db::name("user")->insertGetId($user);
            if ($rst <= 0) {
                throw new \RuntimeException('用户写入失败');
            }

            $new = [
                'invitation_code' => null,
                'user_nickname' => 'User' . $rst
            ];
            if (!empty($inviteInfo['user'])) {
                $parentUser = $inviteInfo['user'];
                $parentUid = (int) $parentUser['id'];
                $new['parent_user_id'] = $parentUid;
                $new['parent_tree'] = empty($parentUser['parent_tree'])
                    ? (string) $parentUid
                    : $parentUid . '|' . $parentUser['parent_tree'];
                $new['invite_code_id'] = (int) $inviteInfo['code_id'];
                Db::name("user")->where('id', $parentUid)->setInc('invitation_count');
                Db::name('invitation_code')->where('id', $inviteInfo['code_id'])->setInc('used_count');
                Apibase::addActionLog($parentUid, $rst, 'invite_user_reg');
            }
            if (Db::name("user")->where('id', $rst)->update($new) === false) {
                throw new \RuntimeException('用户关系写入失败');
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error("注册失败,请重试!");
        }
        // if (isset($parent_tree) && !empty($parent_tree)) {
        //     Apibase::invite_reward($rst, $parent_tree, 'reg');
        // }
        //注册后钩子
        $param  = ['uid' => $rst];
        $result = hook_one("after_register", $param);
        //token
        $device_type = "web";
        $userTokenQuery = Db::name("user_token")
            ->where('user_id', $rst)
            ->where('device_type', $device_type);
        $findUserToken  = $userTokenQuery->find();
        $currentTime    = time();
        $expireTime     = $currentTime + 24 * 3600 * 180;
        $token          = md5(uniqid()) . md5(uniqid());
        if (empty($findUserToken)) {
            $result = $userTokenQuery->insert([
                'token'       => $token,
                'user_id'     => $rst,
                'expire_time' => $expireTime,
                'create_time' => $currentTime,
                'device_type' => $device_type
            ]);
        } else {
            $result = $userTokenQuery
                ->where('user_id', $rst)
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

        // 自动绑定api
        $where = array();
        $where['platform'] = $platform;
        $where['uid'] = $rst;

        $findApi = Db::name("third_api")->where($where)->find();
        if (!$findApi) {
            //添加到数据库
            $api_info = [
                'platform' => $platform,
                'uid' => $rst,
                'api_key' => $uid,
                'secret_key' => $data['token'],
                'passphrase' => '',
                'create_time' => time(),
                'update_time' => time(),
                'status' => 1,
            ];
            $ret = Db::name("third_api")->insert($api_info);
        }

        $this->success("新用户登录成功!", ['token' => $token, 'device_type' =>  $device_type]);
    }

    private function resolveInvitationCode($code)
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return ['user' => null, 'code_id' => 0];
        }

        $invite = Db::name('invitation_code')->where(['code' => $code, 'status' => 1])->find();
        if (empty($invite)) {
            $this->error('邀请码错误');
        }
        if ((int) $invite['max_use_count'] > 0 && (int) $invite['used_count'] >= (int) $invite['max_use_count']) {
            $this->error('该邀请码使用人数已达上限');
        }

        $user = Db::name('user')->where('id', $invite['owner_user_id'])
            ->field('id,user_type,parent_user_id,parent_tree')->find();
        if (empty($user)) {
            $this->error('邀请码所属用户不存在');
        }

        return ['user' => $user, 'code_id' => (int) $invite['id']];
    }


    private function curl_get($url, $params = false, $ispost = 0)
    {
        $httpInfo = array();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        if ($ispost) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            if ($params) {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . $params);
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }
        $response = curl_exec($ch);
        if ($response === FALSE) {
            //echo "cURL Error: " . curl_error($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
        curl_close($ch);
        return $response;
    }
}
