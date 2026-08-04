<?php
require_once __DIR__ . '/_bootstrap.php';

$canTransfer = (string)($settings['transfer'] ?? '1') === '1' && (string)($user['transfer'] ?? '1') === '1' && (string)($user['acct_status'] ?? 'hold') === 'active';

api_json(200, [
  'ok' => true,
  'data' => [
    'can_transfer' => $canTransfer,
    'currency' => user_currency_symbol($user),
    'acct_balance' => (float)($user['acct_balance'] ?? 0),
    // Backend rejects the transfer when amount > limit_remain — expose
    // it so the UI can warn upfront instead of failing on submit.
    'limit_remain' => (float)($user['limit_remain'] ?? 0),
    'acct_status' => $user['acct_status'] ?? '',
    'global_transfer' => (string)($settings['transfer'] ?? '1') === '1',
    'user_transfer' => (string)($user['transfer'] ?? '1') === '1'
  ]
]);
