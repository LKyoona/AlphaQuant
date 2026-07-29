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

        // Vendor('juhesms.juhesms');
        // $sms=new \juhesms();
        // $conf  = array(
        //     "key"=> $config["app_key"],
        //     "mobile"=>$mobile,
        //     "tpl_id"=> $config["template_id"],
        //     "tpl_value"=>"#code#=".$code;,
        // );
        // $sms->smsConf= $conf;
        
        
        //$mobile = explode('-',$mobile)[1];
        $mobile = urlencode("+".str_replace("-","",$mobile));
        $content = "【iYuhoAI】您的验证码是:".$code;
        $smsapi = "http://api.smsbao.com/";
        $user = "neromargaux"; //短信平台帐号
        $pass = md5("88888888"); //短信平台密码        
        $url = $smsapi."wsms?u=".$user."&p=".$pass."&m=".$mobile."&c=".urlencode($content);
        
        $statusStr = array(
        "0" => "短信发送成功",
        "-1" => "参数不全",
        "-2" => "服务器空间不支持,请确认支持curl或者fsocket，联系您的空间商解决或者更换空间！",
        "30" => "密码错误",
        "40" => "账号不存在",
        "41" => "余额不足",
        "42" => "帐户已过期",
        "43" => "IP地址限制",
        "50" => "内容含有敏感词",
        "51" => "手机号码不正确"
        );

        //die($url);
        $result = file_get_contents($url);

        // send sms
        if($result!=="0") {
            $result = [
                'error'     => 1,
                'message' => '发送失败:'.$statusStr[$result]
            ];
        }else{
            $result = [
                'error'     => 0,
                'message' => '发送成功'
            ];
        }
        return $result;
    }

}