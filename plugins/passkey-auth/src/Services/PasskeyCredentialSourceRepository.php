<?php

namespace PasskeyAuth\Services;

use PasskeyAuth\Models\Passkey;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\TrustPath\EmptyTrustPath;
use Webauthn\Util\Base64;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Illuminate\Support\Facades\Log;

class PasskeyCredentialSourceRepository implements PublicKeyCredentialSourceRepository
{
    private function base64url_encode(string $data): string
    {
        return rtrim(Base64UrlSafe::encode($data), '=');
    }

    private function base64url_decode(string $data): string
    {
        return str_replace(['+', '/'],['-', '_'], $data);
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?PublicKeyCredentialSource
    {
        $passkey = Passkey::where('credential_id', $this->base64url_encode($publicKeyCredentialId))->first();

        if (!$passkey) {
            return null;
        }

        $data = [
            'publicKeyCredentialId' => $this->base64url_encode($publicKeyCredentialId),
            'type' => 'public-key',
            'transports' => ['internal'],
            'attestationType' => 'none',
            'trustPath' => [
                'type' => EmptyTrustPath::class
            ],
            'aaguid' => '00000000-0000-0000-0000-000000000000',
            'credentialPublicKey' => $this->base64url_decode($passkey->public_key),
            'userHandle' => $this->base64url_encode((string) $passkey->user_id),
            'counter' => (int) $passkey->counter,
        ];

        return PublicKeyCredentialSource::createFromArray($data);
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $passkeys = Passkey::where('user_id', $publicKeyCredentialUserEntity->getId())->get();

        return $passkeys->map(function ($passkey) {
            $data = [
                'publicKeyCredentialId' => $this->base64url_decode($passkey->credential_id),
                'type' => 'public-key',
                'transports' => ['internal'],
                'attestationType' => 'none',
                'trustPath' => [
                    'type' => EmptyTrustPath::class
                ],
                'aaguid' => '00000000-0000-0000-0000-000000000000',
                'credentialPublicKey' => $this->base64url_decode($passkey->public_key),
                'userHandle' => $this->base64url_encode((string) $passkey->user_id),
                'counter' => (int) $passkey->counter,
            ];

            Log::debug('createFromArray data in findAllForUserEntity', [
                'data' => array_merge($data, [
                    'publicKeyCredentialId' => bin2hex($data['publicKeyCredentialId']),
                    'credentialPublicKey' => bin2hex($data['credentialPublicKey'])
                ])
            ]);

            return PublicKeyCredentialSource::createFromArray($data);
        })->toArray();
    }

    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $passkey = Passkey::where('credential_id', $this->base64url_encode($publicKeyCredentialSource->getPublicKeyCredentialId()))->first();
        if ($passkey) {
            $passkey->counter = $publicKeyCredentialSource->getCounter();
            $passkey->save();
        }
    }
}