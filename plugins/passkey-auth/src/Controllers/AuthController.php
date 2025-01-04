<?php

namespace PasskeyAuth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PasskeyAuth\Models\Passkey;

class AuthController extends Controller
{
    public function verify(Request $request)
    {
        $credentials = $request->input('credentials');

        // 查找对应的 Passkey 记录
        $passkey = Passkey::where('credential_id', $credentials['id'])->first();

        if (!$passkey) {
            return response()->json([
                'code' => 1,
                'message' => trans('PasskeyAuth::general.passkey_not_found')
            ]);
        }

        // TODO: 验证签名

        // 更新计数器
        $passkey->counter = $credentials['response']['counter'] ?? 0;
        $passkey->save();

        // 返回用户信息
        return response()->json([
            'code' => 0,
            'message' => trans('PasskeyAuth::general.login_success'),
            'data' => [
                'user' => $passkey->user
            ]
        ]);
    }
}