<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

// Reject if the caller is already signed in.
if (!empty($_SESSION['acct_no']) || !empty($_SESSION['login'])) {
    auth_json(409, ['ok' => false, 'message' => 'Already signed in']);
}

$payload = auth_payload();
// terms_accepted skipped here — auth_require treats a `false` bool as
// "empty" and would fire a misleading "missing field" error. Validated
// separately below with a helpful message.
auth_require($payload, [
    'firstname', 'lastname', 'dob', 'country',
    'email', 'phone', 'currency', 'acct_type',
    'password', 'confirm_password', 'pin',
]);

$firstname = auth_field($payload, 'firstname');
$lastname  = auth_field($payload, 'lastname');
$dob       = auth_field($payload, 'dob');
$country   = auth_field($payload, 'country');
$email     = auth_field($payload, 'email');
$phone     = auth_field($payload, 'phone');
$currency  = auth_field($payload, 'currency');
$acctType  = auth_field($payload, 'acct_type');
$password  = auth_field($payload, 'password');
$confirm   = auth_field($payload, 'confirm_password');
$pin       = auth_field($payload, 'pin');
$terms     = (bool)($payload['terms_accepted'] ?? false);

// ─── Validation ────────────────────────────────────────────────────────
if (mb_strlen($firstname) < 2 || mb_strlen($firstname) > 50) {
    auth_json(422, ['ok' => false, 'message' => 'First name must be 2–50 characters.']);
}
if (mb_strlen($lastname) < 2 || mb_strlen($lastname) > 50) {
    auth_json(422, ['ok' => false, 'message' => 'Last name must be 2–50 characters.']);
}
if (!preg_match("/^[\p{L} .'\\-]+$/u", $firstname) || !preg_match("/^[\p{L} .'\\-]+$/u", $lastname)) {
    auth_json(422, ['ok' => false, 'message' => 'Name contains invalid characters.']);
}

$dobParsed = DateTime::createFromFormat('Y-m-d', $dob);
if (!$dobParsed || $dobParsed->format('Y-m-d') !== $dob) {
    auth_json(422, ['ok' => false, 'message' => 'Enter a valid date of birth.']);
}
$age = (new DateTime('today'))->diff($dobParsed)->y;
if ($age < 18) {
    auth_json(422, ['ok' => false, 'message' => 'You must be at least 18 years old.']);
}
if ($age > 120) {
    auth_json(422, ['ok' => false, 'message' => 'Enter a valid date of birth.']);
}

require_once __DIR__ . '/../../include/countries.php';
if ($country === '' || !country_is_known($country)) {
    auth_json(422, ['ok' => false, 'message' => 'Select a supported country.']);
}

$emailNormalized = filter_var($email, FILTER_VALIDATE_EMAIL);
if (!$emailNormalized) {
    auth_json(422, ['ok' => false, 'message' => 'Enter a valid email address.']);
}

$phoneDigits = preg_replace('/\D+/', '', $phone);
if (strlen($phoneDigits) < 7 || strlen($phoneDigits) > 20) {
    auth_json(422, ['ok' => false, 'message' => 'Enter a valid phone number.']);
}

// Accept any code that lives in the shared symbol catalog. Falls in step
// with the client-side dropdown which is built from the same JSON.
require_once __DIR__ . '/../../include/currency.php';
$currency = strtoupper($currency);
if (!isset(currency_symbols_map()[$currency])) {
    auth_json(422, ['ok' => false, 'message' => 'Select a supported currency.']);
}

if (!account_type_is_known($acctType)) {
    auth_json(422, ['ok' => false, 'message' => 'Select a supported account type.']);
}

if ($password !== $confirm) {
    auth_json(422, ['ok' => false, 'message' => 'Passwords do not match.']);
}
if (strlen($password) < 8) {
    auth_json(422, ['ok' => false, 'message' => 'Password must be at least 8 characters.']);
}
if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
    auth_json(422, ['ok' => false, 'message' => 'Password must contain at least one letter and one number.']);
}

if (!preg_match('/^\d{4}$/', $pin)) {
    auth_json(422, ['ok' => false, 'message' => 'PIN must be exactly 4 digits.']);
}

if (!$terms) {
    auth_json(422, ['ok' => false, 'message' => 'You must accept the terms to continue.']);
}

// ─── Uniqueness ────────────────────────────────────────────────────────
$check = $conn->prepare('SELECT id FROM users WHERE acct_email = :email LIMIT 1');
$check->execute(['email' => $emailNormalized]);
if ($check->fetch(PDO::FETCH_ASSOC)) {
    // Generic message to avoid confirming account existence.
    auth_json(422, ['ok' => false, 'message' => 'That email cannot be used for a new account.']);
}

// Generate a unique acct_no in the legacy 9909-prefixed format.
$acctNo = null;
for ($i = 0; $i < 5; $i++) {
    $candidate = '9909' . str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $q = $conn->prepare('SELECT 1 FROM users WHERE acct_no = :n LIMIT 1');
    $q->execute(['n' => $candidate]);
    if (!$q->fetchColumn()) { $acctNo = $candidate; break; }
}
if ($acctNo === null) {
    auth_json(500, ['ok' => false, 'message' => 'Could not allocate an account number. Try again.']);
}

// ─── Insert ────────────────────────────────────────────────────────────
$hash = password_hash($password, PASSWORD_BCRYPT);

$insert = $conn->prepare(
    'INSERT INTO users (
        firstname, lastname, acct_email, acct_phone, acct_password,
        acct_no, acct_type, acct_currency, acct_status,
        country, acct_dob, acct_pin,
        acct_balance, avail_balance, loan_balance, transfer, billing_code,
        password_changed_at, createdAt
    ) VALUES (
        :firstname, :lastname, :email, :phone, :password,
        :acct_no, :acct_type, :currency, :status,
        :country, :dob, :pin,
        0, 0, 0, 1, 0,
        NOW(), NOW()
    )'
);
$insert->execute([
    'firstname' => $firstname,
    'lastname'  => $lastname,
    'email'     => $emailNormalized,
    'phone'     => $phoneDigits,
    'password'  => $hash,
    'acct_no'   => $acctNo,
    'acct_type' => $acctType,
    'currency'  => $currency,
    'status'    => 'hold',
    'country'   => $country,
    'dob'       => $dob,
    'pin'       => $pin,
]);

// ─── Notify (best-effort — never fail signup because of mail issues) ───
try {
    $fullName = trim($firstname . ' ' . $lastname);
    $userMsg = $sendMail->regMsgUser(
        $fullName,
        $acctNo,
        'hold',
        $emailNormalized,
        $phoneDigits,
        $acctType,
        // Never expose the user's chosen PIN in outbound mail. Legacy
        // stuffed it into the welcome template; we substitute a hint.
        '****',
        $appName,
        $appUrl
    );
    $mailer->send_mail($emailNormalized, $userMsg, "Welcome to {$appName}");

    $adminEmail = (string)($settings['url_email'] ?? WEB_EMAIL);
    if ($adminEmail !== '') {
        $mailer->send_mail(
            $adminEmail,
            $userMsg,
            "New signup pending review — {$fullName} ({$acctNo})"
        );
    }
} catch (Throwable $e) {
    error_log('[register] mail send failed: ' . $e->getMessage());
}

auth_json(201, [
    'ok' => true,
    'message' => 'Account created. It is pending review — you\'ll get an email once approved.',
    'data' => [
        'acct_no' => $acctNo,
        'acct_status' => 'hold',
        'next_route' => '/login',
    ],
]);
