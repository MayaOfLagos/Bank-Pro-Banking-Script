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

$stmt = $conn->prepare('SELECT id, acct_no, acct_pin, acct_status FROM users WHERE acct_no = :acct_no LIMIT 1');
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
unset($_SESSION['login']);

auth_json(200, [
    'ok' => true,
    'message' => 'PIN verified. Welcome back.',
    'data' => [
        'next_route' => '/dashboard',
        'csrf_token' => api_csrf_regenerate(),
    ],
]);
