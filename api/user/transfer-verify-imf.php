<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
$code = api_field($payload, 'imf_code');
if ($code === '') api_json(422, ['ok' => false, 'message' => 'IMF code is required']);

if ($code !== (string)($user['acct_imf'] ?? '')) {
  api_json(422, ['ok' => false, 'message' => 'Invalid IMF code']);
}

$stmt = $conn->prepare('SELECT * FROM temp_trans WHERE acct_id=:acct_id ORDER BY wire_id DESC LIMIT 1');
$stmt->execute(['acct_id' => $user['id']]);
$temp = $stmt->fetch(PDO::FETCH_ASSOC);
$amount = (float)($temp['amount'] ?? 0);

$otp = (string)($user['acct_otp'] ?? '');

if (!empty($user['acct_email'])) {
  $email_message = new message();
  $sendMail = new emailMessage();
  $message = $sendMail->pinRequest(user_currency_symbol($user), $amount, trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')), $otp, $settings['url_name'] ?? WEB_TITLE);
  $email_message->send_mail($user['acct_email'], $message, '[OTP CODE] - ' . ($settings['url_name'] ?? WEB_TITLE));
}

$_SESSION['wire-transfer'] = $user['id'];
api_json(200, ['ok' => true, 'message' => 'IMF verified', 'data' => ['next_route' => '/transfer-verify']]);
