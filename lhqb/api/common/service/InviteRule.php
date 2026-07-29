<?php


namespace api\common\service;

use think\Controller;
use think\Db;
use Think\Exception;
use api\common\service\Pool;
use api\common\service\Apibase;

class InviteRule extends Controller
{

    protected static $enable_user_level_rule = 1;
    protected static $enable_quant_revenue_rule = 1;
    protected static $enable_buy_package_rule = 0;
    protected static $enable_buy_cdkey_rule = 1;
    protected static $enable_recharge_rule = 0;

    
    
     //检测用户级别是否可以提升
    public static function checkUserLevelCanUpgrade($uid)
    {
        if (self::$enable_user_level_rule !== 1) {
            return;
        }
        $user_level = Db::name('user')->where('id', $uid)->value('level_id');
        
        if ($user_level >= 0 && $user_level <= 4) {
            $min_num = Db::name('user_level')->where('id', $user_level + 1)->value('min_num'); /*最低推荐人数*/
            $max_num = Db::name('user_level')->where('id', $user_level + 1)->value('max_num'); /*最高推荐人数*/
            $user_zhitui_num = self::getZhituiNum($uid);
            if ($user_zhitui_num>=$min_num&&$user_zhitui_num<=$max_num) {
                $update = array('level_id' => $user_level + 1);
                // if ($user_level + 1 == 4) {  //成为合伙人
                //     $update = array('is_partner' => 1);
                // }
                Db::name('user')->where('id', $uid)->update($update);
                //继续检测
                self::checkUserLevelCanUpgrade($uid);
            }
        }
    }
    //量化分润后操作
    public static function  quantRevenue($uid, $coin_symbol, $amount)
    {
        if (self::$enable_quant_revenue_rule !== 1) {
            return;
        }
        //两级分润
        $app_conf = cmf_get_option('app_config');
        $rate2 = floatval($app_conf['quant_level1_rate']);
        $rate3 = floatval($app_conf['quant_level2_rate']);        
        $user = Db::name('user')->where(['id' => $uid])->field('parent_user_id')->find();
        if (!empty($user['parent_user_id'])) {
            $parent_user = Db::name('user')->where(['id' => $user['parent_user_id']])->field('id,parent_user_id')->find();
            if (!empty($parent_user)) {
                $memo = "直推分润";
                self::addBalance($parent_user['id'], $coin_symbol, $amount * $rate2 / 100, $memo);
                //2代
                if (!empty($parent_user['parent_user_id'])) {
                    $parent_user2 = Db::name('user')->where(['id' => $parent_user['parent_user_id']])->field('id,parent_user_id')->find();
                    if (!empty($parent_user2)) {
                        $memo = "间推分润";
                        self::addBalance($parent_user2['id'], $coin_symbol, $amount * $rate2 / 100, $memo);
                    }
                }
            }
        }
        //团队奖励
        $parent_tree =  Db::name('user')->where('id', $uid)->value('parent_tree');
        $uids = explode('|', $parent_tree);
        if (empty($uids)) {
            return false;
        }
        $reward_rule = Db::name("user_level")->select()->toArray();
        if (!empty($reward_rule)) {
            $reward_rule = array_column($reward_rule, "revenue_rate", 'id');
        }
        //$user_level = Db::name('user')->where('id', $uid)->value('level_id');
        $max_rate = 0;
        $max_level = 0;
        $pingji = array();
        foreach ($uids as $x) {
            $level = Db::name('user')->where('id', $x)->value('level_id');
            if ($level > 1) {
                $rate = $reward_rule[$level];
                $final_rate = 0;
                if ($level == $max_level) {
                    if(!isset($pingji[$level])){
                        $final_rate = 0;  //平级 0
                        $pingji[$level] = 1;
                        $memo = "团队平级奖({$level})";
                    }
                }
                if ($level < $max_level) {
                    continue;
                }
                
                if ($level > $max_level) {
                    if ($rate > $max_rate) {
                        $final_rate = $rate - $max_rate;
                        $max_rate = $rate;
                        $memo = "团队奖({$rate}-{$max_rate})";
                    }
                    $max_level = $level;
                }
                
                if($final_rate > 0){
                    if(empty($memo)){
                        $memo = "团队奖";
                    }
                    self::addBalance($x, $coin_symbol, $amount * $final_rate / 100, $memo);
                }
            }
            
        }
        // //合伙人分红池
        // //年度
        // $app_conf = cmf_get_option('app_config');
        // $flag = "buy_package";
        // $partner_year_rate = floatval($app_conf['partner_year_rate']);  //年度分红比例
        // $pool_memo = "购买套餐" . $amount . "*" . $partner_year_rate . "%";
        // Pool::change($uid, 0,  $amount * $partner_year_rate / 100, $flag, $pool_memo, 4);
    }

