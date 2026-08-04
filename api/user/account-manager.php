<?php
require_once __DIR__ . '/_bootstrap.php';

$name = trim((string)($user['mgr_name'] ?? ''));
$email = trim((string)($user['mgr_email'] ?? ''));
$phone = trim((string)($user['mgr_no'] ?? ''));
$image = trim((string)($user['mgr_image'] ?? ''));

api_json(200, [
  'ok' => true,
  'data' => [
    'has_manager' => $name !== '' || $email !== '' || $phone !== '',
    'mgr_name' => $name,
    'mgr_email' => $email,
    'mgr_no' => $phone,
    // profile.php prefixes the same directory; callers get a usable src
    // rather than a bare filename they have to know how to resolve.
    'mgr_image' => $image !== '' ? '/assets/profile/' . $image : '',
    'acct_type' => $user['acct_type'] ?? '',
    'acct_limit' => (float)($user['acct_limit'] ?? 0),
    'currency' => user_currency_symbol($user),
    'created_at' => $user['createdAt'] ?? null
  ]
]);
