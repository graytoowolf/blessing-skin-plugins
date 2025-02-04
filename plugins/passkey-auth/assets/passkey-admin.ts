/**
 * 获取 CSRF Token
 * @returns CSRF Token 字符串
 */
function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (!meta) {
    throw new Error('CSRF token meta tag not found');
  }
  return meta.getAttribute('content') || '';
}

/**
 * 统一处理请求的公共函数
 * @param url 请求地址
 * @param options 请求参数
 * @returns 返回 Promise，解析后的 JSON 数据
 */
async function sendRequest<T = any>(url: string, options: RequestInit = {}): Promise<T> {
  const defaultHeaders: HeadersInit = {
    'X-CSRF-TOKEN': getCsrfToken(),
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  };

  const response = await fetch(url, {
    ...options,
    headers: {
      ...defaultHeaders,
      ...(options.headers || {})
    }
  });

  // 检查响应内容类型，如果为 HTML 则认为响应无效
  const contentType = response.headers.get('content-type');
  if (contentType && contentType.indexOf('text/html') !== -1) {
    throw new Error(blessing.t('passkey-auth.error.invalidResponse'));
  }

  const data = await response.json();
  if (!response.ok) {
    throw new Error(data.message || 'An error occurred.');
  }
  return data as T;
}

interface RenameResponse {
  message: string;
}

interface DeleteResponse {
  message: string;
}

interface ModalResult {
  value: string;
}

/**
 * 重命名 passkey
 * 使用 blessing.notify.showModal 获取用户输入的新名称，
 * 如果输入为空则提示错误，否则发起重命名请求。
 * @param id passkey 的标识符
 * @param currentName 当前名称
 */
function renamePasskey(id: string | number, currentName: string): void {
  blessing.notify.showModal({
    mode: "prompt",
    title: blessing.t('passkey-auth.admin.rename.title'),
    text: blessing.t('passkey-auth.admin.rename.empty'),
    placeholder: currentName
  })
  .then((newNameInput: string | ModalResult) => {
    let newName: string;
    // 如果返回的是对象且含有 value 属性，则使用 value，否则直接转换为字符串
    if (typeof newNameInput === 'object' && newNameInput !== null && 'value' in newNameInput) {
      newName = String(newNameInput.value || '').trim();
    } else {
      newName = String(newNameInput || '').trim();
    }

    if (!newName) {
      alert(blessing.t('passkey-auth.admin.rename.empty'));
      return;
    }

    return sendRequest<RenameResponse>(blessing.base_url + '/admin/passkeys/' + id + '/rename', {
      method: 'POST',
      body: JSON.stringify({ name: newName })
    })
    .then((data) => {
      alert(data.message);
      location.reload();
    })
    .catch((error: Error) => {
      alert(error.message || blessing.t('passkey-auth.admin.rename.failed'));
    });
  })
  .catch((error: Error) => {
    // 模态框关闭或发生错误时的处理（可选）
    console.error(error);
  });
}

/**
 * 删除 passkey
 * @param id passkey 的标识符
 * @param name passkey 的名称（可用于提示信息）
 */
function deletePasskey(id: string | number, name: string): void {
  sendRequest<DeleteResponse>(blessing.base_url + '/admin/passkeys/' + id + '/delete', {
    method: 'POST'
  })
  .then((data) => {
    alert(data.message);
    location.reload();
  })
  .catch((error: Error) => {
    alert(error.message || blessing.t('passkey-auth.admin.delete.failed'));
  });
}

// 将函数挂载到 window 对象上以供全局使用
(window as any).renamePasskey = renamePasskey;
(window as any).deletePasskey = deletePasskey;
