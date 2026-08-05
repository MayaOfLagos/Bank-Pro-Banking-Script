<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/transfer_otp.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

// Only meaningful once every prior gate has been cleared; otherwise a
// customer could mint codes without ever passing COT/TAX/IMF.
if (($_SESSION['transfer_verification_stage'] ?? '') !== 'otp') {
  api_json(409, ['ok' => false, 'message' => 'Complete the required transfer verification steps first']);
}

$pendingTransferId = (int)($_SESSION['pending_transfer_id'] ?? 0);
if ($pendingTransferId < 1) {
  api_json(409, [
    'ok' => false,
    'message' => 'No transfer is awaiting verification. Please start the transfer again.',
    'data' => ['next_route' => '/dashboard'],
  ]);
}

// Confirm the pending transfer still exists and belongs to this customer
// before spending a send on it.
$owns = $conn->prepare('SELECT 1 FROM temp_trans WHERE wire_id = :wire_id AND acct_id = :acct_id LIMIT 1');
$owns->execute(['wire_id' => $pendingTransferId, 'acct_id' => (int)$user['id']]);
if (!$owns->fetchColumn()) {
  api_json(409, [
    'ok' => false,
    'message' => 'No transfer is awaiting verification. Please start the transfer again.',
    'data' => ['next_route' => '/dashboard'],
  ]);
}

// 60-second floor between sends. Session-scoped because the pending transfer
// is session-scoped; a fresh login has to re-submit the transfer anyway.
$lastSentAt = (int)($_SESSION['transfer_otp_last_sent_at'] ?? 0);
$sinceLast = time() - $lastSentAt;
if ($lastSentAt > 0 && $sinceLast < 60) {
  api_json(429, [
    'ok' => false,
    'message' => 'Please wait ' . (60 - $sinceLast) . 's before requesting another code.',
    'data' => ['retry_after' => 60 - $sinceLast],
  ]);
}

if (!transfer_otp_issue($conn, $user, $settings, $pendingTransferId)) {
  api_json(502, [
    'ok' => false,
    'message' => 'We could not send the code right now. Please try again shortly or contact support.',
  ]);
}

$_SESSION['transfer_otp_last_sent_at'] = time();

api_json(200, [
  'ok' => true,
  'message' => 'A new verification code has been sent to your email.',
  'data' => ['expires_in' => TRANSFER_OTP_TTL_SECONDS],
]);
