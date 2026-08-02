<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
    api_json(422, ['ok' => false, 'message' => 'Invalid payload']);
}

$globalEnabled = (string)($settings['transfer'] ?? '1') === '1';
$userEnabled = (string)($user['transfer'] ?? '1') === '1';
if (!$globalEnabled || !$userEnabled || (string)($user['acct_status'] ?? 'hold') !== 'active') {
    api_json(403, ['ok' => false, 'message' => 'Wire transfer disabled for this account']);
}

$amount = (float)inputValidation((string)($body['amount'] ?? '0'));
$acct_name = inputValidation((string)($body['acct_name'] ?? ''));
$bank_name = inputValidation((string)($body['bank_name'] ?? ''));
$acct_number = inputValidation((string)($body['acct_number'] ?? ''));
$acct_country = inputValidation((string)($body['acct_country'] ?? ''));
$acct_swift = inputValidation((string)($body['acct_swift'] ?? ''));
$acct_routing = inputValidation((string)($body['acct_routing'] ?? ''));
$acct_type = inputValidation((string)($body['acct_type'] ?? ''));
$acct_remarks = inputValidation((string)($body['acct_remarks'] ?? ''));

if ($amount <= 0) {
    api_json(422, ['ok' => false, 'message' => 'Invalid amount entered']);
}

$acct_balance = (float)($user['acct_balance'] ?? 0);
if ($amount > $acct_balance) {
    api_json(422, ['ok' => false, 'message' => 'Insufficient Balance']);
}

$required = [$acct_name, $bank_name, $acct_number, $acct_country, $acct_type];
foreach ($required as $field) {
    if ($field === '') {
        api_json(422, ['ok' => false, 'message' => 'Please complete all required transfer fields']);
    }
}

$trans_id = uniqid();
$trans_opt = substr(number_format(time() * rand(), 0, '', ''), 0, 6);
$acct_otp = substr(number_format(time() * rand(), 0, '', ''), 0, 6);

$conn->beginTransaction();

try {
    $insert = $conn->prepare("INSERT INTO temp_trans (amount,trans_id,acct_id,bank_name,acct_name_id,acct_number,acct_type,acct_country,acct_swift,acct_routing,acct_remarks,trans_otp) VALUES(:amount,:trans_id,:acct_id,:bank_name,:acct_name,:acct_number,:acct_type,:acct_country,:acct_swift,:acct_routing,:acct_remarks,:trans_otp)");
    $insert->execute([
        'amount' => $amount,
        'trans_id' => $trans_id,
        'acct_id' => $user['id'],
        'bank_name' => $bank_name,
        'acct_name' => $acct_name,
        'acct_number' => $acct_number,
        'acct_type' => $acct_type,
        'acct_country' => $acct_country,
        'acct_swift' => $acct_swift,
        'acct_routing' => $acct_routing,
        'acct_remarks' => $acct_remarks,
        'trans_otp' => $trans_opt
    ]);

    $update = $conn->prepare('UPDATE users SET acct_otp=:acct_otp WHERE id=:id');
    $update->execute([
        'acct_otp' => $acct_otp,
        'id' => $user['id']
    ]);

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollBack();
    api_json(500, ['ok' => false, 'message' => 'Unable to start wire transfer']);
}

$email_message = new message();
$sendMail = new emailMessage();
$currency = user_currency_symbol($user);
$fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
$appName = $settings['url_name'] ?? WEB_TITLE;

if (!empty($user['acct_email'])) {
    $mailBody = $sendMail->pinRequest($currency, $amount, $fullName, $acct_otp, $appName);
    $email_message->send_mail($user['acct_email'], $mailBody, '[OTP CODE] - ' . $appName);
}

$_SESSION['wire-transfer'] = $acct_otp;

$hasBillingCodes = !empty($user['acct_cot']) || !empty($user['acct_tax']) || !empty($user['acct_imf']);
$nextRoute = $hasBillingCodes ? '/transfer-cot' : '/transfer-verify';

api_json(200, [
    'ok' => true,
    'message' => 'Wire transfer initialized. Please complete verification.',
    'data' => [
        'next_route' => $nextRoute
    ]
]);
