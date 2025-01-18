<?php

use Blessing\Filter;
use App\Services\Hook;
use Illuminate\Contracts\Events\Dispatcher;
use PasskeyAuth\Services\WebAuthnServer;
use PasskeyAuth\Services\PasskeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialRpEntity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use App\Events\RenderingFooter;

return function (Dispatcher $events,Filter $filter) {
    // 注册 WebAuthn 服务
    app()->singleton(WebAuthnServer::class, function ($app) {
        $rpEntity = new PublicKeyCredentialRpEntity(
            option('site_name'),
            parse_url(url('/'), PHP_URL_HOST)
        );

        $repository = new PasskeyCredentialSourceRepository();

        return new WebAuthnServer($rpEntity, $repository);
    });

    // 添加菜单项到用户侧边栏（放在靠前的位置）
    Hook::addMenuItem('user', 3, [
        'title' => 'PasskeyAuth::general.manage',
        'link'  => 'user/passkey',
        'icon'  => 'fa-key',
    ]);

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


    // 注册路由，优先级设为最高，确保在其他路由之前注册
    Hook::addRoute(function () {
        Route::namespace('PasskeyAuth\\Controllers')
            ->group(__DIR__.'/routes.php');
    }, -100);  // 使用负数优先级，数字越小优先级越高


    // 注册前端资源
    Hook::addScriptFileToPage(plugin_assets('passkey-auth', 'passkey-login.js'), ['auth/login', 'user/passkey']);
    Hook::addScriptFileToPage(plugin_assets('passkey-auth', 'passkey-admin.js'), ['admin/passkeys']);

    Hook::addMenuItem('admin', 5, [
        'title' => '通行密钥',
        'link' => 'admin/passkeys',
        'icon' => 'fa-key',
    ]);


};