    //购买套餐后操作
    public static function buyPackage($uid, $coin_symbol, $amount)
    {
        if (self::$enable_buy_package_rule !== 1) {
            return;
        }

        $parent_tree =  Db::name('user')->where('id', $uid)->value('parent_tree');

        $uids = explode('|', $parent_tree);
        if (empty($uids)) {
            return false;
        }
        $reward_rule = Db::name("user_level")->select()->toArray();
        if (!empty($reward_rule)) {
            $reward_rule = array_column($reward_rule, "profit_rate", 'id');
        }
        //$user_level = Db::name('user')->where('id', $uid)->value('level_id');
        $max_rate = 0;
        $left_rate = 70;
        $last_level = 0;
        foreach ($uids as $x) {
            $level = Db::name('user')->where('id', $x)->value('level_id');
            $last_level = $level;
            if ($left_rate > 0) {
                if ($level > 1) {
                    $rate = $reward_rule[$level];
                    $final_rate = 0;
                    if ($level > $last_level) {
                        if ($rate > $max_rate) {
                            $final_rate = $rate - $max_rate;
                            $max_rate = $rate;
                        }
                    }
                    if ($level == $last_level) {
                        $final_rate = 0;  //平级 0
                    }
                    if ($level < $last_level) {
                        break;
                    }
                    $final_rate = min($final_rate, $left_rate);
                    $left_rate = $left_rate  - $final_rate;
                    $memo = "分润";
                    self::addBalance($x, $coin_symbol, $amount * $final_rate / 100, $memo);
                }
            }
        }
        //合伙人分红池
        //年度
        $app_conf = cmf_get_option('app_config');
        $flag = "buy_package";
        $partner_year_rate = floatval($app_conf['partner_year_rate']);  //年度分红比例
        $pool_memo = "购买套餐" . $amount . "*" . $partner_year_rate . "%";
        Pool::change($uid, 0,  $amount * $partner_year_rate / 100, $flag, $pool_memo, 4);
    }

    //购买激活码后操作
    public static function buyCdkey($uid, $coin_symbol, $amount)
    {
        if (self::$enable_buy_cdkey_rule !== 1) {
            return;
        }
        //两级分润
        $app_conf = cmf_get_option('app_config');
        $rate2 = floatval($app_conf['quant_level1_rate']);
        $rate3 = floatval($app_conf['quant_level2_rate']);        
        $user = Db::name('user')->where(['id' => $uid])->field('parent_user_id')->find();
        if (!empty($user['parent_user_id'])) {
            $parent_user = Db::name('user')->where(['id' => $user['parent_user_id']])->field('id,parent_user_id')->find();
            if (!empty($parent_user)) {
                $memo = "分润";
                self::addBalance($parent_user['id'], $coin_symbol, $amount * $rate2 / 100, $memo);
                //2代
                if (!empty($parent_user['parent_user_id'])) {
                    $parent_user2 = Db::name('user')->where(['id' => $parent_user['parent_user_id']])->field('id,parent_user_id')->find();
                    if (!empty($parent_user2)) {
                        $memo = "分润";
                        self::addBalance($parent_user2['id'], $coin_symbol, $amount * $rate3 / 100, $memo);
                    }
                }
            }
        }        
    }

