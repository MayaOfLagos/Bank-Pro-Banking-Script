<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';
require_once __DIR__ . '/../../include/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

if (in_array(strtolower((string)($user['acct_status'] ?? '')), ['hold', 'blocked', 'suspended'], true)) {
  api_json(403, ['ok' => false, 'message' => 'Loan requests are unavailable for this account']);
}

$payload = api_payload();
api_require($payload, ['amount', 'loan_remarks']);

$amount = (float)api_field($payload, 'amount', '0');
$remarks = api_field($payload, 'loan_remarks');
if ($amount <= 0) api_json(422, ['ok' => false, 'message' => 'Invalid amount']);
if ($remarks === '') api_json(422, ['ok' => false, 'message' => 'Loan description required']);

$reference = 'LOAN-' . strtoupper(bin2hex(random_bytes(6)));
$stmt = $conn->prepare('INSERT INTO loan (loan_reference_id,acct_id,amount,loan_remarks) VALUES (:loan_reference_id,:acct_id,:amount,:loan_remarks)');
$stmt->execute([
  'loan_reference_id' => $reference,
  'acct_id' => $user['id'],
  'amount' => $amount,
  'loan_remarks' => $remarks
]);

$loanName = notify_plain(trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')), 80);
$loanMoney = user_currency_symbol($user) . number_format($amount, 2);
notify_user($conn, (int)$user['id'], 'loan.requested', 'Loan application received',
  'Your loan application for **' . $loanMoney . '** is under review. Reference `' . $reference . '`.',
  ['severity' => 'info', 'link' => '/loans/' . $reference, 'meta' => ['reference' => $reference, 'amount' => $amount]]);
notify_admin($conn, 'loan.requested', 'Loan application awaiting decision',
  $loanName . ' applied for a loan of **' . $loanMoney . '**. Reference `' . $reference . '`.',
  ['severity' => 'warning', 'link' => '/loans/' . $reference, 'meta' => ['reference' => $reference, 'user_id' => (int)$user['id'], 'amount' => $amount]]);

$email_message = new message();
$sendMail = new emailMessage($settings);
if (!empty($user['acct_email'])) {
  $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
  $message = $sendMail->LoanMsg(user_currency_symbol($user), $amount, $remarks, $fullName, $settings['url_name'] ?? WEB_TITLE, WEB_URL);
  $email_message->send_to_both($user['acct_email'], $message, 'Loan Status - ' . ($settings['url_name'] ?? WEB_TITLE));
}

api_json(200, ['ok' => true, 'message' => 'Loan submitted successfully', 'data' => ['loan_reference_id' => $reference]]);
