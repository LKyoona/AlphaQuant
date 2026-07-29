<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/30
 * Time: 12:07
 */

namespace api\common\service;

use think\Controller;
use think\Db;
use Think\Exception;

class Apibase extends Controller
{
    /**
     * 判断是否SSL协议
     * @return boolean
     */
    public static function is_ssl()
    {
        if (isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))) {
            return true;
        } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
            return true;
        }
        return false;
    }
    /**
     * 返回带协议的域名
     */
    public static function sp_get_host()
    {
        $host = $_SERVER["HTTP_HOST"];
        $protocol = self::is_ssl() ? "https://" : "http://";
        return $protocol . $host;
    }
    /**
     * @param $num         科学计数法字符串  如 2.1E-5
     * @param int $double 小数点保留位数 默认18位
     * @return string
     */

    public static function sctonum($num, $double = 18)
    {
        if (false !== stripos($num, "e")) {
            $a = explode("e", strtolower($num));
            $b = bcmul($a[0], bcpow(10, $a[1], $double), $double);
            $c = rtrim($b, '0');
            return $c;
        } else {
            return $num;
        }
    }

    public static function addActionLog($uid, $object, $action)
    {

        $find_log = Db::name("user_action_log")->where(['user_id' => $uid, 'object' => $object, 'action' => $action])->find();
        if ($find_log) {
            Db::name("user_action_log")->where(['user_id' => $uid, 'object' => $object, 'action' => $action])->setInc('count', 1);
            $update_data['last_visit_time'] = time();
            Db::name("user_action_log")->where(['user_id' => $uid, 'object' => $object, 'action' => $action])->update($update_data);
            return $find_log['count'] + 1;
        } else {
            $log = array(
                'user_id' => $uid,
                'count' => 1,
                'last_visit_time' => time(),
                'object' => $object,
                'action' => $action,
            );
            Db::name("user_action_log")->insert($log);
            return 1;
        }
    }

    public static function updateBalance($type, $uid, $balance_type, $change, $detial, $detial_type, $extension)
    {
        $model = Db::name("user");
        $amount = $model->where(['id' => $uid])->value($balance_type);
        Db::startTrans();
        try {
            $ff = ($change < 0) ? '-' : '+';
            $temp = [
                $balance_type => Db::raw("{$balance_type}{$ff}" . abs($change))
            ];
            $set = $model->where(['id' => $uid])->update($temp);
            $new_balance = $change + $amount;

            if ($set) {
                $log = array(
                    'type' => $type,
                    'user_id' => $uid,
                    'balance_type' => $balance_type,
                    'change' => $change,
                    'amount' => $new_balance,
                    'detial' => $detial,
                    'detial_type' => $detial_type,
                    'ctime' => time(),
                    'extension' => $extension,
                );
                $log_id = Db::name("BalanceLog")->insert($log);
                if ($log_id) {
                    // 提交事务
                    Db::commit();
                }
            }
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return false;
        }
    }

    /**
     * 获得随机字符串
     * @param $len             需要的长度
     * @param $special        是否需要特殊符号
     * @return string       返回随机字符串
     */
    public static function getRandomStr($len, $special = false)
    {
        $chars = array(
            "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k",
            "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v",
            "w", "x", "y", "z", "A", "B", "C", "D", "E", "F", "G",
            "H", "I", "J", "K", "L", "M", "N", "O", "P", "Q", "R",
            "S", "T", "U", "V", "W", "X", "Y", "Z", "0", "1", "2",
            "3", "4", "5", "6", "7", "8", "9"
        );

        if ($special) {
            $chars = array_merge($chars, array(
                "!", "@", "#", "$", "?", "|", "{", "/", ":", ";",
                "%", "^", "&", "*", "(", ")", "-", "_", "[", "]",
                "}", "<", ">", "~", "+", "=", ",", "."
            ));
        }

        $charsLen = count($chars) - 1;
        shuffle($chars);                            //打乱数组顺序
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $str .= $chars[mt_rand(0, $charsLen)];    //随机取出一位
        }
        return $str;
    }

    //根据ID计算唯一邀请码
    public static function createCode($Id)
    {
        static $sourceString = [
            0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10,
            'a', 'b', 'c', 'd', 'e', 'f',
            'g', 'h', 'i', 'j', 'k', 'l',
            'm', 'n', 'o', 'p', 'q', 'r',
            's', 't', 'u', 'v', 'w', 'x',
            'y', 'z'
        ];

        $num = $Id;
        $code = '';
        while ($num) {
            $mod = $num % 36;
            $num = (int)($num / 36);
            $code = "{$sourceString[$mod]}{$code}";
        }

        //判断code的长度
        if (empty($code[4]))
            str_pad($code, 5, '0', STR_PAD_LEFT);

        return $code;
    }

    /*
     * 依据邀请奖励规则发放奖励
     * uid  自身uid
     * parent_tree 上级用户ID树
     * type 场景类型    reg：注册/deposit:存入
     * detial
     */
    public static function invite_reward($uid, $parent_tree, $type, $detial = '')
    {
        $uids = explode('|', $parent_tree);
        if (empty($uids)) {
            return false;
        }
        //查询邀请奖励规则
        $reward_rule = Db::name("UserInviteReward")->where(['status' => 1])->select()->toArray();
        if (!empty($reward_rule)) {
            $reward_rule = array_column($reward_rule, NULL, 'type');
        }
        foreach ($uids as $k => $x) {
            $x = (int)$x;
            if ($x > 0) {
                if (isset($reward_rule[$k + 1]) && !empty($reward_rule[$k + 1])) {
                    $rule = $reward_rule[$k + 1];
                    $parent_need_allocation = $rule["{$type}_parent_reward"];
                    $offspring_need_allocation = $rule["{$type}_offspring_reward"];
                    $rule['parent_tree'] = $parent_tree;
                    if ($parent_need_allocation > 0) {
                        $need_detial = empty($detial) ? $uid : $detial;
                        self::updateBalance(1, $x, 'score', (int)$parent_need_allocation, $need_detial, 'invite_reward_' . (string)$rule['type'] . '_' . $type . '_parent_score_income', json_encode($rule));
                    }
                    if ($offspring_need_allocation > 0) {
                        self::updateBalance(1, $uid, 'score', (int)$offspring_need_allocation, $uid, 'invite_reward_' . (string)$rule['type'] . '_' . $type . '_offspring_score_income', json_encode($rule));
                    }
                }
            }
        }
        return true;
    }

    /**
     * 获取随机字符串
     *
     * @param $length
     * @param bool $numeric
     * @return string
     */
    public static function random($length, $numeric = false)
    {
        $seed = base_convert(md5(microtime() . $_SERVER['DOCUMENT_ROOT']), 16, $numeric ? 10 : 35);
        $seed = $numeric ? (str_replace('0', '', $seed) . '012340567890') : ($seed . 'zZ' . strtoupper($seed));

        if ($numeric) {
            $hash = '';
        } else {
            $hash = chr(rand(1, 26) + rand(0, 1) * 32 + 64);
            $length--;
        }

        $max = strlen($seed) - 1;
        for ($i = 0; $i < $length; $i++) {
            $hash .= $seed[mt_rand(0, $max)];
        }

        return $hash;
    }

    /**
     * 获取数字随机字符串
     *
     * @param bool $prefix 判断是否需求前缀
     * @param int $length 长度
     * @return string
     */
    public static function randomNum($prefix = false, $length = 8)
    {
        $str = $prefix ? $prefix : '';
        return $str . substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, $length);
    }

    public static function timetodate($c)
    {
        if ($c < 86400) {
            $time = explode(' ', gmstrftime('%H %M %S', $c));
            $duration = $time[0] . '小时' . $time[1] . '分' . $time[2] . '秒';
        } else {
            $time = explode(' ', gmstrftime('%j %H %M %S', $c));
            $duration = ($time[0] - 1) . '天' . $time[1] . '小时' . $time[2] . '分' . $time[3] . '秒';
        }
        return $duration;
    }

    /*
     * 推送方法
     */
    public static function send_push($type, $title, $content, $url = '', $uids = [])
    {
        Db::startTrans();
        $code = 0;
        try {
            $log = [
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'url' => $url,
                'status' => 1,
                'ctime' => time(),
            ];
            $data = [];
            if (!empty($uids)) {
                foreach ($uids as $x) {
                    $log['user_id'] = $x;
                    array_push($data, $log);
                }
            } else {
                $log['user_id'] = 0;
                array_push($data, $log);
            }
            if (!empty($data)) {
                $log_id = Db::name('PushLog')->insertAll($data);
                if ($log_id) {
                    Db::commit();
                    $code = 1;
                }
            }
        } catch (\Exception $e) {
        }
        return $code;
    }

    /*
     * 具体发送推送函数
     */
    public static function jpush_send_push($title, $content, $type, $url, $registration_id = 0, $os = 'android')
    {
        $code = 0;
        $message = '';
        $app_conf = cmf_get_option("app_config");
        if (empty($app_conf['jpush_app_key']) || empty($app_conf['jpush_master_secret'])) {
            $message = '请先设置推送配置';
        } else {
            $client = new \JPush\Client($app_conf['jpush_app_key'], $app_conf['jpush_master_secret']);

            $msg = array(
                'title' => $title,
                'extras' => array(
                    'type' => $type,
                    'content' => $content,
                    'url' => $url,
                ),
            );

            $pusher = $client->push()
                ->setPlatform('all')
                ->options(array(
                    // apns_production: 表示APNs是否生产环境，
                    // True 表示推送生产环境，False 表示要推送开发环境；如果不指定则默认为推送生产环境
                    'apns_production' => true, //APP_DEBUG ? false : true,
                ));
            if (!empty($registration_id)) {
                if (strtolower($os) == 'android') {
                    $pusher->androidNotification($content, $msg);
                } else {
                    $msg['alert'] = $title;
                    unset($msg['title']);
                    $pusher->iosNotification(['title' => $title, 'body' => $content], $msg);
                }
                $pusher->addRegistrationId($registration_id);
            } else {
                $pusher->addAllAudience();
                $pusher->androidNotification($content, $msg);
                $msg['alert'] = $title;
                unset($msg['title']);
                $pusher->iosNotification(['title' => $title, 'body' => $content], $msg);
            }
            try {
                $rst = $pusher->send();
                if (!empty($rst['body']['msg_id'])) {
                    $code = 1;
                    $message = '成功';
                } else {
                    $message = '推送失败';
                }
            } catch (\JPush\Exceptions\JPushException $e) {
                $message = '推送失败';
            }
        }
        return ['code' => $code, 'msg' => $message];
    }


    /*
     * 获取extension配置项
     */
    public static function get_extension($type)
    {
        $rst = Db::name("Extension")
            ->where(['type' => $type])
            ->value('detial');
        return empty($rst) ? null : $rst;
    }

    /**
     * 根据最近在线时间，计算直观描述，如：10分钟前，1分钟前，1天前
     */
    public static  function calcViewTime($time = 0)
    {
        if ($time == 0) {
            return '1000' . lang("天前");
        }
        $c = time() - $time;
        if ($c < 86400) {
            if ($c < 3600) {
                $t = sprintf("%.d", $c / 60);
                if ($t == 0) {
                    $duration = "刚刚";
                } else {
                    $duration = $t . lang("分钟前");
                }
            } else {
                $t = sprintf("%.d", $c / 3600);
                $duration = $t . lang("小时前");
            }
        } else {
            $t = sprintf("%.d", $c / 86400);
            $duration = $t . lang("天前");
        }
        return $duration;
    }

    /**
     * 根据最近在线时间，计算直观描述，如：10分钟，1分钟，1天
     */
    public static  function calcViewTime2($time = 0)
    {
        if ($time == 0) {
            return '1000' . lang("天");
        }
        $c = time() - $time;
        if ($c < 86400) {
            if ($c < 3600) {
                $t = sprintf("%.d", $c / 60);
                if ($t == 0) {
                    $duration = "刚刚";
                } else {
                    $duration = $t . lang("分钟");
                }
            } else {
                $t = sprintf("%.d", $c / 3600);
                $duration = $t . lang("小时");
            }
        } else {
            $t = sprintf("%.d", $c / 86400);
            $duration = $t . lang("天");
        }
        return $duration;
    }

    /**
     * 支付密码检测 
     */
    public static  function check_pay_password($password, $uid)
    {
        $user_info = Db::name("user")
            ->where("id = $uid and user_status = 1")
            ->field('has_paypwd,paypwd,paypwd_count,paypwd_last_time')
            ->find();
        if (empty($user_info)) {
            $ret = [0, '用户不存在'];
            return $ret;
        }
        if (empty($user_info['has_paypwd'])) {
            $ret = [0, '请先设置支付密码'];
            return $ret;
        }
        $paypwd_count = $user_info['paypwd_count'];
        if ($paypwd_count >= 5) {
            if (time() - $user_info['paypwd_last_time'] < 3600 * 2) {
                $ret = [0, '支付密码因多次输入错误已被冻结两个小时,请稍后重试'];
                return $ret;
            } else {
                $update_data = array('paypwd_count' => 0);
                $user_info = Db::name("user")->where("id = $uid")->update($update_data);
                $paypwd_count = 0;
            }
        }

        if (!cmf_compare_password('pay' . $password, $user_info['paypwd'])) {
            $update_data = array('paypwd_count' => $user_info['paypwd_count'] + 1, 'paypwd_last_time' => time());
            $user_info = Db::name("user")->where("id = $uid")->update($update_data);
            $ret = [0, '支付密码输入不正确'];
            return $ret;
        } else {
            $update_data = array('paypwd_count' => 0, 'paypwd_last_time' => time());
            $user_info = Db::name("user")->where("id = $uid")->update($update_data);
            $ret = [1, ''];
            return $ret;
        }
    }
}
