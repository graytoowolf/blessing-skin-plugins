<?php

use App\Models\Texture;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

return function (Dispatcher $events) {
    $events->listen('texture.uploaded', function ($event) {
        if($event->public){
            $skinurl=url('/skinlib/show').'/'.$event->tid;
            $url='http://data.zz.baidu.com/urls?site='.url('').'&token='.env('BAIDU_ZIYUAN_TOKEN');
            $response = Http::withBody($skinurl ,'text/plain')->post($url);
            log::info($response);
        }
    });

};