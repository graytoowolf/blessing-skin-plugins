<?php

namespace bingapi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Configuration
{
  protected $baseUrl = 'https://www.bing.com';
  protected $endpoint = '/HPImageArchive.aspx?format=js&idx=0&n=1';

  public function api()
  {
    try {
      $response = Http::get($this->baseUrl . $this->endpoint);
      $json = $response->json();

      if (empty($json['images'][0]['url'])) {
        throw new \Exception("图片URL不存在");
      }

      $imageUrl = $this->baseUrl . $json['images'][0]['url'];
      $filteredUrl = $this->filterImageUrl($imageUrl);

      return $this->redirectWithCache($filteredUrl);
    } catch (\Exception $e) {
      // 日志记录异常或其他错误处理
      report($e);
      // 提供默认图片或错误提示
      $defaultImageUrl = $this->baseUrl . '/th?id=OHR.EdaleValley_ZH-CN8464524952_1920x1080.jpg';
      return $this->redirectWithCache($defaultImageUrl);
    }
  }

  protected function filterImageUrl($imageUrl)
  {
    return Str::of($imageUrl)->replace('&rf=LaDigue_1920x1080.jpg&pid=hp', '');
  }

  protected function redirectWithCache($url)
  {
    return redirect()->away($url)->withHeaders([
      'Cache-Control' => 'public, max-age=1800',
      'Expires' => gmdate(DATE_RFC7231, strtotime('+30 minutes'))
    ]);
  }
}
