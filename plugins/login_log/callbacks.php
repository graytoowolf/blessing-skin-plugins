<?php

return [
    App\Events\PluginWasEnabled::class => function () {
        $columns = [
            'login_at',
        ];
        $exists = [];
        $initialized = true;

        foreach ($columns as $column) {
            $exists[$column] = Schema::hasColumn('users', $column);

            if (!$exists[$column]) {
                $initialized = false;
            }
        }
        if ($initialized) {
            return;
        }
        Schema::table('users', function ($table) use ($exists) {
            $exists['login_at'] || $table->dateTime('login_at')->nullable();
        });
    },
];
