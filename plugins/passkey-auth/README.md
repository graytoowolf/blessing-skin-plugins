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
