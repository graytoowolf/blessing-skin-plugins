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
    protected $baseUrl = 'https://www.bing.com';
    protected $endpoint = '/HPImageArchive.aspx?format=js&idx=0&n=1';
    protected $cacheKey = 'latest_bing_image_url'; // 缓存键名

    public function api()
    {
        $imageUrl = Cache::get($this->cacheKey);
        if ($imageUrl === null) {
            $imageUrl = $this->fetchImageUrl();
            if ($imageUrl) {
                // 计算次日0点0分的时间
                $nextDayMidnight = Carbon::tomorrow()->startOfDay();
                // 计算从现在到次日0点0分的秒数
                $remainingSecondsToday = $nextDayMidnight->diffInSeconds(Carbon::now());
                Cache::put($this->cacheKey, $imageUrl, $remainingSecondsToday);
            }
        }

        return $this->redirectWithCache($imageUrl);
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
            return $this->handleException();
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

    protected function handleException()
    {
        $defaultImageUrl = $this->baseUrl . self::DEFAULT_IMAGE;
        return $defaultImageUrl;
    }
}
