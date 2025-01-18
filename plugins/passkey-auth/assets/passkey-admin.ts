// 定义类型
interface Window {
    blessing: {
        base_url: string;
    }
}

interface ApiResponse {
    message: string;
    code?: number;
}

function getCsrfToken(): string {
    const metaElement = document.querySelector('meta[name="csrf-token"]');
    if (!metaElement) {
        throw new Error('CSRF token meta tag not found');
    }
    const token = metaElement.getAttribute('content');
    if (!token) {
        throw new Error('CSRF token not found');
    }
    return token;
}

async function deletePasskey(id: number | string): Promise<void> {
    if (!confirm(trans('passkey-auth.admin.delete.confirm'))) {
        return;
    }

    try {
        const response = await fetch(`${blessing.base_url}/admin/passkeys/${id}/delete`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
        });

        if (response.headers.get('content-type')?.includes('text/html')) {
            throw new Error(trans('passkey-auth.error.invalidResponse'));
        }

        const data = await response.json() as ApiResponse;
        if (!response.ok) {
            throw new Error(data.message || trans('passkey-auth.admin.delete.failed'));
        }

        alert(data.message);
        location.reload();
    } catch (error) {
        alert(error instanceof Error ? error.message : trans('passkey-auth.admin.delete.failed'));
    }
}

function showRenameDialog(id: number | string, currentName: string): void {
    const renameIdInput = document.querySelector<HTMLInputElement>('#renamePasskeyId');
    const newNameInput = document.querySelector<HTMLInputElement>('#newName');
    const renameModal = document.querySelector<HTMLElement>('#renameModal');

    if (!renameIdInput || !newNameInput || !renameModal) {
        console.error('Required elements not found');
        return;
    }

    renameIdInput.value = String(id);
    newNameInput.value = currentName;

    // 使用 Bootstrap 的类型定义
    ($(renameModal) as any).modal('show');
}

async function renamePasskey(): Promise<void> {
    const renameIdInput = document.querySelector<HTMLInputElement>('#renamePasskeyId');
    const newNameInput = document.querySelector<HTMLInputElement>('#newName');
    const renameModal = document.querySelector<HTMLElement>('#renameModal');

    if (!renameIdInput || !newNameInput || !renameModal) {
        console.error('Required elements not found');
        return;
    }

    const id = renameIdInput.value;
    const newName = newNameInput.value.trim();

    if (!newName) {
        alert(trans('passkey-auth.admin.rename.empty'));
        return;
    }

    try {
        const response = await fetch(`${blessing.base_url}/admin/passkeys/${id}/rename`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ name: newName })
        });

        if (response.headers.get('content-type')?.includes('text/html')) {
            throw new Error(trans('passkey-auth.error.invalidResponse'));
        }

        const data = await response.json() as ApiResponse;
        if (!response.ok) {
            throw new Error(data.message || trans('passkey-auth.admin.rename.failed'));
        }

        alert(data.message);
        ($(renameModal) as any).modal('hide');
        location.reload();
    } catch (error) {
        alert(error instanceof Error ? error.message : trans('passkey-auth.admin.rename.failed'));
    }
}