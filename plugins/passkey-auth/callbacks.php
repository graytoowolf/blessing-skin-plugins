<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return [
    App\Events\PluginWasEnabled::class => function () {
        try {
            // 创建数据表
            if (!Schema::hasTable('passkeys')) {
                Schema::create('passkeys', function (Blueprint $table) {
                    $table->increments('id');
                    $table->integer('user_id');
                    $table->string('name');
                    $table->string('credential_id');
                    $table->text('public_key');
                    $table->integer('counter')->default(0);
                    $table->timestamps();

                    // 添加索引
                    $table->index('user_id', 'idx_user_id');
                    $table->unique('credential_id', 'uk_credential_id');
                });
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
];
