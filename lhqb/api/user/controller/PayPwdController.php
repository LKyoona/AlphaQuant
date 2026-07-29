<?php
namespace api\user\controller;
use think\Db;
use think\Validate;
use cmf\controller\RestBaseController;
class PayPwdController extends RestBaseController
{   //添加密码
    public function addpwd(){
        //判断用户是否有密码，有就结束
        $validate = new Validate([
            'pwd'        => 'require',
            ]);
        $validate->message([
           'pwd.require'   => '请输入密码!',
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $regh='';
        $regh= "/^\d{6,}$/";
        if(!preg_match($regh,$data['pwd'])){
            $this->error('密码格式错误(要求至少6位以上数字),请检查后重试！');
        }
        $userId    = $this->getUserId();

        $sql=  Db::name("user")->where(['id'=> $userId  ,'user_status'=>['neq',0]])->find();
        if(empty($sql)){
            $this->error('暂无该用户信息！');
        }elseif( $sql['has_paypwd']==1 ){
            $this->error('已有支付密码，无需重新设置！');
        }

        // 第三方账号授权登录时，不验证mobile
        $open_uid = intval($sql['open_uid']);
        if ($open_uid === 0) {
            $mobile = $sql['mobile'];
            if(empty($mobile)){
                $this->error('请先绑定手机号后再设置支付密码');
            }
        }


        //通过验证后，修改用户的三个字段，修改成功返回信息，修改失败返回信息
        $isup=  Db::name("user")->where(['id'=> $userId  ,'user_status'=>['neq',0]])->update([
            'auth_id' => 2,
            'has_paypwd'=>1,
            //'paypwd' => $data['pwd']
            'paypwd' => cmf_password('pay'.$data['pwd'])
        ]);
        if($isup){
            $this->success('操作成功！');
        }else{
            $this->error('操作失败，请重试！');
        }
    }
    //修改密码 
    public function uppwd(){
        //判断是否已有密码 ，没有就结束，用户还没密码，请先设置
        $validate = new Validate([
            'old_pwd'        => 'require',
            'pwd'        => 'require',
            ]);
        $validate->message([
           'old_pwd.require'   => '请输入原支付密码!',
           'pwd.require'   => '请输入新支付密码!',
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $userId    = $this->getUserId();
        $sql=  Db::name("user")->where(['id'=> $userId  ,'user_status'=>['neq',0]])->find();
        if(empty($sql)){
            $this->error('暂无该用户信息！');
        }elseif( $sql['has_paypwd']==0 ){
            $this->error('还没有支付密码，请先设置支付密码！');
        }
        $regh='';
        $regh= "/^\d{6,}$/";
        if(!preg_match($regh,$data['pwd'])){
            $this->error('密码格式错误，请检查后重试！');
        }
        if (trim($data['old_pwd']) == trim($data['pwd'])) {
            $this->error('新的支付密码不能与原支付密码重复');
        }
        if (!cmf_compare_password('pay'.$data['old_pwd'], $sql['paypwd'])) {
            $this->error('原支付密码不正确');
        }         
        $isup=  Db::name("user")->where(['id'=> $userId  ,'user_status'=>['neq',0]])->update([
            'paypwd' => cmf_password('pay'.$data['pwd'])
        ]);
        if($isup){
            $this->success('修改支付密码成功！');
        }else{
            $this->error('修改支付密码失败，请重试！');
        }
    }
    //手机号验证(忘记支付密码时)
    public function forgetpwd(){
        $validate = new Validate([
            'new_pwd'       => 'require',
            'vcode'        => 'require',
            ]);
        $validate->message([
            'vcode.require'   => '请输入验证码!',
            'new_pwd.require'  => '请输入您的新支付密码!',
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $regh= "/^\d{6,}$/";
        if(!preg_match($regh,$data['new_pwd'])){
            $this->error('密码格式错误，请检查后重试！');
        }
        // if (!cmf_check_mobile($data['mobile'])) {
        //     $this->error("请输入正确的手机格式!");
        // } 
        $userId = $this->getUserId();
        $mobile = Db::name("user")->where('id', $userId)->value('mobile');
        // if ($mobile != $data['mobile']) {
        //     $this->error("您绑定的手机号与当前手机号不符合！");
        // }
        $verificationError = cmf_check_verification_code($mobile, $data['vcode']);
        if ($verificationError !== '') {
            $this->error($verificationError);
        }
        if ($verificationError === '') {
            $isup=  Db::name("user")->where(['id'=> $userId  ,'user_status'=>['neq',0]])->update([
                'paypwd' => cmf_password('pay'.$data['new_pwd'])
            ]);
            if($isup!==false){
                cmf_clear_verification_code($mobile);
                $this->success('重置支付密码成功！');
            }else{
                $this->error('重置支付密码失败，请重试！');
            }
        }
    }

     //原密码验证(修改密码时)
     public function oldpwdva(){
        $validate = new Validate([
            'vcode'        => 'require',
            ]);
        $validate->message([
            'vcode.require'   => '请输入验证码!',
        ]);
        $data = $this->request->param();
        if (!$validate->check($data)) {
            $this->error($validate->getError());
        }
        $userId = $this->getUserId();
        $mobile = Db::name("user")->where('id', $userId)->value('paypwd');
        $paypwd = 'pay'.$data['vcode'];
         if (!cmf_compare_password($paypwd, $mobile)) {
             $this->error("密码不正确!");
         }else{
            $this->success("验证成功！");
        }
    }
}
