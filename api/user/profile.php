<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/countries.php';
require_once __DIR__ . '/../../include/smtp.php';
require_once __DIR__ . '/../../include/admin_alerts.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Whitelists — anything outside these lists gets rejected on save. Kept
// tight so a hand-crafted POST can't stash arbitrary strings.
$GENDER_ALLOWED = ['male', 'female', 'other', 'prefer_not_to_say'];
$MARITAL_ALLOWED = ['single', 'married', 'divorced', 'widowed', 'other'];

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
  if (mb_strlen($firstname) > 100 || mb_strlen($lastname) > 100) {
    api_json(422, ['ok' => false, 'message' => 'Name is too long (max 100 characters)']);
  }
  if ($phone !== '' && !preg_match('/^[0-9+()\s\-]{5,25}$/', $phone)) {
    api_json(422, ['ok' => false, 'message' => 'Enter a valid phone number']);
  }

  // Optional demographic fields — all nullable, all length-capped. Sent
  // from the expanded ProfileEditView form; older clients that omit them
  // keep the existing value because we only touch keys the payload sent.
  $dob = api_field($payload, 'acct_dob');
  if ($dob !== '') {
    // Accept only ISO YYYY-MM-DD; anything else is a client bug.
    $parsed = date_create_from_format('Y-m-d', $dob);
    if (!$parsed || $parsed->format('Y-m-d') !== $dob) {
      api_json(422, ['ok' => false, 'message' => 'Date of birth must be YYYY-MM-DD']);
    }
    // Reject future dates and absurd past ones (older than 120 years).
    $today = new DateTimeImmutable('today');
    $earliest = $today->modify('-120 years');
    if ($parsed > $today || $parsed < $earliest) {
      api_json(422, ['ok' => false, 'message' => 'Date of birth is out of range']);
    }
  }

  $gender = strtolower(api_field($payload, 'acct_gender'));
  if ($gender !== '' && !in_array($gender, $GENDER_ALLOWED, true)) {
    api_json(422, ['ok' => false, 'message' => 'Invalid gender selection']);
  }

  $marital = strtolower(api_field($payload, 'marital_status'));
  if ($marital !== '' && !in_array($marital, $MARITAL_ALLOWED, true)) {
    api_json(422, ['ok' => false, 'message' => 'Invalid marital status selection']);
  }

  $occupation = api_field($payload, 'acct_occupation');
  if (mb_strlen($occupation) > 100) {
    api_json(422, ['ok' => false, 'message' => 'Occupation is too long (max 100 characters)']);
  }

  // Country stored as full name to match the wire-transfer flow and the
  // existing admin views that print users.country directly.
  $country = api_field($payload, 'country');
  if ($country !== '' && country_code_from_value($country) === '') {
    api_json(422, ['ok' => false, 'message' => 'Country is not recognised']);
  }

  $state = api_field($payload, 'state');
  if (mb_strlen($state) > 100) {
    api_json(422, ['ok' => false, 'message' => 'State is too long (max 100 characters)']);
  }

  $address = api_field($payload, 'acct_address');
  if (mb_strlen($address) > 300) {
    api_json(422, ['ok' => false, 'message' => 'Address is too long (max 300 characters)']);
  }

  // Build the UPDATE dynamically so we only touch fields the payload
  // actually sent — protects older clients (only sending name/phone)
  // from silently wiping demographics the user filled in previously.
  $columnByKey = [
    'firstname' => $firstname,
    'lastname' => $lastname,
    'acct_phone' => $phone,
  ];
  if (array_key_exists('acct_dob', $payload)) {
    $columnByKey['acct_dob'] = $dob !== '' ? $dob : null;
  }
  if (array_key_exists('acct_gender', $payload)) {
    $columnByKey['acct_gender'] = $gender !== '' ? $gender : null;
  }
  if (array_key_exists('marital_status', $payload)) {
    $columnByKey['marital_status'] = $marital !== '' ? $marital : null;
  }
  if (array_key_exists('acct_occupation', $payload)) {
    $columnByKey['acct_occupation'] = $occupation !== '' ? $occupation : null;
  }
  if (array_key_exists('country', $payload)) {
    $columnByKey['country'] = $country !== '' ? $country : null;
  }
  if (array_key_exists('state', $payload)) {
    $columnByKey['state'] = $state !== '' ? $state : null;
  }
  if (array_key_exists('acct_address', $payload)) {
    $columnByKey['acct_address'] = $address !== '' ? $address : null;
  }

  // Snapshot the old->new diff before the write — $user still holds the row
  // as it was loaded, and the reflection loop below overwrites it.
  $identityChanges = [];
  foreach ($columnByKey as $column => $value) {
    $before = (string)($user[$column] ?? '');
    $after = (string)($value ?? '');
    if ($before !== $after) {
      $identityChanges[$column] = ['from' => $before, 'to' => $after];
    }
  }

  $assignments = [];
  $bindings = ['id' => $user['id']];
  foreach ($columnByKey as $column => $value) {
    $assignments[] = "`{$column}` = :{$column}";
    $bindings[$column] = $value;
  }
  $sql = 'UPDATE users SET ' . implode(', ', $assignments) . ' WHERE id = :id';
  $update = $conn->prepare($sql);
  $update->execute($bindings);

  // Reflect the change on the in-memory row so the response below shows
  // the value we just persisted rather than the pre-update snapshot.
  foreach ($columnByKey as $column => $value) {
    $user[$column] = $value;
  }

  // Alert the operators only when something really changed: these are the
  // fields a KYC file is built on and nothing else records a customer edit.
  if ($identityChanges !== []) {
    admin_notify(
      (new AdminAlert)->adminCustomerProfileChangedMsg(
        trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
        $identityChanges,
        (string)($_SERVER['REMOTE_ADDR'] ?? '')
      ),
      'Customer identity details changed'
    );
  }
} elseif ($method !== 'GET') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

