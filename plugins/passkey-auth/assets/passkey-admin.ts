// 全局类型声明（通常放在单独的 .d.ts 文件中）
declare const blessing: {
  base_url: string;
};

declare function trans(key: string, params?: Record<string, unknown>): string;

// CSRF Token 获取函数
function getCsrfToken(): string {
  const meta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
  return meta?.getAttribute('content') || '';
}

// 删除 Passkey 函数
function deletePasskey(id: number): void {
  if (!confirm(trans('passkey-auth.admin.delete.confirm'))) {
      return;
  }

  fetch(`${blessing.base_url}/admin/passkeys/${id}/delete`, {
      method: 'POST',
      headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Content-Type': 'application/json',
          'Accept': 'application/json'
      },
  })
  .then(async (response: Response) => {
      if (response.headers.get('content-type')?.includes('text/html')) {
          throw new Error(trans('passkey-auth.error.invalidResponse'));
      }

      const data = await response.json();

      if (!response.ok) {
          throw new Error(data.message || trans('passkey-auth.admin.delete.failed'));
      }
      return data;
  })
  .then((data: { message: string }) => {
      alert(data.message);
      location.reload();
  })
  .catch((error: Error) => {
      alert(error.message || trans('passkey-auth.admin.delete.failed'));
  });
}

// 显示重命名对话框
function showRenameDialog(id: number, currentName: string): void {
  ($('#renamePasskeyId') as JQuery<HTMLInputElement>).val(id.toString());
  ($('#newName') as JQuery<HTMLInputElement>).val(currentName);
  ($('#renameModal') as JQuery).modal('show');
}

// 重命名 Passkey
function renamePasskey(): void {
  const id = parseInt(($('#renamePasskeyId') as JQuery<HTMLInputElement>).val() as string, 10);
  const newName = ($('#newName') as JQuery<HTMLInputElement>).val()?.toString().trim() || '';

  if (!newName) {
      alert(trans('passkey-auth.admin.rename.empty'));
      return;
  }

  fetch(`${blessing.base_url}/admin/passkeys/${id}/rename`, {
      method: 'POST',
      headers: {
          'X-CSRF-TOKEN': getCsrfToken(),
          'Content-Type': 'application/json',
          'Accept': 'application/json'
      },
      body: JSON.stringify({ name: newName })
  })
  .then(async (response: Response) => {
      if (response.headers.get('content-type')?.includes('text/html')) {
          throw new Error(trans('passkey-auth.error.invalidResponse'));
      }

      const data = await response.json();

      if (!response.ok) {
          throw new Error(data.message || trans('passkey-auth.admin.rename.failed'));
      }
      return data;
  })
  .then((data: { message: string }) => {
      alert(data.message);
      ($('#renameModal') as JQuery).modal('hide');
      location.reload();
  })
  .catch((error: Error) => {
      alert(error.message || trans('passkey-auth.admin.rename.failed'));
  });
}
