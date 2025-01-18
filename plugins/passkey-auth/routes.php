<?php

use Illuminate\Support\Facades\Route;

Route::prefix('auth/login/passkey')->middleware(['web', 'guest'])->group(function () {
    Route::get('/challenge', 'PasskeyController@getLoginChallenge');
    Route::post('/', 'PasskeyController@login');
});

Route::prefix('user/passkey')->middleware(['web', 'auth'])->group(function () {
    Route::get('/', 'PasskeyController@showManagePage');
    Route::get('/register', 'PasskeyController@getRegisterChallenge');
    Route::post('/register', 'PasskeyController@register');
    Route::post('/{id}/rename', 'PasskeyController@rename')->where('id', '[0-9]+');
    Route::post('/{id}/delete', 'PasskeyController@delete')->where('id', '[0-9]+');
});

Route::prefix('admin/passkeys')->middleware(['web', 'auth', 'role:admin'])->group(function () {
    Route::get('/', 'AdminController@index');
    Route::post('/{id}/rename', 'AdminController@rename')->where('id', '[0-9]+');
    Route::post('/{id}/delete', 'AdminController@delete')->where('id', '[0-9]+');
});