    //用户充值后操作
    public static function recharge($uid, $coin_symbol, $amount, $txid)
    {
        if (self::$enable_recharge_rule !== 1) {
            return;
        }
        if ($coin_symbol == 'USDT') {
            //转换燃料
            $app_conf = cmf_get_option('app_config');
            $exchange_rate = floatval($app_conf['quant_usdt_to_balance_rate']);  //USDT转换成燃料
            if ($exchange_rate > 0) {
                //扣除原有的币种
                $wallet_data = Db::name('wallet')
                    ->where('coin_symbol', $coin_symbol)
                    ->where('uid', $uid)
                    ->field('cloud_balance,id')->find();
                $walletId = $wallet_data['id'];
                $coin_balance = $wallet_data['cloud_balance'];
                $result = Db::name('wallet')
                    ->where('id', $walletId)
                    ->setDec('cloud_balance', $amount);
                //写入交易日志
                $insert_data['uid']  = $uid;
                $insert_data['type'] =  7; //兑换燃料
                $insert_data['wallet_type'] =  1;
                $insert_data['wallet_id'] = $walletId;
                $insert_data['coin_symbol'] =  $coin_symbol;
                $insert_data['from_address'] =  "";
                $insert_data['to_address'] =  "balance";
                $insert_data['amount'] =  -$amount;
                $insert_data['amount_before'] =   $coin_balance;
                $insert_data['amount_after'] =   $coin_balance - $amount;
                $insert_data['fee'] =   0;
                $insert_data['log_time'] =  time();
                $insert_data['memo'] = "兑换燃料1:" . $exchange_rate;
                $insert_data['blockhash'] = "";
                $insert_data['tx_id'] = $txid;
                $insert_data['transfer_status'] = 1;
                $result = Db::name('transfer_log')->insert($insert_data);
                //转换成燃料
                Apibase::updateBalance(1, $uid, 'balance', $amount * $exchange_rate, $txid, 'recharge', "");
            }
            $quant_revenue_type = intval($app_conf['quant_revenue_type']);  //收益扣除方式
            if ($quant_revenue_type == 2) { //如果是扣除燃料，USDT统计
                //注入分红池
                $related_id = $txid;
                $flag = "recharge";
                //月度
                $partner_month_rate = floatval($app_conf['partner_month_rate']);  //月度分红比例
                $pool_memo = "充值" . $amount . "*" . $partner_month_rate . "%";
                Pool::change($uid, $related_id,  $amount * $partner_month_rate / 100, $flag, $pool_memo, 3);
                //年度
                $partner_year_rate = floatval($app_conf['partner_year_rate']);  //年度分红比例
                $pool_memo = "充值" . $amount . "*" . $partner_year_rate . "%";
                Pool::change($uid, $related_id,  $amount * $partner_year_rate / 100, $flag, $pool_memo, 4);
            }
            //检测用户等级
            self::checkUserLevelCanUpgradeByRecharge($uid, $coin_symbol, $amount);
            //充值邀请分润
            self::rechargeInviteReward($uid, $coin_symbol, $amount);
        }
    }



