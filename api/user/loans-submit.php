<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
api_require($payload, ['amount', 'loan_remarks']);

$amount = (float)api_field($payload, 'amount', '0');
$remarks = api_field($payload, 'loan_remarks');
if ($amount <= 0) api_json(422, ['ok' => false, 'message' => 'Invalid amount']);
if ($remarks === '') api_json(422, ['ok' => false, 'message' => 'Loan description required']);

$reference = uniqid();
$stmt = $conn->prepare('INSERT INTO loan (loan_reference_id,acct_id,amount,loan_remarks) VALUES (:loan_reference_id,:acct_id,:amount,:loan_remarks)');
$stmt->execute([
  'loan_reference_id' => $reference,
  'acct_id' => $user['id'],
  'amount' => $amount,
  'loan_remarks' => $remarks
]);

$email_message = new message();
$sendMail = new emailMessage();
if (!empty($user['acct_email'])) {
  $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
  $message = $sendMail->LoanMsg(user_currency_symbol($user), $amount, $remarks, $fullName, $settings['url_name'] ?? WEB_TITLE, WEB_URL);
  $email_message->send_to_both($user['acct_email'], $message, 'Loan Status - ' . ($settings['url_name'] ?? WEB_TITLE));
}

api_json(200, ['ok' => true, 'message' => 'Loan submitted successfully', 'data' => ['loan_reference_id' => $reference]]);
