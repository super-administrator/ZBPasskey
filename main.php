<?php
require __DIR__ . '/bootstrap.php';
if (!$zbp->islogin || !$zbp->CheckRights('admin')) { http_response_code(403); exit('请先登录后台'); }
try { pk_environment(); pk_session(); } catch (Throwable $e) { exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')); }
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
?><!doctype html><html lang="zh-CN"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>通行密钥管理</title><style>body{font:16px system-ui;max-width:760px;margin:40px auto;padding:20px}input,button{font:inherit;padding:8px;margin:5px}li{margin:12px 0}#status{white-space:pre-wrap}</style>
<h1>通行密钥管理</h1><p>为当前账号绑定指纹、面容、设备 PIN 或安全密钥。请保留密码和原动态令牌作为恢复方式。绑定、删除或重命名时需再次验证身份。</p>
<p><label>密钥名称 <input id="pk-name" maxlength="60" placeholder="例如：我的手机" autocomplete="off"></label></p>
<p><label>当前密码 <input id="pk-password" type="password" autocomplete="current-password"></label></p>
<?php if (pk_totp_required($zbp->user)): ?><p><label>动态令牌 <input id="pk-otp" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="六位动态令牌"></label></p><?php endif; ?>
<button id="pk-create">绑定通行密钥</button><ul id="pk-list"></ul><p id="status" role="status"></p>
<script>window.ZBPasskeyConfig=<?php echo json_encode(['csrf'=>$_SESSION['pk_csrf']], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;</script>
<script src="passkey.js"></script></html>
