<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +---------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------
namespace cmf\controller;

use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Request;
use think\Config;
use think\Response;
use think\Loader;
use think\Db;

class RestBaseController
{
    //token
    protected $token = '';
    //hd token
    protected $hd_token = '';

    //设备类型
    protected $deviceType = '';

    protected $apiVersion;

    //用户 id
    protected $userId = 0;

    //用户
    protected $user;

    //HD用户 id
    protected $hd_userId = 0;

    //HD用户
    protected $hd_user;

    //用户类型
    protected $userType;

    protected $allowedDeviceTypes = ['mobile', 'android', 'iphone', 'ipad', 'web', 'pc', 'mac', 'wxapp'];

    protected $apiLangPack = null;

    /**
     * @var \think\Request Request实例
     */
    protected $request;
    // 验证失败是否抛出异常
    protected $failException = false;
    // 是否批量验证
    protected $batchValidate = false;

    /**
     * 前置操作方法列表
     * @var array $beforeActionList
     * @access protected
     */
    protected $beforeActionList = [];

    /**
     * 架构函数
     * @param Request $request Request对象
     * @access public
     */
    public function __construct(Request $request = null)
    {
        if (is_null($request)) {
            $request = Request::instance();
        }

        Request::instance()->root(cmf_get_root() . '/');

        $this->request = $request;

        $this->apiVersion = $this->request->header('XX-Api-Version');

        // 用户验证初始化
        $this->_initUser();
        //HD用户验证初始化
        $this->_initHdUser();
        // 控制器初始化
        $this->_initialize();

        // 前置操作方法
        if ($this->beforeActionList) {
            foreach ($this->beforeActionList as $method => $options) {
                is_numeric($method) ?
                    $this->beforeAction($options) :
                    $this->beforeAction($method, $options);
            }
        }
    }

    // 初始化
    protected function _initialize()
    {
    }


    private function _initHdUser()
    {

        $hd_token      = $this->request->header('XX-Hd-Token');


        if (empty($hd_token)) {
            return;
        }

        $this->hd_token = $hd_token;

        $hd_user = Db::name('hd_user')
            ->where(['hd_token' => $hd_token, 'status' => 1])
            ->find();

        if (!empty($hd_user)) {
            $this->hd_user     = $hd_user;
            $this->hd_userId   = $hd_user['id'];
        }

    }

    private function _initUser()
    {
        $token      = $this->request->header('XX-Token');
        $deviceType = $this->request->header('XX-Device-Type');

        if (empty($deviceType)) {
            return;
        }

        if (!in_array($deviceType, $this->allowedDeviceTypes)) {
            return;
        }

        $this->deviceType = $deviceType;

        if (empty($token)) {
            return;
        }

        $this->token = $token;

        $user = Db::name('user_token')
            ->alias('a')
            ->field('b.*')
            ->where(['token' => $token, 'device_type' => $deviceType])
            ->join('__USER__ b', 'a.user_id = b.id')
            ->find();

        if (!empty($user)) {
            $this->user     = $user;
            $this->userId   = $user['id'];
            $this->userType = $user['user_type'];
        }

    }

    /**
     * 前置操作
     * @access protected
     * @param string $method 前置操作方法名
     * @param array $options 调用参数 ['only'=>[...]] 或者['except'=>[...]]
     */
    protected function beforeAction($method, $options = [])
    {
        if (isset($options['only'])) {
            if (is_string($options['only'])) {
                $options['only'] = explode(',', $options['only']);
            }
            if (!in_array($this->request->action(), $options['only'])) {
                return;
            }
        } elseif (isset($options['except'])) {
            if (is_string($options['except'])) {
                $options['except'] = explode(',', $options['except']);
            }
            if (in_array($this->request->action(), $options['except'])) {
                return;
            }
        }

        call_user_func([$this, $method]);
    }


    /**
     * 设置验证失败后是否抛出异常
     * @access protected
     * @param bool $fail 是否抛出异常
     * @return $this
     */
    protected function validateFailException($fail = true)
    {
        $this->failException = $fail;
        return $this;
    }

    /**
     * 验证数据
     * @access protected
     * @param array $data 数据
     * @param string|array $validate 验证器名或者验证规则数组
     * @param array $message 提示信息
     * @param bool $batch 是否批量验证
     * @param mixed $callback 回调方法（闭包）
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate($data, $validate, $message = [], $batch = false, $callback = null)
    {
        if (is_array($validate)) {
            $v = Loader::validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                list($validate, $scene) = explode('.', $validate);
            }
            $v = Loader::validate($validate);
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }
        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        if (is_array($message)) {
            $v->message($message);
        }

        if ($callback && is_callable($callback)) {
            call_user_func_array($callback, [$v, &$data]);
        }

        if (!$v->check($data)) {
            if ($this->failException) {
                throw new ValidateException($v->getError());
            } else {
                return $v->getError();
            }
        } else {
            return true;
        }
    }

    /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param mixed $msg 提示信息
     * @param mixed $data 返回的数据
     * @param array $header 发送的Header信息
     * @return void
     */
    protected function success($msg = '', $data = '', array $header = [])
    {
        $code   = 1;
        $msg    = $this->translateApiMessage($msg);
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
        ];

