# 通行密钥登录 ZBPasskey 0.1.3

![通行密钥登录图标](logo.png)

**作者：superadmin & codex**

由 superadmin 与 codex 共同完成。superadmin 的思路、需求定义与实际使用验证，以及 codex 的方案细化、实现与测试，都是本项目的重要组成部分。

[下载安装包](https://github.com/super-administrator/ZBPasskey/releases/latest) · [更新记录](CHANGELOG.md) · [MIT 许可证](LICENSE)

适配目标：Z-BlogPHP 1.7.5.3540 (Optimus)、PHP 7.4、LiangbuLogin 令牌登录器 2.3。

## 登录与管理

- 默认显示“使用通行密钥登录”：点击后通过设备指纹、面容或 PIN 验证，无需用户名、密码或动态令牌。支持浏览器提供的手机扫码等跨设备流程。
- “使用密码和动态令牌登录”展开原 Z-Blog 登录表单，继续由 LiangbuLogin 检查动态令牌。
- 绑定、删除、重命名密钥时，重新验证当前密码。LiangbuLogin 全局及当前账号均启用时，还要求六位动态令牌（当前时间前后各一个 30 秒窗口）。
- 每个账号最多 20 个密钥。可发现凭据、设备用户验证为必需项；设备必须支持相应能力。
- 插件不修改 Z-Blog 核心文件或 LiangbuLogin 文件。

## 环境要求

1. 网站使用 HTTPS，Z-Blog 网站地址与访问域名一致，建议启用固定域名。反向代理须让 Z-Blog 正确识别 HTTPS。
2. PHP 7.4+，启用 OpenSSL、mbstring、PHP session；session 保存目录可写。
3. `zb_users/data/` 可写，文件锁和同目录原子重命名可用。适用于单服务器或提供一致文件锁的共享存储；多节点独立磁盘不适用。
4. 使用支持 WebAuthn 的近期浏览器。私钥由设备/凭据管理器保存，网站仅保存公钥。

## 安装

1. 在后台“插件管理”中上传 `ZBPasskey-0.1.3.zba` 并启用“通行密钥登录”。
2. 点击该插件的管理入口，配置页使用 Z-Blog 标准后台顶部导航与左侧菜单；也可在个人资料编辑页点击“管理我的通行密钥”。直接地址为 `https://你的域名/zb_users/plugin/ZBPasskey/main.php`（网站装在子目录时加上子目录）。
3. 输入名称、当前密码及需要的动态令牌，点击“绑定通行密钥”，完成设备验证。
4. 保持当前后台窗口，另开一个普通浏览器窗口退出账号后，验证通行密钥登录。隐私模式是否可访问原凭据取决于浏览器。

若后台不支持上传：解压 ZIP，将完整 `ZBPasskey` 目录上传到 `zb_users/plugin/`，再从插件管理启用。
不需要 Composer，不需要修改数据库，不要上传开发测试目录。

## 恢复、停用与备份

设备丢失时，使用原密码＋动态令牌登录，删除丢失设备的密钥。无法使用通行密钥时，点击备用登录入口。
停用插件恢复原登录页；停用或卸载不会自动删除已绑定密钥。彻底清除需先停用，再删除 `zb_users/data/zbpasskey.php` 和 `zbpasskey.php.lock`。
备份网站时同时备份该 PHP 数据文件及 Z-Blog 数据库/站点配置。域名或站点 GUID 改变后需重新绑定。
删除密钥阻止之后的密钥登录，不会撤销 Z-Blog 已签发的登录 Cookie。

## 安全实现与依赖

WebAuthn 验证基于 lbuchs/WebAuthn v2.2.0，固定上游提交 `20adb4a240c3997bd8cac7dc4dde38ab0bea0ed1`（MIT）。
上游声明 PHP 8.0+；本插件为 PHP 7.4 做了单行兼容修改：`str_starts_with($origin, 'android:apk-key-hash:')` 改为 `strpos($origin, 'android:apk-key-hash:') === 0`。不依赖全局 polyfill。完整许可证见 `vendor/LICENSE`。
仅接受 `none` 匿名证明，验证库校验密钥、RP ID、挑战、签名、用户在场及用户验证标志；插件另外严格匹配完整 HTTPS origin、用户句柄、账号 ID/GUID 和后台权限。
挑战有效期 120 秒、一次性使用；管理请求检查 session CSRF 及 HTTP Origin；密码/令牌失败按账号限制为每五分钟五次。
密钥数据使用独立文件锁、同目录原子替换及 PHP 访问保护。数据文件不含密码、TOTP 密钥或设备私钥。

## 已验证范围

详见 [验证记录](TEST-REPORT.md)。已完成 PHP 7.4.33 WebAssembly、Chrome 虚拟认证器以及 UTM Arch ARM 原生 PHP 7.4.33 集成测试；superadmin 已反馈实际绑定后成功登录。具体生物识别方式和凭据保存位置未单独记录，仍需在各自生产环境验证。

## 许可与署名

本插件原创部分采用 MIT 许可证，Copyright (c) 2026 superadmin & codex。第三方代码保留原作者署名与许可证，见 [第三方声明](THIRD-PARTY-NOTICES.md)。


升级时从后台覆盖安装新版即可，已有绑定数据保存在 `zb_users/data/zbpasskey.php`，无需重新绑定。
