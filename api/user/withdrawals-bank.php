<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
api_require($payload, ['amount', 'bankname', 'account_number', 'routineno']);

$amount = (float)api_field($payload, 'amount', '0');
$bankname = api_field($payload, 'bankname');
$accountNumber = api_field($payload, 'account_number');
$routeNo = api_field($payload, 'routineno');
$acctname = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));

if ($amount <= 0) api_json(422, ['ok' => false, 'message' => 'Invalid amount']);
if ($amount > (float)($user['acct_balance'] ?? 0)) api_json(422, ['ok' => false, 'message' => 'Insufficient Balance']);

$conn->beginTransaction();
try {
  $newBalance = (float)$user['acct_balance'] - $amount;
  $u = $conn->prepare('UPDATE users SET acct_balance=:bal WHERE id=:id');
  $u->execute(['bal' => $newBalance, 'id' => $user['id']]);

  $ref = uniqid();
  $i = $conn->prepare('INSERT INTO withdrawal (user_id,amount,bankname,account_number,routineno,acctname,reference_id,trans_type) VALUES(:user_id,:amount,:bankname,:account_number,:routineno,:acctname,:reference_id,:trans_type)');
  $i->execute([
    'user_id' => $user['id'],
    'amount' => $amount,
    'bankname' => $bankname,
    'account_number' => $accountNumber,
    'routineno' => $routeNo,
    'acctname' => $acctname,
    'reference_id' => $ref,
    'trans_type' => 2
  ]);
  $conn->commit();

  $email_message = new message();
  $sendMail = new emailMessage();
  if (!empty($user['acct_email'])) {
    $msg = $sendMail->BankWithdrawMsg(user_currency_symbol($user), $acctname, $amount, $bankname, $accountNumber, $routeNo, $acctname, WEB_TITLE);
    $email_message->send_to_both($user['acct_email'], $msg, 'Withdrawal Notification - ' . WEB_TITLE);
  }

  api_json(200, ['ok' => true, 'message' => 'Bank withdrawal processed', 'data' => ['reference_id' => $ref]]);
} catch (Throwable $e) {
  $conn->rollBack();
  api_json(500, ['ok' => false, 'message' => 'Unable to process withdrawal']);
}
