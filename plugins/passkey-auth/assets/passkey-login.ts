// 定义类型
interface Window {
    blessing: {
        base_url: string;
        site_name: string;
        notify: {
            toast: {
                success: (message: string) => void;
                error: (message: string) => void;
                warning: (message: string) => void;
                info: (message: string) => void;
            };
            showModal: (options: {
                mode: string;
                type?: string;
                title: string;
                text: string;
                placeholder?: string;
            }) => Promise<{ value: string | null }>;
        };
        fetch: {
            get: (url: string) => Promise<any>;
            post: (url: string, data?: any) => Promise<any>;
        };
    };
}

interface PublicKeyCredentialCreationOptionsJSON {
    rp: {
        id: string;
        name: string;
    };
    user: {
        id: string;
        name: string;
        displayName: string;
    };
    challenge: string;
    pubKeyCredParams: Array<{
        type: string;
        alg: number;
    }>;
    timeout: number;
    excludeCredentials?: Array<{
        id: string;
        type: string;
        transports?: string[];
    }>;
    authenticatorSelection: {
        requireResidentKey: boolean;
        residentKey: string;
        userVerification: string;
    };
    attestation: string;
    extensions: any;
}

interface PublicKeyCredentialRequestOptionsJSON {
    challenge: string;
    timeout: number;
    rpId: string;
    allowCredentials?: Array<{
        id: string;
        type: string;
        transports?: string[];
    }>;
    userVerification: string;
    token: string;
}

interface CredentialResponse {
    id: string;
    type: string;
    rawId: string;
    response: {
        clientDataJSON: string;
        attestationObject?: string;
        authenticatorData?: string;
        signature?: string;
        userHandle?: string | null;
    };
    clientExtensionResults: any;
}

