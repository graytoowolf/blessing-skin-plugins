<?php

use Illuminate\Contracts\Events\Dispatcher;

return function (Dispatcher $events) {
    $events->listen('texture.uploaded', function ($event) {
        if ($event->public) {
            $skinurl = url('/skinlib/show') . '/' . $event->tid;
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
