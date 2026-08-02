<?php
// Never expose PHP errors to the browser
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

// Harden the session cookie
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (($_SERVER['HTTPS'] ?? '') === 'on')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    if ($isHttps) {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Security headers — sent before any output
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
// script-src keeps 'unsafe-inline' for the legacy front pages' inline scripts;
// 'unsafe-eval' is dropped because nothing here uses eval(). The Vue portal
// serves its own tighter CSP via user-app.php that overrides this one.
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; font-src 'self' data: https:; connect-src 'self';");

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 600)) {
    session_unset();
    session_destroy();
    // AJAX/API requests: clear the session and let the endpoint's own guard return JSON 401
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
        header('Location: /login');
        exit;
    }
    // For AJAX, fall through — the session is now empty; endpoint auth checks return 401
    return;
}
$_SESSION['LAST_ACTIVITY'] = time();