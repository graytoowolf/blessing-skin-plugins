<?php

use App\Services\Hook;

return function () {
    Hook::addRoute(function () {
        Route::namespace('bingapi')
            ->group(function () {
                Route::get('/background.jpg', 'Configuration@api');
            });
    });
};
