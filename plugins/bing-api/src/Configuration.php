<?php

namespace bingapi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Configuration
{
  public function api()
  {
    $response = Http::get("https://www.bing.com/HPImageArchive.aspx?format=js&idx=0&n=1"); //从bing获取数据
    $json = $response->json();
    if (empty($json) || empty($json['images']) || empty($json['images'][0]['url'])) {
      // 如果 JSON 数据为空或者不包含期望的数据结构，返回空字符串或者抛出异常
      $replaced = 'https://www.bing.com/th?id=OHR.EdaleValley_ZH-CN8464524952_1920x1080.jpg';
    } else {
      $imageUrl = 'https://www.bing.com' . $json['images'][0]['url'];
      $replaced = Str::of($imageUrl)->replace('&rf=LaDigue_1920x1080.jpg&pid=hp', ''); //替换多余字符
    }
    return redirect()->away($replaced)->withHeaders([
      'cache-control' => 'public',
      'expires' => date(DATE_RFC7231, strtotime(gmdate('Y-m-d H:i:s')) + 1800),
    ]);
  }
}
