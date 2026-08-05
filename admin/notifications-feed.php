<?php
/**
 * JSON feed behind the navbar notification bell.
 *
 * GET  → { ok: true, data: { notifications: [...max 10], unread_count: N } }
 * POST → action=read&id=N | action=read_all
 *        { ok: true, data: { unread_count: N } }
 *
 * ── Why this file does NOT include layout/header.php ──────────────────────
 *
 * Every other admin page bootstraps through admin/layout/header.php, which is
 * exactly the wrong shape for an XHR endpoint. Three of the things it does are
 * actively harmful here:
 *
 *   1. admin/include/session.php redirects to login.php on idle timeout. A
 *      302 to an HTML login page is the classic "my JSON parser exploded"
 *      failure: fetch() follows the redirect, gets 200 text/html, and the
 *      client sees a JSON syntax error instead of "you are signed out".
 *      This endpoint answers 401 with a JSON body, always.
 *   2. It calls auth_flow_apply_idle_timeout(), which STAMPS
 *      $_SESSION['last_activity'] on every call. A bell polling every 60s
 *      would therefore keep an unattended admin session alive forever and
 *      quietly disable the idle-timeout policy the panel just gained. The
 *      check below is deliberately read-only: it compares last_activity
 *      against the configured window and refuses, without touching it. The
 *      next real page load performs the actual teardown.
 *   3. ob_start('admin_csrf_inject_forms') would run a form-stamping regex
 *      over a JSON body for no reason.
 *
 * So the bootstrap is assembled by hand, with the same session hardening
 * flags admin/include/adminloginFunction.php applies, and the same
 * `empty($_SESSION['admin'])` gate every admin page uses.
 *
 * ── CSRF on POST ──────────────────────────────────────────────────────────
 *
 * admin_csrf_valid() only ever inspects $_POST['admin_csrf'], because the
 * panel's protection is the output-buffer form stamper in
 * admin/include/adminFunction.php — there is no form here. The token arrives
 * in an X-Admin-Csrf request header instead, which has a useful property a
 * body field does not: a cross-origin <form> cannot set a custom header, so
 * the browser is forced into a preflight the panel never answers. Compared
 * with hash_equals() against the same $_SESSION['admin_csrf'] the rest of the
 * panel uses; the shared helper is left alone on purpose.
 *
 * Paths are all __DIR__-relative. PHP-FPM does not put the process CWD at the
 * script directory, so "../include/config.php" would resolve somewhere else
 * entirely on the production host.
 */

require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/auth_flow.php';
require_once __DIR__ . '/../include/notifications.php';
require_once __DIR__ . '/include/adminNotifications.php';

// Errors must never leak into the response body — a PHP warning printed above
// the JSON makes the whole payload unparseable on the client.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

/**
 * Emit a JSON envelope and stop. Shape matches the customer API: a boolean
 * `ok` plus either `data` or `error`, never a bare array.
 *
 * @param int   $status HTTP status
 * @param bool  $ok
 * @param array $payload data (when $ok) or ['message' => ...]
 */
function admin_feed_respond($status, $ok, array $payload)
{
    http_response_code((int)$status);
    $body = $ok ? array('ok' => true, 'data' => $payload)
                : array('ok' => false, 'error' => $payload);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Auth gate ─────────────────────────────────────────────────────────────
// No session, no data. Not a redirect, not an empty 200 — 401 with a body the
// client can branch on.
if (empty($_SESSION['admin'])) {
    admin_feed_respond(401, false, array('message' => 'Not signed in.'));
}

$conn = dbConnect();
if (!($conn instanceof PDO)) {
    admin_feed_respond(503, false, array('message' => 'Database unavailable.'));
}

// Read-only idle check. See the header comment: stamping last_activity here
// would make the bell an infinite session keep-alive.
try {
    $idleSeconds = auth_flow_idle_seconds($conn);
    if (isset($_SESSION['last_activity'])
        && (time() - (int)$_SESSION['last_activity']) > $idleSeconds) {
        admin_feed_respond(401, false, array('message' => 'Session expired.'));
    }
} catch (Throwable $e) {
    // A settings-table hiccup must not lock the bell out; the real gate is
    // the session check above, this is only the idle policy.
    error_log('[admin-notify-feed] idle check failed: ' . $e->getMessage());
}

$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';

// ── POST: mark read ───────────────────────────────────────────────────────
if ($method === 'POST') {
    $sessionToken = isset($_SESSION['admin_csrf']) && is_string($_SESSION['admin_csrf'])
        ? $_SESSION['admin_csrf']
        : '';
    $headerToken = isset($_SERVER['HTTP_X_ADMIN_CSRF']) ? (string)$_SERVER['HTTP_X_ADMIN_CSRF'] : '';

    // hash_equals() needs two non-empty strings to be meaningful; an empty
    // session token would otherwise match an empty header token.
    if ($sessionToken === '' || $headerToken === '' || !hash_equals($sessionToken, $headerToken)) {
        admin_feed_respond(403, false, array('message' => 'Missing or stale security token.'));
    }

    $action = isset($_POST['action']) ? strtolower(trim((string)$_POST['action'])) : '';

    if ($action === 'read') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if ($id <= 0) {
            admin_feed_respond(400, false, array('message' => 'A notification id is required.'));
        }
        admin_notify_mark_read($conn, $id);
        admin_feed_respond(200, true, array('unread_count' => admin_notify_unread_count($conn)));
    }

    if ($action === 'read_all') {
        admin_notify_mark_all_read($conn);
        admin_feed_respond(200, true, array('unread_count' => admin_notify_unread_count($conn)));
    }

    admin_feed_respond(400, false, array('message' => 'Unknown action.'));
}

if ($method !== 'GET' && $method !== 'HEAD') {
    header('Allow: GET, POST');
    admin_feed_respond(405, false, array('message' => 'Method not allowed.'));
}

// ── GET: the 10 newest visible admin notifications ────────────────────────
$rows = admin_notify_recent($conn, 10);

$notifications = array();
foreach ($rows as $row) {
    // Values are handed to the client as data, never as markup. The bell
    // script assigns them with textContent, so the escaping that would
    // otherwise be needed here happens by construction on the other side —
    // and `excerpt` is plain text precisely so nothing is tempted to
    // innerHTML it. severity_class / icon come from fixed allowlists, so the
    // only strings the client ever puts in an attribute are ours.
    $notifications[] = array(
        'id'             => (int)$row['id'],
        'event'          => (string)$row['event'],
        'title'          => (string)$row['title'],
        'excerpt'        => admin_notify_excerpt(isset($row['body']) ? $row['body'] : '', 90),
        'severity'       => (string)$row['severity'],
        'severity_class' => admin_notify_severity_class($row['severity']),
        'severity_icon'  => admin_notify_severity_icon($row['severity']),
        'link'           => isset($row['link']) && $row['link'] !== '' ? (string)$row['link'] : null,
        'is_read'        => !empty($row['read_at']),
        'created_at'     => (string)$row['created_at'],
        'relative_time'  => admin_notify_relative_time($row['created_at'], admin_notify_now_ts($conn)),
    );
}

admin_feed_respond(200, true, array(
    'notifications' => $notifications,
    'unread_count'  => admin_notify_unread_count($conn),
));
