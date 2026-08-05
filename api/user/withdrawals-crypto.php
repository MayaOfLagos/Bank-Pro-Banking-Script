<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';
require_once __DIR__ . '/../../include/notifications.php';

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
  $withdrawalRowId = (int)$conn->lastInsertId();
  $conn->commit();

  // After the commit — the balance has already moved, so a notification
  // failure here must not (and cannot) undo it.
  $wName = notify_plain(trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')), 80);
  // withdraw_method is free-form payload text with no allowlist, so it is the
  // one fragment here an attacker fully controls. Stripped before it reaches a
  // markdown body — especially the admin one.
  $wMethodLabel = notify_plain($withdrawMethod, 40);
  $wMoney = user_currency_symbol($user) . number_format($amount, 2);
  $wLink = '/transactions/withdrawal/' . $withdrawalRowId;
  notify_user($conn, (int)$user['id'], 'withdrawal.requested', 'Withdrawal requested',
    'Your ' . $wMethodLabel . ' withdrawal of **' . $wMoney . '** is pending review. Reference `' . $ref . '`.',
    ['severity' => 'info', 'link' => $wLink, 'meta' => ['reference' => $ref, 'amount' => $amount, 'method' => $withdrawMethod]]);
  notify_admin($conn, 'withdrawal.requested', 'Crypto withdrawal awaiting approval',
    $wName . ' requested a ' . $wMethodLabel . ' withdrawal of **' . $wMoney . '**. Reference `' . $ref . '`.',
    ['severity' => 'warning', 'link' => $wLink, 'meta' => ['reference' => $ref, 'user_id' => (int)$user['id'], 'amount' => $amount, 'method' => $withdrawMethod]]);

  $email_message = new message();
  $sendMail = new emailMessage($settings);
  if (!empty($user['acct_email'])) {
    $msg = $sendMail->WithdrawMsg(user_currency_symbol($user), trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')), $amount, $withdrawMethod, $walletAddress, WEB_TITLE);
    $email_message->send_to_both($user['acct_email'], $msg, 'Withdrawal Notification - ' . WEB_TITLE);
  }

  api_json(200, ['ok' => true, 'message' => 'Withdrawal processed', 'data' => ['reference_id' => $ref]]);
} catch (Throwable $e) {
  $conn->rollBack();
  api_json(500, ['ok' => false, 'message' => 'Unable to process withdrawal']);
}
