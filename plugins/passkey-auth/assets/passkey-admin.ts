function getCsrfToken(): string | null {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.getAttribute('content');
}

function deletePasskey(id: number): void {
  if (!confirm(globalThis.trans('passkey-auth.admin.delete.confirm'))) {
      return;
  }

  fetch(`${globalThis.blessing.base_url}/admin/passkeys/${id}/delete`, {
      method: 'POST',
      headers: {
          'X-CSRF-TOKEN': getCsrfToken() || '',
          'Content-Type': 'application/json',
          'Accept': 'application/json'
      },
  })
      .then(async (response) => {
          if (response.headers.get('content-type')?.includes('text/html')) {
              throw new Error(globalThis.trans('passkey-auth.error.invalidResponse'));
          }
          const data = await response.json();
          if (!response.ok) {
              throw new Error(data.message || globalThis.trans('passkey-auth.admin.delete.failed'));
          }
          return data;
      })
      .then((data) => {
          alert(data.message);
          location.reload();
      })
      .catch((error: Error) => {
          alert(error.message || globalThis.trans('passkey-auth.admin.delete.failed'));
      });
}

function showRenameDialog(id: number, currentName: string): void {
  ($('#renamePasskeyId') as JQuery<HTMLInputElement>).val(id);
  ($('#newName') as JQuery<HTMLInputElement>).val(currentName);
  ($('#renameModal') as JQuery<HTMLElement>).modal('show');
}

function renamePasskey(): void {
  const id = Number(($('#renamePasskeyId') as JQuery<HTMLInputElement>).val());
  const newName = ($('#newName') as JQuery<HTMLInputElement>).val()?.trim();

  if (!newName) {
      alert(globalThis.trans('passkey-auth.admin.rename.empty'));
      return;
  }

  fetch(`${globalThis.blessing.base_url}/admin/passkeys/${id}/rename`, {
      method: 'POST',
      headers: {
          'X-CSRF-TOKEN': getCsrfToken() || '',
          'Content-Type': 'application/json',
          'Accept': 'application/json'
      },
      body: JSON.stringify({ name: newName })
  })
      .then(async (response) => {
          if (response.headers.get('content-type')?.includes('text/html')) {
              throw new Error(globalThis.trans('passkey-auth.error.invalidResponse'));
          }
          const data = await response.json();
          if (!response.ok) {
              throw new Error(data.message || globalThis.trans('passkey-auth.admin.rename.failed'));
          }
          return data;
      })
      .then((data) => {
          alert(data.message);
          ($('#renameModal') as JQuery<HTMLElement>).modal('hide');
          location.reload();
      })
      .catch((error: Error) => {
          alert(error.message || globalThis.trans('passkey-auth.admin.rename.failed'));
      });
}

document.getElementById('renameButton')?.addEventListener('click', renamePasskey);
