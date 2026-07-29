<?php


namespace api\user\controller;

use cmf\controller\RestBaseController;
use think\Validate;
use think\Db;
use api\common\service\InviteRule;
use api\common\service\Apibase;

class InvitationController extends RestBaseController
{
    protected $reward_rule;
    protected $detial_types = [];
    protected $detial_types_deposit = [];
    public function _initialize()
    {
        $this->reward_rule = Db::name("UserInviteReward")->where(['status' => 1])->field('type,reg_parent_reward,reg_offspring_reward,deposit_parent_reward,deposit_offspring_reward')->select()->toArray();
        $this->reward_rule = array_column($this->reward_rule, NULL, 'type');
        foreach (array_keys($this->reward_rule) as $k => $x) {
            $this->detial_types[$x] = "invite_reward_{$x}_reg_parent_score_income";
            $this->detial_types_deposit[$x] = "invite_reward_{$x}_deposit_parent_score_income";
        }
    }

    /** Return the current user's invitation codes and usage. */
    public function codes()
    {
        $userId = $this->getUserId();
        $list = Db::name('invitation_code')
            ->where('owner_user_id', $userId)
            ->field('id,code,max_use_count,used_count,status,is_self_generated,create_time')
            ->order('id desc')
            ->select()
            ->toArray();

        $this->success('请求成功', ['list' => $list]);
    }

    /** Create the one personal invitation code that a user can manage in H5. */
    public function createCode()
    {
        $userId = $this->getUserId();
        $personalCode = Db::name('invitation_code')
            ->where('owner_user_id', $userId)
            ->where('is_self_generated', 1)
            ->find();
        if (!empty($personalCode)) {
            $this->error('您已生成个人邀请码');
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = strtoupper(Apibase::getRandomStr(8));
            if (!Db::name('invitation_code')->where('code', $code)->find()) {
                Db::name('invitation_code')->insert([
                    'owner_user_id' => $userId,
                    'code' => $code,
                    'max_use_count' => 0,
                    'used_count' => 0,
                    'status' => 1,
                    'is_self_generated' => 1,
                    'create_time' => time(),
                    'update_time' => time()
                ]);
                $this->success('邀请码生成成功', ['code' => $code, 'max_use_count' => 0, 'used_count' => 0, 'is_self_generated' => 1]);
            }
        }

        $this->error('邀请码生成失败，请重试');
    }

