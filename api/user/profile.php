<?php
require_once __DIR__ . '/_bootstrap.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($method === 'POST') {
  $payload = api_payload();
  api_require($payload, ['action', 'firstname', 'lastname']);

  if (api_field($payload, 'action') !== 'update') {
    api_json(422, ['ok' => false, 'message' => 'Invalid profile action']);
  }

  $firstname = api_field($payload, 'firstname');
  $lastname = api_field($payload, 'lastname');
  $phone = api_field($payload, 'phone', api_field($payload, 'acct_phone'));

  if ($firstname === '' || $lastname === '') {
    api_json(422, ['ok' => false, 'message' => 'First name and last name are required']);
  }

  $update = $conn->prepare('UPDATE users SET firstname=:firstname, lastname=:lastname, acct_phone=:acct_phone WHERE id=:id');
  $update->execute([
    'firstname' => $firstname,
    'lastname' => $lastname,
    'acct_phone' => $phone,
    'id' => $user['id'],
  ]);

  $user['firstname'] = $firstname;
  $user['lastname'] = $lastname;
  $user['acct_phone'] = $phone;
} elseif ($method !== 'GET') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

api_json(200, [
  'ok' => true,
  'message' => $method === 'POST' ? 'Profile updated successfully' : 'Profile loaded',
  'data' => [
    'id' => (int)$user['id'],
    'firstname' => $user['firstname'] ?? '',
    'lastname' => $user['lastname'] ?? '',
    'full_name' => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
    'image' => $user['image'] ?? '',
    'account_number' => (string)($user['acct_no'] ?? ''),
    // acct_no remains until the login flow migrates off it as the credential field.
    'acct_no' => $user['acct_no'] ?? '',
    'acct_type' => $user['acct_type'] ?? '',
    'email' => $user['acct_email'] ?? '',
    'phone' => $user['acct_phone'] ?? '',
    'acct_dob' => $user['acct_dob'] ?? '',
    'acct_occupation' => $user['acct_occupation'] ?? '',
    'state' => $user['state'] ?? '',
    'country' => $user['country'] ?? '',
    'acct_status' => $user['acct_status'] ?? '',
    'acct_currency' => $user['acct_currency'] ?? 'USD',
    'currency' => user_currency_symbol($user)
  ]
]);
