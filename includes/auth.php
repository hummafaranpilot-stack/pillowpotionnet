<?php
// Long-lived session cookie (1 year) — once logged in on a device, stay logged in
// instead of being asked again on every visit.
$_ONE_YEAR = 60 * 60 * 24 * 365;
ini_set('session.gc_maxlifetime', $_ONE_YEAR);
session_set_cookie_params([
    'lifetime' => $_ONE_YEAR,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
// Refresh the cookie's expiry on every request so it keeps sliding forward.
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), $_COOKIE[session_name()], time() + $_ONE_YEAR, '/', '', !empty($_SERVER['HTTPS']), true);
}

define('ADMIN_PASSWORD', 'PillowPotion@2026');

/** For pages rendered in a browser — redirects to the login form. */
function require_login() {
    if (empty($_SESSION['pp_admin'])) {
        header('Location: /login.php');
        exit;
    }
}

/** For JSON API endpoints — returns a 401 instead of redirecting. */
function require_login_api() {
    if (empty($_SESSION['pp_admin'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'not authenticated']);
        exit;
    }
}
