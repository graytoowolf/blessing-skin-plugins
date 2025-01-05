<?php

namespace PasskeyAuth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PasskeyAuth\Models\Passkey;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest')->only('verify');
    }

    public function verify(Request $request)
    {
        $credentials = $request->input('credentials');

        if (!$credentials || !isset($credentials['id'])) {
            return response()->json([
                'code' => 1,
                'message' => 'Invalid credentials format'
            ]);
        }

        // 查找对应的 Passkey 记录
        $passkey = Passkey::where('credential_id', $credentials['id'])
            ->with('user')  // 预加载用户关系
            ->first();

        if (!$passkey) {
            Log::error('Passkey not found', [
                'credential_id' => $credentials['id']
            ]);
            return response()->json([
                'code' => 1,
                'message' => trans('PasskeyAuth::general.passkey_not_found')
            ]);
        }

        // 检查用户关联
        $user = $passkey->user;
        if (!$user) {
            Log::error('Passkey found but user is null', [
                'passkey_id' => $passkey->id,
                'credential_id' => $credentials['id'],
                'user_id' => $passkey->user_id
            ]);
            return response()->json([
                'code' => 1,
                'message' => 'Associated user not found'
            ]);
        }

        // TODO: 验证签名

        // 更新计数器
        $passkey->counter = $credentials['response']['counter'] ?? 0;
        $passkey->save();

        // 登录用户
        auth()->login($user);

        // 返回用户信息
        return response()->json([
            'code' => 0,
            'message' => trans('PasskeyAuth::general.login_success'),
            'data' => [
                'user' => $user,
                'redirect' => '/user'
            ]
        ]);
    }
}