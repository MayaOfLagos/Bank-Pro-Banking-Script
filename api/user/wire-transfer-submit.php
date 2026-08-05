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

$amount = (float)api_field($body, 'amount', '0');
$acct_name = api_field($body, 'acct_name');
$bank_name = api_field($body, 'bank_name');
$acct_number = api_field($body, 'acct_number');
$acct_country = api_field($body, 'acct_country');
$acct_swift = api_field($body, 'acct_swift');
$acct_routing = api_field($body, 'acct_routing');
$acct_type = api_field($body, 'acct_type');
$acct_remarks = api_field($body, 'acct_remarks');

if ($amount <= 0) {
    api_json(422, ['ok' => false, 'message' => 'Invalid amount entered']);
}

$acct_balance = (float)($user['acct_balance'] ?? 0);
if ($amount > $acct_balance) {
    api_json(422, ['ok' => false, 'message' => 'Insufficient Balance']);
}
if ($amount > (float)($user['limit_remain'] ?? 0)) {
    api_json(422, ['ok' => false, 'message' => 'Amount exceeds your remaining transfer limit']);
}

$required = [$acct_name, $bank_name, $acct_number, $acct_country, $acct_type];
foreach ($required as $field) {
    if ($field === '') {
        api_json(422, ['ok' => false, 'message' => 'Please complete all required transfer fields']);
    }
}

$trans_id = bin2hex(random_bytes(16));
$acct_otp = (string)random_int(100000, 999999);

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
        'trans_otp' => $acct_otp
    ]);
    $pendingTransferId = (int)$conn->lastInsertId();

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
$sendMail = new emailMessage($settings);
$currency = user_currency_symbol($user);
$fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
$appName = $settings['url_name'] ?? WEB_TITLE;

if (!empty($user['acct_email'])) {
    $mailBody = $sendMail->pinRequest($currency, $amount, $fullName, $acct_otp, $appName);
    $email_message->send_mail($user['acct_email'], $mailBody, '[OTP CODE] - ' . $appName);
}

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

api_json(200, [
    'ok' => true,
    'message' => 'Wire transfer initialized. Please complete verification.',
    'data' => [
        'next_route' => $nextRoute
    ]
]);
