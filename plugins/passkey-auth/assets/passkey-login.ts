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

type CredentialResponse = {
  id: string;
  type: string;
  rawId: string;
  response: Record<string, string>;
};

function str2ab(str: string): Uint8Array {
  return Uint8Array.from(atob(str), c => c.charCodeAt(0));
}

function array2b64String(a: Uint8Array): string {
  return btoa(String.fromCharCode(...a));
}

function base64URLEncode(a: Uint8Array): string {
  return array2b64String(a).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

document.addEventListener('DOMContentLoaded', () => {
  function setButtonState(button: HTMLButtonElement, state: boolean) {
      button.disabled = state;
  }

  async function registerPasskey() {
      const registerButton = document.querySelector<HTMLButtonElement>(".btn#passkey-register");
      if (!registerButton) return;

      setButtonState(registerButton, true);
      blessing.notify.toast.success(trans('passkey-auth.verifying'));

      try {
          const data = await blessing.fetch.get('/user/passkey/register');
          const options = {
              ...data,
              user: { id: str2ab(data.user.id), name: data.user.name, displayName: data.user.displayName },
              challenge: str2ab(data.challenge)
          };

          const credential = await navigator.credentials.create({ publicKey: options }) as PublicKeyCredential;

          const response: CredentialResponse = {
              id: credential.id,
              type: credential.type,
              rawId: base64URLEncode(new Uint8Array(credential.rawId)),
              response: {
                  clientDataJSON: base64URLEncode(new Uint8Array((credential.response as AuthenticatorAttestationResponse).clientDataJSON)),
                  attestationObject: base64URLEncode(new Uint8Array((credential.response as AuthenticatorAttestationResponse).attestationObject))
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
          setButtonState(registerButton, false);
      }
  }

  async function loginPasskey() {
      const loginButton = document.querySelector<HTMLButtonElement>(".btn#passkey-login");
      if (!loginButton) return;

      setButtonState(loginButton, true);
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
              rawId: base64URLEncode(new Uint8Array(credential.rawId)),
              response: {
                  authenticatorData: base64URLEncode(new Uint8Array((credential.response as AuthenticatorAssertionResponse).authenticatorData)),
                  clientDataJSON: base64URLEncode(new Uint8Array((credential.response as AuthenticatorAssertionResponse).clientDataJSON)),
                  signature: base64URLEncode(new Uint8Array((credential.response as AuthenticatorAssertionResponse).signature))
              }
          };

          const result = await blessing.fetch.post('/auth/login/passkey', { type: 'passkey', credentials: response });
          if (result.code === 0) {
              blessing.notify.toast.success(trans('passkey-auth.login_success'));
              setTimeout(() => window.location.href = result.data.redirect || '/user', 1000);
          } else {
              blessing.notify.toast.error(result.message);
          }
      } catch (error) {
          handleError(error, 'passkey-auth.operation_failed');
      } finally {
          setButtonState(loginButton, false);
      }
  }

  document.querySelector<HTMLButtonElement>(".btn#passkey-register")?.addEventListener('click', registerPasskey);
  document.querySelector<HTMLButtonElement>(".btn#passkey-login")?.addEventListener('click', loginPasskey);
});
