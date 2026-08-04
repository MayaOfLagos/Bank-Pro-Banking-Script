<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/smtp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
api_require($payload, ['new_email']);

// Normalise before any comparison so casing/whitespace never lets the
// same address slip past uniqueness checks.
$rawEmail = api_field($payload, 'new_email');
$newEmail = strtolower(trim($rawEmail));
$newEmail = filter_var($newEmail, FILTER_SANITIZE_EMAIL);

if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
  api_json(422, ['ok' => false, 'message' => 'Enter a valid email address']);
}
if (mb_strlen($newEmail) > 190) {
  api_json(422, ['ok' => false, 'message' => 'Email address is too long']);
}

$currentEmail = strtolower(trim((string)($user['acct_email'] ?? '')));
if ($newEmail === $currentEmail) {
  api_json(422, ['ok' => false, 'message' => 'That is already your current email']);
}

// Uniqueness — must not collide with another account. Case-insensitive
// on the stored value so 'Alice@x.com' and 'alice@x.com' count as one.
$check = $conn->prepare('SELECT id FROM users WHERE LOWER(acct_email) = :email AND id != :me LIMIT 1');
$check->execute(['email' => $newEmail, 'me' => $user['id']]);
if ($check->fetchColumn()) {
  api_json(409, ['ok' => false, 'message' => 'That email is already in use']);
}

// Rate limit: if a pending request exists and was issued in the last 60
// seconds, refuse. Prevents rapid resends from spamming an inbox.
if (!empty($user['pending_email_expires'])) {
  $issuedAt = strtotime((string)$user['pending_email_expires']) - (15 * 60);
  if ($issuedAt !== false && (time() - $issuedAt) < 60) {
    $wait = 60 - (time() - $issuedAt);
    api_json(429, [
      'ok' => false,
      'message' => "Please wait {$wait}s before requesting another code",
    ]);
  }
}

// Generate a zero-padded 6-digit OTP. random_int is cryptographically
// safe; storing plaintext is acceptable here because it's short-lived
// (15 min), single-use, and never leaves the server after verification.
$otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

$store = $conn->prepare(
  'UPDATE users SET pending_email = :pending_email,
                    pending_email_otp = :pending_email_otp,
                    pending_email_expires = DATE_ADD(NOW(), INTERVAL 15 MINUTE),
                    pending_email_attempts = 0
   WHERE id = :id'
);
$store->execute([
  'pending_email' => $newEmail,
  'pending_email_otp' => $otp,
  'id' => $user['id'],
]);

$appName = (string)($settings['url_name'] ?? WEB_TITLE);
$appEmail = defined('WEB_EMAIL') ? WEB_EMAIL : '';
$fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
$displayName = $fullName !== '' ? $fullName : 'there';

$mailer = new message();

// Deliver the OTP to the NEW address — that's the whole point of the
// verification loop: prove the user actually controls the destination
// inbox before we swap the login email.
$otpSubject = "Confirm your new email address - {$appName}";
$otpBody = "
    <div style=\"font-family:Arial,sans-serif;font-size:14px;color:#111;line-height:1.6\">
        <h3 style=\"margin-bottom:10px\">Hi " . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . ",</h3>
        <p>Use the code below to confirm this address as the new sign-in email for your <strong>" . htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') . "</strong> account.</p>
        <p style=\"font-size:28px;font-weight:800;letter-spacing:6px;margin:20px 0;color:#111\">{$otp}</p>
        <p>The code expires in 15 minutes. If you didn't request this change, ignore this email and the address on your account stays the same.</p>
    </div>
";
$mailer->send_mail($newEmail, $otpBody, $otpSubject);

// Notify the OLD address as an out-of-band security signal — if the
// account is being taken over, the legitimate owner learns immediately
// even if they don't spot the pending state in the UI.
if ($currentEmail !== '') {
  $safeNewEmail = htmlspecialchars($newEmail, ENT_QUOTES, 'UTF-8');
  $noticeSubject = "Email change requested - {$appName}";
  $noticeBody = "
      <div style=\"font-family:Arial,sans-serif;font-size:14px;color:#111;line-height:1.6\">
          <h3 style=\"margin-bottom:10px\">Hi " . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . ",</h3>
          <p>We received a request to change the email on your account to <strong>{$safeNewEmail}</strong>. A verification code was sent there — the change will not take effect until it is confirmed.</p>
          <p>If this wasn't you, sign in and cancel the request from Profile &rarr; Personal details, then update your password immediately." . ($appEmail !== '' ? " You can also contact support at {$appEmail}." : '') . "</p>
      </div>
  ";
  $mailer->send_to_both($currentEmail, $noticeBody, $noticeSubject);
}

api_json(200, [
  'ok' => true,
  'message' => 'Verification code sent to your new email',
  'data' => [
    'new_email' => $newEmail,
    'expires_at' => date('c', time() + (15 * 60)),
  ],
]);
