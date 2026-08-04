<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/smtp.php';

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

$appName = (string)($settings['url_name'] ?? WEB_TITLE);
$appEmail = defined('WEB_EMAIL') ? WEB_EMAIL : '';
$fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
$displayName = $fullName !== '' ? $fullName : 'there';

$mailer = new message();
$safeNewEmail = htmlspecialchars($newEmail, ENT_QUOTES, 'UTF-8');
$safeOldEmail = htmlspecialchars($oldEmail, ENT_QUOTES, 'UTF-8');

// Confirmation to the new (now live) address.
$confirmBody = "
    <div style=\"font-family:Arial,sans-serif;font-size:14px;color:#111;line-height:1.6\">
        <h3 style=\"margin-bottom:10px\">Hi " . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . ",</h3>
        <p>Your <strong>" . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . "</strong> sign-in email is now <strong>{$safeNewEmail}</strong>.</p>
        <p>Use this address next time you sign in.</p>
    </div>
";
$mailer->send_mail($newEmail, $confirmBody, "Email address updated - {$appName}");

// Alert to the old (former) address so a compromise doesn't go silent.
if ($oldEmail !== '' && strcasecmp($oldEmail, $newEmail) !== 0) {
  $alertBody = "
      <div style=\"font-family:Arial,sans-serif;font-size:14px;color:#111;line-height:1.6\">
          <h3 style=\"margin-bottom:10px\">Hi " . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . ",</h3>
          <p>The sign-in email on your <strong>" . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . "</strong> account was changed from <strong>{$safeOldEmail}</strong> to <strong>{$safeNewEmail}</strong>.</p>
          <p>If this wasn't you, contact support immediately" . ($appEmail !== '' ? " at {$appEmail}" : '') . " and reset your password.</p>
      </div>
  ";
  $mailer->send_to_both($oldEmail, $alertBody, "Email address changed - {$appName}");
}

api_json(200, [
  'ok' => true,
  'message' => 'Your email has been updated',
  'data' => ['email' => $newEmail],
]);
