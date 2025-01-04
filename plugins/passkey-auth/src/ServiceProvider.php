<?php

namespace PasskeyAuth;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function register()
    {
        // 注册服务
    }

    public function boot()
    {
        // 发布前端资源
        $this->publishes([
            __DIR__.'/../assets' => public_path('assets/passkey-auth')
        ], 'public');
    }
}