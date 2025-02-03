/**
 * 获取 CSRF Token
 * @returns {string} CSRF Token 字符串
 */
function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]');
  if (!meta) {
    throw new Error('CSRF token meta tag not found');
  }
  return meta.getAttribute('content') || '';
}

/**
 * 请求参数的类型
 */
interface RequestOptions extends RequestInit {
  headers?: { [key: string]: string };
}

/**
 * 统一处理请求的公共函数
 * @param {string} url 请求地址
 * @param {RequestOptions} [options={}] 请求参数
 * @returns {Promise<Object>} 返回 Promise，解析后的 JSON 数据
 */
async function sendRequest(url: string, options: RequestOptions = {}): Promise<any> {
  const defaultHeaders = {
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
    throw new Error(trans('passkey-auth.error.invalidResponse'));
  }

  const data = await response.json();
  if (!response.ok) {
    throw new Error(data.message || 'An error occurred.');
  }
  return data;
}

/**
 * 重命名 passkey
 * 使用 blessing.notify.showModal 获取用户输入的新名称，
 * 如果输入为空则提示错误，否则发起重命名请求。
 * @param {string | number} id passkey 的标识符
 * @param {string} currentName 当前名称
 */
function renamePasskey(id: string | number, currentName: string): void {
  blessing.notify.showModal({
    mode: "prompt",
    title: trans('passkey-auth.admin.rename.title'),
    text: trans('passkey-auth.admin.rename.empty'),
    placeholder: currentName
  })
  .then((newNameInput: string | { value: string }) => {
    let newName: string;
    // 如果返回的是对象且含有 value 属性，则使用 value，否则直接转换为字符串
    if (typeof newNameInput === 'object' && newNameInput !== null && 'value' in newNameInput) {
      newName = String(newNameInput.value || '').trim();
    } else {
      newName = String(newNameInput || '').trim();
    }

    if (!newName) {
      alert(trans('passkey-auth.admin.rename.empty'));
      return;
    }

    sendRequest(blessing.base_url + '/admin/passkeys/' + id + '/rename', {
      method: 'POST',
      body: JSON.stringify({ name: newName })
    })
    .then((data) => {
      alert(data.message);
      location.reload();
    })
    .catch((error) => {
      alert(error.message || trans('passkey-auth.admin.rename.failed'));
    });
  })
  .catch((error) => {
    // 模态框关闭或发生错误时的处理（可选）
    console.error(error);
  });
}

/**
 * 删除 passkey
 * @param {string | number} id passkey 的标识符
 * @param {string} name passkey 的名称（可用于提示信息）
 */
function deletePasskey(id: string | number, name: string): void {
  sendRequest(blessing.base_url + '/admin/passkeys/' + id + '/delete', {
    method: 'POST'
  })
  .then((data) => {
    alert(data.message);
    location.reload();
  })
  .catch((error) => {
    alert(error.message || trans('passkey-auth.admin.delete.failed'));
  });
}
