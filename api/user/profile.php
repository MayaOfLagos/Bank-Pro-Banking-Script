<?php
require_once __DIR__ . '/_bootstrap.php';

api_json(200, [
  'ok' => true,
  'data' => [
    'id' => (int)$user['id'],
    'firstname' => $user['firstname'] ?? '',
    'lastname' => $user['lastname'] ?? '',
    'full_name' => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
    'image' => $user['image'] ?? '',
    'acct_no' => $user['acct_no'] ?? '',
    'acct_type' => $user['acct_type'] ?? '',
    'acct_email' => $user['acct_email'] ?? '',
    'acct_phone' => $user['acct_phone'] ?? '',
    'acct_dob' => $user['acct_dob'] ?? '',
    'acct_occupation' => $user['acct_occupation'] ?? '',
    'state' => $user['state'] ?? '',
    'country' => $user['country'] ?? '',
    'acct_status' => $user['acct_status'] ?? '',
    'currency' => user_currency_symbol($user)
  ]
]);
