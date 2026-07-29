<?php

namespace api\home\controller;

use cmf\controller\RestBaseController;
use think\Db;


class CrawlerController extends RestBaseController
{



    //更新行情信息
    public function crawl()
    {

        //测试阶段 先采集huobipro的信息 更新到全部

        //外网爬虫地址
        $base_url = "http://qt.com/ccxt/crawler.php";

       

        //查询币种 先采集id 1-6的行情
        for($i=1;$i<7;$i++){
             $tickerData = Db::name('ticker')
            ->field('id,exchange_class,market')
            ->where('id', $i)
            ->where('status', 1)
            ->find();
            if(!empty($tickerData)){
                 $exchange = "huobipro";//$tickerData['exchange_class'];
                 $market = $tickerData['market'];
                 $url = $base_url.'?exchange='.$exchange.'&market='.$market;
                 $result = $this->curl_get($url );
                 $data = json_decode($result);
                 $update_data['price'] = $data->price;
                 $update_data['change'] = $data->change;
                 $update_data['volume'] = $data->volume;
                 $update_data['update_time'] = $data->update_time;
                 Db::name('ticker')->where('market', $market )->update($update_data);   //更新所有的数据，后续再单独采集            
            }
        }
        
        $this->success('采集成功！');
    }

    function curl_get($url){
        //初始化
        $curl = curl_init();
        //设置抓取的url
        curl_setopt($curl, CURLOPT_URL, $url);
        //设置头文件的信息作为数据流输出
        curl_setopt($curl, CURLOPT_HEADER, 1);
        //设置获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        //执行命令
        $data = curl_exec($curl);
        // 获得响应结果里的：头大小
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        //关闭URL请求
        curl_close($curl);        
        // 根据头大小去头信息内容
        $data =  substr($data, $headerSize);   
        //显示获得的数据
        return($data);        
    }
}
