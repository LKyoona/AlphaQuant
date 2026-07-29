<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2019/3/26
 * Time: 14:26
 */

namespace api\user\model;


use api\common\model\CommonModel;

class UserMomentCommentModel extends CommonModel
{
    protected $hidden = ['create_time', 'delete_time', 'status','path','parent_id'];


}