<?php
require_once __DIR__ . '/includes/analytics-auth.php';

$_SESSION = [];
session_destroy();

header('Location: /analytics-login');
exit;
