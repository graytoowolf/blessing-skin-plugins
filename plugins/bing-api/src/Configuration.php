<?php

namespace bingapi;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class Configuration
{
    const DEFAULT_IMAGE = '/th?id=OHR.CardinalfishAnemone_EN-US1278259894_1920x1080.jpg'; // 假设的默认图片路径
    protected $baseUrl = 'https://cn.bing.com';
    protected $endpoint = '/HPImageArchive.aspx?format=js&idx=0&n=1';
    protected $cacheKey = 'latest_bing_image_url'; // 缓存键名

    public function api()
    {
        $imageUrl = Cache::remember($this->cacheKey, $this->getCacheTtl(), function () {
            $url = $this->fetchImageUrl();
            return $url ?? $this->getDefaultImage();
        });

        return redirect()->away($imageUrl);
    }

    protected function getCacheTtl(): int
    {
        return (int) Carbon::tomorrow()->startOfDay()->diffInSeconds(Carbon::now());
    }

    protected function fetchImageUrl()
    {
        try {
            $response = Http::get($this->baseUrl . $this->endpoint);

            if (!$response->successful()) {
                throw new Exception("API请求失败，状态码：" . $response->status());
            }

            $json = $response->json();
            if (empty($json['images'][0]['url'])) {
                return null;
            }

            $imageUrl = $this->baseUrl . $json['images'][0]['url'];
            return $this->filterImageUrl($imageUrl);
        } catch (Exception $e) {
            report($e);
            return $this->baseUrl . self::DEFAULT_IMAGE;
        }
    }

    protected function filterImageUrl($imageUrl)
    {
        // 移除原始参数
        $cleanUrl = Str::of($imageUrl)->replace('&rf=LaDigue_1920x1080.jpg&pid=hp', '');
        

        return $cleanUrl;
    }
}
