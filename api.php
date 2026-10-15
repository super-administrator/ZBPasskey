<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    require __DIR__ . '/bootstrap.php';
    pk_environment();
    pk_session();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_SERVER['HTTP_ORIGIN'] ?? '') !== pk_origin()) { throw new RuntimeException('请求来源无效'); }
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 150000) { throw new RuntimeException('请求过大'); }
    $in = json_decode(file_get_contents('php://input', false, null, 0, 150001), true, 32, JSON_THROW_ON_ERROR);
    if (!hash_equals($_SESSION['pk_csrf'], (string)($in['csrf'] ?? ''))) { throw new RuntimeException('页面已过期，请刷新'); }
    $action = $in['action'] ?? '';
    $manage = in_array($action, ['list','remove','rename','createOptions','createFinish'], true);
    $uid = (int)$zbp->user->ID;
    if ($manage && (!$zbp->islogin || $uid < 1 || !$zbp->CheckRights('admin'))) { throw new RuntimeException('请先登录后台'); }
    $wa = new \lbuchs\WebAuthn\WebAuthn('Z-Blog Passkey', parse_url(pk_origin(), PHP_URL_HOST), ['none'], true);
    [$fp, $store] = pk_db();
    $db =& $store['credentials'];
    $result = [];
    if ($action === 'list') {
        foreach ($db as $id => $row) { if ($row['uid'] === $uid) { $result[] = ['id'=>$id,'name'=>$row['name'],'created'=>$row['created']]; } }
    } elseif ($action === 'remove' || $action === 'rename' || $action === 'createOptions') {
        pk_reauthenticate($in, $uid, $store, $fp);
        if ($action === 'remove' || $action === 'rename') {
            $id = (string)($in['id'] ?? '');
            if (!isset($db[$id]) || $db[$id]['uid'] !== $uid) { throw new RuntimeException('密钥不存在'); }
            if ($action === 'remove') { unset($db[$id]); }
            else { $db[$id]['name'] = mb_substr(trim((string)($in['name'] ?? '通行密钥')), 0, 60); }
        } else {
            $exclude = [];
            foreach ($db as $id=>$row) { if ($row['uid'] === $uid) { $exclude[] = pk_decode($id); } }
            if (count($exclude) >= 20) { throw new RuntimeException('每个账号最多绑定 20 个密钥'); }
            $handle = hash('sha256', $zbp->guid . ':passkey:' . $uid, true);
            $result = $wa->getCreateArgs($handle, $zbp->user->Name, $zbp->user->Name, 60, 'required', true, null, $exclude);
            $_SESSION['pk_pending'] = ['kind'=>'create','uid'=>$uid,'passwordHash'=>hash('sha256', $zbp->user->Password),'expires'=>time()+120,'challenge'=>$wa->getChallenge()->getBinaryString(),'name'=>mb_substr(trim((string)($in['name'] ?? '通行密钥')),0,60)];
        }
    } elseif ($action === 'getOptions') {
        $recent = array_values(array_filter($_SESSION['pk_get_times'] ?? [], function ($t) { return $t > time()-60; }));
        if (count($recent) >= 20) { throw new RuntimeException('请求过于频繁，请稍后重试'); }
        $recent[] = time(); $_SESSION['pk_get_times'] = $recent;
        $result = $wa->getGetArgs([], 60, true, true, true, true, true, true);
        $_SESSION['pk_pending'] = ['kind'=>'get','expires'=>time()+120,'challenge'=>$wa->getChallenge()->getBinaryString()];
    } elseif ($action === 'createFinish' || $action === 'getFinish') {
        $pending = $_SESSION['pk_pending'] ?? [];
        unset($_SESSION['pk_pending']); // consume even on failed verification
        if (($pending['expires'] ?? 0) < time() || ($pending['kind'] ?? '') !== ($action === 'createFinish' ? 'create' : 'get')) { throw new RuntimeException('验证已过期，请重试'); }
        $client = pk_decode($in['clientDataJSON'] ?? '');
        $cd = json_decode($client, true, 32, JSON_THROW_ON_ERROR);
        if (($cd['origin'] ?? '') !== pk_origin() || !empty($cd['crossOrigin'])) { throw new RuntimeException('密钥来源不符'); }
        if ($action === 'createFinish') {
            if (($pending['uid'] ?? 0) !== $uid || !hash_equals($pending['passwordHash'] ?? '', hash('sha256', $zbp->user->Password))) { throw new RuntimeException('登录账号已改变'); }
            $data = $wa->processCreate($client, pk_decode($in['attestationObject'] ?? ''), $pending['challenge'], true, true);
            $id = pk_id($data->credentialId);
            if (isset($db[$id])) { throw new RuntimeException('密钥已经存在'); }
            $owned = 0; foreach ($db as $row) { if ($row['uid'] === $uid) { $owned++; } }
            if ($owned >= 20) { throw new RuntimeException('每个账号最多绑定 20 个密钥'); }
            $db[$id] = ['uid'=>$uid,'guid'=>$zbp->user->Guid,'name'=>$pending['name'],'created'=>gmdate('c'),'key'=>$data->credentialPublicKey,'counter'=>(int)$data->signatureCounter];
        } else {
            $id = pk_id(pk_decode($in['id'] ?? ''));
            if (!isset($db[$id])) { throw new RuntimeException('登录验证失败'); }
            $row = $db[$id];
            $member = $zbp->GetMemberByID($row['uid']);
            if (!$member || (int)$member->ID !== $row['uid'] || (int)$member->Level > 5 || (int)$member->Level < 1 || !hash_equals((string)($row['guid'] ?? ''), (string)$member->Guid)) { throw new RuntimeException('账号无法登录后台'); }
            $handle = hash('sha256', $zbp->guid . ':passkey:' . $row['uid'], true);
            if (!hash_equals($handle, pk_decode($in['userHandle'] ?? ''))) { throw new RuntimeException('账号验证失败'); }
            $wa->processGet($client, pk_decode($in['authenticatorData'] ?? ''), pk_decode($in['signature'] ?? ''), $row['key'], $pending['challenge'], $row['counter'], true, true);
            $db[$id]['counter'] = (int)$wa->getSignatureCounter();
            pk_save($fp, $store); $fp = null;
            session_regenerate_id(true);
            $zbp->user = $member; $zbp->islogin = true;
            if (!$zbp->CheckRights('admin')) { throw new RuntimeException('账号没有后台权限'); }
            SetLoginCookie($member, 0);
            foreach ($GLOBALS['hooks']['Filter_Plugin_VerifyLogin_Succeed'] as $hook => &$signal) { $hook(); }
            $result = ['redirect'=>$zbp->host . 'zb_system/cmd.php?act=admin'];
        }
    } else { throw new RuntimeException('未知操作'); }
    if (isset($fp) && is_resource($fp)) {
        if (in_array($action, ['remove','rename','createOptions','createFinish'], true)) { pk_save($fp, $store); }
        else { flock($fp, LOCK_UN); fclose($fp); }
    }
    echo json_encode(['ok'=>true,'data'=>$result], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    if (isset($fp) && is_resource($fp)) { flock($fp, LOCK_UN); fclose($fp); }
    http_response_code(400);
    // Do not disclose key material or library internals.
    error_log('ZBPasskey: ' . $e->getMessage());
    $message = get_class($e) === 'RuntimeException' ? $e->getMessage() : '密钥验证失败，请刷新后重试';
    echo json_encode(['ok'=>false,'error'=>$message], JSON_UNESCAPED_UNICODE);
}
