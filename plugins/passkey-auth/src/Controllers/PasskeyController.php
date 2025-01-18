<?php

namespace PasskeyAuth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PasskeyAuth\Models\Passkey;
use PasskeyAuth\Services\WebAuthnServer;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialParameters;
use Cose\Algorithms;
use Webauthn\AuthenticationExtensions\AuthenticationExtensionsClientInputs;
use Illuminate\Support\Facades\Base64;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\PublicKeyCredential;
use Webauthn\CollectedClientData;
use Illuminate\Support\Facades\Log;

class PasskeyController extends Controller
{
    private PublicKeyCredentialRpEntity $rpEntity;
    private WebAuthnServer $webAuthnServer;

    public function __construct(WebAuthnServer $webAuthnServer)
    {
        $this->rpEntity = new PublicKeyCredentialRpEntity(
            option('site_name'),
            parse_url(url('/'), PHP_URL_HOST)
        );
        $this->webAuthnServer = $webAuthnServer;
    }

    private function base64_urlsafe_encode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function base64_urlsafe_decode($data) {
        $data = str_replace(['-', '_'], ['+', '/'], $data);
        $padding = strlen($data) % 4;
        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }
        return base64_decode($data);
    }

    public function showManagePage()
    {
        $passkeys = Passkey::where('user_id', Auth::id())->get();
        return view('PasskeyAuth::manage', compact('passkeys'));
    }

    public function getRegisterChallenge()
    {
        $user = Auth::user();

        $userEntity = new PublicKeyCredentialUserEntity(
            $user->email,
            (string) Auth::id(),
            $user->nickname
        );

        $challenge = random_bytes(32);

        $publicKeyCredentialParametersList = [
            new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_ES256),
            new PublicKeyCredentialParameters('public-key', Algorithms::COSE_ALGORITHM_RS256),
        ];

        $authenticatorSelection = new AuthenticatorSelectionCriteria(
            authenticatorAttachment: null,
            userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
        );

        $extensions = new AuthenticationExtensionsClientInputs();

        $publicKeyCredentialCreationOptions = new PublicKeyCredentialCreationOptions(
            $this->rpEntity,
            $userEntity,
            $challenge,
            $publicKeyCredentialParametersList,
            timeout: 300000,
            authenticatorSelection: $authenticatorSelection,
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            extensions: $extensions
        );

        session(['passkey_creation_options' => $publicKeyCredentialCreationOptions]);

        // 转换为前端需要的格式
        return response()->json([
            'rp' => [
                'name' => $this->rpEntity->getName(),
                'id' => $this->rpEntity->getId(),
            ],
            'user' => [
                'id' => $this->base64_urlsafe_encode($userEntity->getId()),
                'name' => $userEntity->getName(),
                'displayName' => $userEntity->getDisplayName(),
            ],
            'challenge' => $this->base64_urlsafe_encode($challenge),
            'pubKeyCredParams' => array_map(function ($param) {
                return [
                    'type' => $param->getType(),
                    'alg' => $param->getAlg(),
                ];
            }, $publicKeyCredentialParametersList),
            'timeout' => 300000,
            'excludeCredentials' => [],
            'authenticatorSelection' => [
                'requireResidentKey' => true,
                'residentKey' => 'required',
                'userVerification' => 'required',
            ],
            'attestation' => 'none',
            'extensions' => $extensions->jsonSerialize(),
        ]);
    }

    public function register(Request $request)
    {
        $creationOptions = session('passkey_creation_options');
        if (!$creationOptions) {
            return response()->json(['message' => '无效的挑战'], 400);
        }

        try {
            $publicKeyCredentialSource = $this->webAuthnServer->createCredential(
                $creationOptions,
                $request->all()
            );

            $passkey = new Passkey();
            $passkey->user_id = Auth::id();
            $passkey->credential_id = $this->base64_urlsafe_encode($publicKeyCredentialSource->getPublicKeyCredentialId());
            $passkey->public_key = $this->base64_urlsafe_encode($publicKeyCredentialSource->getCredentialPublicKey());
            $passkey->name = 'Passkey ' . date('Y-m-d H:i:s');
            $passkey->save();

            return response()->json(['data' => ['id' => $passkey->id]]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
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

    public function getLoginChallenge()
    {
        $challenge = random_bytes(32);

        $options = new PublicKeyCredentialRequestOptions(
            $challenge,
            timeout: 300000,
            rpId: parse_url(url('/'), PHP_URL_HOST),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED
        );

        // 使用更持久的存储方式
        $token = bin2hex(random_bytes(16));
        cache()->put('passkey_request_options:' . $token, $options, now()->addMinutes(5));

        Log::debug('Generated login challenge:', [
            'challenge' => $this->base64_urlsafe_encode($challenge),
            'options' => $options,
            'token' => $token
        ]);

        return response()->json([
            'challenge' => $this->base64_urlsafe_encode($challenge),
            'timeout' => 300000,
            'rpId' => parse_url(url('/'), PHP_URL_HOST),
            'userVerification' => 'required',
            'allowCredentials' => [],
            'token' => $token
        ]);
    }

    public function login(Request $request)
    {
        $token = $request->input('token');
        Log::debug('Login request:', [
            'credentials' => $request->input('credentials'),
            'token' => $token
        ]);

        $requestOptions = cache()->get('passkey_request_options:' . $token);
        if (!$requestOptions) {
            Log::error('Invalid challenge:', [
                'token' => $token
            ]);
            return response()->json(['message' => '无效的挑战'], 400);
        }

        try {
            $publicKeyCredentialSource = $this->webAuthnServer->verifyAssertion(
                $requestOptions,
                $request->input('credentials'),
                'https://' . parse_url(url('/'), PHP_URL_HOST)
            );

            $passkey = Passkey::where('credential_id', $this->base64_urlsafe_encode($publicKeyCredentialSource->getPublicKeyCredentialId()))->first();
            if (!$passkey) {
                return response()->json(['message' => '未找到对应的密钥'], 400);
            }

            $user = $passkey->user;
            if (!$user) {
                return response()->json(['message' => '未找到对应的用户'], 400);
            }

            Log::debug('Login user:', [
                'email' => $user->email,
                'id' => $user->uid
            ]);

            Auth::login($user);

            return response()->json([
                'code' => 0,
                'message' => '登录成功',
                'data' => [
                    'redirect' => '/user'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('WebAuthn verifyAssertion error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }
}
