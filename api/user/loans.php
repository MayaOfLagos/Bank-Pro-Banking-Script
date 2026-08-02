<?php
require_once __DIR__ . '/_bootstrap.php';

$stmt = $conn->prepare('SELECT * FROM loan WHERE acct_id=:acct_id ORDER BY loan_id DESC');
$stmt->execute(['acct_id' => $user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currency = user_currency_symbol($user);
foreach ($rows as &$loan) {
  $loan['currency'] = $currency;
  $loan['status_code'] = (int)($loan['loan_status'] ?? 0);
  $loan['status'] = api_loan_status((string)$loan['status_code']);
  $loan['status_label'] = $loan['status'];
}

$accountStatus = strtolower((string)($user['acct_status'] ?? ''));
$canRequest = !in_array($accountStatus, ['hold', 'blocked', 'suspended'], true);

api_json(200, [
  'ok' => true,
  'data' => [
    'loans' => $rows,
    'can_request' => $canRequest,
  ],
]);
