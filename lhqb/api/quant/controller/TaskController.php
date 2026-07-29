<?php

namespace api\quant\controller;

use cmf\controller\RestBaseController;
use think\Db;
use think\Validate;
use api\common\service\Apibase;
use api\common\service\InviteRule;
use api\common\service\Pool;

class TaskController extends RestBaseController
{
    
    public function index()
    {
        $logs = DB::name('quant_robot_revenue')
            ->field('*')
            ->where(['deal_status' => 0])
            ->limit(0, 100)
            ->select()->toArray();
        if (empty($logs)) {
            die('none quant order process ...');
        }

        $app_conf = cmf_get_option('app_config');
        $rate1 = floatval($app_conf['quant_system_rate']);  /*系统分成比例*/
        $rate2 = floatval($app_conf['quant_level1_rate']);  /*一级分润比例*/
        $rate3 = floatval($app_conf['quant_level2_rate']); /*二级分润比例*/

        $quant_revenue_type = intval($app_conf['quant_revenue_type']);  //1 USDT  2 自定义燃料  如果是USDT才给上级分润,如果是燃料 不分
        $diy_invite_rule = intval($app_conf['diy_invite_rule']);

        $count = 0;
        foreach ($logs as $x) {
            //开始事务处理
            Db::startTrans();
            $id = $x['id'];
            $revenue = $x['revenue'];
            $coin_symbol =  'USDT'; //$x['money']
            $uid =  $x['uid'];
            if ($revenue <= 0) {
                DB::name('quant_robot_revenue')->where('id', $id)->update(['deal_status' => 1]);
                Db::commit();
                continue;
            }

            $reduce_revenue = $revenue * $rate1 / 100;  /*分润数量*/

            if ($quant_revenue_type == 1) {
                //获取用户钱包余额    
                $walletData = Db::name('wallet')
                    ->field("id,uid,cloud_balance")
                    ->where('uid', intval($uid))
                    ->where('type', 1)
                    ->where('coin_symbol', $coin_symbol)
                    ->find();
                if (!empty($walletData)) {
                    $user_balance = floatval($walletData['cloud_balance']);
                } else {
                    DB::name('quant_robot_revenue')->where('id', $id)->update(['deal_status' => 1]);
                    Db::commit();
                    continue;
                }
                // 数据库存储数据【wallet表】
                $result = Db::name('wallet')
                    ->where('id', $walletData['id'])
                    ->setDec('cloud_balance', $reduce_revenue);
                if (!$result) {
                    Db::rollback();
                    continue;
                }
                //写入交易日志
                $memo = '机器人收益分润扣除: ' . $reduce_revenue . ' ' . $coin_symbol;
                $balance_after = $user_balance - $reduce_revenue;
                $insert_data['uid']  = $walletData['uid'];
                $insert_data['type'] = 5; // 分润扣除 quant_profit_reduce
                $insert_data['wallet_type'] = 1; // 云端钱包
                $insert_data['wallet_id'] = $walletData['id'];
                $insert_data['coin_symbol'] =  $coin_symbol;
                $insert_data['from_address'] =  '';
                $insert_data['to_address'] =  'revenue_reduce';
                $insert_data['amount'] =  -$reduce_revenue;
                $insert_data['amount_before'] = $user_balance;
                $insert_data['amount_after'] = $balance_after;
                $insert_data['fee'] = 0;
                $insert_data['log_time'] = time();
                $insert_data['memo'] = $memo;
                $insert_data['blockhash'] = '';
                $insert_data['tx_id'] = '';
                $insert_data['transfer_status'] = 1;
                $result = Db::name('transfer_log')->insert($insert_data);
            } else {
                //type = 10  扣除
                //Apibase::updateBalance(10, $uid , 'balance', -$reduce_revenue, $id, 'revenue_reduce', json_encode($x));
                $model = Db::name("user");
                $amount = $model->where(['id' => $uid])->value('balance');
                $temp = [
                    'balance' => Db::raw("balance-" . abs($reduce_revenue))
                ];
                $set = $model->where(['id' => $uid])->update($temp);
                $new_balance =  $amount - $reduce_revenue;
                if (!$set) {
                    Db::rollback();
                    continue;
                }
                $log = array(
                    'type' => 10,
                    'user_id' => $uid,
                    'balance_type' => 'balance',
                    'change' => -$reduce_revenue,
                    'amount' => $new_balance,
                    'detial' => $id,
                    'detial_type' => 'revenue_reduce',
                    'ctime' => time(),
                    'extension' => json_encode($x),
                );
                $result = Db::name("BalanceLog")->insert($log);
            }

            if ($result) {
                // 给上级分润
                if ($quant_revenue_type == 1) {
                    //检测是否开启了自定义邀请规则
                    $user = Db::name('user')->where(['id' => $uid])->field('parent_user_id')->find();
                    if (!empty($user['parent_user_id'])) {
                        $parent_user = Db::name('user')->where(['id' => $user['parent_user_id']])->field('id,parent_user_id')->find();
                        if (!empty($parent_user)&&$rate2>0) {
                            $this->addBalance($parent_user['id'], $coin_symbol, $reduce_revenue * $rate2 / 100);
                            //2代
                            if (!empty($parent_user['parent_user_id'])&&$rate3>0) {
                                $parent_user2 = Db::name('user')->where(['id' => $parent_user['parent_user_id']])->field('id,parent_user_id')->find();
                                if (!empty($parent_user2)) {
                                    $this->addBalance($parent_user2['id'], $coin_symbol, $reduce_revenue * $rate3 / 100);
                                }
                            }
                        }
                    }
                    //end
                }
                $result = DB::name('quant_robot_revenue')->where('id', $id)->update(['deal_status' => 1]);
                if ($result) {
                    $count += 1;
                    Db::commit();
                } else {
                    Db::rollback();
                }
            } else {
                Db::rollback();
            }
        }


        echo 'ok cout:' . $count;
        die();
    }

