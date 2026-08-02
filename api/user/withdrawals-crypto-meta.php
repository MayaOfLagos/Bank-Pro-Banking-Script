<?php
require_once __DIR__ . '/_bootstrap.php';

$stmt = $conn->prepare('SELECT id, crypto_name, wallet_address FROM crypto_currency ORDER BY crypto_name');
$stmt->execute();
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

api_json(200, [
  'ok' => true,
  'data' => [
    'currency' => user_currency_symbol($user),
    'acct_balance' => (float)($user['acct_balance'] ?? 0),
    'acct_status' => $user['acct_status'] ?? '',
    'crypto_currency' => $list
  ]
]);
