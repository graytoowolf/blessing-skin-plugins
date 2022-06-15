<?php

use App\Services\Hook;

return function () {
    Hook::addRoute(function () {
        Route::namespace('file')
            ->group(function () {
                Route::get('/api/yggdrasil/minecraft/profile', 'TextureController@skin');
            });
    });
};
