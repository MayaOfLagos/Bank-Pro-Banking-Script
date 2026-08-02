<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = auth_payload();
auth_require($payload, ['email', 'reset_token', 'new_password']);

$email = filter_var(auth_field($payload, 'email'), FILTER_VALIDATE_EMAIL);
$token = auth_field($payload, 'reset_token');
$newPassword = auth_field($payload, 'new_password');

if (!$email || !preg_match('/^[a-zA-Z0-9_\-]{8,}$/', $token)) {
    auth_json(422, ['ok' => false, 'message' => 'Reset link is invalid or has expired.']);
}

if (strlen($newPassword) < 6) {
    auth_json(422, ['ok' => false, 'message' => 'Password must be at least 6 characters.']);
}

$stmt = $conn->prepare('SELECT * FROM users WHERE acct_email = :email AND resettoken = :token AND resettokenexp >= NOW() LIMIT 1');
$stmt->execute([
    'email' => $email,
    'token' => $token,
]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    auth_json(422, ['ok' => false, 'message' => 'Reset link is invalid or has expired.']);
}

$hash = password_hash($newPassword, PASSWORD_BCRYPT);
$update = $conn->prepare('UPDATE users SET acct_password = :password, password_changed_at = NOW(), resettoken = NULL, resettokenexp = NULL WHERE acct_email = :email AND resettoken = :token');
$update->execute([
    'password' => $hash,
    'email' => $email,
    'token' => $token,
]);

auth_send_sms_if_enabled($settings, (string)($user['acct_phone'] ?? ''), 'Security Alert: Password Changed');
auth_send_password_changed_email($user, $appName, $mailer);

auth_json(200, [
    'ok' => true,
    'message' => 'Your password has been updated successfully.',
    'data' => [
        'next_route' => '/login',
    ],
]);
