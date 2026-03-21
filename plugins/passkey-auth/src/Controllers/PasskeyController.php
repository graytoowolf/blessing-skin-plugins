<?php

namespace PasskeyAuth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

class PasskeyController extends Controller
{
    private PublicKeyCredentialRpEntity $rpEntity;
    private WebAuthnServer $webAuthnServer;
    private const CHALLENGE_TIMEOUT = 300;

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

        $challengeToken = bin2hex(random_bytes(16));
        $cacheKey = 'passkey_creation_options:' . $challengeToken;
        Cache::put($cacheKey, [
            'options' => $publicKeyCredentialCreationOptions,
            'created_at' => time(),
            'used' => false,
        ], self::CHALLENGE_TIMEOUT);

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
            'token' => $challengeToken,
        ]);
    }

    public function register(Request $request)
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['message' => trans('PasskeyAuth::general.invalid_challenge')], 400);
        }

        $cacheKey = 'passkey_creation_options:' . $token;
        $cacheData = Cache::get($cacheKey);

        if (!$cacheData) {
            Log::warning('Passkey registration challenge not found or expired', ['token' => $token]);
            return response()->json(['message' => trans('PasskeyAuth::general.invalid_challenge')], 400);
        }

        if ($cacheData['used']) {
            Log::warning('Passkey registration challenge already used', ['token' => $token]);
            return response()->json(['message' => trans('PasskeyAuth::general.invalid_challenge')], 400);
        }

        $creationOptions = $cacheData['options'];

        try {
            $publicKeyCredentialSource = $this->webAuthnServer->createCredential(
                $creationOptions,
                $request->all()
            );

            $passkey = new Passkey();
            $passkey->user_id = Auth::id();
            $passkey->credential_id = $this->base64_urlsafe_encode($publicKeyCredentialSource->getPublicKeyCredentialId());
            $passkey->public_key = $this->base64_urlsafe_encode($publicKeyCredentialSource->getCredentialPublicKey());
            $passkey->counter = 0;
            $passkey->name = 'Passkey ' . date('Y-m-d H:i:s');
            $passkey->save();

            Cache::put($cacheKey, array_merge($cacheData, ['used' => true]), 60);

            return response()->json(['data' => ['id' => $passkey->id]]);
        } catch (\Exception $e) {
            Log::error('Passkey registration failed', [
                'error' => $e->getMessage(),
                'token' => $token,
            ]);
            Cache::forget($cacheKey);
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
        $challengeToken = bin2hex(random_bytes(16));

        $options = new PublicKeyCredentialRequestOptions(
            $challenge,
            timeout: self::CHALLENGE_TIMEOUT * 1000,
            rpId: parse_url(url('/'), PHP_URL_HOST),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED
        );

        $cacheKey = 'passkey_request_options:' . $challengeToken;
        Cache::put($cacheKey, [
            'options' => $options,
            'challenge' => $challenge,
            'created_at' => time(),
            'used' => false,
        ], self::CHALLENGE_TIMEOUT);

        return response()->json([
            'challenge' => $this->base64_urlsafe_encode($challenge),
            'timeout' => self::CHALLENGE_TIMEOUT * 1000,
            'rpId' => parse_url(url('/'), PHP_URL_HOST),
            'userVerification' => 'required',
            'allowCredentials' => [],
            'token' => $challengeToken
        ]);
    }

    public function login(Request $request)
    {
        $token = $request->input('token');

        if (!$token) {
            Log::warning('Login attempt without token');
            return response()->json(['message' => trans('PasskeyAuth::general.invalid_challenge')], 400);
        }

        $cacheKey = 'passkey_request_options:' . $token;
        $cacheData = cache()->get($cacheKey);

        if (!$cacheData) {
            Log::warning('Login challenge not found or expired', ['token' => $token]);
            return response()->json(['message' => trans('PasskeyAuth::general.invalid_challenge')], 400);
        }

        if ($cacheData['used']) {
            Log::warning('Login challenge already used (replay attack)', ['token' => $token]);
            return response()->json(['message' => trans('PasskeyAuth::general.invalid_challenge')], 400);
        }

        $elapsed = time() - $cacheData['created_at'];
        if ($elapsed > self::CHALLENGE_TIMEOUT) {
            Log::warning('Login challenge expired', [
                'token' => $token,
                'elapsed_seconds' => $elapsed,
            ]);
            cache()->forget($cacheKey);
            return response()->json(['message' => trans('PasskeyAuth::general.challenge_expired')], 400);
        }

        $requestOptions = $cacheData['options'];
        $origin = url('/');

        try {
            $publicKeyCredentialSource = $this->webAuthnServer->verifyAssertion(
                $requestOptions,
                $request->input('credentials'),
                $origin
            );
        } catch (\Exception $e) {
            Log::info('WebAuthn login assertion failed', [
                'error' => $e->getMessage(),
                'token' => $token,
            ]);
            cache()->forget($cacheKey);
            return response()->json(['message' => trans('PasskeyAuth::general.passkey_verify_failed')], 400);
        }

        $credentialId = $this->base64_urlsafe_encode($publicKeyCredentialSource->getPublicKeyCredentialId());
        $passkey = Passkey::where('credential_id', $credentialId)->first();

        if (!$passkey) {
            Log::error('Passkey not found after assertion', [
                'credential_id' => $credentialId,
            ]);
            cache()->forget($cacheKey);
            return response()->json(['message' => trans('PasskeyAuth::general.passkey_not_found')], 400);
        }

        $user = $passkey->user;
        if (!$user) {
            Log::error('Passkey user association broken', [
                'passkey_id' => $passkey->id,
            ]);
            cache()->forget($cacheKey);
            return response()->json(['message' => trans('PasskeyAuth::general.user_not_found')], 400);
        }

        $counter = $publicKeyCredentialSource->getCounter();
        if ($counter > 0) {
            $passkey->counter = $counter;
            $passkey->save();
        }

        Cache::put($cacheKey, array_merge($cacheData, ['used' => true]), 60);

        Log::info('Passkey login successful', [
            'user_id' => $user->uid,
            'email' => $user->email,
        ]);

        Auth::login($user);

        return response()->json([
            'code' => 0,
            'message' => trans('PasskeyAuth::general.login_success'),
            'data' => [
                'redirect' => '/user'
            ]
        ]);
    }
}