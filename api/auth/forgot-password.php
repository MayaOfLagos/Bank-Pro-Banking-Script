<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = auth_payload();
auth_require($payload, ['email']);

$email = filter_var(auth_field($payload, 'email'), FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    auth_json(422, ['ok' => false, 'message' => 'Invalid email address.']);
}

$stmt = $conn->prepare('SELECT * FROM users WHERE acct_email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Do not disclose whether an account exists for the submitted email.
    auth_json(200, [
        'ok' => true,
        'message' => 'If an account matches that email, a password reset link will be sent.',
    ]);
}

$token = bin2hex(random_bytes(16));
$expiresAt = date('Y-m-d H:i:s', time() + 1800);

$update = $conn->prepare('UPDATE users SET resettoken = :token, resettokenexp = :exp WHERE acct_email = :email');
$update->execute([
    'token' => $token,
    'exp' => $expiresAt,
    'email' => $email,
]);

auth_send_sms_if_enabled($settings, (string)($user['acct_phone'] ?? ''), 'Alert: Password Reset');
auth_send_reset_email($user, $token, $appName, $appUrl, $mailer);

auth_json(200, [
    'ok' => true,
    'message' => 'Password reset link sent to your email.',
]);
