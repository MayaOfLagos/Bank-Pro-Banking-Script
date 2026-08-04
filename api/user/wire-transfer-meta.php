<?php
require_once __DIR__ . '/_bootstrap.php';

$globalEnabled = (string)($settings['transfer'] ?? '1') === '1';
$userEnabled = (string)($user['transfer'] ?? '1') === '1';
$canTransfer = $globalEnabled && $userEnabled && (string)($user['acct_status'] ?? 'hold') === 'active';

api_json(200, [
    'ok' => true,
    'data' => [
        'can_transfer' => $canTransfer,
        'global_transfer' => $globalEnabled,
        'user_transfer' => $userEnabled,
        'acct_status' => $user['acct_status'] ?? 'unknown',
        'acct_balance' => (float)($user['acct_balance'] ?? 0),
        // Backend rejects the transfer when amount > limit_remain — expose
        // it so the UI can warn upfront instead of failing on submit.
        'limit_remain' => (float)($user['limit_remain'] ?? 0),
        'currency' => user_currency_symbol($user)
    ]
]);
