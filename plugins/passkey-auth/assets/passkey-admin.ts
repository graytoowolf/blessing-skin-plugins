// 获取 CSRF Token
function getCsrfToken(): string | null {
  const csrfMeta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
  return csrfMeta?.getAttribute('content') || null;
}

// 删除 Passkey
function deletePasskey(id: string): void {
  if (!confirm(trans('passkey-auth.admin.delete.confirm'))) {
      return;
  }

  fetch(`${blessing.base_url}/admin/passkeys/${id}/delete`, {
      method: 'POST',
      headers: {
          'X-CSRF-TOKEN': getCsrfToken() || '',
          'Content-Type': 'application/json',
          'Accept': 'application/json'
      },
  })
      .then(async (response): Promise<any> => {
          if (response.headers.get('content-type')?.includes('text/html')) {
              throw new Error(trans('passkey-auth.error.invalidResponse'));
          }
          const data = await response.json();
          if (!response.ok) {
              throw new Error(data.message || trans('passkey-auth.admin.delete.failed'));
          }
          return data;
      })
      .then((data: { message: string }): void => {
          alert(data.message);
          location.reload();
      })
      .catch((error: Error): void => {
          alert(error.message || trans('passkey-auth.admin.delete.failed'));
      });
}

// 显示重命名对话框
function showRenameDialog(id: string, currentName: string): void {
  $('#renamePasskeyId').val(id);
  $('#newName').val(currentName);
  $('#renameModal').modal('show');
}

// 重命名 Passkey
function renamePasskey(): void {
  const id = $('#renamePasskeyId').val() as string;
  const newName = ($('#newName').val() as string).trim();

  if (!newName) {
      alert(trans('passkey-auth.admin.rename.empty'));
      return;
  }

  fetch(`${blessing.base_url}/admin/passkeys/${id}/rename`, {
      method: 'POST',
      headers: {
          'X-CSRF-TOKEN': getCsrfToken() || '',
          'Content-Type': 'application/json',
          'Accept': 'application/json'
      },
      body: JSON.stringify({ name: newName })
  })
      .then(async (response): Promise<any> => {
          if (response.headers.get('content-type')?.includes('text/html')) {
              throw new Error(trans('passkey-auth.error.invalidResponse'));
          }
          const data = await response.json();
          if (!response.ok) {
              throw new Error(data.message || trans('passkey-auth.admin.rename.failed'));
          }
          return data;
      })
      .then((data: { message: string }): void => {
          alert(data.message);
          $('#renameModal').modal('hide');
          location.reload();
      })
      .catch((error: Error): void => {
          alert(error.message || trans('passkey-auth.admin.rename.failed'));
      });
}
