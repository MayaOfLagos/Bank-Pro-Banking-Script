<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ERROR | E_WARNING | E_PARSE);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', 1);
    session_start();
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval' https:; style-src 'self' 'unsafe-inline' https:; img-src 'self' data: https:; font-src 'self' data: https:; connect-src 'self';");

// Idle-timeout enforcement is now settings-driven (settings.session_idle_minutes),
// shared with the customer portal via include/auth_flow.php. Falls back
// to a hard-coded 1100s ceiling if the DB is unreachable so a transient
// outage doesn't lock every admin out.
require_once __DIR__ . '/../../include/auth_flow.php';

$sessionAdminTimedOut = false;
try {
    if (isset($conn) && $conn instanceof PDO) {
        $sessionAdminTimedOut = auth_flow_apply_idle_timeout($conn);
    } else {
        $sessionAdminConn = dbConnect();
        $sessionAdminTimedOut = auth_flow_apply_idle_timeout($sessionAdminConn);
    }
} catch (Throwable $e) {
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity'] > 1100)) {
        session_unset();
        session_destroy();
        session_start();
        $sessionAdminTimedOut = true;
    }
    $_SESSION['last_activity'] = time();
}

if ($sessionAdminTimedOut) {
    header('Location:./login.php');
    exit;
}
