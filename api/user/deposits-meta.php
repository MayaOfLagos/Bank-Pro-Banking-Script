<?php
require_once __DIR__ . '/_bootstrap.php';

$bankStmt = $conn->prepare("SELECT * FROM v_bank WHERE id='48' LIMIT 1");
$bankStmt->execute();
$vBank = $bankStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$cryptoStmt = $conn->prepare('SELECT id, crypto_name, wallet_address FROM crypto_currency ORDER BY crypto_name');
$cryptoStmt->execute();
$cryptoList = $cryptoStmt->fetchAll(PDO::FETCH_ASSOC);

api_json(200, [
  'ok' => true,
  'data' => [
    'currency' => user_currency_symbol($user),
    'trans_limit_min' => (float)($settings['trans_limit_min'] ?? 0),
    'trans_limit_max' => (float)($settings['trans_limit_max'] ?? 0),
    'bank_deposit_enabled' => (string)($settings['bank_deposit'] ?? '0') === '1',
    'virtual_bank' => $vBank,
    'crypto_currency' => $cryptoList,
    'acct_status' => $user['acct_status'] ?? ''
  ]
]);
