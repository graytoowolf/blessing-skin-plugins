<?php

namespace PasskeyAuth\Listeners;

use PasskeyAuth\Models\Passkey;

class AuthenticationListener
{
    public function handle($event)
    {
        // 检查认证类型
        if (!isset($event['type']) || $event['type'] !== 'passkey') {
            return;
        }

        // 获取凭证
        $credential = $event['credentials'];
        if (!$credential) {
            return false;
        }

        // 查找 Passkey
        $passkey = Passkey::where('credential_id', $credential['id'])->first();
        if (!$passkey) {
            return false;
        }

        // 验证签名和计数器
        // TODO: 实现 WebAuthn 验证逻辑

        // 更新计数器
        $passkey->counter = $credential['counter'];
        $passkey->save();

        return $passkey->user;
    }
}