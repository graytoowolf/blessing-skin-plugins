# Passkey Authentication

通过 WebAuthn/Passkey 实现无密码登录和多设备认证管理。

## 功能特性
- 网站主体使用Laravel 8.83.27版本
- 支持使用 Passkey (生物识别/PIN码) 进行安全登录
- 支持绑定多个认证设备
- 支持管理已绑定的 Passkey
- 兼容主流浏览器的 WebAuthn API

- 插件启用时创建相关数据库。禁用时不需要删除。
## 使用说明

1. 在用户中心的"Passkey 管理"页面可以注册新的 Passkey
2. 注册时需要输入 Passkey 名称以便识别不同设备
3. 可以删除不再使用的 Passkey
4. 登录时可以选择使用 Passkey 认证

## 系统要求

- 视图使用 Twig 作为模板引擎
- Blessing Skin v5 v6 或更高版本
- 支持 WebAuthn 的现代浏览器
- 支持生物识别或 PIN 码的设备

## License

MIT License

PluginWasDisabled
触发时机

插件被禁用之后。

#plugin
类型：App\Services\Plugin

含义：被禁用的插件的实例

#PluginWasEnabled
触发时机

插件被启用之后。

#plugin
类型：App\Services\Plugin

含义：被启用的插件的实例

## 下面是JS文件实例
function str2ab(str){
    return Uint8Array.from(window.atob(str), c=>c.charCodeAt(0));
}

function array2b64String(a) {
    return window.btoa(String.fromCharCode(...a));
}


document.addEventListener('DOMContentLoaded', async () => {
    const registerButton = document.querySelector(".btn#passkey-register");
    registerButton.addEventListener('click', async () => {
        registerButton.disabled = true;
        blessing.notify.toast.success("正在发起验证…")
        await blessing.fetch.get('/user/passkey/register').then((data) => {
            data.user.id = str2ab(data.user.id.replace(/-/g, '+').replace(/_/g, '/'));
            data.challenge = str2ab(data.challenge.replace(/-/g, '+').replace(/_/g, '/'));
            return data;
        }).then((data) =>
            navigator.credentials.create({ publicKey: data })
        ).then((credentialInfo) => {
            const publicKeyCredential = {
                id: credentialInfo.id,
                type: credentialInfo.type,
                rawId: array2b64String(new Uint8Array(credentialInfo.rawId)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
                response: {
                    clientDataJSON: array2b64String(new Uint8Array(credentialInfo.response.clientDataJSON)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, ''),
                    attestationObject: array2b64String(new Uint8Array(credentialInfo.response.attestationObject)).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '')
                }
            };
            return publicKeyCredential;
        }).then(async (authenticatorResponse) => {
            let passkey = await blessing.fetch.post('/user/passkey/register', authenticatorResponse);
            let name = await blessing.notify.showModal({
                mode: "prompt",
                title: "通行密钥命名",
                text: "给你的通行密钥设置一个名字吧",
                placeholder: "Passkey"
            })
            return {id: passkey.data.id, name: name.value}
        }).then((data) => {
            if(data.name === '') {
                return { code: 0 }
            }
            return blessing.fetch.post(`/user/passkey/${data.id}/rename`, { name: data.name });
        }).then((response) => {
            console.log(response)
            if(response.code !== 0) {
                blessing.notify.toast.error(response.message);
            } else {
                blessing.notify.toast.success("通行密钥添加成功");
                location.reload();
            }
        }).catch((e) => {
            if(e instanceof DOMException && e.name == "NotAllowedError") {
                blessing.notify.toast.error("验证被拒绝")
            };
        })
        registerButton.disabled = false;
    })


    const deleteButtons = document.querySelectorAll('.btn.passkey-delete');
    deleteButtons.forEach((deleteButton) => {
        const name = deleteButton.parentNode.parentNode.childNodes[1].childNodes[1].childNodes[1].childNodes[1].childNodes[3].textContent;
        deleteButton.addEventListener('click', () => {
            blessing.notify.showModal({
                mode: "prompt",
                type: "danger",
                title: "确定删除通行密钥吗？",
                text: `此操作不可恢复！\n删除该通行密钥后，你将无法再使用该通行密钥登录 LittleSkin。\n我们不提供任何备份，也没有什么神奇的撤销按钮。\n我们警告过你了，确定要这样做吗？\n如果确定，请在下方输入该通行密钥的名称。`,
                placeholder: name
            }).then((data) => {
                if(data.value !== name) {
                    return {code: 1, message: "通行密钥名称输入错误"};
                }
                return blessing.fetch.post(
                    `/user/passkey/${deleteButton.parentNode.parentNode.id.replace('passkey-', '')}/delete`,
                    { name: data.value }
                )
            }).then(response => {
                if(response.code !== 0) {
                    blessing.notify.toast.error(response.message);
                } else {
                    blessing.notify.toast.success(response.message);
                    location.reload();
                }
            })
        })
    })

    const renameButtons = document.querySelectorAll('.btn.passkey-rename');
    renameButtons.forEach((renameButton) => {
        const name = renameButton.parentNode.parentNode.childNodes[1].childNodes[1].childNodes[1].childNodes[1].childNodes[3].textContent;
        renameButton.addEventListener('click', () => {
            blessing.notify.showModal({
                mode: "prompt",
                title: "通行密钥命名",
                text: "给你的通行密钥起个名字吧",
                placeholder: name
            }).then((data) => {
                if(data.value === '') {
                    return { code: 0 }
                }
                return blessing.fetch.post(`/user/passkey/${renameButton.parentNode.parentNode.id.replace('passkey-', '')}/rename`, { name: data.value });
            }).then(response => {
                if(response.code !== 0) {
                    blessing.notify.toast.error(response.message);
                } else {
                    blessing.notify.toast.success("通行密钥重命名成功");
                    location.reload();
                }
            })
        })
    })

})

