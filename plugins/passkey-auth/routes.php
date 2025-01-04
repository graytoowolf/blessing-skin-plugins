<?php

use Illuminate\Support\Facades\Route;

Route::prefix('user/passkey')
    ->middleware(['web', 'auth'])
    ->group(function () {
        // 显示管理页面
        Route::get('/', 'PasskeyController@showManagePage');

        // Passkey 注册相关
        Route::get('/register', 'PasskeyController@getRegisterChallenge');
        Route::post('/register', 'PasskeyController@register');

        // Passkey 管理相关
        Route::post('/{id}/rename', 'PasskeyController@rename')
            ->where('id', '[0-9]+');
        Route::post('/{id}/delete', 'PasskeyController@delete')
            ->where('id', '[0-9]+');
    });