<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/transfer_otp.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
$code = api_field($payload, 'imf_code');
if ($code === '') api_json(422, ['ok' => false, 'message' => 'IMF code is required']);

if ((int)($_SESSION['pending_transfer_id'] ?? 0) < 1 || ($_SESSION['transfer_verification_stage'] ?? '') !== 'imf') {
  api_json(409, ['ok' => false, 'message' => 'No transfer is awaiting IMF verification']);
}

security_enforce_verify_lock($conn, (int)$user['id'], 'api_json');

if ($code !== (string)($user['acct_imf'] ?? '')) {
  $result = security_record_verify_failure($conn, (int)$user['id']);
  api_json(422, [
    'ok' => false,
    'message' => 'Invalid IMF code',
    'data' => ['attempts_remaining' => $result['attempts_remaining']],
  ]);
}

security_reset_verify_attempts($conn, (int)$user['id']);

// IMF is always the final gate — issue a fresh code and start its window now.
transfer_otp_issue($conn, $user, $settings, (int)$_SESSION['pending_transfer_id']);

$_SESSION['transfer_verification_stage'] = 'otp';
api_json(200, ['ok' => true, 'message' => 'IMF verified', 'data' => ['next_route' => '/transfer-verify']]);
