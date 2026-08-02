<?php
require_once __DIR__ . '/_bootstrap.php';

api_json(200, [
    'ok' => true,
    'data' => [
        'id' => (int)$user['id'],
        'acct_no' => $user['acct_no'] ?? '',
        'firstname' => $user['firstname'] ?? '',
        'lastname' => $user['lastname'] ?? '',
        'full_name' => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
        'email' => $user['acct_email'] ?? '',
        'currency' => user_currency_symbol($user),
        'acct_status' => $user['acct_status'] ?? ''
    ]
]);
