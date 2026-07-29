<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 小夏 < 449134904@qq.com>
// +----------------------------------------------------------------------
namespace app\admin\controller;

use cmf\controller\AdminBaseController;
use api\common\service\Apibase;
use think\Db;

/**
 * Class UserController
 * @package app\admin\controller
 * @adminMenuRoot(
 *     'name'   => '用户与代理管理',
 *     'action' => 'default',
 *     'parent' => 'user/AdminIndex/default',
 *     'display'=> true,
 *     'order'  => 10000,
 *     'icon'   => '',
 *     'remark' => '管理组'
 * )
 */
class UserController extends AdminBaseController
{

    /**
     * 管理员列表
     * @adminMenu(
     *     'name'   => '管理员与代理账号',
     *     'parent' => 'default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员管理',
     *     'param'  => ''
     * )
     */
    public function index()
    {
        $content = hook_one('admin_user_index_view');

        if (!empty($content)) {
            return $content;
        }

        $where = ["user_type" => 1];
        /**搜索条件**/
        $userLogin = $this->request->param('user_login');
        $userEmail = trim((string) $this->request->param('user_email', ''));

        if ($userLogin) {
            $where['user_login'] = ['like', "%$userLogin%"];
        }

        if ($userEmail) {
            $where['user_email'] = ['like', "%$userEmail%"];;
        }
        $users = Db::name('user')
            ->where($where)
            ->order("id DESC")
            ->paginate(10);
        $users->appends(['user_login' => $userLogin, 'user_email' => $userEmail]);
        // 获取分页显示
        $page = $users->render();

        $rolesSrc = Db::name('role')->select();
        $roles    = [];
        foreach ($rolesSrc as $r) {
            $roleId           = $r['id'];
            $roles["$roleId"] = $r;
        }
        $this->assign("page", $page);
        $this->assign("roles", $roles);
        $this->assign("users", $users);
        return $this->fetch();
    }

    /**
     * 管理员添加
     * @adminMenu(
     *     'name'   => '管理员添加',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员添加',
     *     'param'  => ''
     * )
     */
    public function add()
    {
        $content = hook_one('admin_user_add_view');

        if (!empty($content)) {
            return $content;
        }

        $roles = Db::name('role')->where(['status' => 1])->order("id DESC")->select();
        $this->assign("roles", $roles);
        return $this->fetch();
    }

    /**
     * 代理后台开通管理
     * @adminMenu(
     *     'name'   => '代理后台开通',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '为现有代理用户开通受限后台权限',
     *     'param'  => ''
     * )
     */
    public function agentAccess()
    {
        if (cmf_get_current_admin_id() != 1) {
            $this->error('只有超级管理员可以管理代理后台');
        }

        $keyword = $this->request->param('keyword', '', 'trim');
        $agentRole = $this->getAgentRole(false);
        $agentRoleId = empty($agentRole) ? 0 : (int) $agentRole['id'];

        $query = Db::name('user')->alias('u')
            ->join('agent_admin_credential c', 'u.id = c.user_id', 'LEFT')
            ->field('u.id,u.user_login,u.user_nickname,u.mobile,u.user_email,u.invitation_code,u.invitation_count,u.user_type,u.user_status,u.parent_user_id,u.create_time,c.admin_login,c.status as agent_admin_status');

        if ($keyword !== '') {
            if (ctype_digit($keyword)) {
                $query->where(function ($where) use ($keyword) {
                    $where->where('u.id', (int) $keyword)
                        ->whereOr('u.mobile', 'like', '%' . $keyword . '%')
                        ->whereOr('u.invitation_code', $keyword);
                });
            } else {
                $query->where('u.user_login|u.user_nickname|u.mobile|u.invitation_code', 'like', '%' . $keyword . '%');
            }
        }

        $users = $query->where('u.id', '<>', 1)->order('u.id desc')->paginate(20, false, [
            'query' => ['keyword' => $keyword]
        ]);
        $openedUserIds = Db::name('agent_admin_credential')->where('status', 1)->column('user_id');
        $credentials = session('agent_access_credentials');
        session('agent_access_credentials', null);

        $this->assign('users', $users);
        $this->assign('page', $users->render());
        $this->assign('keyword', $keyword);
        $this->assign('agentRoleId', $agentRoleId);
        $this->assign('openedUserIds', array_map('intval', $openedUserIds));
        $this->assign('credentials', $credentials);

        return $this->fetch();
    }

