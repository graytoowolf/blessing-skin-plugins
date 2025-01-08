interface BlessingInterface {
  notify: {
      toast: {
          success: (message: string) => void;
          error: (message: string) => void;
          warning: (message: string) => void;
          info: (message: string) => void;
      };
      showModal: (options: {
          mode: string;
          title: string;
          text: string;
          placeholder?: string;
          type?: string;
      }) => Promise<{ value: string | null }>;
  };
  fetch: {
      get: (url: string) => Promise<any>;
      post: (url: string, data?: any) => Promise<any>;
  };
  site_name: string;
}

declare const blessing: BlessingInterface;
declare function trans(key: string, params?: Record<string, string>): string;

interface PublicKeyCredentialCreationOptionsExtended {
  user: {
      id: Uint8Array;
      name: string;
      displayName: string;
  };
  challenge: Uint8Array;
  rp?: {
      id?: string;
      name?: string;
  };
  pubKeyCredParams?: Array<{
      type: string;
      alg: number;
  }>;
  timeout?: number;
  excludeCredentials?: Array<{
      type: string;
      id: ArrayBuffer;
  }>;
  authenticatorSelection?: {
      authenticatorAttachment?: string;
      requireResidentKey?: boolean;
      residentKey?: string;
      userVerification?: string;
  };
  attestation?: string;
}

function str2ab(str: string): Uint8Array {
  return Uint8Array.from(window.atob(str), c => c.charCodeAt(0));
}

function array2b64String(a: Uint8Array): string {
  return window.btoa(String.fromCharCode(...a));
}

document.addEventListener('DOMContentLoaded', async () => {
  const registerButton = document.querySelector<HTMLButtonElement>(".btn#passkey-register");
  if (registerButton) {
      registerButton.addEventListener('click', async () => {
          registerButton.disabled = true;
          blessing.notify.toast.success(trans('passkey-auth.verifying'));

          try {
              const data = await blessing.fetch.get('/user/passkey/register');
              const options: PublicKeyCredentialCreationOptionsExtended = {
                  ...data,
                  user: {
                      id: str2ab(data.user.id.replace(/-/g, '+').replace(/_/g, '/')),
                      name: data.user.name,
                      displayName: data.user.displayName
                  },
                  challenge: str2ab(data.challenge.replace(/-/g, '+').replace(/_/g, '/'))
              };

              const credential = await navigator.credentials.create({ publicKey: options }) as PublicKeyCredential;
              const response = {
                  id: credential.id,
                  type: credential.type,
                  rawId: array2b64String(new Uint8Array(credential.rawId))
                      .replace(/\+/g, '-')
                      .replace(/\//g, '_')
                      .replace(/=/g, ''),
                  response: {
                      clientDataJSON: array2b64String(new Uint8Array((credential.response as AuthenticatorAttestationResponse).clientDataJSON))
                          .replace(/\+/g, '-')
                          .replace(/\//g, '_')
                          .replace(/=/g, ''),
                      attestationObject: array2b64String(new Uint8Array((credential.response as AuthenticatorAttestationResponse).attestationObject))
                          .replace(/\+/g, '-')
                          .replace(/\//g, '_')
                          .replace(/=/g, '')
                  }
              };

              const result = await blessing.fetch.post('/user/passkey/register', response);

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
              const credential = await navigator.credentials.get({
                  publicKey: {
                      challenge: new Uint8Array(32),
                      rpId: window.location.hostname,
                      userVerification: 'preferred',
                      timeout: 60000
                  }
              }) as PublicKeyCredential;

              const response = {
                  id: credential.id,
                  type: credential.type,
                  rawId: array2b64String(new Uint8Array(credential.rawId))
                      .replace(/\+/g, '-')
                      .replace(/\//g, '_')
                      .replace(/=/g, ''),
                  response: {
                      authenticatorData: array2b64String(new Uint8Array((credential.response as AuthenticatorAssertionResponse).authenticatorData))
                          .replace(/\+/g, '-')
                          .replace(/\//g, '_')
                          .replace(/=/g, ''),
                      clientDataJSON: array2b64String(new Uint8Array((credential.response as AuthenticatorAssertionResponse).clientDataJSON))
                          .replace(/\+/g, '-')
                          .replace(/\//g, '_')
                          .replace(/=/g, ''),
                      signature: array2b64String(new Uint8Array((credential.response as AuthenticatorAssertionResponse).signature))
                          .replace(/\+/g, '-')
                          .replace(/\//g, '_')
                          .replace(/=/g, '')
                  }
              };

              const result = await blessing.fetch.post('/auth/login/passkey', {
                  type: 'passkey',
                  credentials: response
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
                  card.remove(); // 删除卡片元素
              } catch (error) {
                  blessing.notify.toast.error(trans('passkey-auth.delete_failed', { msg: (error as Error).message }));
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
                  blessing.notify.toast.error(trans('passkey-auth.rename_failed', { msg: (error as Error).message }));
              }
          }
      });
  });
});

// 错误处理
function handleError(error: unknown, defaultTransKey: string): void {
  if (error instanceof DOMException && error.name === "NotAllowedError") {
      blessing.notify.toast.warning(trans('passkey-auth.verify_rejected'));
  } else {
      const message = error instanceof Error ? error.message : String(error);
      blessing.notify.toast.error(trans(defaultTransKey, { msg: message }));
  }
}
