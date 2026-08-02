<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_json(405, ['ok' => false, 'message' => 'Method not allowed']);
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

$stmt = $conn->prepare('SELECT * FROM users WHERE acct_no = :identifier OR acct_username = :identifier OR acct_email = :identifier LIMIT 1');
$stmt->execute(['identifier' => $acctNo]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($acctPassword, (string)$user['acct_password'])) {
    auth_json(422, ['ok' => false, 'message' => 'Invalid login details']);
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

auth_send_login_email($user, $device, $ipAddress, $nowDate, $appName, $appUrl, $bankPhone, $mailer);

session_regenerate_id(true);
$_SESSION['login'] = (string)$user['acct_no'];
unset($_SESSION['acct_no']);

auth_json(200, [
    'ok' => true,
    'message' => 'Login successful. Enter your transaction PIN to continue.',
    'data' => [
        'next_route' => '/pin',
    ],
]);
