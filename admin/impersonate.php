<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/flash.php';

// Only a real admin session can start impersonating — checked before
// csrf_check() reads $_POST at all, matching every other admin action.
$admin = require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/users.php');
    exit;
}

csrf_check();
$targetId = (int) ($_POST['user_id'] ?? 0);

if (start_impersonating((int) $admin['id'], $targetId)) {
    header('Location: /dashboard.php');
    exit;
}

flash_set('error', 'Tento účet nelze takto zobrazit.');
header('Location: /admin/users.php');
exit;
