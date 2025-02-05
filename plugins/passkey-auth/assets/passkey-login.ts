// Web Authentication API 类型扩展
interface PublicKeyCredentialWithExtensions extends PublicKeyCredential {
  getClientExtensionResults: () => AuthenticationExtensionsClientOutputs;
}

// Base64URL 编解码工具
const base64urlEncode = (buffer: ArrayBuffer): string => {
  return btoa(String.fromCharCode(...new Uint8Array(buffer)))
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=/g, '');
};

const base64urlDecode = (input: string): ArrayBuffer => {
  let paddedInput = input.replace(/-/g, '+').replace(/_/g, '/');
  const padLength = (4 - (paddedInput.length % 4)) % 4;
  paddedInput += '='.repeat(padLength);
  const binaryString = atob(paddedInput);
  const buffer = new ArrayBuffer(binaryString.length);
  const bytes = new Uint8Array(buffer);
  for (let i = 0; i < binaryString.length; i++) {
    bytes[i] = binaryString.charCodeAt(i);
  }
  return buffer;
};

// 错误处理
const handleError = (error: unknown, defaultTransKey: string): void => {
  if (error instanceof DOMException && error.name === 'NotAllowedError') {
    blessing.notify.toast.warning(trans('passkey-auth.verify_rejected'));
  } else {
    const message = error instanceof Error ? error.message : String(error);
    blessing.notify.toast.error(trans(defaultTransKey, { msg: message }));
  }
};

