// 全局类型声明
declare const blessing: {
  base_url: string;
};

declare function trans(key: string, params?: Record<string, unknown>): string;

// CSRF Token 获取
const getCsrfToken = (): string => {
  const csrfMeta = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');
  return csrfMeta?.getAttribute('content') || '';
};

// 删除 Passkey
export const deletePasskey = (id: string): void => {
  if (!confirm(trans('passkey-auth.admin.delete.confirm'))) return;

  fetch(`${blessing.base_url}/admin/passkeys/${id}/delete`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': getCsrfToken(),
      'Content-Type': 'application/json',
      'Accept': 'application/json',
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
};

// 重命名模态框操作
declare const $: {
  (selector: string): any;
  modal: (action: 'show' | 'hide') => void;
  val: (value?: string) => string;
};

export const showRenameDialog = (id: string, currentName: string): void => {
  $('#renamePasskeyId').val(id);
  $('#newName').val(currentName);
  $('#renameModal').modal('show');
};

export const renamePasskey = (): void => {
  const id = $('#renamePasskeyId').val() as string;
  const newName = ($('#newName').val() as string).trim();

  if (!newName) {
    alert(trans('passkey-auth.admin.rename.empty'));
    return;
  }

  fetch(`${blessing.base_url}/admin/passkeys/${id}/rename`, {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': getCsrfToken(),
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify({ name: newName }),
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
};