document.addEventListener('DOMContentLoaded', async () => {
    const registerButton = document.querySelector<HTMLButtonElement>(".btn#passkey-register");
    if (registerButton) {
        registerButton.addEventListener('click', async () => {
            registerButton.disabled = true;
            blessing.notify.toast.success(trans('passkey-auth.verifying'));

            try {
                const data: PublicKeyCredentialCreationOptionsJSON = await blessing.fetch.get('/user/passkey/register');
                const options: PublicKeyCredentialCreationOptions = {
                    ...data,
                    user: {
                        id: base64url_decode(data.user.id),
                        name: data.user.name,
                        displayName: data.user.displayName
                    },
                    challenge: base64url_decode(data.challenge),
                    excludeCredentials: data.excludeCredentials?.map(credential => ({
                        ...credential,
                        id: base64url_decode(credential.id)
                    })) || [],
                    rp: {
                        id: data.rp.id,
                        name: data.rp.name
                    },
                    pubKeyCredParams: data.pubKeyCredParams,
                    authenticatorSelection: data.authenticatorSelection,
                    timeout: data.timeout,
                    attestation: data.attestation as AttestationConveyancePreference
                };

                const credential = await navigator.credentials.create({ publicKey: options }) as PublicKeyCredential;

                const response: CredentialResponse = {
                    id: credential.id,
                    type: credential.type,
                    rawId: base64url_encode(credential.rawId),
                    response: {
                        clientDataJSON: base64url_encode(credential.response.clientDataJSON),
                        attestationObject: base64url_encode((credential.response as AuthenticatorAttestationResponse).attestationObject)
                    },
                    clientExtensionResults: credential.getClientExtensionResults()
                };

                const result = await blessing.fetch.post('/user/passkey/register', response);

                if (!result.data || !result.data.id) {
                    throw new Error('Invalid server response');
                }

                const nameModal = await blessing.notify.showModal({
                    mode: "prompt",
                    title: trans('passkey-auth.rename_title'),
                    text: trans('passkey-auth.set_name'),
                    placeholder: "Passkey"
                });

                const passKeyName = nameModal.value || `Passkey ${new Date().toLocaleString()}`;
                await blessing.fetch.post(`/user/passkey/${result.data.id}/rename`, { name: passKeyName });

                blessing.notify.toast.success(trans('passkey-auth.key_added'));
                location.reload();
            } catch (error) {
                handleError(error, 'passkey-auth.operation_failed');
            } finally {
                registerButton.disabled = false;
            }
        });
    }

    const loginButton = document.querySelector<HTMLButtonElement>(".btn#passkey-login");
    if (loginButton) {
        loginButton.addEventListener('click', async () => {
            loginButton.disabled = true;
            blessing.notify.toast.info(trans('passkey-auth.verifying'));

            try {
                const data: PublicKeyCredentialRequestOptionsJSON = await blessing.fetch.get('/auth/login/passkey/challenge');
                const options: PublicKeyCredentialRequestOptions = {
                    ...data,
                    challenge: base64url_decode(data.challenge),
                    allowCredentials: data.allowCredentials?.map(credential => ({
                        ...credential,
                        id: base64url_decode(credential.id)
                    })) || [],
                    rpId: data.rpId,
                    timeout: data.timeout,
                    userVerification: data.userVerification as UserVerificationRequirement
                };

                const credential = await navigator.credentials.get({
                    publicKey: options
                }) as PublicKeyCredential;

                const response: CredentialResponse = {
                    id: credential.id,
                    type: credential.type,
                    rawId: base64url_encode(credential.rawId),
                    response: {
                        authenticatorData: base64url_encode((credential.response as AuthenticatorAssertionResponse).authenticatorData),
                        clientDataJSON: base64url_encode(credential.response.clientDataJSON),
                        signature: base64url_encode((credential.response as AuthenticatorAssertionResponse).signature),
                        userHandle: (credential.response as AuthenticatorAssertionResponse).userHandle ? base64url_encode((credential.response as AuthenticatorAssertionResponse).userHandle) : null
                    },
                    clientExtensionResults: credential.getClientExtensionResults()
                };

                const result = await blessing.fetch.post('/auth/login/passkey', {
                    type: 'passkey',
                    credentials: response,
                    token: data.token
                });

                if (result.code === 0) {
                    blessing.notify.toast.success(trans('passkey-auth.login_success'));
                    setTimeout(() => {
                        window.location.href = result.data.redirect || '/user';
                    }, 1000);
                } else {
                    blessing.notify.toast.error(result.message);
                }
            } catch (error) {
                handleError(error, 'passkey-auth.operation_failed');
            } finally {
                loginButton.disabled = false;
            }
        });
    }

    // 删除 Passkey
    document.querySelectorAll<HTMLButtonElement>('.btn.passkey-delete').forEach(button => {
        button.addEventListener('click', async () => {
            const id = button.dataset.id;
            const card = button.closest('.card');
            if (!card) return;

            const nameElement = card.querySelector('.col-8');
            if (!nameElement) return;

            const name = nameElement.textContent?.trim() || '';

            const result = await blessing.notify.showModal({
                mode: "prompt",
                type: "danger",
                title: trans('passkey-auth.delete_confirm_title'),
                text: trans('passkey-auth.delete_confirm_text', { msg: blessing.site_name }),
                placeholder: name
            });

            if (result.value === name) {
                try {
                    await blessing.fetch.post(`/user/passkey/${id}/delete`);
                    blessing.notify.toast.success(trans('passkey-auth.delete_success'));
                    card.remove();
                } catch (error) {
                    blessing.notify.toast.error(trans('passkey-auth.delete_failed', { msg: error instanceof Error ? error.message : String(error) }));
                }
            } else if (result.value) {
                blessing.notify.toast.warning(trans('passkey-auth.name_mismatch'));
            }
        });
    });

    // 重命名 Passkey
    document.querySelectorAll<HTMLButtonElement>('.btn.passkey-rename').forEach(button => {
        button.addEventListener('click', async () => {
            const id = button.dataset.id;
            const card = button.closest('.card');
            if (!card) return;

            const nameElement = card.querySelector('.col-8');
            if (!nameElement) return;

            const currentName = nameElement.textContent?.trim() || '';

            const result = await blessing.notify.showModal({
                mode: "prompt",
                title: trans('passkey-auth.rename_title'),
                text: trans('passkey-auth.rename_text'),
                placeholder: currentName
            });

            if (result.value) {
                try {
                    await blessing.fetch.post(`/user/passkey/${id}/rename`, { name: result.value });
                    nameElement.textContent = result.value;
                    blessing.notify.toast.success(trans('passkey-auth.rename_success'));
                } catch (error) {
                    blessing.notify.toast.error(trans('passkey-auth.rename_failed', { msg: error instanceof Error ? error.message : String(error) }));
                }
            }
        });
    });
});

function base64url_encode(buffer: ArrayBuffer): string {
    return btoa(String.fromCharCode(...new Uint8Array(buffer)))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '');
}

function base64url_decode(input: string): ArrayBuffer {
    // 添加回 base64 填充
    input = input.replace(/-/g, '+').replace(/_/g, '/');
    const pad = input.length % 4;
    if (pad) {
        input += '='.repeat(4 - pad);
    }

    const binary = atob(input);
    const buffer = new ArrayBuffer(binary.length);
    const view = new Uint8Array(buffer);
    for (let i = 0; i < binary.length; i++) {
        view[i] = binary.charCodeAt(i);
    }
    return buffer;
}

function handleError(error: unknown, defaultTransKey: string): void {
    if (error instanceof DOMException && error.name === "NotAllowedError") {
        blessing.notify.toast.warning(trans('passkey-auth.verify_rejected'));
    } else {
        const message = error instanceof Error ? error.message : String(error);
        blessing.notify.toast.error(trans(defaultTransKey, { msg: message }));
    }
}