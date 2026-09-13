<?php
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';

// Deliberately does NOT require_role(['admin']) — while impersonating,
// current_user() returns the impersonated account, which is never an
// admin. is_impersonating() is what actually gates this.
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_impersonating()) {
    header('Location: /dashboard.php');
    exit;
}

csrf_check();
stop_impersonating();
header('Location: /admin/users.php');
exit;