    public function agentAccessPost()
    {
        if (cmf_get_current_admin_id() != 1) {
            $this->error('只有超级管理员可以管理代理后台');
        }

        $userId = $this->request->param('id', 0, 'intval');
        $actionType = $this->request->param('action_type', 'enable', 'trim');
        $user = Db::name('user')->where('id', $userId)->find();
        if (empty($user) || $userId === 1) {
            $this->error('代理用户不存在或不可操作');
        }

        $agentRole = $this->getAgentRole(true);
        $agentRoleId = (int) $agentRole['id'];

        if ($actionType === 'disable') {
            Db::name('role_user')->where([
                'role_id' => $agentRoleId,
                'user_id' => $userId
            ])->delete();
            Db::name('agent_admin_credential')->where('user_id', $userId)->update([
                'status'      => 0,
                'update_time' => time()
            ]);
            cache(null, 'admin_menus');
            $this->success('代理后台权限已关闭');
        }

        $credential = Db::name('agent_admin_credential')->where('user_id', $userId)->find();
        $alreadyOpened = !empty($credential) && (int) $credential['status'] === 1;
        $userLogin = $this->request->param('admin_login', '', 'trim');
        $password = $this->request->param('admin_pass', '', 'trim');
        if (!$alreadyOpened) {
            $userLogin = $this->generateAgentLogin($userId);
            $password = $this->generateAgentPassword(12);
        } else {
            if ($userLogin === '') {
                $userLogin = $credential['admin_login'];
            }
            if ($password !== '' && strlen($password) < 8) {
                $this->error('后台密码至少需要8位');
            }
        }
        if (!preg_match('/^[A-Za-z0-9_@.+-]{4,50}$/', $userLogin)) {
            $this->error('后台用户名需为4-50位字母、数字或常用符号');
        }

        $duplicate = Db::name('agent_admin_credential')
            ->where('admin_login', $userLogin)
            ->where('user_id', '<>', $userId)
            ->find();
        if (!empty($duplicate) || Db::name('user')->where('user_login', $userLogin)->where('id', '<>', $userId)->count() > 0) {
            $this->error('后台用户名已被使用');
        }

        $otherRoleCount = Db::name('role_user')
            ->where('user_id', $userId)
            ->where('role_id', '<>', $agentRoleId)
            ->count();
        if ($otherRoleCount > 0) {
            $this->error('该用户已经是其他后台角色，请先在管理员管理中确认后再操作');
        }

        $updateData = [
            'user_type'   => 1,
            'user_status' => 1
        ];

        Db::startTrans();
        try {
            Db::name('user')->where('id', $userId)->update($updateData);
            $credentialData = [
                'user_id'      => $userId,
                'admin_login'  => $userLogin,
                'status'       => 1,
                'update_time'  => time()
            ];
            if ($password !== '') {
                $credentialData['admin_pass'] = cmf_password($password);
            }
            if (empty($credential)) {
                $credentialData['create_time'] = time();
                Db::name('agent_admin_credential')->insert($credentialData);
            } else {
                Db::name('agent_admin_credential')->where('user_id', $userId)->update($credentialData);
            }

            // The agent role is intentionally exclusive so another role cannot
            // accidentally grant extra menus.
            Db::name('role_user')->where('user_id', $userId)->delete();
            Db::name('role_user')->insert([
                'role_id' => $agentRoleId,
                'user_id' => $userId
            ]);
            $this->syncAgentRolePermissions($agentRoleId);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            $this->error($e->getMessage() ?: '代理后台开通失败');
        }

        cache(null, 'admin_menus');
        if (!$alreadyOpened || $password !== '') {
            session('agent_access_credentials', [
                'user_id'     => $userId,
                'admin_login' => $userLogin,
                'admin_pass'  => $password
            ]);
        }
        $this->success(
            $alreadyOpened ? '代理后台账号已更新' : '代理后台已开通，请记录随机账号和密码',
            url('user/agentAccess', ['keyword' => $userId])
        );
    }

    protected function getAgentRole($createIfMissing = false)
    {
        $role = Db::name('role')->where('name', '代理')->find();
        if (empty($role)) {
            $role = Db::name('role')->where('name', '代理后台')->find();
        }

        if (empty($role) && $createIfMissing) {
            $roleId = Db::name('role')->insertGetId([
                'name'       => '代理后台',
                'status'     => 1,
                'list_order' => 20,
                'remark'     => '仅查看和操作本人邀请关系内的用户'
            ]);
            $role = Db::name('role')->where('id', $roleId)->find();
        }

        return $role;
    }

