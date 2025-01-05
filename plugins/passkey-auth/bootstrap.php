<?php

use Blessing\Filter;
use App\Services\Hook;
use App\Services\Plugin;
use Illuminate\Contracts\Events\Dispatcher;
use PasskeyAuth\Listeners\AuthenticationListener;
use PasskeyAuth\Models\Passkey;

return function (Dispatcher $events, Plugin $plugin, Filter $filter) {
    // 添加菜单项到用户侧边栏（放在靠前的位置）
    Hook::addMenuItem('user', 3, [
        'title' => 'PasskeyAuth::general.manage',
        'link'  => 'user/passkey',
        'icon'  => 'fa-key',
    ]);

    // 添加 Passkey 登录按钮到 OAuth 登录页面
    $filter->add('oauth_providers', function ($providers) {
        // 只在登录页面显示 Passkey 按钮
        if (request()->is('auth/login')) {
            $providers->push([
                'driver' => 'passkey',
                'name' => trans('PasskeyAuth::general.login'),
                'icon' => 'key'
            ]);
        }
        return $providers;
    });

    // 添加徽章
    $filter->add('user_badges', function ($badges, $user) {
        if (Passkey::where('user_id', $user->uid)->count() > 0) {
            $badges[] = [
                'text' => trans('PasskeyAuth::general.badge'),  // 使用翻译文本
                'color' => 'info',  // 使用蓝色
                'icon' => 'key'     // 使用钥匙图标
            ];
        }
        return $badges;
    });

    // 注册 JavaScript 文件
    Hook::addScriptFileToPage($plugin->assets('passkey-login.js'), ['user/passkey','auth/login']);

    // 注册路由，优先级设为 1，确保在 OAuth 路由之前注册
    Hook::addRoute(function () {
        Route::namespace('PasskeyAuth\\Controllers')
            ->group(__DIR__.'/routes.php');
    }, 1);

};