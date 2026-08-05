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

if (strlen($newPassword) < 8) {
    auth_json(422, ['ok' => false, 'message' => 'Password must be at least 8 characters.']);
}
if (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/\d/', $newPassword)) {
    auth_json(422, ['ok' => false, 'message' => 'Password must contain at least one letter and one number.']);
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

// Unauthenticated endpoint, but not a spam vector: reaching this line
// required a valid, unexpired, single-use reset token bound to this exact
// email, and the token was just consumed by the UPDATE above. One row per
// genuine reset.
require_once __DIR__ . '/../../include/notifications.php';
$resetName = notify_plain(trim((string)($user['firstname'] ?? '') . ' ' . (string)($user['lastname'] ?? '')), 80);
$resetEmailLabel = notify_plain($email, 120);
notify_user($conn, (int)$user['id'], 'profile.password_changed', 'Your password was reset',
    'Your password was reset using the "forgot password" link. If this was not you, contact support immediately.',
    ['severity' => 'warning', 'link' => '/profile/security', 'meta' => ['via' => 'reset_token']]);
notify_admin($conn, 'profile.password_changed', 'Customer reset their password',
    ($resetName !== '' ? $resetName : $resetEmailLabel) . ' completed a password reset via the emailed link.',
    ['severity' => 'info', 'meta' => ['user_id' => (int)$user['id'], 'via' => 'reset_token']]);

auth_send_password_changed_email($user, $appName, $mailer, $settings);

auth_json(200, [
    'ok' => true,
    'message' => 'Your password has been updated successfully.',
    'data' => [
        'next_route' => '/login',
    ],
]);
