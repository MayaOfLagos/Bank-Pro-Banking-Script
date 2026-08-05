<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/transfer_otp.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
api_require($payload, ['amount', 'acct_name', 'bank_name', 'acct_number', 'acct_type']);

$amount = (float)api_field($payload, 'amount', '0');
$acctName = api_field($payload, 'acct_name');
$bankName = api_field($payload, 'bank_name');
$acctNumber = api_field($payload, 'acct_number');
$acctType = api_field($payload, 'acct_type');
$acctRemarks = api_field($payload, 'acct_remarks');

if ((string)($settings['transfer'] ?? '1') !== '1' || (string)($user['transfer'] ?? '1') !== '1' || (string)($user['acct_status'] ?? '') !== 'active') {
  api_json(403, ['ok' => false, 'message' => 'Domestic transfer disabled for this account']);
}
if ($amount <= 0) {
  api_json(422, ['ok' => false, 'message' => 'Invalid amount']);
}
if ($amount > (float)($user['acct_balance'] ?? 0)) {
  api_json(422, ['ok' => false, 'message' => 'Insufficient balance']);
}
if ($amount > (float)($user['limit_remain'] ?? 0)) {
  api_json(422, ['ok' => false, 'message' => 'Amount exceeds your remaining transfer limit']);
}

$transId = bin2hex(random_bytes(16));
$transOtp = (string)random_int(100000, 999999);

$conn->beginTransaction();
try {
  $insert = $conn->prepare('INSERT INTO temp_trans (amount,trans_id,acct_id,bank_name,acct_name_id,acct_number,acct_type,acct_remarks,trans_otp,trans_type) VALUES(:amount,:trans_id,:acct_id,:bank_name,:acct_name,:acct_number,:acct_type,:acct_remarks,:trans_otp,:trans_type)');
  $insert->execute([
    'amount' => $amount,
    'trans_id' => $transId,
    'acct_id' => $user['id'],
    'bank_name' => $bankName,
    'acct_name' => $acctName,
    'acct_number' => $acctNumber,
    'acct_type' => $acctType,
    'acct_remarks' => $acctRemarks,
    'trans_otp' => $transOtp,
    'trans_type' => 'domestic transfer'
  ]);
  $pendingTransferId = (int)$conn->lastInsertId();

  $update = $conn->prepare('UPDATE users SET acct_otp=:acct_otp WHERE id=:id');
  $update->execute(['acct_otp' => $transOtp, 'id' => $user['id']]);
  $conn->commit();

  $_SESSION['pending_transfer_id'] = $pendingTransferId;
  $_SESSION['pending_transfer_created_at'] = time();
  $nextRoute = '/transfer-verify';
  if (!empty($user['acct_cot'])) {
    $_SESSION['transfer_verification_stage'] = 'cot';
    $nextRoute = '/transfer-cot';
  } elseif (!empty($user['acct_tax'])) {
    $_SESSION['transfer_verification_stage'] = 'tax';
    $nextRoute = '/transfer-tax';
  } elseif (!empty($user['acct_imf'])) {
    $_SESSION['transfer_verification_stage'] = 'imf';
    $nextRoute = '/transfer-imf';
  } else {
    $_SESSION['transfer_verification_stage'] = 'otp';
  }

  // Only mail the code when the customer lands on the OTP screen next. If a
  // COT/TAX/IMF gate comes first, the code is issued by whichever handler
  // clears the last gate — otherwise the 15-minute window would be spent on
  // screens that do not need it.
  if ($_SESSION['transfer_verification_stage'] === 'otp') {
    transfer_otp_issue($conn, $user, $settings, $pendingTransferId);
  }

  api_json(200, ['ok' => true, 'message' => 'Domestic transfer initialized', 'data' => ['next_route' => $nextRoute]]);
} catch (Throwable $e) {
  $conn->rollBack();
  api_json(500, ['ok' => false, 'message' => 'Unable to start domestic transfer']);
}