    protected function syncAgentRolePermissions($roleId)
    {
        $rules = [
            'user/adminindex/default1',
            'user/adminindex/index',
            'user/adminindex/childs',
            'user/adminindex/simulatedisk',
            'user/adminindex/simulatediskpost'
        ];

        Db::name('auth_access')->where([
            'role_id' => $roleId,
            'type'    => 'admin_url'
        ])->delete();
        foreach ($rules as $rule) {
            Db::name('auth_access')->insert([
                'role_id'  => $roleId,
                'rule_name'=> $rule,
                'type'     => 'admin_url'
            ]);
        }
    }

    protected function generateAgentLogin($userId)
    {
        do {
            $login = 'agent' . (int) $userId . '_' . strtolower($this->randomString(5, 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'));
            $exists = Db::name('agent_admin_credential')->where('admin_login', $login)->count()
                + Db::name('user')->where('user_login', $login)->count();
        } while ($exists > 0);

        return $login;
    }

    protected function generateAgentPassword($length = 12)
    {
        return $this->randomString($length, 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%');
    }

    protected function randomString($length, $characters)
    {
        $value = '';
        $maxIndex = strlen($characters) - 1;
        for ($index = 0; $index < $length; $index++) {
            $value .= $characters[random_int(0, $maxIndex)];
        }

        return $value;
    }

    /**
     * 管理员添加提交
     * @adminMenu(
     *     'name'   => '管理员添加提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员添加提交',
     *     'param'  => ''
     * )
     */
    public function addPost()
    {
        if ($this->request->isPost()) {
            if (!empty($_POST['role_id']) && is_array($_POST['role_id'])) {
                $role_ids = $_POST['role_id'];
                unset($_POST['role_id']);
                $_POST['user_email'] = Apibase::getRandomStr(6);
                $_POST['invitation_code'] = $_POST['user_email'];
               // printf($_POST['user_email']);
                $result = $this->validate($this->request->param(), 'User');
                if ($result !== true) {
                    $this->error($result);
                } else {
                    $_POST['user_pass'] = cmf_password($_POST['user_pass']);
                    $result             = DB::name('user')->insertGetId($_POST);
                    if ($result !== false) {
                        //$role_user_model=M("RoleUser");
                        foreach ($role_ids as $role_id) {
                            if (cmf_get_current_admin_id() != 1 && $role_id == 1) {
                                $this->error("为了网站的安全，非网站创建者不可创建超级管理员！");
                            }
                            Db::name('RoleUser')->insert(["role_id" => $role_id, "user_id" => $result]);
                        }
                        $this->success("添加成功！", url("user/index"));
                    } else {
                        $this->error("添加失败！");
                    }
                }
            } else {
                $this->error("请为此用户指定角色！");
            }

        }
    }

    /**
     * 管理员编辑
     * @adminMenu(
     *     'name'   => '管理员编辑',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员编辑',
     *     'param'  => ''
     * )
     */
    public function edit()
    {
        $content = hook_one('admin_user_edit_view');

        if (!empty($content)) {
            return $content;
        }

        $id    = $this->request->param('id', 0, 'intval');
        $roles = DB::name('role')->where(['status' => 1])->order("id DESC")->select();
        $this->assign("roles", $roles);
        $role_ids = DB::name('RoleUser')->where(["user_id" => $id])->column("role_id");
        $this->assign("role_ids", $role_ids);

        $user = DB::name('user')->where(["id" => $id])->find();
        $this->assign($user);
        return $this->fetch();
    }

    /**
     * 管理员编辑提交
     * @adminMenu(
     *     'name'   => '管理员编辑提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员编辑提交',
     *     'param'  => ''
     * )
     */
    public function editPost()
    {
        if ($this->request->isPost()) {
            if (!empty($_POST['role_id']) && is_array($_POST['role_id'])) {
                if (empty($_POST['user_pass'])) {
                    unset($_POST['user_pass']);
                } else {
                    $_POST['user_pass'] = cmf_password($_POST['user_pass']);
                }
                $role_ids = $this->request->param('role_id/a');
                unset($_POST['role_id']);
                $result = $this->validate($this->request->param(), 'User.edit');

                if ($result !== true) {
                    // 验证失败 输出错误信息
                    $this->error($result);
                } else {
                    $result = DB::name('user')->update($_POST);
                    if ($result !== false) {
                        $uid = $this->request->param('id', 0, 'intval');
                        DB::name("RoleUser")->where(["user_id" => $uid])->delete();
                        foreach ($role_ids as $role_id) {
                            if (cmf_get_current_admin_id() != 1 && $role_id == 1) {
                                $this->error("为了网站的安全，非网站创建者不可创建超级管理员！");
                            }
                            DB::name("RoleUser")->insert(["role_id" => $role_id, "user_id" => $uid]);
                        }
                        $this->success("保存成功！");
                    } else {
                        $this->error("保存失败！");
                    }
                }
            } else {
                $this->error("请为此用户指定角色！");
            }

        }
    }

    /**
     * 管理员个人信息修改
     * @adminMenu(
     *     'name'   => '个人信息',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员个人信息修改',
     *     'param'  => ''
     * )
     */
    public function userInfo()
    {
        $id   = cmf_get_current_admin_id();
        $user = Db::name('user')->where(["id" => $id])->find();
        $this->assign($user);
        return $this->fetch();
    }

    /**
     * 管理员个人信息修改提交
     * @adminMenu(
     *     'name'   => '管理员个人信息修改提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员个人信息修改提交',
     *     'param'  => ''
     * )
     */
    public function userInfoPost()
    {
        if ($this->request->isPost()) {

            $data             = $this->request->post();
            $data['birthday'] = strtotime($data['birthday']);
            $data['id']       = cmf_get_current_admin_id();
            $create_result    = Db::name('user')->update($data);;
            if ($create_result !== false) {
                $this->success("保存成功！");
            } else {
                $this->error("保存失败！");
            }
        }
    }

    /**
     * 管理员删除
     * @adminMenu(
     *     'name'   => '管理员删除',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '管理员删除',
     *     'param'  => ''
     * )
     */
    public function delete()
    {
        $id = $this->request->param('id', 0, 'intval');
        if ($id == 1) {
            $this->error("最高管理员不能删除！");
        }

        $user = Db::name('user')->where('id', $id)->field('id,parent_user_id,invite_code_id')->find();
        if (empty($user)) {
            $this->error('用户不存在！');
        }
        if (Db::name('user')->where('parent_user_id', $id)->count() > 0) {
            $this->error('该用户仍有下级用户，请先调整邀请关系，不能直接删除！');
        }

        Db::startTrans();
        try {
            if (Db::name('user')->delete($id) === false) {
                throw new \RuntimeException('用户删除失败');
            }
            Db::name("RoleUser")->where(["user_id" => $id])->delete();
            Db::name('user_token')->where('user_id', $id)->delete();
            Db::name('agent_admin_credential')->where('user_id', $id)->delete();

            $parentUserId = (int) $user['parent_user_id'];
            if ($parentUserId > 0) {
                $actualCount = Db::name('user')->where('parent_user_id', $parentUserId)->count();
                Db::name('user')->where('id', $parentUserId)->update(['invitation_count' => $actualCount]);
            }
            $inviteCodeId = (int) $user['invite_code_id'];
            if ($inviteCodeId > 0) {
                $actualUsedCount = Db::name('user')->where('invite_code_id', $inviteCodeId)->count();
                Db::name('invitation_code')->where('id', $inviteCodeId)->update([
                    'used_count' => $actualUsedCount,
                    'update_time' => time()
                ]);
            }
            Db::commit();
            $this->success("删除成功！");
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error("删除失败！");
        }
    }

    /**
     * 停用管理员
     * @adminMenu(
     *     'name'   => '停用管理员',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '停用管理员',
     *     'param'  => ''
     * )
     */
    public function ban()
    {
        $id = $this->request->param('id', 0, 'intval');
        if (!empty($id)) {
            $result = Db::name('user')->where(["id" => $id, "user_type" => 1])->setField('user_status', '0');
            if ($result !== false) {
                $this->success("管理员停用成功！", url("user/index"));
            } else {
                $this->error('管理员停用失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }

    /**
     * 启用管理员
     * @adminMenu(
     *     'name'   => '启用管理员',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '启用管理员',
     *     'param'  => ''
     * )
     */
    public function cancelBan()
    {
        $id = $this->request->param('id', 0, 'intval');
        if (!empty($id)) {
            $result = Db::name('user')->where(["id" => $id, "user_type" => 1])->setField('user_status', '1');
            if ($result !== false) {
                $this->success("管理员启用成功！", url("user/index"));
            } else {
                $this->error('管理员启用失败！');
            }
        } else {
            $this->error('数据传入失败！');
        }
    }
}
