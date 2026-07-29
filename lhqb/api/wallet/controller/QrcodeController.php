<?php

/**
 * 生成二维码
 */
namespace api\wallet\controller;

use cmf\phpqrcode\QRcode;

class QrcodeController  {

    public function make($url) {
		header('Content-Type: image/png');
		//清除缓冲区，不清除的情况下  某些情况会出不来图片
		ob_clean();

    	$size = '10';
		
		$level = 'L';
		
		$QRcode = new QRcode();

		$QRcode::png($url, false, $level, $size);
		
		die();
    }
    

}

