<?php


return [
    App\Events\PluginWasEnabled::class => function () {
        //判断sitemap.xml是否存在
        $sitemapnema = public_path('sitemap.xml');
        if (!file_exists($sitemapnema)) {
            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>');
            $xml->addAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
            $xml->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $xml->addAttribute('xsi:schemaLocation', 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');
            $url = $xml->addChild('url');
            $url->addChild('loc', url('/'));
            $url->addChild('lastmod', date('Y-m-d'));
            $url->addChild('changefreq', 'daily');
            $url->addChild('priority', '1.0');
            $xml->asXML($sitemapnema);
        }
    },

];
