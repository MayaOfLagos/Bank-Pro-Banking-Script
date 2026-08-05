<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

if (empty($_SESSION['login'])) {
    auth_json(401, ['ok' => false, 'message' => 'Login session expired. Please sign in again.']);
}

$payload = auth_payload();
auth_require($payload, ['pin']);

$pin = auth_field($payload, 'pin');
if ($pin === '') {
    auth_json(422, ['ok' => false, 'message' => 'Enter your PIN']);
}

$stmt = $conn->prepare('SELECT id, acct_no, acct_pin, acct_status, firstname, lastname, acct_email FROM users WHERE acct_no = :acct_no LIMIT 1');
$stmt->execute(['acct_no' => (string)$_SESSION['login']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    auth_json(404, ['ok' => false, 'message' => 'Account not found']);
}

// Same policy gate as login.php — an account that lost 'active' status
// between login and PIN entry must not be able to complete auth. Wipe
// the half-formed session and bounce back to /login.
$blockMessage = auth_account_block_message($user);
if ($blockMessage !== null) {
    session_unset();
    session_destroy();
    auth_json(403, [
        'ok' => false,
        'message' => $blockMessage,
        'data' => ['acct_status' => (string)$user['acct_status'], 'next_route' => '/login'],
    ]);
}

security_enforce_verify_lock($conn, (int)$user['id'], 'auth_json');

if ((string)$pin !== (string)$user['acct_pin']) {
    $result = security_record_verify_failure($conn, (int)$user['id']);
    auth_json(422, [
        'ok' => false,
        'message' => 'Invalid PIN code',
        'data' => ['attempts_remaining' => $result['attempts_remaining']],
    ]);
}

security_reset_verify_attempts($conn, (int)$user['id']);
session_regenerate_id(true);
$_SESSION['acct_no'] = (string)$user['acct_no'];
$_SESSION['pw_snapshot'] = $_SESSION['pw_snapshot'] ?? time();
// Refresh session_started_at at the moment the session becomes fully
// authenticated (post-PIN). api/user/_bootstrap.php's forced-logout
// check compares users.sessions_invalidated_at against this value.
$_SESSION['session_started_at'] = time();
unset($_SESSION['login']);

// New authenticated session. Emitted HERE and not in login.php: this is the
// first point at which the caller has proved BOTH password and PIN, so an
// unauthenticated or half-authenticated caller can never push rows into the
// table. One PIN verify == one new session, so this is naturally once-per-
// session; the 10-minute dedupe below only exists to stop a customer who is
// bouncing between tabs/devices from filling their own drawer.
//
// The dedupe reads the notifications table rather than adding a users column,
// so this ships with the notifications migration alone — no second schema
// change to sequence, and no fighting the last_login_email_at window that the
// email alert below owns.
require_once __DIR__ . '/../../include/notifications.php';
$shouldNotifyLogin = true;
try {
    $recentLoginStmt = $conn->prepare(
        'SELECT 1 FROM notifications
          WHERE audience = :audience AND user_id = :uid AND event = :event
            AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
          LIMIT 1'
    );
    $recentLoginStmt->execute([
        'audience' => 'user',
        'uid' => (int)$user['id'],
        'event' => 'auth.login',
    ]);
    $shouldNotifyLogin = !$recentLoginStmt->fetchColumn();
} catch (Throwable $loginNoticeError) {
    // Table not migrated yet. Fall through and let notify_user() do its own
    // logging + no-op rather than silently disabling the feature.
    error_log('[pin-verify] login notice dedupe unavailable: ' . $loginNoticeError->getMessage());
}
if ($shouldNotifyLogin) {
    // The User-Agent is wholly attacker-supplied and the name is self-chosen;
    // both land in a markdown body that the portal renders. notify_plain()
    // strips the syntax so neither can inject a link or an image.
    $loginDevice = notify_plain($_SERVER['HTTP_USER_AGENT'] ?? '', 120);
    if ($loginDevice === '') {
        $loginDevice = 'Unknown device';
    }
    $loginIp = notify_plain($_SERVER['REMOTE_ADDR'] ?? '', 45);
    if ($loginIp === '') {
        $loginIp = '0.0.0.0';
    }
    $loginName = notify_plain(trim((string)($user['firstname'] ?? '') . ' ' . (string)($user['lastname'] ?? '')), 80);
    notify_user($conn, (int)$user['id'], 'auth.login', 'New sign-in to your account',
        'A new sign-in was completed from `' . $loginIp . '` (' . $loginDevice . '). If this was not you, change your password immediately.',
        ['severity' => 'info', 'link' => '/profile/security', 'meta' => ['ip' => $loginIp, 'device' => $loginDevice]]);
    notify_admin($conn, 'auth.login', 'Customer signed in',
        ($loginName !== '' ? $loginName : 'Account ' . (string)$user['acct_no']) . ' signed in from `' . $loginIp . '`.',
        ['severity' => 'info', 'meta' => ['user_id' => (int)$user['id'], 'ip' => $loginIp, 'device' => $loginDevice]]);
}

// Rate-limited login alert: fire at most once per 10-minute window per
// user. The UPDATE is atomic — only one concurrent PIN verify wins the
// write, so even rapid parallel logins produce exactly one email.
//
// The whole block is best-effort. The customer is already authenticated by
// this point; a notification must never be able to turn a successful login
// into a 500. That is not hypothetical — this code references
// users.last_login_email_at, and if its migration has not been applied yet
// the UPDATE throws "Unknown column" and locks every customer out of the
// platform. Deployment order must never be able to cause an outage.
try {
    $rateLimitStmt = $conn->prepare(
        'UPDATE users SET last_login_email_at = NOW()
         WHERE id = :id
           AND (last_login_email_at IS NULL
             OR last_login_email_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE))'
    );
    $rateLimitStmt->execute(['id' => (int)$user['id']]);
    if ($rateLimitStmt->rowCount() > 0) {
        require_once __DIR__ . '/../../include/userClass.php';
        $alertTpl  = new emailMessage($settings);
        $alertName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
        $alertBody = $alertTpl->LoginAlert(
            $alertName,
            (string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device'),
            (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            date('Y-m-d H:i:s'),
            $appName
        );
        $alertTo = (string)($user['acct_email'] ?? '');
        if ($alertTo !== '') {
            $mailer->send_to_both($alertTo, $alertBody, 'Login Alert - ' . $appName);
        }
    }
} catch (Throwable $loginAlertError) {
    error_log('[pin-verify] login alert skipped: ' . $loginAlertError->getMessage());
}

auth_json(200, [
    'ok' => true,
    'message' => 'PIN verified. Welcome back.',
    'data' => [
        'next_route' => '/dashboard',
        'csrf_token' => api_csrf_regenerate(),
    ],
]);
