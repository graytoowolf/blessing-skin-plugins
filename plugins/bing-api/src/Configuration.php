<?php

namespace bingapi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Option;

class Configuration
{
  public function api()
  {
    $response = Http::get("https://cn.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1"); //从bing获取数据
    $imgurl = 'https://s.cn.bing.net' . $response->json()['images'][0]['url']; // 解析JSON文件
    $replaced = Str::of($imgurl)->replace('&pid=hp', ''); //替换多余字符
    Option::set('home_pic_url', $replaced); //更新数据库背景地址
    return 'ok';
    //return response(Http::get($imgurl),200,['Content-Type' => 'image/jpeg','Cache-Control' => 'public, max-age=21600']);//返回图片。缓存6小时

  }
}
