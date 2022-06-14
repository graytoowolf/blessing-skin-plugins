<?php

use App\Services\Hook;

return function () {
    Hook::addRoute(function () {
        Route::namespace('bingapi')
            ->group(function () {
                Route::get('/api/bing', 'Configuration@api');
            });
    });
};
