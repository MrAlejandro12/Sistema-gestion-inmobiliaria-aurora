<?php
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/config/db.php';
secureSessionStart();
if (isset($_SESSION['user_id'])) {
    auditLog('Logout — sesión cerrada');
}
session_destroy();
header('Location: /sgi_aurora/index.php');
exit;
