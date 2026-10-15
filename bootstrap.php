<?php
require_once dirname(__DIR__, 3) . '/zb_system/function/c_system_base.php';
$zbp->Load();
if (!$zbp->CheckPlugin('ZBPasskey')) { http_response_code(403); exit('插件未启用'); }
spl_autoload_register(function ($class) {
    $prefix = 'lbuchs\\WebAuthn\\';
    if (strpos($class, $prefix) === 0) {
        $file = __DIR__ . '/vendor/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) { require_once $file; }
    }
});
function pk_origin() {
    global $zbp;
    $p = parse_url($zbp->host);
    if (!$p || ($p['scheme'] ?? '') !== 'https') { throw new RuntimeException('网站地址必须配置为 HTTPS'); }
    return 'https://' . strtolower($p['host']) . (isset($p['port']) ? ':' . $p['port'] : '');
}
function pk_session() {
    pk_origin();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('ZBPasskeySession');
        session_start(['use_strict_mode'=>1, 'use_only_cookies'=>1, 'cookie_secure'=>true, 'cookie_httponly'=>true, 'cookie_samesite'=>'Strict', 'cookie_path'=>'/']);
    }
    if (empty($_SESSION['pk_csrf'])) { $_SESSION['pk_csrf'] = bin2hex(random_bytes(32)); }
}
function pk_db() {
    // PHP guard prevents disclosure even if this data file is requested directly.
    $file = ZBP_PATH . 'zb_users/data/zbpasskey.php';
    $fp = fopen($file . '.lock', 'c+');
    if (!$fp || !flock($fp, LOCK_EX)) { throw new RuntimeException('无法锁定密钥存储'); }
    $raw = is_file($file) ? file_get_contents($file) : '';
    $prefix = "<?php exit; ?>\n";
    if ($raw === '') { $data = ['credentials'=>[], 'attempts'=>[]]; }
    else {
        if (strpos($raw, $prefix) !== 0) { fclose($fp); throw new RuntimeException('密钥存储损坏'); }
        $data = json_decode(substr($raw, strlen($prefix)), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data) || !isset($data['credentials'], $data['attempts']) || !is_array($data['credentials']) || !is_array($data['attempts'])) { fclose($fp); throw new RuntimeException('密钥存储损坏'); }
    }
    return [$fp, $data];
}
function pk_save($fp, $data) {
    $file = ZBP_PATH . 'zb_users/data/zbpasskey.php';
    $raw = "<?php exit; ?>\n" . json_encode($data, JSON_THROW_ON_ERROR);
    // Same-directory rename keeps readers from observing a partially written store.
    $tmp = $file . '.' . bin2hex(random_bytes(12)) . '.php';
    if (file_put_contents($tmp, $raw) !== strlen($raw)) { @unlink($tmp); throw new RuntimeException('密钥存储写入失败'); }
    chmod($tmp, 0600);
    if (!rename($tmp, $file)) { @unlink($tmp); throw new RuntimeException('密钥存储替换失败'); }
    flock($fp, LOCK_UN); fclose($fp);
}
function pk_decode($v) {
    if (!is_string($v) || strlen($v) > 100000 || !preg_match('/^[A-Za-z0-9_-]+$/D', $v)) { throw new RuntimeException('无效编码'); }
    $r = base64_decode(strtr($v, '-_', '+/'), true);
    if ($r === false) { throw new RuntimeException('无效编码'); }
    return $r;
}
function pk_id($v) { return rtrim(strtr(base64_encode($v), '+/', '-_'), '='); }
function pk_environment() {
    if (PHP_VERSION_ID < 70400 || !extension_loaded('openssl') || !extension_loaded('mbstring')) {
        throw new RuntimeException('需要 PHP 7.4+、OpenSSL 和 mbstring 扩展');
    }
}
function pk_totp_required($member) {
    global $zbp;
    return $zbp->CheckPlugin('LiangbuLogin') && $zbp->Config('LiangbuLogin')->enable && $member->Metas->LB_enable;
}
function pk_reauthenticate($in, $uid, &$store, $fp) {
    global $zbp;
    $key = (string)$uid;
    $attempts = array_values(array_filter($store['attempts'][$key] ?? [], function ($t) { return $t > time()-300; }));
    if (count($attempts) >= 5) { throw new RuntimeException('尝试过多，请五分钟后重试'); }
    $attempts[] = time();
    $store['attempts'][$key] = $attempts;
    $member = null;
    $valid = $zbp->Verify_Original($zbp->user->Name, (string)($in['password'] ?? ''), $member) && (int)$member->ID === $uid;
    if ($valid && pk_totp_required($member)) {
        $otp = (string)($in['otp'] ?? '');
        $valid = class_exists('Google2FA') && preg_match('/^[0-9]{6}$/D', $otp) && Google2FA::verify_key($member->Metas->LB_key, $otp, 1);
    }
    if (!$valid) { pk_save($fp, $store); throw new RuntimeException('密码或动态令牌不正确'); }
    $store['attempts'][$key] = [];
}
