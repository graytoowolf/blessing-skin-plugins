<?php

use Blessing\Filter;
use App\Services\Hook;
use App\Services\PluginManager;
use Illuminate\Contracts\Events\Dispatcher;
use PasskeyAuth\Services\WebAuthnServer;
use PasskeyAuth\Services\PasskeyCredentialSourceRepository;
use Webauthn\PublicKeyCredentialRpEntity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use App\Events\RenderingFooter;

return function (Dispatcher $events,Filter $filter,PluginManager $plugins) {
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
    $oauth = $plugins->get('oauth'); // 插件标识符需要与 composer.json 中 name 字段一致
    $isOAuthEnabled = $oauth && $oauth->isEnabled();
    // 场景1: OAuth 启用时集成到提供商列表
    if ($isOAuthEnabled) {
        $filter->add('oauth_providers', function ($providers) {
            return $providers->put('passkey', [
                'icon' => 'fingerprint',
                'displayName' => trans('PasskeyAuth::general.login'),
                'button' => true,
                'class' => 'bg-light border-secondary',
                'brand' => false
            ]);
        });
    }
    // 场景2: OAuth 未启用时直接注入到登录页面
    else {
        $filter->add('auth_page_rows:login', function ($rows) {
            // 定位登录按钮位置
            $loginIndex = array_search('auth.login-submit', $rows);

            if ($loginIndex !== false) {
                array_splice($rows, $loginIndex + 1, 0, ['PasskeyAuth::passkey-button']);
            } else {
                array_splice($rows, count($rows) - 1, 0, ['PasskeyAuth::passkey-button']);
            }

            return $rows;
        });
    }



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