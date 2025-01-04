<?php

namespace PasskeyAuth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PasskeyAuth\Models\Passkey;

class PasskeyController extends Controller
{
    public function showManagePage()
    {
        $passkeys = Passkey::where('user_id', Auth::id())->get();
        return view('PasskeyAuth::manage', compact('passkeys'));
    }

    public function getRegisterChallenge()
    {
        $challenge = random_bytes(32);
        session(['passkey_challenge' => $challenge]);

        return response()->json([
            'challenge' => base64_encode($challenge),
            'user' => [
                'id' => base64_encode(Auth::id()),
                'name' => Auth::user()->email,
                'displayName' => Auth::user()->nickname
            ],
            'rp' => [
                'name' => option('site_name'),
                'id' => parse_url(url('/'), PHP_URL_HOST)
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257]
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'userVerification' => 'preferred'
            ]
        ]);
    }

    public function register(Request $request)
    {
        // 验证挑战
        $challenge = session('passkey_challenge');
        if (!$challenge) {
            return response()->json(['message' => '无效的挑战'], 400);
        }

        // 创建新的 Passkey 记录
        $passkey = new Passkey();
        $passkey->user_id = Auth::id();
        $passkey->credential_id = $request->input('id');
        $passkey->public_key = $request->input('response.attestationObject');
        $passkey->save();

        return response()->json(['data' => ['id' => $passkey->id]]);
    }

    public function rename(Request $request, $id)
    {
        $passkey = Passkey::where('user_id', Auth::id())
            ->findOrFail($id);

        $passkey->name = $request->input('name');
        $passkey->save();

        return response()->json(['message' => '重命名成功']);
    }

    public function delete(Request $request, $id)
    {
        $passkey = Passkey::where('user_id', Auth::id())
            ->findOrFail($id);

        $passkey->delete();

        return response()->json(['message' => '删除成功']);
    }
}