    /*托管费用*/
    public function scoredec(){
        $logss = DB::name('quant_robot_score')
            ->field('*')
            ->where(['deal_status' => 0])
            ->limit(0, 100)
            ->select()->toArray();
            
        if (empty($logss)) {
            die('none score order process ...');
        }
        
        $app_conf = cmf_get_option('app_config');
        $rate1 = floatval($app_conf['quant_start_rate']);  /*托管费用比例 如果金额不足 机器人停止*/
        
        $count = 0;
        foreach ($logss as $x) {
            //开始事务处理
            Db::startTrans();
            $id = $x['id'];
            $revenue = $x['price'];
            $coin_symbol =  'USDT'; //$x['money']
            $uid =  $x['uid'];
            if ($revenue <= 0) {
                DB::name('quant_robot_score')->where('id', $id)->update(['deal_status' => 1]);
                Db::commit();
                continue;
            }

            $reduce_revenue = $revenue * $rate1 / 100;  /*机器人收益分成金额USDT*/

            $stop_robot = 0;

            //获取用户钱包余额    
            $walletData = Db::name('wallet')
                ->field("id,uid,cloud_balance")
                ->where('uid', intval($uid))
                ->where('type', 1)
                ->where('coin_symbol', $coin_symbol)
                ->find();
            if (!empty($walletData)) {
                $user_balance = floatval($walletData['cloud_balance']);

                if ($reduce_revenue > $user_balance) {
                    $stop_robot = 1;
                }
            } else {
                DB::name('quant_robot_score')->where('id', $id)->update(['deal_status' => 1]);
                Db::commit();
                continue;
            }
            // 数据库存储数据【wallet表】
            $result = Db::name('wallet')
                ->where('id', $walletData['id'])
                ->setDec('cloud_balance', $reduce_revenue);
            if (!$result) {
                Db::rollback();
                continue;
            }
            //写入交易日志
            $memo = '机器人收益分润扣除: ' . $reduce_revenue . ' ' . $coin_symbol;
            $balance_after = $user_balance - $reduce_revenue;
            $insert_data['uid']  = $walletData['uid'];
            $insert_data['type'] = 23; // 分润扣除 quant_profit_reduce
            $insert_data['wallet_type'] = 1; // 云端钱包
            $insert_data['wallet_id'] = $walletData['id'];
            $insert_data['coin_symbol'] =  $coin_symbol;
            $insert_data['from_address'] =  '';
            $insert_data['to_address'] =  'score_reduce';
            $insert_data['amount'] =  -$reduce_revenue;
            $insert_data['amount_before'] = $user_balance;
            $insert_data['amount_after'] = $balance_after;
            $insert_data['fee'] = 0;
            $insert_data['log_time'] = time();
            $insert_data['memo'] = $memo;
            $insert_data['blockhash'] = '';
            $insert_data['tx_id'] = '';
            $insert_data['transfer_status'] = 1;
            $result = Db::name('transfer_log')->insert($insert_data);
            

            if ($result) {
                

                $res = DB::name('quant_robot_score')->where('id', $id)->update(['deal_status' => 1]);
                if ($res) {

                    if ($stop_robot == 1) {
                        $update_data = array('status' => 0, 'values_str' => '', 'show_msg' => '');
                        $res = DB::name('quant_robot')->where('id', $x['qrobot_id'])->update($update_data);

                        if ($res) {
                            $count += 1;
                            Db::commit();
                        }else{
                            Db::rollback();
                        }
                    }else{
                        $count += 1;
                        Db::commit();
                    }
                    
                } else {
                    Db::rollback();
                }
            } else {
                Db::rollback();
            }
        }


        echo 'ok cout:' . $count;
        die();
        
    }
    
    
    /**
     * 机器人收益分润定时任务
     */
    public function indexaa()
    {
        $logs = DB::name('quant_robot_revenue')
            ->field('*')
            ->where(['deal_status' => 0])
            ->limit(0, 100)
            ->select()->toArray();
        if (empty($logs)) {
            die('none quant order process ...');
        }

        $app_conf = cmf_get_option('app_config');
        $rate1 = floatval($app_conf['quant_revenue_rate']);  /*收益分成 如果金额不足 机器人停止*/

        $quant_revenue_type = intval($app_conf['quant_revenue_type']);  //1 USDT  2 自定义燃料  如果是USDT才给上级分润,如果是燃料 不分

        $count = 0;
        foreach ($logs as $x) {
            //开始事务处理
            Db::startTrans();
            $id = $x['id'];
            $revenue = $x['revenue'];
            $coin_symbol =  'USDT'; //$x['money']
            $uid =  $x['uid'];
            if ($revenue <= 0) {
                DB::name('quant_robot_revenue')->where('id', $id)->update(['deal_status' => 1]);
                Db::commit();
                continue;
            }

            $reduce_revenue = $revenue * $rate1 / 100;  /*机器人收益分成金额USDT*/

            $stop_robot = 0;

            //获取用户钱包余额    
            $walletData = Db::name('wallet')
                ->field("id,uid,cloud_balance")
                ->where('uid', intval($uid))
                ->where('type', 1)
                ->where('coin_symbol', $coin_symbol)
                ->find();
            if (!empty($walletData)) {
                $user_balance = floatval($walletData['cloud_balance']);

                if ($reduce_revenue > $user_balance) {
                    $stop_robot = 1;
                }
            } else {
                DB::name('quant_robot_revenue')->where('id', $id)->update(['deal_status' => 1]);
                Db::commit();
                continue;
            }
            // 数据库存储数据【wallet表】
            $result = Db::name('wallet')
                ->where('id', $walletData['id'])
                ->setDec('cloud_balance', $reduce_revenue);
            if (!$result) {
                Db::rollback();
                continue;
            }
            //写入交易日志
            $memo = '机器人收益分润扣除: ' . $reduce_revenue . ' ' . $coin_symbol;
            $balance_after = $user_balance - $reduce_revenue;
            $insert_data['uid']  = $walletData['uid'];
            $insert_data['type'] = 5; // 分润扣除 quant_profit_reduce
            $insert_data['wallet_type'] = 1; // 云端钱包
            $insert_data['wallet_id'] = $walletData['id'];
            $insert_data['coin_symbol'] =  $coin_symbol;
            $insert_data['from_address'] =  '';
            $insert_data['to_address'] =  'revenue_reduce';
            $insert_data['amount'] =  -$reduce_revenue;
            $insert_data['amount_before'] = $user_balance;
            $insert_data['amount_after'] = $balance_after;
            $insert_data['fee'] = 0;
            $insert_data['log_time'] = time();
            $insert_data['memo'] = $memo;
            $insert_data['blockhash'] = '';
            $insert_data['tx_id'] = '';
            $insert_data['transfer_status'] = 1;
            $result = Db::name('transfer_log')->insert($insert_data);
            

            if ($result) {
                $user = Db::name('user')->where(['id' => $uid])->field('parent_user_id')->find();

                if (!empty($user['parent_user_id'])) {
                    $parent_user = Db::name('user')->where(['id' => $user['parent_user_id']])->field('id,parent_user_id,level_id')->find();
                    if (!empty($parent_user)) {
                        $rate_level = Db::name('user_level')->where('id',$parent_user['level_id'])->value('revenue_rate');
                        //转换成燃料
                        $exchange_rate = $revenue*10*$rate_level/100;
                        Apibase::updateBalance(1, $parent_user['id'], 'balance', $exchange_rate, $id, 'revenue', "分润");
                    }
                }

                $result = DB::name('quant_robot_revenue')->where('id', $id)->update(['deal_status' => 1]);
                if ($result) {

                    if ($stop_robot == 1) {
                        $update_data = array('status' => 0, 'values_str' => '', 'show_msg' => '');
                        $res = DB::name('quant_robot')->where('id', $x['qrobot_id'])->update($update_data);

                        if ($res) {
                            $count += 1;
                            Db::commit();
                        }else{
                            Db::rollback();
                        }
                    }else{
                        $count += 1;
                        Db::commit();
                    }
                    
                } else {
                    Db::rollback();
                }
            } else {
                Db::rollback();
            }
        }


        echo 'ok cout:' . $count;
        die();
    }


