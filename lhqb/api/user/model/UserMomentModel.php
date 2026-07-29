<?php


namespace api\user\model;


use api\common\model\CommonModel;
use api\user\service\JinglanIM;
use think\Log;

class UserMomentModel extends CommonModel
{
    /**
     * 用户动态重组数组，拼接用户昵称
     * @param $data 查询出来的所有的动态
     */
    public static function reformArray($data,$userId = 0){
/*
        //查询用户名称
        $userData = UserModel::getUserData($userId);//Db::name('user')->column('user_nickname', 'id');
        
        //转化：拼接图片路径，查询用户昵称
        $data = json_decode(json_encode($data), true);
        foreach ($data as $key => $value) {
        	print_r($value);
            $data[$key]['user_nickname'] = $userData[$value['user_id']]['user_nickname'];//昵称
            $data[$key]['avatar'] = $userData[$value['user_id']]['avatar'];//头像
            $data[$key]['national_flag'] = $userData[$value['user_id']]['national_flag'];//国家图标

            $data[$key]['create_time'] = JinglanIM::calcViewTime($data[$key]['create_time']);//时间转化

            $imageUrl = [];
            $image = json_decode($value['image'], true);
            if($image){
                foreach ($image as $img) {
                    if ($img['status'] < 2) {//status<2，表示未审核/已经审核，2表示审核未通过
                        $imageUrl[] = cmf_get_image_preview_url($img['image']);
                    }
                }
                $data[$key]['image_url'] = array_slice($imageUrl, 0, 3);
            }else{
                $data[$key]['image_url'] = [];
            }

            //去除多余字段
            unset($data[$key]['image']);
            unset($data[$key]['list_order']);
            unset($data[$key]['status']);
            unset($data[$key]['longitude']);
            unset($data[$key]['latitude']);
            unset($data[$key]['delete_time']);
        }
*/
        return $data;
    }


    public function comments(){
        return $this->hasMany('UserMomentCommentModel','moment_id','id')->where('status','=',1)->limit(3);
    }


}