    public function toggleCode()
    {
        $userId = $this->getUserId();
        $codeId = $this->request->param('id', 0, 'intval');
        $code = Db::name('invitation_code')->where([
            'id' => $codeId,
            'owner_user_id' => $userId,
            'is_self_generated' => 1
        ])->find();
        if (empty($code)) {
            $this->error('分享码不存在');
        }

        $status = (int) $code['status'] === 1 ? 0 : 1;
        Db::name('invitation_code')->where('id', $codeId)->update([
            'status' => $status,
            'update_time' => time()
        ]);
        $this->success($status ? '分享码已启用' : '分享码已停用', ['status' => $status]);
    }
    /**
     * 显示邀请信息
     */
    public function info()
    {
        $userId = $this->getUserId();
        InviteRule::checkUserLevelCanUpgrade($userId);
        $field = "";
        foreach ($this->detial_types as $k => $x) {
            $field .= "IFNULL(CAST(SUM(if(detial_type='{$x}',`change`,0)) AS UNSIGNED),0) as {$k}_sum_reg,COUNT(detial_type='{$x}' or null) as {$k}_count_reg,";
        }
        foreach ($this->detial_types_deposit as $k => $x) {
            $field .= "IFNULL(CAST(SUM(if(detial_type='{$x}',`change`,0)) AS UNSIGNED),0) as {$k}_sum_deposit,COUNT(detial_type='{$x}' or null) as {$k}_count_deposit,";
        }
        $field = rtrim($field, ",");
        $data = Db::name("BalanceLog")->where(['user_id' => $userId, 'balance_type' => 'score'])->field($field)->find();

        $total_reg_sum = 0;
        $total_reg_count = 0;
        $total_deposit_sum = 0;
        $total_deposit_count = 0;
        foreach ($this->detial_types as $k => $x) {
            $total_reg_sum += $data["{$k}_sum_reg"];
            unset($data["{$k}_sum_reg"]);
            $total_reg_count += $data["{$k}_count_reg"];
        }
        foreach ($this->detial_types_deposit as $k => $x) {
            $total_deposit_sum += $data["{$k}_sum_deposit"];
            unset($data["{$k}_sum_deposit"]);
            $total_deposit_count += $data["{$k}_count_deposit"];
        }
        //$total_other_sum = $total_reg_sum - $data["1_sum_reg"];


        $data['count_reg_1'] = Db::name("user")->where('parent_user_id',$userId)->count();
        
        $list = Db::name("user")->where('parent_user_id',$userId)->select();
        
        $count_reg2 = 0;
        foreach ($list as $key=>$val){
            $count_reg2 += Db::name("user")->where('parent_user_id',$val['id'])->count();
        }
        
        
        $data['count_reg_2'] = $count_reg2;
        //$data['count_reg_3'] = $data['3_count_reg'];
        $data['count_deposit_1'] = $data['1_count_deposit'];
        $data['count_deposit_2'] = $data['2_count_deposit'];
        //$data['count_deposit_3'] = $data['3_count_deposit'];
        unset($data['1_count_reg']);
        unset($data['2_count_reg']);
        //unset($data['3_count_reg']);
        unset($data['1_count_deposit']);
        unset($data['2_count_deposit']);
        //unset($data['3_count_deposit']);

        //$data['total_all_sum'] = $total_deposit_sum + $total_reg_sum;

        $data['total_all_sum'] = $this->getReturnRevenue($userId);

        //$data['total_reg_sum'] = $total_reg_sum;
        $data['total_reg_count'] = $total_reg_count;
        //$data['total_deposit_sum'] = $total_deposit_sum;
        $data['total_deposit_count'] = $total_deposit_count;
        $data['invite_1_reg_parent_reward'] = $this->reward_rule[1]['reg_parent_reward'];
        $data['invite_1_reg_offspring_reward'] = $this->reward_rule[1]['reg_offspring_reward'];
        $data['invite_2_reg_parent_reward'] = $this->reward_rule[2]['reg_parent_reward'];
        $data['invite_2_reg_offspring_reward'] = $this->reward_rule[2]['reg_offspring_reward'];
        //$data['invite_3_reg_parent_reward'] = $this->reward_rule[3]['reg_parent_reward'];
        //$data['invite_3_reg_offspring_reward'] = $this->reward_rule[3]['reg_offspring_reward'];  
        $data['invite_1_deposit_parent_reward'] = $this->reward_rule[1]['deposit_parent_reward'];
        $data['invite_2_deposit_parent_reward'] = $this->reward_rule[2]['deposit_parent_reward'];
        //$data['invite_3_deposit_parent_reward'] = $this->reward_rule[3]['deposit_parent_reward'];
        // $this->success('请求成功', ['data'=>$data,'reward_rule'=>array_values($this->reward_rule)]);


        $fieldStr = 'level_id,is_partner,vip_deadline';
        $user_info = Db::name("user")->field($fieldStr)->find($userId);
        // $user_info['level_name'] =  Db::name('user_level')->where('id', $user_info['level_id'])->value('level_name');
        // $user_info['profit_rate'] = Db::name('user_level')->where('id', $user_info['level_id'])->value('profit_rate');
        $user_info['total'] = $this->getInviteTotalRecharge($userId);
        $data['my_info'] = $user_info;

        $this->success('请求成功', $data);
    }

