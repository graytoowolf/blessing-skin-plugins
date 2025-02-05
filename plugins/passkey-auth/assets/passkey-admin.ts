/**
* 获取 CSRF Token
*/
function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (!meta) {
      throw new Error('CSRF token meta tag not found');
  }
  return meta.getAttribute('content') || '';
}
/**
* 更新表格中的 passkey 名称
*/
function updatePasskeyName(id: string | number, newName: string): void {
  const nameSpan = document.querySelector<HTMLSpanElement>(`span.passkey-name[data-id="${id}"]`);
  if (nameSpan) {
      nameSpan.textContent = newName;
  }
}

/**
* 移除表格中的 passkey 行
*/
function removePasskeyRow(id: string | number): void {
  const nameSpan = document.querySelector<HTMLSpanElement>(`span.passkey-name[data-id="${id}"]`);
  if (nameSpan) {
      const row = nameSpan.closest('tr');
      if (row) {
          row.remove();

          const tbody = document.querySelector('tbody');
          if (tbody) {
              const remainingRows = tbody.querySelectorAll('tr');
              if (remainingRows.length === 0 ||
                  (remainingRows.length === 1 && remainingRows[0].querySelector('td[colspan]'))) {
                  location.reload();
              }
          }
      }
  }
}

// 初始化事件监听器
document.addEventListener('DOMContentLoaded', () => {
  // 重命名按钮事件监听
  document.querySelectorAll<HTMLButtonElement>('.btn-link').forEach(button => {
      button.addEventListener('click', async () => {
          const nameSpan = button.previousElementSibling as HTMLSpanElement;
          if (!nameSpan) return;

          const id = nameSpan.getAttribute('data-id');
          if (!id) return;

          const currentName = nameSpan.textContent || '';

          try {
              const result = await blessing.notify.showModal({
                  mode: 'prompt',
                  title: trans('passkey-auth.admin.rename.title'),
                  text: trans('passkey-auth.admin.rename.empty'),
                  placeholder: currentName
              }).catch(() => {
                // Modal 被取消时，抛出一个特定的错误
                throw new Error('USER_CANCELLED');
              });

              const newName = result.value.trim();

              if (!newName) {
                  blessing.notify.toast.error(trans('passkey-auth.admin.rename.empty'));
                  return;
              }

              const data = await blessing.fetch.post(`/admin/passkeys/${id}/rename`,
                { name: newName }
              );

              updatePasskeyName(id, newName);
              blessing.notify.toast.success(data.message);
          } catch (error) {
              if (error.message === 'USER_CANCELLED') {
                return;
              }
              blessing.notify.toast.error(
                  error instanceof Error ? error.message : trans('passkey-auth.admin.rename.failed')
              );
          }
      });
  });

  // 删除按钮事件监听
  document.querySelectorAll<HTMLButtonElement>('.btn-danger').forEach(button => {
      button.addEventListener('click', async () => {
        try {
          const row = button.closest('tr');
          if (!row) return;

          const nameSpan = row.querySelector<HTMLSpanElement>('.passkey-name');
          if (!nameSpan) return;

          const id = nameSpan.getAttribute('data-id');
          if (!id) return;

          await blessing.notify.showModal({
            mode: 'confirm',
            type: 'danger',
            title: trans('passkey-auth.delete_confirm_title'),
            text: trans('passkey-auth.admin.delete.confirm')
          }).catch(() => {
              // Modal 被取消时，抛出一个特定的错误
              throw new Error('USER_CANCELLED');
          });

          const data = await blessing.fetch.post(`/admin/passkeys/${id}/delete`);
          removePasskeyRow(id);
          blessing.notify.toast.success(data.message);
        } catch (error) {
            // 忽略用户取消的情况
            if (error.message === 'USER_CANCELLED') {
              return;
          }
          // 处理其他错误
          blessing.notify.toast.error(
            error instanceof Error ? error.message : trans('passkey-auth.admin.delete.failed')
          );
        }
      });
  });
});
