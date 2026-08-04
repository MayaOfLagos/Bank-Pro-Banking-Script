<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

if ((string)($user['acct_status'] ?? 'hold') !== 'active') {
  api_json(403, ['ok' => false, 'message' => 'Account on hold']);
}

if ((string)($user['can_deposit'] ?? '1') !== '1') {
  api_json(403, ['ok' => false, 'message' => 'Deposits are disabled for this account.']);
}

$amount = (float)api_field($_POST, 'amount', '0');
$cryptoId = api_field($_POST, 'crypto_id', api_field($_POST, 'crypto_name'));
$walletAddress = api_field($_POST, 'wallet_address');

if ($amount <= 0 || $cryptoId === '' || $walletAddress === '') {
  api_json(422, ['ok' => false, 'message' => 'Fill required form fields']);
}

$min = (float)($settings['trans_limit_min'] ?? 0);
$max = (float)($settings['trans_limit_max'] ?? 0);
if ($min > 0 && $amount < $min) api_json(422, ['ok' => false, 'message' => 'Amount less than deposit limit']);
if ($max > 0 && $amount > $max) api_json(422, ['ok' => false, 'message' => 'Amount greater than deposit limit']);

if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
  api_json(422, ['ok' => false, 'message' => 'Upload payment screenshot']);
}

$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int)($file['size'] ?? 0) > 10 * 1024 * 1024) {
  api_json(422, ['ok' => false, 'message' => 'Image must be smaller than 10 MB']);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
  api_json(422, ['ok' => false, 'message' => 'Invalid image format']);
}

$filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
$target = __DIR__ . '/../../assets/deposit/' . $filename;
if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
  api_json(500, ['ok' => false, 'message' => 'Failed to upload image']);
}

$referenceId = uniqid();
$stmt = $conn->prepare('INSERT INTO deposit (amount,user_id,wallet_address,crypto_id,image,refrence_id) VALUES(:amount,:user_id,:wallet_address,:crypto_id,:image,:refrence_id)');
$stmt->execute([
  'amount' => $amount,
  'user_id' => $user['id'],
  'wallet_address' => $walletAddress,
  'crypto_id' => $cryptoId,
  'image' => $filename,
  'refrence_id' => $referenceId
]);

$email_message = new message();
$sendMail = new emailMessage();
if (!empty($user['acct_email'])) {
  $txStmt = $conn->prepare('SELECT d.refrence_id, c.crypto_name FROM deposit d INNER JOIN crypto_currency c ON d.crypto_id=c.id WHERE d.user_id=:acct_id ORDER BY d.d_id DESC LIMIT 1');
  $txStmt->execute(['acct_id' => $user['id']]);
  $latest = $txStmt->fetch(PDO::FETCH_ASSOC);
  $message = $sendMail->depositMsg(user_currency_symbol($user), $amount, $latest['crypto_name'] ?? '', trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')), $latest['refrence_id'] ?? $referenceId, $settings['url_name'] ?? WEB_TITLE);
  $email_message->send_to_both($user['acct_email'], $message, '[DEPOSIT] - ' . ($settings['url_name'] ?? WEB_TITLE));
}

api_json(200, ['ok' => true, 'message' => 'Deposit submitted successfully']);