// DOM 加载完成事件
document.addEventListener('DOMContentLoaded', async () => {
  // 注册 Passkey
  const registerButton = document.querySelector<HTMLButtonElement>('.btn#passkey-register');
  if (registerButton) {
    registerButton.addEventListener('click', async () => {
      try {
        registerButton.disabled = true;
        blessing.notify.toast.success(trans('passkey-auth.verifying'));

        const data = await blessing.fetch.get<any>('/user/passkey/register');
        const credential = (await navigator.credentials.create({
          publicKey: {
            ...data,
            user: {
              ...data.user,
              id: base64urlDecode(data.user.id),
            },
            challenge: base64urlDecode(data.challenge),
            excludeCredentials: data.excludeCredentials?.map((cred: any) => ({
              ...cred,
              id: base64urlDecode(cred.id),
            })),
            rp: data.rp,
            pubKeyCredParams: data.pubKeyCredParams,
            authenticatorSelection: data.authenticatorSelection,
            timeout: data.timeout,
            attestation: data.attestation,
          },
        })) as PublicKeyCredentialWithExtensions;

        const response = {
          id: credential.id,
          type: credential.type,
          rawId: base64urlEncode(credential.rawId),
          response: {
            clientDataJSON: base64urlEncode(credential.response.clientDataJSON),
            attestationObject: base64urlEncode(
              (credential.response as AuthenticatorAttestationResponse).attestationObject
            ),
          },
          clientExtensionResults: credential.getClientExtensionResults(),
        };

        const result = await blessing.fetch.post<{ data?: { id: string } }>('/user/passkey/register', response);
        if (!result.data?.id) throw new Error('Invalid server response');

        const nameModal = await blessing.notify.showModal({
          mode: 'prompt',
          title: trans('passkey-auth.rename_title'),
          text: trans('passkey-auth.set_name'),
          placeholder: 'Passkey',
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

  // 登录 Passkey
  const loginButton = document.querySelector<HTMLButtonElement>('.btn#passkey-login');
  if (loginButton) {
    loginButton.addEventListener('click', async () => {
      try {
        loginButton.disabled = true;
        blessing.notify.toast.info(trans('passkey-auth.verifying'));

        const data = await blessing.fetch.get<any>('/auth/login/passkey/challenge');
        const credential = (await navigator.credentials.get({
          publicKey: {
            ...data,
            challenge: base64urlDecode(data.challenge),
            allowCredentials: data.allowCredentials?.map((cred: any) => ({
              ...cred,
              id: base64urlDecode(cred.id),
            })),
            rpId: data.rpId,
            timeout: data.timeout,
            userVerification: data.userVerification,
          },
        })) as PublicKeyCredentialWithExtensions;

        const response = {
          id: credential.id,
          type: credential.type,
          rawId: base64urlEncode(credential.rawId),
          response: {
            authenticatorData: base64urlEncode(credential.response.authenticatorData),
            clientDataJSON: base64urlEncode(credential.response.clientDataJSON),
            signature: base64urlEncode(credential.response.signature),
            userHandle: credential.response.userHandle
              ? base64urlEncode(credential.response.userHandle)
              : null,
          },
          clientExtensionResults: credential.getClientExtensionResults(),
        };

        const result = await blessing.fetch.post<{ code: number; data?: { redirect?: string } }>(
          '/auth/login/passkey',
          {
            type: 'passkey',
            credentials: response,
            token: data.token,
          }
        );

        if (result.code === 0) {
          blessing.notify.toast.success(trans('passkey-auth.login_success'));
          setTimeout(() => {
            window.location.href = result.data?.redirect || '/user';
          }, 1000);
        } else {
          blessing.notify.toast.error(result['message'] || trans('passkey-auth.no_local_passkey'));
        }
      } catch (error) {
        handleError(error, 'passkey-auth.operation_failed');
      } finally {
        loginButton.disabled = false;
      }
    });
  }

  // 删除 Passkey
  document.querySelectorAll<HTMLButtonElement>('.btn.passkey-delete').forEach((button) => {
    button.addEventListener('click', async () => {
      const id = button.dataset.id;
      const card = button.closest<HTMLDivElement>('.card');
      if (!card || !id) return;

      const nameElement = card.querySelector<HTMLElement>('.col-8');
      const name = nameElement?.textContent?.trim() || '';

      try {
        const result = await blessing.notify.showModal({
          mode: 'prompt',
          type: 'danger',
          title: trans('passkey-auth.delete_confirm_title'),
          text: trans('passkey-auth.delete_confirm_text', { msg: blessing.site_name }),
          placeholder: name,
        }).catch(() => {
          throw new Error('USER_CANCELLED');
        });

        if (result.value !== name) {
          if (result.value) {
            blessing.notify.toast.warning(trans('passkey-auth.name_mismatch'));
          }
          return;
        }

        await blessing.fetch.post(`/user/passkey/${id}/delete`);
        blessing.notify.toast.success(trans('passkey-auth.delete_success'));
        card.remove();

      } catch (error) {
        if (error.message === 'USER_CANCELLED') {
          return;
        }
        blessing.notify.toast.error(
          trans('passkey-auth.delete_failed', { msg: (error as Error).message })
        );
      }
    });
  });

  // 重命名 Passkey
  document.querySelectorAll<HTMLButtonElement>('.btn.passkey-rename').forEach((button) => {
    button.addEventListener('click', async () => {
      const id = button.dataset.id;
      const card = button.closest<HTMLDivElement>('.card');
      if (!card || !id) return;

      const nameElement = card.querySelector<HTMLElement>('.col-8');
      const currentName = nameElement?.textContent?.trim() || '';

      try {
        const result = await blessing.notify.showModal({
          mode: 'prompt',
          title: trans('passkey-auth.rename_title'),
          text: trans('passkey-auth.rename_text'),
          placeholder: currentName,
        }).catch(() => {
          throw new Error('USER_CANCELLED');
        });

        if (result.value) {
          await blessing.fetch.post(`/user/passkey/${id}/rename`, { name: result.value });
          if (nameElement) nameElement.textContent = result.value;
          blessing.notify.toast.success(trans('passkey-auth.rename_success'));
        }
      } catch (error) {
        if (error.message !== 'USER_CANCELLED') {
          blessing.notify.toast.error(
            error instanceof Error ? error.message : trans('passkey-auth.rename_failed')
          );
        }
      }
    });
  });
});
