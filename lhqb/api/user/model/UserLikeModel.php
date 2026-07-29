<?php


namespace api\user\model;

use api\common\model\CommonModel;

class UserLikeModel extends CommonModel
{

    /**
     * url   自动转化
     * @param $value
     * @return string
     */
    public function getUrlAttr($value)
    {
        $url = json_decode($value, true);
        if (!empty($url)) {
            $url = url($url['action'], $url['param'], true, true);
        } else {
            $url = '';
        }
        return $url;
    }

    /**
     * thumbnail 自动转化图片地址为绝对地址
     * @param $value
     * @return string
     */
    public function getThumbnailAttr($value)
    {
        if (!empty($value)) {
            $value = cmf_get_image_url($value);
        }

        return $value;
    }

}
