<?php
if (!defined('ZBP_PATH')) { exit; }
RegisterPlugin('ZBPasskey', 'ActivePlugin_ZBPasskey');
function ActivePlugin_ZBPasskey() {
    Add_Filter_Plugin('Filter_Plugin_Login_Header', 'ZBPasskey_Login');
    Add_Filter_Plugin('Filter_Plugin_Member_Edit_Response', 'ZBPasskey_Menu');
}
function ZBPasskey_Login() {
    global $zbp;
    echo '<script defer src="' . htmlspecialchars($zbp->host . 'zb_users/plugin/ZBPasskey/passkey.js', ENT_QUOTES, 'UTF-8') . '"></script>';
}
function InstallPlugin_ZBPasskey() {}
function UninstallPlugin_ZBPasskey() {} // Keep credentials when disabled/uninstalled.

function ZBPasskey_Menu() {
    global $zbp;
    $url = $zbp->host . 'zb_users/plugin/ZBPasskey/main.php';
    echo '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">管理我的通行密钥</a></p>';
}
