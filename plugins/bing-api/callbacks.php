<?php

use Option;

return [
    App\Events\PluginWasEnabled::class => function () {
        Option::set('home_pic_url','/background.jpg');
    },

    App\Events\PluginWasDisabled::class => function () {
        Option::set('home_pic_url','./app/bg.webp');
    }
];