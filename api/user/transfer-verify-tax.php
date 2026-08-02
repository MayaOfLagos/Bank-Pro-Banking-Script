<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
$code = api_field($payload, 'tax_code');
if ($code === '') api_json(422, ['ok' => false, 'message' => 'Tax code is required']);

if ((int)($_SESSION['pending_transfer_id'] ?? 0) < 1 || ($_SESSION['transfer_verification_stage'] ?? '') !== 'tax') {
  api_json(409, ['ok' => false, 'message' => 'No transfer is awaiting TAX verification']);
}

security_enforce_verify_lock($conn, (int)$user['id'], 'api_json');

if ($code !== (string)($user['acct_tax'] ?? '')) {
  security_record_verify_failure($conn, (int)$user['id']);
  api_json(422, ['ok' => false, 'message' => 'Invalid TAX code']);
}

security_reset_verify_attempts($conn, (int)$user['id']);
$_SESSION['transfer_verification_stage'] = !empty($user['acct_imf']) ? 'imf' : 'otp';
$nextRoute = $_SESSION['transfer_verification_stage'] === 'imf' ? '/transfer-imf' : '/transfer-verify';
api_json(200, ['ok' => true, 'message' => 'Tax verified', 'data' => ['next_route' => $nextRoute]]);
