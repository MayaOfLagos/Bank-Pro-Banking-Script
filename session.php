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

// Idle-timeout enforcement is now driven by settings.session_idle_minutes
// (admin-configurable, clamped to 5..1440 by include/auth_flow.php).
// Applies to BOTH customer /api/* sessions and — via admin/include/session.php
// — the admin panel. We load config + the helper here so every request
// that goes through session.php reads the current policy without an
// extra include on the caller side.
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/auth_flow.php';

try {
    $sessionFlowConn = dbConnect();
    $sessionTimedOut = auth_flow_apply_idle_timeout($sessionFlowConn);
} catch (Throwable $e) {
    // DB unreachable — do not lock users out. Preserve the old
    // hard-coded 600s behaviour so a temporary outage never counts as
    // "session policy = 0 seconds".
    $sessionTimedOut = false;
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity'] > 600)) {
        session_unset();
        session_destroy();
        session_start();
        $sessionTimedOut = true;
    }
    $_SESSION['last_activity'] = time();
}

if ($sessionTimedOut) {
    // AJAX/API requests: session is now empty; the endpoint's own auth
    // guard will 401. Non-AJAX callers get bounced to /login the same
    // way the old code did.
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
        header('Location: /login');
        exit;
    }
    return;
}
