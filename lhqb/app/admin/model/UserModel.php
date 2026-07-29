<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 老猫 <thinkcmf@126.com>
// +----------------------------------------------------------------------
namespace app\admin\model;

use think\Model;
use think\Db;

class UserModel extends Model
{

    protected $type = [
        'more' => 'array',
    ];
    /**
     * 获取用户昵称，头像
     */
    public static function getUserData()
    {

        $userInfo = Db::name('user')
            ->field('id,avatar,user_nickname')
            ->select();

        //id为键名
        $user = [];
        foreach ($userInfo as $item) {
            $user[$item['id']] = $item;
        }
        return $user;
    }
}