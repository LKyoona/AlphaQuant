<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/14
 * Time: 17:53
 */

namespace app\admin\model;


use think\Db;
use think\Model;

class UserMomentCommentModel extends Model
{
    public static function getComments($momentId)
    {
        $info = Db::name('user_moment_comment')
            ->alias('iumc')
            ->where('moment_id', '=', $momentId)
            ->where('status', '<', 2)
            ->select();

        $info = json_decode(json_encode($info), true);
        $allUser = UserModel::getUserData();
        foreach ($info as $key => $value) {
            $info[$key]['user_nickname'] = $allUser[$value['user_id']]['user_nickname'];
            $info[$key]['to_user_nickname'] = $allUser[$value['to_user_id']]['user_nickname'] ?? '';
            $info[$key]['national_flag'] = $allUser[$value['user_id']]['national_flag'];
        }
        return $info;
    }

}