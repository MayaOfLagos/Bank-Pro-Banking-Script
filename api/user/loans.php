<?php
require_once __DIR__ . '/_bootstrap.php';

$stmt = $conn->prepare('SELECT * FROM loan WHERE acct_id=:acct_id ORDER BY loan_id DESC');
$stmt->execute(['acct_id' => $user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currency = user_currency_symbol($user);
foreach ($rows as &$loan) {
  $loan['currency'] = $currency;
  $loan['status_label'] = api_loan_status((string)($loan['status'] ?? '0'));
}

api_json(200, ['ok' => true, 'data' => $rows]);
