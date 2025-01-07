<?php

namespace PasskeyAuth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PasskeyAuth\Models\Passkey;
use Illuminate\Support\Facades\Log;
use Exception;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('guest')->only(['verify', 'getLoginChallenge']);
    }

    public function getLoginChallenge()
    {
        $challenge = random_bytes(32);
        session(['passkey_login_challenge' => $challenge]);

        return response()->json([
            'challenge' => base64_encode($challenge),
            'rpId' => parse_url(url('/'), PHP_URL_HOST),
            'timeout' => 60000,
            'userVerification' => 'preferred'
        ]);
    }

    protected function base64url_decode($data)
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }

    protected function verifySignature($publicKey, $authenticatorData, $clientDataHash, $signature)
    {
        try {
            // 从 PEM 格式的公钥中提取原始密钥数据
            $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($publicKey), 64, "\n") . "-----END PUBLIC KEY-----";
            $key = openssl_pkey_get_public($pem);
            if (!$key) {
                throw new Exception("Invalid public key");
            }

            // 验证签名
            $dataToVerify = $authenticatorData . $clientDataHash;
            $result = openssl_verify(
                $dataToVerify,
                $signature,
                $key,
                OPENSSL_ALGO_SHA256
            );

            openssl_free_key($key);

            if ($result === 1) {
                return true;
            } elseif ($result === 0) {
                throw new Exception("Invalid signature");
            } else {
                throw new Exception("Error verifying signature");
            }
        } catch (Exception $e) {
            Log::error('Signature verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
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

        // 验证 challenge
        $challenge = session('passkey_login_challenge');
        if (!$challenge) {
            return response()->json([
                'code' => 1,
                'message' => trans('PasskeyAuth::general.invalid_challenge')
            ]);
        }
        session()->forget('passkey_login_challenge');

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

        try {
            // 解码客户端数据
            $clientDataJSON = $this->base64url_decode($credentials['response']['clientDataJSON']);
            $clientData = json_decode($clientDataJSON);

            // 验证 challenge 匹配
            $expectedChallenge = base64_encode($challenge);
            if ($clientData->challenge !== $expectedChallenge) {
                throw new Exception('Challenge mismatch');
            }

            // 验证 origin
            $expectedOrigin = url('/');
            if ($clientData->origin !== $expectedOrigin) {
                throw new Exception('Origin mismatch');
            }

            // 验证 type
            if ($clientData->type !== 'webauthn.get') {
                throw new Exception('Invalid type');
            }

            // 解码认证器数据和签名
            $authenticatorData = $this->base64url_decode($credentials['response']['authenticatorData']);
            $signature = $this->base64url_decode($credentials['response']['signature']);
            $clientDataHash = hash('sha256', $clientDataJSON, true);

            // 验证签名
            if (!$this->verifySignature($passkey->public_key, $authenticatorData, $clientDataHash, $signature)) {
                throw new Exception('Signature verification failed');
            }

            // 验证 RP ID hash（authenticatorData 的前32字节）
            $rpIdHash = substr($authenticatorData, 0, 32);
            $expectedRpIdHash = hash('sha256', parse_url(url('/'), PHP_URL_HOST), true);
            if ($rpIdHash !== $expectedRpIdHash) {
                throw new Exception('Invalid RP ID hash');
            }

            // 验证用户在场标志（flags）
            $flags = ord($authenticatorData[32]);
            $userPresent = ($flags & 0x01) !== 0;
            $userVerified = ($flags & 0x04) !== 0;

            if (!$userPresent) {
                throw new Exception('User presence check failed');
            }

            // 更新计数器
            $counter = unpack('N', substr($authenticatorData, 33, 4))[1];
            if ($counter <= $passkey->counter) {
                throw new Exception('Counter value decreased');
            }
            $passkey->counter = $counter;
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

        } catch (Exception $e) {
            Log::error('WebAuthn verification failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'credential_id' => $credentials['id']
            ]);

            return response()->json([
                'code' => 1,
                'message' => trans('PasskeyAuth::general.operation_failed', ['msg' => $e->getMessage()])
            ]);
        }
    }
}