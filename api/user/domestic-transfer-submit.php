<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';
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

if ((string)($user['acct_status'] ?? '') === 'hold') {
  api_json(403, ['ok' => false, 'message' => 'Account on hold']);
}
if ($amount <= 0) {
  api_json(422, ['ok' => false, 'message' => 'Invalid amount']);
}
if ($amount > (float)($user['acct_balance'] ?? 0)) {
  api_json(422, ['ok' => false, 'message' => 'Insufficient balance']);
}

$transId = uniqid();
$transOtp = substr(number_format(time() * rand(), 0, '', ''), 0, 6);

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

  $otp = substr(number_format(time() * rand(), 0, '', ''), 0, 6);
  $update = $conn->prepare('UPDATE users SET acct_otp=:acct_otp WHERE id=:id');
  $update->execute(['acct_otp' => $otp, 'id' => $user['id']]);
  $conn->commit();

  $email_message = new message();
  $sendMail = new emailMessage();
  if (!empty($user['acct_email'])) {
    $message = $sendMail->pinRequest(user_currency_symbol($user), $amount, trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')), $otp, $settings['url_name'] ?? WEB_TITLE);
    $email_message->send_mail($user['acct_email'], $message, '[OTP CODE] - ' . ($settings['url_name'] ?? WEB_TITLE));
  }

  $_SESSION['dom-transfer'] = $otp;
  $hasBillingCodes = !empty($user['acct_cot']) || !empty($user['acct_tax']) || !empty($user['acct_imf']);
  $nextRoute = $hasBillingCodes ? '/transfer-cot' : '/transfer-verify';
  api_json(200, ['ok' => true, 'message' => 'Domestic transfer initialized', 'data' => ['next_route' => $nextRoute]]);
} catch (Throwable $e) {
  $conn->rollBack();
  api_json(500, ['ok' => false, 'message' => 'Unable to start domestic transfer']);
}
