/// <reference types="jquery" />
interface ApiResponse {
    message: string;
    data?: {
        id: number;
        name: string;
        key: string;
        last_used_at: string | null;
        expires_at: string | null;
        created_at: string;
        updated_at: string;
    };
}

// 获取CSRF令牌
function getCsrfToken(): string {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.getAttribute('content') || '' : '';
}

// 验证过期时间
function validateExpiresAt(expiresAt: string): boolean {
    if (!expiresAt) return true; // 空值表示永不过期
    const expiresDate = new Date(expiresAt);
    const now = new Date();
    return expiresDate > now;
}

// 格式化日期时间
function formatDateTime(datetime: string | null): string {
    if (!datetime) return '从未使用';
    return new Date(datetime).toLocaleString('zh-CN', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

// 创建新的表格行
function createTableRow(key: NonNullable<ApiResponse['data']>): HTMLTableRowElement {
    const tr = document.createElement('tr');

    // 检查过期状态
    const isExpired = key.expires_at ? new Date(key.expires_at) < new Date() : false;
    const status = key.expires_at
        ? (isExpired
            ? '<span class="badge badge-danger">已过期</span>'
            : '<span class="badge badge-success">有效</span>')
        : '<span class="badge badge-info">永久</span>';

    tr.innerHTML = `
        <td>${key.name}</td>
        <td>${key.key}</td>
        <td>${formatDateTime(key.last_used_at)}</td>
        <td>${key.expires_at ? formatDateTime(key.expires_at) : '永不过期'}</td>
        <td>${status}</td>
        <td>
            <button class="btn btn-danger btn-sm delete-key" data-id="${key.id}">删除</button>
        </td>
    `;

    return tr;
}

// 处理删除事件
async function handleDelete(event: Event): Promise<void> {
    const button = (event.target as Element).closest('.delete-key');
    if (!button) return;

    const id = (button as HTMLElement).dataset.id;
    if (!id) return;

    try {
        const result = await blessing.notify.showModal({
            mode: 'confirm',
            title: '确定要删除这个密钥吗？',
            text: '此操作不可逆',
            type: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: '确定',
            cancelButtonText: '取消'
        });

        // 如果用户没有确认，直接返回
        if (result === false) return;

        // 确保base_url以斜杠结尾
        const baseUrl = blessing.base_url.endsWith('/')
            ? blessing.base_url
            : blessing.base_url + '/';

        const response = await fetch(`${baseUrl}admin/api-keys/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });

        const data: ApiResponse = await response.json();
        if (data.message) {
            await blessing.notify.showModal({
                mode: 'alert',
                type: 'success',
                title: '成功',
                text: data.message,
            });

            // 找到并移除对应的表格行
            const row = button.closest('tr');
            if (row) {
                row.remove();
            }
        }
    } catch (error) {
        console.error('Error:', error);
        await blessing.notify.showModal({
            mode: 'alert',
            type: 'error',
            title: '错误',
            text: '删除密钥失败'
        });
    }
}

// 生成密钥按钮点击事件
const generateButton = document.getElementById('generateKeyBtn');
if (generateButton) {
    generateButton.addEventListener('click', async function () {

        const form = document.getElementById('newKeyForm') as HTMLFormElement;
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = new FormData(form);
        const data: { [key: string]: string } = {};
        formData.forEach((value, key) => data[key] = value.toString());

        // 验证过期时间
        const expiresAt = data['expires_at'];
        if (expiresAt && !validateExpiresAt(expiresAt)) {
            await blessing.notify.showModal({
                mode: 'alert',
                type: 'error',
                title: '错误',
                text: '过期时间必须在当前时间之后'
            });
            return;
        }



        // 确保base_url以斜杠结尾
        const baseUrl = blessing.base_url.endsWith('/')
            ? blessing.base_url
            : blessing.base_url + '/';

        try {
            const response = await fetch(baseUrl + 'admin/api-keys/generate', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const responseData: ApiResponse = await response.json();

            if (responseData.message && responseData.data) {
                const modal = document.getElementById('newKeyModal');
                if (modal) {
                    $(modal).modal('hide');
                }

                // 显示成功消息
                await blessing.notify.showModal({
                    mode: 'alert',
                    type: 'success',
                    title: responseData.message,
                    text: `新密钥: ${responseData.data.key}`,
                });

                // 添加新行到表格
                const tbody = document.querySelector('table tbody');
                if (tbody) {
                    const newRow = createTableRow(responseData.data);
                    tbody.insertBefore(newRow, tbody.firstChild);
                }

                // 重置表单
                form.reset();
            }
        } catch (error) {
            console.error('Error:', error);
            await blessing.notify.showModal({
                mode: 'alert',
                type: 'error',
                title: '错误',
                text: '生成密钥失败'
            });
        }
    });
}

// 使用事件委托处理删除按钮点击
const table = document.querySelector('table');
if (table) {
    table.addEventListener('click', handleDelete);
}

// 清除模态框表单
const modalElement = document.getElementById('newKeyModal');
if (modalElement) {
    modalElement.addEventListener('hidden.bs.modal', function () {
        const form = document.getElementById('newKeyForm') as HTMLFormElement;
        if (form) {
            form.reset();
        }
    });
}
