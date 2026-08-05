<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';
require_once __DIR__ . '/../../include/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
api_require($payload, ['otp']);

$otp = preg_replace('/\D/', '', api_field($payload, 'otp'));
if (strlen($otp) !== 6) {
  api_json(422, ['ok' => false, 'message' => 'Enter the 6-digit code']);
}

if (empty($user['pending_email']) || empty($user['pending_email_otp']) || empty($user['pending_email_expires'])) {
  api_json(422, ['ok' => false, 'message' => 'No email change is pending. Start a new request.']);
}

$expiresAt = strtotime((string)$user['pending_email_expires']);
if ($expiresAt === false || $expiresAt < time()) {
  // Wipe the stale pending state so the next request lands cleanly.
  $conn->prepare('UPDATE users SET pending_email = NULL, pending_email_otp = NULL, pending_email_expires = NULL, pending_email_attempts = 0 WHERE id = :id')
       ->execute(['id' => $user['id']]);
  api_json(422, ['ok' => false, 'message' => 'That code has expired. Request a new one.']);
}

$attempts = (int)($user['pending_email_attempts'] ?? 0);
$maxAttempts = 5;

// hash_equals prevents an attacker from timing individual digit
// comparisons — even though both values are short, the code path used
// here is worth keeping constant-time.
if (!hash_equals((string)$user['pending_email_otp'], $otp)) {
  $attempts++;
  if ($attempts >= $maxAttempts) {
    $conn->prepare('UPDATE users SET pending_email = NULL, pending_email_otp = NULL, pending_email_expires = NULL, pending_email_attempts = 0 WHERE id = :id')
         ->execute(['id' => $user['id']]);
    api_json(429, ['ok' => false, 'message' => 'Too many wrong attempts. Request a new code.']);
  }
  $conn->prepare('UPDATE users SET pending_email_attempts = :attempts WHERE id = :id')
       ->execute(['attempts' => $attempts, 'id' => $user['id']]);
  $remaining = $maxAttempts - $attempts;
  api_json(422, [
    'ok' => false,
    'message' => "Incorrect code. {$remaining} attempt" . ($remaining === 1 ? '' : 's') . ' left.',
  ]);
}

$newEmail = (string)$user['pending_email'];
$oldEmail = (string)($user['acct_email'] ?? '');

// Second uniqueness check right before promotion — someone else may
// have claimed the address during the 15-minute verification window.
$check = $conn->prepare('SELECT id FROM users WHERE LOWER(acct_email) = :email AND id != :me LIMIT 1');
$check->execute(['email' => strtolower($newEmail), 'me' => $user['id']]);
if ($check->fetchColumn()) {
  $conn->prepare('UPDATE users SET pending_email = NULL, pending_email_otp = NULL, pending_email_expires = NULL, pending_email_attempts = 0 WHERE id = :id')
       ->execute(['id' => $user['id']]);
  api_json(409, ['ok' => false, 'message' => 'That email was just taken by another account. Try a different one.']);
}

$promote = $conn->prepare(
  'UPDATE users SET acct_email = :new_email,
                    pending_email = NULL,
                    pending_email_otp = NULL,
                    pending_email_expires = NULL,
                    pending_email_attempts = 0
   WHERE id = :id'
);
$promote->execute(['new_email' => $newEmail, 'id' => $user['id']]);

// Fires only after the OTP has been verified and the address promoted, so a
// half-finished change never produces a notification.
$emailChangeName = notify_plain(trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')), 80);
$newEmailLabel = notify_plain($newEmail, 120);
$oldEmailLabel = notify_plain($oldEmail, 120);
notify_user($conn, (int)$user['id'], 'profile.email_changed', 'Your email address was changed',
  'Your account email is now **' . $newEmailLabel . '**. If this was not you, contact support immediately.',
  ['severity' => 'warning', 'link' => '/profile/security', 'meta' => ['new_email' => $newEmail]]);
notify_admin($conn, 'profile.email_changed', 'Customer changed their email address',
  $emailChangeName . ' changed their account email from ' . ($oldEmailLabel !== '' ? $oldEmailLabel : 'an unset address') . ' to **' . $newEmailLabel . '**.',
  ['severity' => 'warning', 'meta' => ['user_id' => (int)$user['id'], 'old_email' => $oldEmail, 'new_email' => $newEmail]]);

$appName = (string)($settings['url_name'] ?? WEB_TITLE);
$appEmail = defined('WEB_EMAIL') ? WEB_EMAIL : '';
$fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
$displayName = $fullName !== '' ? $fullName : 'there';

$mailer = new message();
$template = new emailMessage($settings);

// Confirmation to the new (now live) address.
$confirmBody = $template->emailChangeConfirmed($displayName, $newEmail, $appName);
$mailer->send_mail($newEmail, $confirmBody, "Email address updated - {$appName}");

// Alert to the old (former) address so a compromise doesn't go silent.
if ($oldEmail !== '' && strcasecmp($oldEmail, $newEmail) !== 0) {
  $supportEmail = (string)($settings['url_email'] ?? $appEmail);
  $alertBody = $template->emailChangeAlert($displayName, $newEmail, $supportEmail, $appName);
  $mailer->send_to_both($oldEmail, $alertBody, "Email address changed - {$appName}");
}

api_json(200, [
  'ok' => true,
  'message' => 'Your email has been updated',
  'data' => ['email' => $newEmail],
]);
