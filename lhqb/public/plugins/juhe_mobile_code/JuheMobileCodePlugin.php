<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace plugins\juhe_mobile_code;//Demo插件英文名，改成你的插件英文就行了
use cmf\lib\Plugin;

/**
 * JuheMobileCodePlugin
 */
class JuheMobileCodePlugin extends Plugin
{

    public $info = [
        'name'        => 'JuheMobileCode',
        'title'       => '聚合手机验证码',
        'description' => '聚合手机验证码插件',
        'status'      => 1,
        'author'      => 'Kinlink',
        'version'     => '1.0'
    ];

    public $has_admin = 0;//插件是否有后台管理界面

    public function install() //安装方法必须实现
    {
        return true;//安装成功返回true，失败false
    }

    public function uninstall() //卸载方法必须实现
    {
        return true;//卸载成功返回true，失败false
    }

    //实现的send_mobile_verification_code钩子方法
    public function sendMobileVerificationCode($param)
    {
        $mobile        = $param['mobile'];//手机号
        $code          = $param['code'];//验证码
        $config        = $this->getConfig();

        $expire_minute = intval($config['expire_minute']);
        $expire_minute = empty($expire_minute) ? 30 : $expire_minute;
        $expire_time   = time() + $expire_minute * 60;
        $result        = false;

        if (empty($config["app_key"])) {
            $result = [
                'error'     => 1,
                'message' => '系统设置有误,验证码发送失败'
            ];
            return $result;
        }

        if (empty($config["template_id"])) {
            $result = [
                'error'     => 1,
                'message' => '系统设置有误,验证码发送失败'
            ];
            return $result;
        }
        
        
        $mobile = explode('-',$mobile)[1];
        $content = "您的验证码是:".$code."【吉币网】";
        $smsapi = "http://api.sms.cn/sms/?ac=send";
        $user = "sunnyguo888"; //短信平台帐号
        $pass = $config["app_key"]; //短信平台密码        
        $url = $smsapi."&uid=".$user."&pwd=".$pass."&mobile=".$mobile."&content=".urlencode($content).'&format=json';
        
        
        $res = file_get_contents($url);
        
        $result1 = json_decode($res,true);
        
        if($result1['stat'] == 100){
            $result = [
                'error'     => 0,
                'message' => '发送成功'
            ];
        }else{
            $result = [
                'error'     => 1,
                'message' => '发送失败'
            ];
        }
    
        // Vendor('juhesms.juhesms');
        // $sms=new \juhesms();
        // $conf  = array(
        //     "key"=> $config["app_key"],
        //     "mobile"=>$mobile,
        //     "tpl_id"=> $config["template_id"],
        //     "tpl_value"=>"#code#=".$code,
        // );
        // $sms->smsConf= $conf;

        // send sms
        // if(!$sms->Send()) {
        //     $result = [
        //         'error'     => 1,
        //         'message' => '发送失败'
        //     ];
        // }else{
        //     $result = [
        //         'error'     => 0,
        //         'message' => '发送成功'
        //     ];
        // }
        return $result;
    }

}