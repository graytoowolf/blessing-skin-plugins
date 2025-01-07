<?php

use Blessing\Filter;
use App\Services\Hook;
use App\Services\Plugin;
use Illuminate\Contracts\Events\Dispatcher;
use PasskeyAuth\Listeners\AuthenticationListener;
use PasskeyAuth\Models\Passkey;
use Illuminate\Support\Collection;

return function (Dispatcher $events, Plugin $plugin, Filter $filter) {
    // 添加菜单项到用户侧边栏（放在靠前的位置）
    Hook::addMenuItem('user', 3, [
        'title' => 'PasskeyAuth::general.manage',
        'link'  => 'user/passkey',
        'icon'  => 'fa-key',
    ]);

    // 注册 JavaScript 文件
    Hook::addScriptFileToPage($plugin->assets('passkey-login.js'), ['auth/login', 'user/passkey']);

    // 添加 Passkey 按钮到 OAuth 提供商列表
    $filter->add('oauth_providers', function (Collection $providers) {
        // 将 Passkey 放在列表最前面
        return (new Collection([
            'passkey' => [
                'icon' => 'fingerprint',
                'displayName' => trans('PasskeyAuth::general.login'),
                'button' => true, // 标记这是一个按钮而不是链接
            ]
        ]))->union($providers);
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

    // 注册路由，优先级设为最高，确保在其他路由之前注册
    Hook::addRoute(function () {
        Route::namespace('PasskeyAuth\\Controllers')
            ->group(__DIR__.'/routes.php');
    }, -100);  // 使用负数优先级，数字越小优先级越高

};