    public static function rechargeInviteReward($uid, $coin_symbol, $amount)
    {
        $parent_tree =  Db::name('user')->where('id', $uid)->value('parent_tree');

        $uids = explode('|', $parent_tree);
        if (empty($uids)) {
            return false;
        }
        $reward_rule = Db::name("user_level")->select()->toArray();
        if (!empty($reward_rule)) {
            $reward_rule = array_column($reward_rule, "profit_rate", 'id');
        }
        //$user_level = Db::name('user')->where('id', $uid)->value('level_id');
        $max_rate = 0;
        $left_rate = 60;
        $last_level = 0;
        foreach ($uids as $x) {
            $level = Db::name('user')->where('id', $x)->value('level_id');
            $last_level = $level;
            if ($left_rate > 0) {
                if ($level > 1) {
                    $rate = $reward_rule[$level];
                    $final_rate = 0;
                    if ($level > $last_level) {
                        if ($rate > $max_rate) {
                            $final_rate = $rate - $max_rate;
                            $max_rate = $rate;
                        }
                    }
                    if ($level == $last_level) {
                        $final_rate = 5;
                    }
                    if ($level < $last_level) {
                        break;
                    }
                    $final_rate = min($final_rate, $left_rate);
                    $left_rate = $left_rate  - $final_rate;
                    $memo = "分润";
                    self::addBalance($x, $coin_symbol, $amount * $final_rate / 100, $memo);
                }
            }
        }
    }

    public static function checkUserLevelCanUpgradeByRecharge($uid, $coin_symbol, $amount)
    {
        $app_conf = cmf_get_option('app_config');
        $max_partner_num = intval($app_conf['partner_num']);
        $partner_recharge_num = floatval($app_conf['partner_recharge_num']);
        $partner_gas_num = floatval($app_conf['partner_gas_num']);
        //检测合伙人
        if ($partner_recharge_num > 0) {
            if ($amount >= $partner_recharge_num) {
                $is_partner = Db::name('user')->where('id', $uid)->value('is_partner');
                if ($is_partner == 0) {
                    //需要当合伙人
                    //检测合伙人数量是否满了
                    $partner_count = Db::name('user')->where('is_partner', 1)->count();
                    if ($partner_count < $max_partner_num) { //可以当合伙人
                        $update = array('is_partner' => 1, 'level_id' => 5);
                        Db::name('user')->where('id', $uid)->update($update);
                    }
                }
            }
        }
        //检测普通用户
        $need_recharge_num = Db::name('user_level')->where('id', 2)->value('need_recharge_num');
        if ($amount >= $need_recharge_num) {
            $user_level = Db::name('user')->where('id', $uid)->value('level_id');
            if ($user_level < 2) {
                $update = array('level_id' => 2);
                Db::name('user')->where('id', $uid)->update($update);
            }
        }
    }

    //写日志加币
    public static function addBalance($uid, $coin_symbol, $amount, $memo)
    {
        //写日志加币
        $cloud_wallet = Db::name("Wallet")->where(['uid' => $uid, 'coin_symbol' => $coin_symbol, 'type' => 1])->field('id,coin_symbol,cloud_balance,address')->find();
        $cloud_balance = $cloud_wallet['cloud_balance'];
        $revenue = $amount;
        if ($revenue > 0) {
            $transfer_log = [
                'uid' => $uid,
                'type' => 6, //分润增加
                'wallet_type' => 1,
                'related_id' => 0,
                'wallet_id' => $cloud_wallet['id'],
                'coin_symbol' => $coin_symbol,
                'from_address' => '',
                'to_address' => $cloud_wallet['address'],
                'amount' => $revenue,
                'amount_before' => $cloud_balance,
                'amount_after' => $cloud_balance + $revenue,
                'fee' => 0,
                'log_time' => time(),
                'tx_id' => '',
                'memo' => $memo,
                'transfer_status' => 1,
            ];
            Db::name("TransferLog")->insertGetId($transfer_log);
            Db::name("Wallet")->where(['uid' => $uid, 'coin_symbol' => $coin_symbol, 'type' => 1])->update([
                'cloud_balance' => Db::raw("cloud_balance+" . $revenue),
            ]);
        }
    }

    public  static function getInviteUserCountByLevel($uid, $level_id)
    {
        return Db::name('user')->where('level_id', $level_id)->where('parent_user_id', $uid)->count();
    }

    public  static function getTeamNum($uid)
    {
        return Db::name('user')->where('parent_tree', 'like', "%{$uid}%")->count();
    }


    public  static function getZhituiNum($uid)
    {
        return Db::name('user')->where('parent_user_id', $uid)->count();
    }
}
