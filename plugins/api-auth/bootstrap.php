<?php

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Route;
use ApiAuth\Middleware\ApiKeyMiddleware;
use App\Services\Hook;

return function (Dispatcher $events) {
    // 注册中间件
    app()->router->aliasMiddleware('api.auth', ApiKeyMiddleware::class);

    // 注册菜单
    Hook::addMenuItem('admin', 6, [
        'title' => 'API 密钥管理',
        'link'  => 'admin/api-keys',
        'icon'  => 'fa-key'
    ]);

    // 注册路由
    Route::group([
        'middleware' => ['web', 'auth', 'role:admin'],
        'prefix' => 'admin/api-keys',
        'namespace' => 'ApiAuth\Controllers'
    ], function () {
        Route::get('/', 'ApiKeyController@index')->name('api.keys.list');
        Route::post('/generate', 'ApiKeyController@generate')->name('api.keys.generate');
        Route::delete('/{id}', 'ApiKeyController@delete')->name('api.keys.delete');
    });

    // 添加JS文件到页面
    Hook::addScriptFileToPage(plugin('api-auth')->assets('api-keys.js'), ['admin/api-keys']);
};
