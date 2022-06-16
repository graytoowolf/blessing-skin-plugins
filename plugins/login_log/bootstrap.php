<?php

namespace loginlog;

use Illuminate\Contracts\Events\Dispatcher;

return function (Dispatcher $events) {
    $events->listen('auth.login.succeeded', OnUserLoginSucceeded::class);

};
