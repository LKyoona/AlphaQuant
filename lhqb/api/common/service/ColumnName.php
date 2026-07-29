<?php


namespace api\common\service;

class ColumnName
{
    public static $system_info = [
        'score' => [
            'name' => 'Q Point',
            'symbol' => 'Q Point'
        ],
    ];

    public static $db_balance_log = [
        'type' => [
            '1' => '+',
            '10' => '-',
        ],
        'balance_type' => [
            'balance' => '',
            'score' => '积分',
        ],
        'detial_type' => [
            'invite_reward_1_reg_parent_score_income' => '一级会员注册奖励',
            'invite_reward_2_reg_parent_score_income' => '二级会员注册奖励',
            'invite_reward_3_reg_parent_score_income' => '三级会员注册奖励',
            'invite_reward_1_reg_offspring_score_income' => '推荐奖励',
            'invite_reward_1_deposit_parent_score_income' => '一级会员理财奖励',
            'invite_reward_2_deposit_parent_score_income' => '二级会员理财奖励',
            'invite_reward_3_deposit_parent_score_income' => '三级会员理财奖励',
            'exchange_good' => '兑换商品',
            'invite_user_reg' => '邀请用户注册任务',
            'invite_user_deposit' => '邀请用户理财任务',
            'daily_sign' => '每日签到任务',
            'newbie_deposit' => '新人理财任务',
            'newbie_reg' => '新人注册任务',
            'send_redenvelope' => '发送红包任务',
            'system_add' => '系统赠送',
            'system_reduce' => '系统扣除',
            'recharge' => '充值兑换',
            'revenue_reduce' => '分润扣除',
            'transfer' => '转账',
        ],
    ];

    public static $db_wealth_log = [
        'type' => [
            '1' => '划入',
            '10' => '划出',
            '11' => '划出手续费',
            '20' => '收益划入',
        ],
        'detial_type' => [
            'cloud_balance_order_wealth_1' => '来自：支付账户划入',
            'cloud_balance_order_wealth_2' => '来自：支付账户划入',
            'cloud_balance_order_wealth_3' => '来自：支付账户划入',
            'order_wealth_revenue_1' => '来自：PoS挖矿收益',
            'order_wealth_revenue_2' => '来自：币计划收益',
            'order_wealth_revenue_3' => '来自：币计划收益',
            'order_wealth_redeem_1_cloud_balance' => '发到：支付账户',
            'order_wealth_redeem_2_cloud_balance' => '发到：支付账户',
            'order_wealth_redeem_3_cloud_balance' => '发到：支付账户',
            'order_wealth_redeem_1_cloud_balance_fee' => '赎回手续费',
            'order_wealth_redeem_2_cloud_balance_fee' => '赎回手续费',
            'order_wealth_redeem_3_cloud_balance_fee' => '赎回手续费',
        ],
    ];

    public static $db_push_log = [
        'type' => [
            '1' => '广播资讯',
            '2' => '广播资讯',
            '3' => '私信',
        ],
        'status' => ['已删除', '排队推送', '已处理'],
    ];

    public static $db_wealth_package = [
        'type' => [
            '1' => 'POS挖矿',
            '2' => '币计划活期',
            '3' => '币计划定期',
        ],
        'is_hot' => ['普通', '热门推荐'],
        'status' => ['已删除', '正常', '未发布'],
    ];

    public static $db_lending_ads = [
        'type' => [
            '1' => '法币广告',
            '2' => '币币广告',
        ],
        'split' => ['不允许分拆', '允许分拆'],
        'status' => ['-2' => '已平仓', '-1' => '等待平仓处理', '0' => '已结束', '1' => '正常', '2' => '暂时下架'],
    ];

    public static $db_transfer_log = [
        'type' => [
            '1' => '云端转出',
            '2' => '云端充值',
            '3' => '购买激活码',
            '4' => '信用值扣除',
            '5' => '分润扣除',
            '6' => '分润增加',
            '7' => '兑换燃料',
            '8' => '合伙人分红',
            '9' => '购买套餐',
            '21' => '系统增加',
            '22' => '系统扣除',
            '23' => '托管费用',
            '24' => '中奖',
        ],
        'transfer_status' => [
            '-1' => '失败',
            '0' => '等待处理',
            '2' => '交易中',
            '1' => '交易成功',
        ],
    ];
    
    public static $db_score_log = [
        'type' => [
            '1' => '分润增加',
            '2' => '抽奖',
        ],
    ];

    public static $db_lending_orders = [
        'type' => [
            '1' => '法币',
            '2' => '币币',
        ],
        'lending_status' => ['已完成', '待还款', '待成交'],
        'invest_status' => ['已完成', '待回款', '待成交'],
    ];
}
