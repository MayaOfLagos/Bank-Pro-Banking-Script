<?php
require_once __DIR__ . '/_bootstrap.php';

$referenceId = inputValidation((string)($_GET['id'] ?? ''));
if ($referenceId === '') {
  api_json(422, ['ok' => false, 'message' => 'Loan reference id is required']);
}

$stmt = $conn->prepare('SELECT * FROM loan WHERE loan_reference_id=:id AND acct_id=:acct_id LIMIT 1');
$stmt->execute([
  'id' => $referenceId,
  'acct_id' => $user['id']
]);
$loan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$loan) {
  api_json(404, ['ok' => false, 'message' => 'Loan not found']);
}

$loan['currency'] = user_currency_symbol($user);
$loan['status_label'] = api_loan_status((string)($loan['status'] ?? '0'));
$loan['loan_message'] = $loan['loan_message'] ?? 'N/A';

api_json(200, ['ok' => true, 'data' => $loan]);
