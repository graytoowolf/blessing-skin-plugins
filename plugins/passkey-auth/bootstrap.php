<?php

use App\Services\Hook;
use Illuminate\Contracts\Events\Dispatcher;
use PasskeyAuth\Listeners\AuthenticationListener;

return function (Dispatcher $events) {
    // 添加菜单项到用户侧边栏
    Hook::addMenuItem('user', 3, [
        'title' => 'PasskeyAuth::general.manage',
        'link'  => 'user/passkey',
        'icon'  => 'fa-key',
    ]);

    // 注册 JavaScript 文件
    Hook::addScriptFileToPage(plugin('passkey-auth')->assets('passkey-login.js'), ['user/passkey']);

    // 注册路由
    Hook::addRoute(function () {
        Route::namespace('PasskeyAuth\\Controllers')
            ->group(__DIR__.'/routes.php');
    });

    // 注册认证监听器
    $events->listen('auth.login.attempt', [AuthenticationListener::class, 'handle']);
};