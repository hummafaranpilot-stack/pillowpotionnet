<?php
// Session handling for the /analytics dashboard — separate from includes/auth.php
// (admin panel) so the two logins don't share or clobber each other's session state.

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, // session cookie — cleared when the browser closes
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** For pages rendered in a browser — redirects to the analytics login form. */
function require_analytics_login() {
    if (empty($_SESSION['pp_analytics_logged_in'])) {
        header('Location: /analytics-login');
        exit;
    }
}
