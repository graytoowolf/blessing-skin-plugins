<?php

namespace PasskeyAuth\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PasskeyAuth\Models\Passkey;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

class AuthController extends Controller
{
    private const CHALLENGE_TIMEOUT = 300;

    public function __construct()
    {
        $this->middleware('guest')->only(['verify', 'getLoginChallenge']);
    }

    public function getLoginChallenge()
    {
        $challenge = random_bytes(32);
        $challengeToken = bin2hex(random_bytes(16));

        $cacheKey = 'passkey_challenge:' . $challengeToken;
        Cache::put($cacheKey, [
            'challenge' => $challenge,
            'created_at' => time(),
            'used' => false,
        ], self::CHALLENGE_TIMEOUT);

        return response()->json([
            'challenge' => base64_encode($challenge),
            'rpId' => parse_url(url('/'), PHP_URL_HOST),
            'timeout' => self::CHALLENGE_TIMEOUT * 1000,
            'userVerification' => 'required',
            'token' => $challengeToken,
        ]);
    }

    protected function base64url_decode($data)
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    protected function verifySignature($publicKey, $authenticatorData, $clientDataHash, $signature)
    {
        try {
            $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($publicKey), 64, "\n") . "-----END PUBLIC KEY-----";
            $key = openssl_pkey_get_public($pem);
            if (!$key) {
                throw new Exception("Invalid public key");
            }

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

    protected function validateOrigin($clientDataOrigin): bool
    {
        $siteUrl = url('/');
        $parsedUrl = parse_url($siteUrl);

        $expectedHost = $parsedUrl[PHP_URL_HOST] ?? '';
        $expectedScheme = $parsedUrl[PHP_URL_SCHEME] ?? 'https';

        $parsedOrigin = parse_url($clientDataOrigin);

        if (!$parsedOrigin) {
            Log::warning('Invalid origin format', ['origin' => $clientDataOrigin]);
            return false;
        }

        $originHost = $parsedOrigin[PHP_URL_HOST] ?? '';
        $originScheme = $parsedOrigin[PHP_URL_SCHEME] ?? '';

        if ($originHost !== $expectedHost) {
            Log::warning('Origin host mismatch', [
                'expected' => $expectedHost,
                'actual' => $originHost,
            ]);
            return false;
        }

        if ($originScheme !== $expectedScheme) {
            Log::warning('Origin scheme mismatch', [
                'expected' => $expectedScheme,
                'actual' => $originScheme,
            ]);
            return false;
        }

        return true;
    }

    protected function validateRpIdHash($authenticatorData, $rpId): bool
    {
        $rpIdHash = substr($authenticatorData, 0, 32);
        $expectedRpIdHash = hash('sha256', $rpId, true);

        if ($rpIdHash !== $expectedRpIdHash) {
            Log::warning('RP ID hash mismatch', [
                'expected' => bin2hex($expectedRpIdHash),
                'actual' => bin2hex($rpIdHash),
            ]);
            return false;
        }

        return true;
    }

    public function verify(Request $request)
    {
        $credentials = $request->input('credentials');
        $token = $request->input('token');

        if (!$credentials || !isset($credentials['id'])) {
            return response()->json([
                'code' => 1,
                'message' => 'Invalid credentials format'
            ]);
        }

        if (!$token) {
            return response()->json([
                'code' => 1,
                'message' => trans('PasskeyAuth::general.invalid_challenge')
            ]);
        }

        $cacheKey = 'passkey_challenge:' . $token;
        $challengeData = Cache::get($cacheKey);

        if (!$challengeData) {
            Log::warning('Challenge not found or expired', ['token' => $token]);
            return response()->json([
                'code' => 1,
                'message' => trans('PasskeyAuth::general.invalid_challenge')
            ]);
        }

        if ($challengeData['used']) {
            Log::warning('Challenge already used (replay attack)', ['token' => $token]);
            return response()->json([
                'code' => 1,
                'message' => trans('PasskeyAuth::general.invalid_challenge')
            ]);
        }

        $challenge = $challengeData['challenge'];

        $elapsed = time() - $challengeData['created_at'];
        if ($elapsed > self::CHALLENGE_TIMEOUT) {
            Log::warning('Challenge expired', [
                'token' => $token,
                'elapsed_seconds' => $elapsed,
            ]);
            Cache::forget($cacheKey);
            return response()->json([
                'code' => 1,
                'message' => trans('PasskeyAuth::general.challenge_expired')
            ]);
        }

        $passkey = Passkey::where('credential_id', $credentials['id'])
            ->with('user')
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
            $clientDataJSON = $this->base64url_decode($credentials['response']['clientDataJSON']);
            $clientData = json_decode($clientDataJSON);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid clientDataJSON');
            }

            $expectedChallenge = base64_encode($challenge);
            if ($clientData->challenge !== $expectedChallenge) {
                throw new Exception('Challenge mismatch');
            }

            if (!$this->validateOrigin($clientData->origin)) {
                throw new Exception('Origin validation failed');
            }

            if ($clientData->type !== 'webauthn.get') {
                throw new Exception('Invalid type');
            }

            $authenticatorData = $this->base64url_decode($credentials['response']['authenticatorData']);
            $signature = $this->base64url_decode($credentials['response']['signature']);
            $clientDataHash = hash('sha256', $clientDataJSON, true);

            if (!$this->verifySignature($passkey->public_key, $authenticatorData, $clientDataHash, $signature)) {
                throw new Exception('Signature verification failed');
            }

            $rpId = parse_url(url('/'), PHP_URL_HOST);
            if (!$this->validateRpIdHash($authenticatorData, $rpId)) {
                throw new Exception('Invalid RP ID hash');
            }

            $flags = ord($authenticatorData[32]);
            $userPresent = ($flags & 0x01) !== 0;
            $userVerified = ($flags & 0x04) !== 0;

            if (!$userPresent) {
                throw new Exception('User presence check failed');
            }

            if (!$userVerified) {
                throw new Exception('User verification required but not performed');
            }

            $counter = unpack('N', substr($authenticatorData, 33, 4))[1];
            if ($counter <= $passkey->counter) {
                Log::error('Counter rollback detected', [
                    'credential_id' => $credentials['id'],
                    'stored_counter' => $passkey->counter,
                    'received_counter' => $counter,
                ]);
                throw new Exception('Counter value decreased - possible cloned authenticator');
            }

            Cache::put($cacheKey, array_merge($challengeData, ['used' => true]), 60);

            $passkey->counter = $counter;
            $passkey->save();

            auth()->login($user);

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

            Cache::forget($cacheKey);

            return response()->json([
                'code' => 1,
                'message' => trans('PasskeyAuth::general.operation_failed', ['msg' => $e->getMessage()])
            ]);
        }
    }
}