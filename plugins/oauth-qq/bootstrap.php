<?php

use Blessing\Filter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;

return function (Dispatcher $events, Filter $filter) {
    $events->listen(
        'SocialiteProviders\Manager\SocialiteWasCalled',
        'SocialiteProviders\QQ\QqExtendSocialite@handle'
    );

    config(['services.qq' => [
        'client_id' => env('QQ_CLIENT_ID'),
        'client_secret' => env('QQ_CLIENT_SECRET'),
        'redirect' => env('QQ_REDIRECT_URI'),
    ]]);

    $filter->add('oauth_providers', function (Collection $providers) {
        $providers->put('qq', [
            'icon' => 'qq',
            'displayName' => 'qq',
        ]);

        return $providers;
    });
};
