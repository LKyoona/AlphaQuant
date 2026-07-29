<?php
// +----------------------------------------------------------------------
// | ThinkCMF [ WE CAN DO IT MORE SIMPLE ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013-2018 http://www.thinkcmf.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +---------------------------------------------------------------------
// | Author: Dean <zxxjjforever@163.com>
// +----------------------------------------------------------------------

namespace cmf\controller;

use think\Request;

class CaptchaController
{
    /**
     * captcha/new?height=50&width=200&font_size=25&length=4&bg=243,251,254&id=1
     * @param Request $request
     * @return \think\Response
     */
    public function index(Request $request)
    {
        $fontSize = (int)$request->param('font_size', 25);
        $fontSize = ($fontSize > 8 && $fontSize < 100) ? $fontSize : 25;

        $imageH = (int)$request->param('height', 38);
        $imageH = $imageH > 10 && $imageH < 100 ? $imageH : 38;

        $imageW = (int)$request->param('width', 120);
        $imageW = $imageW > 30 && $imageW < 200 ? $imageW : 120;

        $length = (int)$request->param('length', 4);
        $length = $length > 2 && $length <= 8 ? $length : 4;

        $bg = $request->param('bg', '255,255,255');
        $bgParts = array_map('intval', explode(',', (string)$bg));
        if (count($bgParts) < 3) {
            $bgParts = [255, 255, 255];
        }
        $bgParts = [
            max(0, min(255, $bgParts[0])),
            max(0, min(255, $bgParts[1])),
            max(0, min(255, $bgParts[2])),
        ];

        $id = (int)$request->param('id', 0);
        $captchaKey = 'captcha_code' . ($id > 0 && $id <= 5 ? '_' . $id : '');

        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        session($captchaKey, strtolower($code));

        $image = imagecreatetruecolor($imageW, $imageH);
        $bgColor = imagecolorallocate($image, $bgParts[0], $bgParts[1], $bgParts[2]);
        imagefilledrectangle($image, 0, 0, $imageW, $imageH, $bgColor);

        for ($i = 0; $i < 4; $i++) {
            $noise = imagecolorallocate($image, random_int(150, 220), random_int(150, 220), random_int(150, 220));
            imageline($image, random_int(0, $imageW), random_int(0, $imageH), random_int(0, $imageW), random_int(0, $imageH), $noise);
        }

        $textColor = imagecolorallocate($image, random_int(20, 80), random_int(20, 80), random_int(20, 80));
        $x = (int)(($imageW - ($length * $fontSize * 0.6)) / 2);
        $y = (int)(($imageH - $fontSize) / 2) + $fontSize - 2;

        $fontFile = CMF_ROOT . 'public/themes/admin_h/public/assets/fonts/arial.ttf';
        if (is_file($fontFile) && function_exists('imagettftext')) {
            imagettftext($image, $fontSize, random_int(-8, 8), max(5, $x), $y, $textColor, $fontFile, $code);
        } else {
            imagestring($image, 5, max(5, $x), max(2, (int)(($imageH - 15) / 2)), $code, $textColor);
        }

        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Type: image/png');
        imagepng($image);
        imagedestroy($image);
        exit;
    }
}
