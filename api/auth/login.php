<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/auth_flow.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

// IP allow/block-list check runs BEFORE credentials are checked so a
// banned IP gets zero signal about whether an account exists — makes
// credential-stuffing from a known-bad IP pointless.
$loginIpCheck = auth_flow_ip_allowed($conn, auth_flow_client_ip());
if (!$loginIpCheck['allowed']) {
    auth_json(403, [
        'ok' => false,
        'message' => auth_flow_ip_denied_message(),
    ]);
}

if (!empty($_SESSION['acct_no'])) {
    auth_json(200, [
        'ok' => true,
        'message' => 'Already authenticated',
        'data' => ['next_route' => '/dashboard'],
    ]);
}

if (!empty($_SESSION['login'])) {
    auth_json(200, [
        'ok' => true,
        'message' => 'PIN verification is still required',
        'data' => ['next_route' => '/pin'],
    ]);
}

$payload = auth_payload();
auth_require($payload, ['acct_no', 'acct_password']);

$acctNo = auth_field($payload, 'acct_no');
$acctPassword = auth_field($payload, 'acct_password');

// Bind the same value to three distinct placeholders because PDO on PHP 7
// (and MySQL native prepares) reject reusing a named placeholder in one query.
$stmt = $conn->prepare('SELECT * FROM users WHERE acct_no = :acct OR acct_username = :username OR acct_email = :email LIMIT 1');
$stmt->execute([
    'acct' => $acctNo,
    'username' => $acctNo,
    'email' => $acctNo,
]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    auth_json(422, ['ok' => false, 'message' => 'Invalid login details']);
}

security_enforce_verify_lock($conn, (int)$user['id'], 'auth_json');

if (!password_verify($acctPassword, (string)$user['acct_password'])) {
    $result = security_record_verify_failure($conn, (int)$user['id']);
    auth_json(422, [
        'ok' => false,
        'message' => 'Invalid login details',
        'data' => ['attempts_remaining' => $result['attempts_remaining']],
    ]);
}

// Password checks out — reset the failed-attempts counter before we
// evaluate any policy gates. Credentials were valid; a status block
// is a separate matter.
security_reset_verify_attempts($conn, (int)$user['id']);

// Policy gate: account must be 'active' to advance to PIN. Rejecting
// here means a held / suspended / blocked user never reaches the PIN
// page and never gets a partial "pending_pin" session.
$blockMessage = auth_account_block_message($user);
if ($blockMessage !== null) {
    auth_json(403, [
        'ok' => false,
        'message' => $blockMessage,
        'data' => ['acct_status' => (string)($user['acct_status'] ?? '')],
    ]);
}

$device = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown device';
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$nowDate = date('Y-m-d H:i:s');

$audit = $conn->prepare('INSERT INTO audit_logs (user_id, device, ipAddress, datenow) VALUES (:user_id, :device, :ipAddress, :datenow)');
$audit->execute([
    'user_id' => $user['id'],
    'device' => $device,
    'ipAddress' => $ipAddress,
    'datenow' => $nowDate,
]);

session_regenerate_id(true);
$_SESSION['login'] = (string)$user['acct_no'];
$_SESSION['pw_snapshot'] = time();
// session_started_at is the anchor for the admin-forced-logout check in
// api/user/_bootstrap.php. Stamp it here so any session created AFTER
// an admin hit "sign this user out of all sessions" survives — the
// invalidation cutoff is only for sessions started before it.
$_SESSION['session_started_at'] = time();
unset($_SESSION['acct_no']);

auth_json(200, [
    'ok' => true,
    'message' => 'Login successful. Enter your transaction PIN to continue.',
    'data' => [
        'next_route' => '/pin',
        'csrf_token' => api_csrf_regenerate(),
    ],
]);
