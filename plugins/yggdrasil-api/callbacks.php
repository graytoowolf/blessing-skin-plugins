<?php

require __DIR__.'/src/Utils/helpers.php';

use Illuminate\Support\Str;

return [
    App\Events\PluginWasEnabled::class => function () {
        if (!Schema::hasTable('uuid')) {
            Schema::create('uuid', function ($table) {
                $table->increments('id');
                $table->string('name');
                $table->string('uuid', 255);
            });
        }

        if (!Schema::hasTable('ygg_log')) {
            Schema::create('ygg_log', function ($table) {
                $table->increments('id');
                $table->string('action');
                $table->string('email');
                $table->string('name');
                $table->string('parameters')->default('');
                $table->string('user_agent');
                $table->string('ip')->default('');
                $table->dateTime('time');
            });
        }

        $items = [
            'ygg_uuid_algorithm' => 'v3',
            'ygg_token_expire_1' => '259200', // 3 days
            'ygg_token_expire_2' => '604800', // 7 days
            'ygg_tokens_limit' => '10',
            'ygg_rate_limit' => '1000',
            'ygg_skin_domain' => '',
            'ygg_search_profile_max' => '5',
            'ygg_private_key' => '',
            'ygg_show_config_section' => 'true',
            'ygg_show_activities_section' => 'true',
            'ygg_enable_ali' => 'true',
        ];

        foreach ($items as $key => $value) {
            if (!Option::get($key)) {
                Option::set($key, $value);
            }
        }

        $originalDefaultValue = [
            'ygg_token_expire_1' => '600',
            'ygg_token_expire_2' => '1200',
        ];

        // 原来的令牌过期时间默认值太低了，调高点
        foreach ($originalDefaultValue as $key => $value) {
            if (Option::get($key) == $value) {
                Option::set($key, $items[$key]);
            }
        }

        if (!env('YGG_VERBOSE_LOG')) {
            @unlink(storage_path('logs/yggdrasil.log'));
        }

        // 从旧版升级上来的默认继续使用旧的 UUID 生成算法
        if (DB::table('uuid')->count() > 0 && !Option::get('ygg_uuid_algorithm')) {
            Option::set('ygg_uuid_algorithm', 'v4');
        }

        // 初次使用自动生成私钥
        if (option('ygg_private_key') == '') {
            option(['ygg_private_key' => ygg_generate_rsa_keys()['private']]);
        }

        if (!config('jwt.secret')) {
            $key = Str::random(64);
            config(['jwt.secret' => $key]);

            $path = app()->environmentFilePath();
            $content = file_get_contents($path);
            file_put_contents($path, $content.PHP_EOL.'JWT_SECRET='.$key);
        }
    },
];
