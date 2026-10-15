<?php
require __DIR__ . '/bootstrap.php';
try { pk_environment(); pk_session(); header('Content-Type: application/json'); header('Cache-Control: no-store'); echo json_encode(['csrf'=>$_SESSION['pk_csrf']]); }
catch (Throwable $e) { http_response_code(400); echo '{}'; }