    // 分润增加 quant_profit_share
    public function addBalance($uid, $coin_symbol, $revenue)
    {
        if ($revenue <= 0) {
            return true;
        }
        //获取用户钱包余额    
        $walletData = Db::name('wallet')
            ->field("id,uid,cloud_balance")
            ->where('uid', intval($uid))
            ->where('type', 1)
            ->where('coin_symbol', $coin_symbol)
            ->find();

        if (!empty($walletData)) {
            $user_balance = floatval($walletData['cloud_balance']);
        } else {
            return true;
        }
        $wallet_id = $walletData['id'];
        $memo = '机器人收益分润返利: ' . $revenue . ' ' . $coin_symbol;
        $result = Db::name('wallet')
            ->where('id', $wallet_id)
            ->setInc('cloud_balance', $revenue);

        if ($result) {
            //写入交易日志
            $balance_after = $user_balance + $revenue;
            $insert_data['uid'] = $walletData['uid'];
            $insert_data['type'] = 6; // 分润增加 quant_profit_share
            $insert_data['wallet_type'] = 1; // 云端钱包
            $insert_data['wallet_id'] = $walletData['id'];
            $insert_data['coin_symbol'] =  $coin_symbol;
            $insert_data['from_address'] =  '';
            $insert_data['to_address'] =  '';
            $insert_data['amount'] =  $revenue;
            $insert_data['amount_before'] = $user_balance;
            $insert_data['amount_after'] = $balance_after;
            $insert_data['fee'] = 0;
            $insert_data['log_time'] = time();
            $insert_data['memo'] = $memo;
            $insert_data['blockhash'] = '';
            $insert_data['tx_id'] = '';
            $insert_data['transfer_status'] = 1;
            $result = Db::name('transfer_log')->insert($insert_data);
            return true;
        }
        return false;
    }

