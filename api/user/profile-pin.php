<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';
require_once __DIR__ . '/../../include/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
api_require($payload, ['current_pin', 'new_pin', 'confirm_pin']);

$currentPin = api_field($payload, 'current_pin');
$newPin     = api_field($payload, 'new_pin');
$confirmPin = api_field($payload, 'confirm_pin');

// Legacy schema stores acct_pin as plain varchar(4) — same string
// comparison pattern pin-verify.php uses. Hashing PINs is a scheduled
// follow-up (Tier 2 auth hardening).
if ((string)$currentPin !== (string)($user['acct_pin'] ?? '')) {
  api_json(422, ['ok' => false, 'message' => 'Incorrect current PIN']);
}
if (!preg_match('/^\d{4}$/', $newPin)) {
  api_json(422, ['ok' => false, 'message' => 'PIN must be exactly 4 digits']);
}
if ($newPin !== $confirmPin) {
  api_json(422, ['ok' => false, 'message' => 'Confirm PIN does not match']);
}
if ($newPin === $currentPin) {
  api_json(422, ['ok' => false, 'message' => 'New PIN matches the current one']);
}
// Block trivial patterns — 0000, 1111, 1234, 4321 style sequences.
if (preg_match('/^(\d)\1{3}$/', $newPin)) {
  api_json(422, ['ok' => false, 'message' => 'Choose a PIN that is not all the same digit']);
}
if (in_array($newPin, ['1234', '4321', '0000', '1111', '2222', '3333', '4444', '5555', '6666', '7777', '8888', '9999'], true)) {
  api_json(422, ['ok' => false, 'message' => 'Choose a less predictable PIN']);
}

$stmt = $conn->prepare('UPDATE users SET acct_pin=:pin WHERE id=:id');
$stmt->execute(['pin' => $newPin, 'id' => $user['id']]);

$pinName = notify_plain(trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')), 80);
notify_user($conn, (int)$user['id'], 'profile.pin_changed', 'Your transaction PIN was changed',
  'Your 4-digit transaction PIN was updated just now. If this was not you, contact support immediately.',
  ['severity' => 'warning', 'link' => '/profile/security']);
notify_admin($conn, 'profile.pin_changed', 'Customer changed their transaction PIN',
  $pinName . ' updated their transaction PIN from the customer portal.',
  ['severity' => 'info', 'meta' => ['user_id' => (int)$user['id']]]);

// Best-effort notification — never blocks the response.
try {
  if (!empty($user['acct_email'])) {
    $mailer = new message();
    $sender = new emailMessage($settings);
    $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
    if (method_exists($sender, 'PinChange')) {
      $body = $sender->PinChange($fullName, WEB_EMAIL, WEB_TITLE);
    } else {
      // Fall back to the password-change template if a dedicated PIN
      // template hasn't been added yet — same intent, close-enough copy.
      $body = $sender->PassChange($fullName, WEB_EMAIL, WEB_TITLE);
    }
    $mailer->send_to_both($user['acct_email'], $body, 'Transaction PIN Changed - ' . WEB_TITLE);
  }
} catch (Throwable $e) {
  error_log('[profile-pin] mail failed: ' . $e->getMessage());
}

api_json(200, ['ok' => true, 'message' => 'Transaction PIN updated successfully']);
