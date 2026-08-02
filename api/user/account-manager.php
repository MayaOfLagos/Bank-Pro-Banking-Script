<?php
require_once __DIR__ . '/_bootstrap.php';

api_json(200, [
  'ok' => true,
  'data' => [
    'mgr_name' => $user['mgr_name'] ?? '',
    'mgr_email' => $user['mgr_email'] ?? '',
    'mgr_no' => $user['mgr_no'] ?? '',
    'mgr_image' => $user['mgr_image'] ?? '',
    'acct_type' => $user['acct_type'] ?? '',
    'acct_limit' => (float)($user['acct_limit'] ?? 0),
    'currency' => user_currency_symbol($user),
    'created_at' => $user['createdAt'] ?? null
  ]
]);
