<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
$amount = (float)api_field($payload, 'amount', '0');
$withdrawMethod = api_field($payload, 'withdraw_method');
$walletAddress = api_field($payload, 'wallet_address');

api_require($payload, ['amount', 'withdraw_method', 'wallet_address']);

if ((string)($user['acct_status'] ?? '') !== 'active') {
  api_json(403, ['ok' => false, 'message' => 'Withdrawals are unavailable for this account']);
}

if ((string)($user['can_withdraw'] ?? '1') !== '1') {
  api_json(403, ['ok' => false, 'message' => 'Withdrawals are disabled for this account.']);
}

if ($amount <= 0) api_json(422, ['ok' => false, 'message' => 'Invalid amount']);
if ($amount > (float)($user['acct_balance'] ?? 0)) api_json(422, ['ok' => false, 'message' => 'Insufficient Balance']);

$conn->beginTransaction();
try {
  $newBalance = (float)$user['acct_balance'] - $amount;
  $locked = $conn->prepare('SELECT acct_balance FROM users WHERE id=:id FOR UPDATE');
  $locked->execute(['id' => $user['id']]);
  $lockedBalance = (float)$locked->fetchColumn();
  if ($amount > $lockedBalance) throw new RuntimeException('Insufficient balance');
  $newBalance = $lockedBalance - $amount;
  $u = $conn->prepare('UPDATE users SET acct_balance=:bal WHERE id=:id');
  $u->execute(['bal' => $newBalance, 'id' => $user['id']]);

  $ref = uniqid();
  $i = $conn->prepare('INSERT INTO withdrawal (user_id,amount,withdraw_method,wallet_address,bankname,account_number,routineno,acctname,reference_id,trans_type) VALUES(:user_id,:amount,:withdraw_method,:wallet_address,:bankname,:account_number,:routineno,:acctname,:reference_id,:trans_type)');
  $i->execute([
    'user_id' => $user['id'],
    'amount' => $amount,
    'withdraw_method' => $withdrawMethod,
    'wallet_address' => $walletAddress,
    'bankname' => '',
    'account_number' => '',
    'routineno' => '',
    'acctname' => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
    'reference_id' => $ref,
    'trans_type' => 2
  ]);
  $conn->commit();

  $email_message = new message();
  $sendMail = new emailMessage();
  if (!empty($user['acct_email'])) {
    $msg = $sendMail->WithdrawMsg(user_currency_symbol($user), trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')), $amount, $withdrawMethod, $walletAddress, WEB_TITLE);
    $email_message->send_to_both($user['acct_email'], $msg, 'Withdrawal Notification - ' . WEB_TITLE);
  }

  api_json(200, ['ok' => true, 'message' => 'Withdrawal processed', 'data' => ['reference_id' => $ref]]);
} catch (Throwable $e) {
  $conn->rollBack();
  api_json(500, ['ok' => false, 'message' => 'Unable to process withdrawal']);
}
