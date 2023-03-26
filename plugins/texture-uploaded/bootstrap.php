<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Events\Dispatcher;

return function (Dispatcher $events) {
    $events->listen('texture.uploaded', function ($event) {
        $skinurl = url('/skinlib/show') . '/' . $event->tid;
        //百度推送
        if (env('BAIDU_TOKEN')) {
            //判断是否公开
            $url = 'http://data.zz.baidu.com/urls?site=' . url('') . '&token=' . env('BAIDU_TOKEN');
            $response = Http::withBody($skinurl, 'text/plain')->post($url);
            if ($response->status() != 200) {
                Log::error('百度推送失败', ['status' => $response->status(), 'body' => $response->body()]);
            }
        }
        //必应推送
        //判断env('BING_TOKEN')存在
        if (env('BING_TOKEN')) {
            //判断是否公开

            $url = 'https://ssl.bing.com/webmaster/api.svc/json/SubmitUrlbatch?apikey=' . env('BING_TOKEN');
            $response =  Http::post($url, [
                'siteUrl' => url(''),
                'urlList' => [$skinurl]
            ]);
            if ($response->status() != 200) {
                Log::error('必应推送失败', ['status' => $response->status(), 'body' => $response->body()]);
            }
        }

        if ($event->public) {
            //添加数据到sitemap.xml
            $sitemapnema = public_path('sitemap.xml');
            $xml = simplexml_load_file($sitemapnema);
            $url = $xml->addChild('url');
            $url->addChild('loc', $skinurl);
            $url->addChild('lastmod', date('Y-m-d'));
            $url->addChild('changefreq', 'daily');
            $url->addChild('priority', '1.0');
            $xml->asXML($sitemapnema);
        }
    });
};