    public function poolRevenue()
    {
        $pool_1_balance = Pool::getBalance(3);
        if ($pool_1_balance < 1) {
            echo '分红额度<1';
            die();
        }
        $where = array();
        //$where['is_partner'] = 1;
        $time_now = time();
        $data =  Db::name('user')
            ->where($where)
            ->where("user_level > 1")
            ->field('id,user_level')
            ->select();
        if(empty($data)){
            echo '没有可分红的人';
            die();     
        }      
        $count2 = 0;
        $count3 = 0;
        $count4 = 0;
        $level2_reward = 0;
        $level3_reward = 0;
        $level4_reward = 0;
        foreach ($data as $item) {
            $level = $item['user_level'];
            if($level == 2){
                $count2 = $count2 +1 ;
            }
            if($level == 3){
                $count3 = $count3 +1 ;
            }    
            if($level == 4){
                $count4 = $count4 +1 ;
            }                    
        }
        if($count2>0){
            $level2_reward = $pool_1_balance / 3/ $count2;
        }
        if($count3>0){
            $level3_reward = $pool_1_balance / 3/ $count3;
        }   
        if($count4>0){
            $level4_reward = $pool_1_balance / 3/ $count4;
        }             
        //开始分红
        Db::startTrans();
        foreach ($data as $item) {
            $uid = $item['id'];
            $level = $item['user_level'];
            $coin_symbol = 'USDT';
            $related_id = 0;
            $flag = "revenue";
            $memo = "每日分红";
            if($level == 2){
                $revenue = $level2_reward;
            }
            if($level == 3){
                $revenue = $level3_reward;
            }    
            if($level == 4){
                $revenue = $level4_reward;
            } 
            Pool::change($uid, $related_id, -$revenue, $flag, $memo, 3);
            //查询用户对应币种余额
            $cloud_wallet = Db::name("Wallet")->where(['uid' => $uid, 'coin_symbol' => $coin_symbol, 'type' => 1])->field('id,coin_symbol,cloud_balance,address')->find();
            if (empty($cloud_wallet)) {
                //Db::rollback();
                //$this->error("查询不到{$uid}云端钱包账户信息，请稍后重试");
                continue;
            }
            $temp = [
                'cloud_balance' => Db::raw("cloud_balance+" . $revenue)
            ];
            $set = Db::name("Wallet")->where(['id' => $cloud_wallet['id']])->update($temp);
            if ($set <= 0) {
                //Db::rollback();
                //$this->error("用户{$uid}云端钱包余额变更失败，请稍后重试");
                continue;
            }
            $cloud_balance = $cloud_wallet['cloud_balance'];
            $transfer_log = [
                'uid' => $uid,
                'type' => 8,
                'wallet_type' => 1,
                'related_id' => 0,
                'wallet_id' => $cloud_wallet['id'],
                'coin_symbol' => $coin_symbol,
                'from_address' => 'daily_revenue',
                'to_address' => $cloud_wallet['address'],
                'amount' => $revenue,
                'amount_before' => $cloud_balance,
                'amount_after' => $cloud_balance + $revenue,
                'fee' => 0,
                'log_time' => time(),
                'tx_id' => '',
                'memo' => '每日分红',
                'transfer_status' => 1,
            ];
            $log_id = intval(Db::name("TransferLog")->insertGetId($transfer_log));
            if (empty($log_id)) {
                //Db::rollback();
                //$this->error("用户{$uid}钱包日志写入失败，请稍后重试");
                continue;
            }
        }
        Db::commit();
        echo  "分红成功";
        die();
    }
}
