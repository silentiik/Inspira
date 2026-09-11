<?php
require_once __DIR__ . '/../app/auth.php';

log_out_user();
header('Location: /index.php');
exit;