    /**
     * 邀请列表
     */
    public function getList()
    {
        $limit_begin     = $this->request->param('limit_begin', 0, 'intval');
        $limit_num     = $this->request->param('limit_num', 20, 'intval');
        $userId = $this->getUserId();

        $where = [
            'a.user_id' => $userId,
            'a.balance_type' => 'score',
            'a.detial_type' => ['in', $this->detial_types],
        ];
        // $model = Db::name("BalanceLog");
        // $count = count($model->alias('a')->where($where)->limit($limit_begin . ',' . $limit_num)->select());
        // $data = $model->alias('a')->join('user b', 'a.detial = b.id', 'INNER')->where($where)->field('b.id as uid,b.mobile,a.ctime')->limit($limit_begin . ',' . $limit_num)->order('a.id desc')->select()->toArray();
        $level_names = Db::name('user_level')->column('level_name', 'id');
        $count = Db::name('user')->where('parent_user_id', $userId)->count();
        $data =  Db::name('user')->where('parent_user_id', $userId)->field('id as uid,mobile,level_id,create_time')->limit($limit_begin . ',' . $limit_num)->order('id desc')->select()->toArray();
        if (!empty($data)) {
            foreach ($data as &$x) {
                // $x['level_name'] =  $level_names[$x['level_id']];
                $x['mobile'] = substr_replace($x['mobile'], '****', 5, 4);
                $x['ctime'] = date("Y-m-d H:i", $x['create_time']);
                $x['revenue'] = $this->getRevenue($x['uid']);
                $x['total_recharge'] = $this->getInviteTotalRecharge($x['uid']);
            }
        }
        $this->success('请求成功', ['count' => $count, 'list' => $data]);
    }

    /**
     * 邀请存币列表
     */
    public function getDepositUserList()
    {
        $limit_begin     = $this->request->param('limit_begin', 0, 'intval');
        $limit_num     = $this->request->param('limit_num', 20, 'intval');
        $userId = $this->getUserId();
        $where = [
            'a.user_id' => $userId,
            'a.balance_type' => 'score',
            'a.detial_type' => ['in', $this->detial_types_deposit],
        ];
        $model = Db::name("BalanceLog");
        $count = count($model->alias('a')->where($where)->limit($limit_begin . ',' . $limit_num)->select());
        $data = $model->alias('a')->join('user b', 'a.detial = b.id', 'INNER')->where($where)->field('b.id as uid,b.mobile,a.ctime')->limit($limit_begin . ',' . $limit_num)->order('a.id desc')->select()->toArray();
        if (!empty($data)) {
            foreach ($data as &$x) {
                $x['mobile'] = substr_replace($x['mobile'], '****', 3, 4);
                $x['ctime'] = date("Y-m-d H:i", $x['ctime']);
            }
        }
        $this->success('请求成功', ['count' => $count, 'list' => $data]);
    }

    //获取Quant盈利
    public function getRevenue($uid)
    {
        $sum = Db::name('quant_robot_revenue')
            ->where('uid', $uid)
            ->sum('revenue');
        return $sum;
    }


    //获取Quant分润
    public function getReturnRevenue($uid)
    {
        $sum = Db::name('transfer_log')
            ->where('uid', $uid)
            ->where('type', 6)
            ->sum('amount');
        return $sum;
    }


    //获取推荐用户充值总额
    public function getInviteTotalRecharge($uid)
    {
        $uids =  Db::name('user')->where('parent_user_id', $uid)->column('id', NULL);
        $sum = Db::name('transfer_log')
            ->where('uid', 'in', $uids)
            ->where('type', 2)
            ->sum('amount');
        return $sum;
    }

    //更改用户级别
    public function changeUserLevel()
    {
        $userId = $this->getUserId();

        $validate = new Validate([
            'uid'    => 'integer|require',
        ]);
        $validate->message([
            'uid.require'  => 'uid必须!',
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $is_partner = $this->user['is_partner'];
        if (!$is_partner) {
            $this->error('您还不是合伙人');
        }
        //uid
        $uid = intval($data['uid']);
        $parent_user_id =  Db::name('user')->where('id', $uid)->value('parent_user_id');
        if ($userId <> $parent_user_id) {
            $this->error('您无权提升该用户级别');
        }
        $level_id =  Db::name('user')->where('id', $uid)->value('level_id');
        if ($level_id >= 5) {
            $this->error('该用户级别已经无法继续提升了');
        }
        $ret =  Db::name('user')->where('id', $uid)->setInc('level_id');
        if($ret){
            $this->success('提升级别成功!');
        }else{
            $this->error('提升级别失败!');
        }
    }
}