        $type                                   = $this->getResponseType();
        $header['Access-Control-Allow-Origin']  = '*';
        $header['Access-Control-Allow-Headers'] = 'X-Requested-With,Content-Type,XX-Device-Type,XX-Token,XX-Api-Version,XX-Wxapp-AppId,XX-Hd-Token,language,Language';
        $header['Access-Control-Allow-Methods'] = 'GET,POST,PATCH,PUT,DELETE,OPTIONS';
        $header['Access-Control-Allow-Credentials']  = 'true';
        $response                               = Response::create($result, $type)->header($header);
        throw new HttpResponseException($response);
    }

    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param mixed $msg 提示信息,若要指定错误码,可以传数组,格式为['code'=>您的错误码,'msg'=>'您的错误消息']
     * @param mixed $data 返回的数据
     * @param array $header 发送的Header信息
     * @return void
     */
    protected function error($msg = '', $data = '', array $header = [])
    {
        $code = 0;
        if (is_array($msg)) {
            $code = $msg['code'];
            $msg  = $msg['msg'];
        }
        $msg = $this->translateApiMessage($msg);
        $result = [
            'code' => $code,
            'msg'  => $msg,
            'data' => $data,
        ];

        $type                                   = $this->getResponseType();
        $header['Access-Control-Allow-Origin']  = '*';
        $header['Access-Control-Allow-Headers'] = 'X-Requested-With,Content-Type,XX-Device-Type,XX-Token,XX-Hd-Token,language,Language';
        $header['Access-Control-Allow-Methods'] = 'GET,POST,PATCH,PUT,DELETE,OPTIONS';
        //$header['Access-Control-Allow-Credentials']  = true;
        //header("Access-Control-Allow-Credentials: true");
        $response                               = Response::create($result, $type)->header($header);
        throw new HttpResponseException($response);
    }

    /**
     * 获取当前的response 输出类型
     * @access protected
     * @return string
     */
    protected function getResponseType()
    {
        return 'json';
    }

    protected function getApiLanguage()
    {
        $language = $this->request->param('language', '');
        if (empty($language)) {
            $language = $this->request->header('language');
        }
        if (empty($language)) {
            $language = $this->request->header('Accept-Language');
        }

        $language = strtolower(str_replace('-', '_', (string) $language));
        $langPack = $this->getApiLangPack();
        $aliases  = empty($langPack['_aliases']) || !is_array($langPack['_aliases']) ? [] : $langPack['_aliases'];
        if (isset($aliases[$language])) {
            return $aliases[$language];
        }

        $primaryLanguage = explode('_', $language)[0];
        if (isset($aliases[$primaryLanguage])) {
            return $aliases[$primaryLanguage];
        }

        if (isset($langPack[$language]) && is_array($langPack[$language])) {
            return $language;
        }
        return 'zh_cn';
    }

    protected function getApiLangPack()
    {
        if ($this->apiLangPack !== null) {
            return $this->apiLangPack;
        }

        $configFile = CMF_ROOT . 'data/conf/api_lang.php';
        if (is_file($configFile)) {
            $langPack = include $configFile;
            $this->apiLangPack = is_array($langPack) ? $langPack : [];
        } else {
            $this->apiLangPack = [];
        }

        return $this->apiLangPack;
    }

    protected function translateApiMessage($msg)
    {
        if (!is_string($msg) || $msg === '') {
            return $msg;
        }

        $language = $this->getApiLanguage();
        if ($language === 'zh_cn') {
            return $msg;
        }

        $langPack = $this->getApiLangPack();
        if (empty($langPack[$language]) || !is_array($langPack[$language])) {
            return $msg;
        }

        $message = trim($msg);
        $languages = [$language];
        if ($language !== 'en_us' && !empty($langPack['en_us'])) {
            $languages[] = 'en_us';
        }

        foreach ($languages as $candidate) {
            $messages = empty($langPack[$candidate]['messages']) ? [] : $langPack[$candidate]['messages'];
            if (isset($messages[$message])) {
                return $messages[$message];
            }

            $prefixes = empty($langPack[$candidate]['prefixes']) ? [] : $langPack[$candidate]['prefixes'];
            foreach ($prefixes as $prefix => $translation) {
                if (strpos($message, $prefix) === 0) {
                    $suffix = substr($message, strlen($prefix));
                    $trimmedSuffix = trim($suffix);
                    if ($trimmedSuffix !== '' && isset($messages[$trimmedSuffix])) {
                        $suffix = (strlen($suffix) > strlen(ltrim($suffix)) ? ' ' : '') . $messages[$trimmedSuffix];
                    }
                    return $translation . $suffix;
                }
            }
        }

        return $msg;
    }

    /**
     * 获取当前登录用户的id
     * @return int
     */
    public function getUserId()
    {
        if (empty($this->userId)) {
            $this->error(['code' => 10001, 'msg' => '用户未登录']);
        }
        return $this->userId;


    }

    /**
     * 获取当前登录用户信息
     * @return int
     */
    public function getUser()
    {
        if (empty($this->user)) {
            $this->error(['code' => 10001, 'msg' => '用户未登录']);
        }
        return $this->user;


    }
    /**
     * 获取当前登录HD用户的id
     * @return int
     */
    public function getHdUserId()
    {
        if (empty($this->hd_userId)) {
            $this->error(['code' => 10001, 'msg' => 'HD用户未登录']);
        }
        return $this->hd_userId;


    }
}
