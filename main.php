<?php
require __DIR__ . '/bootstrap.php';
if (!$zbp->islogin || !$zbp->CheckRights('admin')) { http_response_code(403); exit('请先登录后台'); }
try { pk_environment(); pk_session(); } catch (Throwable $e) { exit(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')); }
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
require_once ZBP_PATH . 'zb_system/function/c_system_admin.php';
$blogtitle = '通行密钥管理';
require ZBP_PATH . 'zb_system/admin/admin_header.php';
?>
<style>
#zbpasskey-admin .pk-help{margin:0 0 20px;line-height:1.8;color:#666}
#zbpasskey-admin .pk-settings{max-width:1000px}
#zbpasskey-admin .pk-label{width:180px}
#zbpasskey-admin .pk-input{box-sizing:border-box;width:100%;max-width:420px}
#zbpasskey-admin .pk-actions{margin:20px 0}
#zbpasskey-admin .pk-list{list-style:none;padding:0;margin:12px 0 0}
#zbpasskey-admin .pk-list li{padding:14px 10px;border-bottom:1px solid #e5e5e5;line-height:2;overflow-wrap:anywhere}
#zbpasskey-admin .pk-list button{margin:0 0 0 10px;padding:6px 14px;border:1px solid #aaa;background:#f5f5f5;color:#333;cursor:pointer;font:inherit}
#zbpasskey-admin .pk-list button:disabled{opacity:.6;cursor:wait}
#zbpasskey-admin #status{min-height:1.8em;white-space:pre-wrap;overflow-wrap:anywhere}
@media(max-width:600px){#zbpasskey-admin .pk-label{width:100px}#zbpasskey-admin .pk-list button{margin:6px}}
</style>
<?php require ZBP_PATH . 'zb_system/admin/admin_top.php'; ?>
<div id="divMain">
    <div class="divHeader"><?php echo $blogtitle; ?></div>
    <div class="SubMenu">
        <?php if ($zbp->CheckRights('PluginMng')): ?>
        <a href="<?php echo htmlspecialchars($zbp->cmdurl . '?act=PluginMng', ENT_QUOTES, 'UTF-8'); ?>"><span class="m-left">插件管理</span></a>
        <?php endif; ?>
        <a href="<?php echo htmlspecialchars($zbp->cmdurl . '?act=MemberEdt&id=' . (int)$zbp->user->ID, ENT_QUOTES, 'UTF-8'); ?>"><span class="m-left">个人资料</span></a>
    </div>
    <div id="divMain2">
        <div id="zbpasskey-admin">
            <p class="pk-help">为当前账号绑定指纹、面容、设备 PIN 或安全密钥。请保留密码和原动态令牌作为恢复方式。绑定、删除或重命名时需再次验证身份。</p>
            <div class="divHeader2">绑定与身份验证</div>
            <table class="tableFull tableBorder table_striped pk-settings">
                <tbody>
                    <tr>
                        <td class="pk-label"><label for="pk-name">密钥名称</label></td>
                        <td><input class="edit pk-input" id="pk-name" type="text" maxlength="60" placeholder="例如：我的手机" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <td class="pk-label"><label for="pk-password">当前密码</label></td>
                        <td><input class="edit pk-input" id="pk-password" type="password" autocomplete="current-password"></td>
                    </tr>
                    <?php if (pk_totp_required($zbp->user)): ?>
                    <tr>
                        <td class="pk-label"><label for="pk-otp">动态令牌</label></td>
                        <td><input class="edit pk-input" id="pk-otp" type="text" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="六位动态令牌"></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <p class="pk-actions"><input class="button" type="button" id="pk-create" value="绑定通行密钥"></p>
            <p id="status" role="status" aria-live="polite"></p>
            <div class="divHeader2">已绑定的通行密钥</div>
            <ul class="pk-list" id="pk-list"></ul>
        </div>
    </div>
</div>
<script>
window.ZBPasskeyConfig=<?php echo json_encode(['csrf'=>$_SESSION['pk_csrf']], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
ActiveLeftMenu(<?php echo json_encode($zbp->CheckRights('PluginMng') ? 'aPluginMng' : 'aMemberMng'); ?>);
AddHeaderIcon(<?php echo json_encode($zbp->host . 'zb_users/plugin/ZBPasskey/logo.png', JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>);
</script>
<script src="passkey.js?v=0.1.3"></script>
<?php
require ZBP_PATH . 'zb_system/admin/admin_footer.php';
RunTime();
