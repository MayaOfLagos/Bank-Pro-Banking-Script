<?php
require_once __DIR__ . '/_bootstrap.php';

api_json(200, [
  'ok' => true,
  'data' => [
    'currency' => user_currency_symbol($user),
    'acct_balance' => (float)($user['acct_balance'] ?? 0),
    'acct_status' => $user['acct_status'] ?? '',
    'full_name' => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''))
  ]
]);