// If a pending email change exists, surface a compact summary so the
// ProfileEditView can show "verification pending" state without a
// second API call. The OTP itself never leaves the server.
$pendingEmail = null;
if (!empty($user['pending_email']) && !empty($user['pending_email_expires'])) {
  $expiresAt = strtotime((string)$user['pending_email_expires']);
  if ($expiresAt !== false && $expiresAt > time()) {
    $pendingEmail = [
      'new_email' => (string)$user['pending_email'],
      'expires_at' => date('c', $expiresAt),
    ];
  }
}

api_json(200, [
  'ok' => true,
  'message' => $method === 'POST' ? 'Profile updated successfully' : 'Profile loaded',
  'data' => [
    'id' => (int)$user['id'],
    'firstname' => $user['firstname'] ?? '',
    'lastname' => $user['lastname'] ?? '',
    'full_name' => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
    'image' => '/assets/profile/' . ($user['image'] ?? 'user.png'),
    'account_number' => (string)($user['acct_no'] ?? ''),
    // acct_no remains until the login flow migrates off it as the credential field.
    'acct_no' => $user['acct_no'] ?? '',
    'acct_type' => $user['acct_type'] ?? '',
    'email' => $user['acct_email'] ?? '',
    'pending_email' => $pendingEmail,
    'phone' => $user['acct_phone'] ?? '',
    'acct_dob' => $user['acct_dob'] ?? '',
    'acct_gender' => $user['acct_gender'] ?? '',
    'marital_status' => $user['marital_status'] ?? '',
    'acct_occupation' => $user['acct_occupation'] ?? '',
    'acct_address' => $user['acct_address'] ?? '',
    'state' => $user['state'] ?? '',
    'country' => $user['country'] ?? '',
    'acct_status' => $user['acct_status'] ?? '',
    'acct_currency' => $user['acct_currency'] ?? 'USD',
    'currency' => user_currency_symbol($user)
  ]
]);
