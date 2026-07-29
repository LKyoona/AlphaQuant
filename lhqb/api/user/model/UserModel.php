<?php
// +----------------------------------------------------------------------
// | 文件说明：用户表关联model 
// +----------------------------------------------------------------------
// | Copyright (c) 2017 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: wuwu <15093565100@163.com>
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Date: 2017-7-26
// +----------------------------------------------------------------------
namespace api\user\model;

use think\Db;
use think\Model;

class UserModel extends Model
{
    protected $type = [
        'more' => 'array',
    ];


    /**
     * avatar 自动转化
     * @param $value
     * @return string
     */
    public function getAvatarAttr($value)
    {
        return cmf_gjl_user_avatar_url($value);
    }


    /**
     * 获取所有用户id+昵称
     */
    public static function getNickName()
    {
        //id为键名，nickname为键值
        $user = Db::name('user')->column('user_nickname', 'id');

        return $user;
    }    

    /**
     * 获取用户昵称，头像，国家（如果有备注，有限使用备注名称）
     */
    public static function getUserData($userId = 0)
    {
    // /**
    //     //获取当前登录人员设置的备注名称
    //     $subQuery = Db::table('im_user_name_remark')
    //         ->field('to_user_id,user_nickname,remark')
    //         ->where('user_id','=',$userId)
    //         ->buildSql();

    //     $userInfo = Db::table('im_user_data')
    //         ->alias('iud')
    //         ->join('im_country ic', 'iud.country_id = ic.id')
    //         ->join("{$subQuery} tmp", 'tmp.to_user_id = iud.user_id','left')
    //         ->field('iud.*,ic.national_flag,IFNULL(tmp.user_nickname,iud.user_nickname) user_nickname')
    //         ->select()
    //         ->toArray();*/
        $userInfo = Db::name('user')
            ->field('avatar,user_nickname,id as user_id')
            ->select()
            ->toArray();            
        $userInfo = array_column($userInfo, null, 'user_id');
        return $userInfo;
     
    }    
}
