<?php

namespace ApiAuth\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ApiKey extends Model
{
    protected $table = 'api_keys';

    protected $fillable = [
        'name',
        'key',
        'last_used_at',
        'expires_at'
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime'
    ];

    /**
     * 检查 API 密钥是否已过期
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->isPast();
    }

    /**
     * 通过密钥字符串查找并验证 API 密钥
     *
     * @param string $key
     * @return static
     * @throws ModelNotFoundException 当密钥不存在时
     * @throws \Exception 当密钥已过期时
     */
    public static function findAndValidate(string $key)
    {
        $apiKey = static::where('key', $key)->firstOrFail();

        if ($apiKey->isExpired()) {
            throw new \Exception('API 密钥已过期');
        }

        // 更新最后使用时间
        $apiKey->last_used_at = Carbon::now();
        $apiKey->save();

        return $apiKey;
    }
}