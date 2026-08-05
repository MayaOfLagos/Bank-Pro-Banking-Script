<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';
require_once __DIR__ . '/../../include/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
api_require($payload, ['old_password', 'new_password', 'confirm_password']);

$oldPassword = api_field($payload, 'old_password');
$newPassword = api_field($payload, 'new_password');
$confirmPassword = api_field($payload, 'confirm_password');

if (!password_verify($oldPassword, (string)($user['acct_password'] ?? ''))) {
  api_json(422, ['ok' => false, 'message' => 'Incorrect old password']);
}
if ($newPassword !== $confirmPassword) {
  api_json(422, ['ok' => false, 'message' => 'Confirm password not matched']);
}
if ($newPassword === $oldPassword) {
  api_json(422, ['ok' => false, 'message' => 'New password matched with old password']);
}

$hash = password_hash($newPassword, PASSWORD_BCRYPT);
$stmt = $conn->prepare('UPDATE users SET acct_password=:acct_password WHERE id=:id');
$stmt->execute(['acct_password' => $hash, 'id' => $user['id']]);

$pwName = notify_plain(trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')), 80);
notify_user($conn, (int)$user['id'], 'profile.password_changed', 'Your password was changed',
  'Your sign-in password was changed just now. If this was not you, contact support immediately.',
  ['severity' => 'warning', 'link' => '/profile/security']);
notify_admin($conn, 'profile.password_changed', 'Customer changed their password',
  $pwName . ' changed their sign-in password from the customer portal.',
  ['severity' => 'info', 'meta' => ['user_id' => (int)$user['id']]]);

$email_message = new message();
$sendMail = new emailMessage($settings);
if (!empty($user['acct_email'])) {
  $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
  $msg = $sendMail->PassChange($fullName, WEB_EMAIL, WEB_TITLE);
  $email_message->send_to_both($user['acct_email'], $msg, 'Password Change Notification - ' . WEB_TITLE);
}

api_json(200, ['ok' => true, 'message' => 'Password changed successfully']);
