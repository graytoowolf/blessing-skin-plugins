<?php

namespace bingapi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Configuration
{
  public function api()
  {
    $response = Http::get("https://www.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1"); //从bing获取数据
    //$response 是否获取成功
    if (!$response->ok()) {
      $replaced = 'https://www.bing.com/th?id=OHR.EdaleValley_ZH-CN8464524952_1920x1080.jpg';
    } else {
      $imgurl = 'https://www.bing.com' . $response->json()['images'][0]['url']; // 解析JSON文件
      $replaced = Str::of($imgurl)->replace('&rf=LaDigue_1920x1080.jpg&pid=hp', ''); //替换多余字符
    }
    return redirect()->away($replaced)->withHeaders([
      'cache-control' => 'public',
      'expires' => date(DATE_RFC7231, strtotime(gmdate('Y-m-d H:i:s')) + 1800),
    ]);
  }
}
