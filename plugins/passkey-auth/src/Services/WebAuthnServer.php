<?php

namespace PasskeyAuth\Services;

use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSourceRepository;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\TokenBinding\IgnoreTokenBindingHandler;
use Webauthn\AuthenticationExtensions\ExtensionOutputCheckerHandler;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\PublicKeyCredential;
use Webauthn\CollectedClientData;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA;
use Cose\Algorithm\Signature\RSA;
use Illuminate\Support\Facades\Log;
use Webauthn\Util\Base64;
use Webauthn\AuthenticatorData;

class WebAuthnServer
{
    private $attestationResponseValidator;
    private $assertionResponseValidator;
    private $attestationObjectLoader;

    public function __construct(
        private PublicKeyCredentialRpEntity $rpEntity,
        private PublicKeyCredentialSourceRepository $publicKeyCredentialSourceRepository
    ) {
        $this->initializeValidators();
    }

    private function initializeValidators(): void
    {
        // 创建算法管理器
        $algorithmManager = Manager::create()
            ->add(ECDSA\ES256::create())
            ->add(RSA\RS256::create());

        // 创建验证声明支持管理器
        $attestationStatementSupportManager = new AttestationStatementSupportManager();
        $attestationStatementSupportManager->add(new NoneAttestationStatementSupport());

        // 创建验证对象加载器
        $this->attestationObjectLoader = new AttestationObjectLoader($attestationStatementSupportManager);

        // 创建验证响应验证器
        $this->attestationResponseValidator = new AuthenticatorAttestationResponseValidator(
            $attestationStatementSupportManager,
            $this->publicKeyCredentialSourceRepository,
            new IgnoreTokenBindingHandler(),
            new ExtensionOutputCheckerHandler()
        );

        // 创建断言响应验证器
        $this->assertionResponseValidator = new AuthenticatorAssertionResponseValidator(
            $this->publicKeyCredentialSourceRepository,
            new IgnoreTokenBindingHandler(),
            new ExtensionOutputCheckerHandler(),
            $algorithmManager
        );
    }

    private function base64_urlsafe_encode($data)
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private function base64_urlsafe_decode($data)
    {
        // 移除可能存在的空格和换行符
        $data = trim($data);
        // 替换 base64url 特殊字符
        $data = str_replace(['-', '_'], ['+', '/'], $data);
        // 添加适当的填充
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        // 解码
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64url string');
        }
        return $decoded;
    }

    public function createCredential($publicKeyCredentialCreationOptions, array $data)
    {
        try {
            $clientDataJSON = Base64::decode($data['response']['clientDataJSON']);
            $decodedClientData = json_decode($clientDataJSON, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \InvalidArgumentException('Invalid clientDataJSON: ' . json_last_error_msg());
            }

            $clientData = new CollectedClientData(
                $clientDataJSON,
                $decodedClientData
            );

            // attestationObjectLoader 会自动处理解码
            $attestationObject = $this->attestationObjectLoader->load($data['response']['attestationObject']);

            $response = new AuthenticatorAttestationResponse(
                $clientData,
                $attestationObject
            );

            $publicKeyCredential = new PublicKeyCredential(
                $data['id'],
                $data['type'],
                Base64::decode($data['rawId']),
                $response
            );

            $result = $this->attestationResponseValidator->check(
                $response,
                $publicKeyCredentialCreationOptions,
                'https://' . parse_url(url('/'), PHP_URL_HOST)
            );

            return $result;
        } catch (\Exception $e) {
            Log::error('WebAuthn createCredential error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function verifyAssertion($publicKeyCredentialRequestOptions, array $data, string $origin = null)
    {
        $clientDataJSON = Base64::decode($data['response']['clientDataJSON']);
        $decodedClientData = json_decode($clientDataJSON, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('客户端数据格式无效: ' . json_last_error_msg());
        }

        $clientData = new CollectedClientData(
            $clientDataJSON,
            $decodedClientData
        );

        // 解析认证数据
        $authenticatorDataString = Base64::decode($data['response']['authenticatorData']);
        $rpIdHash = substr($authenticatorDataString, 0, 32);
        $flags = substr($authenticatorDataString, 32, 1);
        $signCount = unpack('N', substr($authenticatorDataString, 33, 4))[1];

        try {
            $authenticatorData = new AuthenticatorData(
                $authenticatorDataString,
                $rpIdHash,
                $flags,
                $signCount,
                null,  // aaguid
                null   // attestedCredentialData
            );
        } catch (\Exception $e) {
            throw new \Exception('验证器数据无效: ' . $e->getMessage());
        }

        try {
            $response = new AuthenticatorAssertionResponse(
                $clientData,
                $authenticatorData,
                Base64::decode($data['response']['signature']),
                $data['response']['userHandle'] ? Base64::decode($data['response']['userHandle']) : null
            );
        } catch (\Exception $e) {
            throw new \Exception('验证响应无效: ' . $e->getMessage());
        }

        $publicKeyCredential = new PublicKeyCredential(
            $data['id'],
            $data['type'],
            Base64::decode($data['rawId']),
            $response
        );

        try {
            $result = $this->assertionResponseValidator->check(
                $publicKeyCredential->rawId,
                $response,
                $publicKeyCredentialRequestOptions,
                $origin,
                null  // userHandle (optional)
            );
            return $result;
        } catch (\Exception $e) {
            // 转换常见错误消息为中文
            $message = $e->getMessage();
            if (strpos($message, 'The credential ID is invalid') !== false) {
                throw new \Exception('无效的凭证ID，请确保使用正确的通行密钥');
            } else if (strpos($message, 'Invalid signature') !== false) {
                throw new \Exception('签名验证失败，请重试');
            } else if (strpos($message, 'User verification failed') !== false) {
                throw new \Exception('用户验证失败，请重试');
            } else {
                throw new \Exception('验证失败: ' . $message);
            }
        }
    }
}
