<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

// Kill switch — admin can turn off new signups from admin/settings.php.
// Rejected BEFORE any DB write / rate-limit row so a disabled endpoint
// leaves zero trace beyond the response.
if ((int)($settings['registration_enabled'] ?? 1) !== 1) {
    auth_json(403, ['ok' => false, 'message' => 'New account registration is currently closed. Try again later.']);
}

// Reject if the caller is already signed in.
if (!empty($_SESSION['acct_no']) || !empty($_SESSION['login'])) {
    auth_json(409, ['ok' => false, 'message' => 'Already signed in']);
}

$payload = auth_payload();

// Honeypot: the form MUST send the "hp_website" key empty. Anything
// filled in almost certainly came from a spam bot script auto-filling
// every input; reject with the same "closed" message so bots can't
// distinguish honeypot rejection from a hard block.
if (isset($payload['hp_website']) && trim((string)$payload['hp_website']) !== '') {
    auth_json(403, ['ok' => false, 'message' => 'New account registration is currently closed. Try again later.']);
}

// Per-IP rate limit: max 5 successful-or-attempted registrations per IP
// per hour. Prevents credential-stuffing / mass-signup floods without
// needing an external captcha.
$ipAddress = trim((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
$rateCheck = $conn->prepare('SELECT COUNT(*) FROM register_attempts WHERE ip_address = :ip AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
$rateCheck->execute(['ip' => $ipAddress]);
if ((int)$rateCheck->fetchColumn() >= 5) {
    auth_json(429, ['ok' => false, 'message' => 'Too many signup attempts from this network. Wait an hour and try again.']);
}
$logAttempt = $conn->prepare('INSERT INTO register_attempts (ip_address) VALUES (:ip)');
$logAttempt->execute(['ip' => $ipAddress]);

// Hard payload cap: refuse absurd oversized bodies before we spend CPU
// running validation regex on them.
foreach ($payload as $key => $value) {
    if (is_string($value) && strlen($value) > 500) {
        auth_json(413, ['ok' => false, 'message' => 'One or more fields is too long.']);
    }
}

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

// Country blocklist: admins set a comma-separated list of two-letter ISO
// codes in admin/settings.php. Normalise the submitted country to an ISO
// code (accepts both codes and legacy full names) then compare against a
// whitelisted, upper-cased list. Uses a distinct message from the email
// filter so a legit user in a blocked country understands what happened —
// hiding it would just generate support tickets.
$countryCode = country_code_from_value($country);
$countryBlockRaw = (string)($settings['signup_country_blocklist'] ?? '');
if ($countryBlockRaw !== '' && $countryCode !== '') {
    $countryBlockList = [];
    foreach (preg_split('/[\s,]+/', $countryBlockRaw) as $entry) {
        $entry = strtoupper(trim((string)$entry));
        if (preg_match('/^[A-Z]{2}$/', $entry)) {
            $countryBlockList[$entry] = true;
        }
    }
    if (isset($countryBlockList[$countryCode])) {
        auth_json(422, ['ok' => false, 'message' => 'Signups from your country are not currently supported.']);
    }
}

$emailNormalized = filter_var($email, FILTER_VALIDATE_EMAIL);
if (!$emailNormalized) {
    auth_json(422, ['ok' => false, 'message' => 'Enter a valid email address.']);
}

// Email domain allow/block lists. Both are admin-configured comma-separated
// lists of bare domains (no @, no scheme). Allowlist wins when both are
// set: only addresses whose domain matches the allowlist survive, and even
// among those the blocklist can still reject. Empty allowlist = allow all
// except blocklist entries. We deliberately reuse the same generic
// "cannot be used" message the duplicate-email path uses so probing the
// endpoint can't reveal which specific list a domain lives on.
$emailAtPos = strrpos($emailNormalized, '@');
$emailDomain = $emailAtPos !== false ? strtolower(substr($emailNormalized, $emailAtPos + 1)) : '';

$parseDomainList = static function (string $raw): array {
    $out = [];
    foreach (preg_split('/[\s,]+/', $raw) as $entry) {
        $entry = strtolower(trim((string)$entry));
        // Strip a leading @ in case the admin typed "@gmail.com".
        if ($entry !== '' && $entry[0] === '@') {
            $entry = substr($entry, 1);
        }
        // Reject obviously malformed entries — anything that isn't a
        // domain-shaped string is silently dropped instead of blocking
        // every signup because an admin left a stray comma.
        if ($entry !== '' && preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/', $entry)) {
            $out[$entry] = true;
        }
    }
    return $out;
};

$emailAllowlist = $parseDomainList((string)($settings['signup_email_allowlist'] ?? ''));
$emailBlocklist = $parseDomainList((string)($settings['signup_email_blocklist'] ?? ''));

if ($emailDomain !== '') {
    if (!empty($emailAllowlist) && !isset($emailAllowlist[$emailDomain])) {
        auth_json(422, ['ok' => false, 'message' => 'That email cannot be used for a new account.']);
    }
    if (isset($emailBlocklist[$emailDomain])) {
        auth_json(422, ['ok' => false, 'message' => 'That email cannot be used for a new account.']);
    }
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

// Default status is admin-controlled — 'hold' keeps the review-first
// posture (safe default), but ops teams that trust their vetting can
// flip it to 'active' for instant onboarding. Whitelist against a known
// set so an admin can't accidentally save a value that later blocks
// login for other reasons.
$allowedDefaultStatuses = ['hold', 'active', 'pending'];
$defaultStatus = strtolower(trim((string)($settings['signup_default_status'] ?? 'hold')));
if (!in_array($defaultStatus, $allowedDefaultStatuses, true)) {
    $defaultStatus = 'hold';
}

// Admin-controlled starting balances/limits. Each value is clamped to
// [0, DEFAULT_BALANCE_CAP] so a UI-side typo (or a legacy row containing
// a negative or absurd number) can't silently ship a huge balance to
// every new account. Cap intentionally matches the admin form's max
// attribute — keep the two in sync if you raise it.
$balanceCap = 10000000.00;
$clampBalance = static function ($value) use ($balanceCap) {
    $n = is_numeric($value) ? (float)$value : 0.0;
    if ($n < 0)            { return 0.0; }
    if ($n > $balanceCap)  { return $balanceCap; }
    return $n;
};
$defaultBalance      = $clampBalance($settings['default_balance']       ?? 0);
$defaultAvailBalance = $clampBalance($settings['default_avail_balance'] ?? 0);
$defaultAcctLimit    = $clampBalance($settings['default_acct_limit']    ?? 0);
$defaultLimitRemain  = $clampBalance($settings['default_limit_remain']  ?? 0);

$insert = $conn->prepare(
    'INSERT INTO users (
        firstname, lastname, acct_email, acct_phone, acct_password,
        acct_no, acct_type, acct_currency, acct_status,
        country, acct_dob, acct_pin,
        acct_balance, avail_balance, loan_balance,
        acct_limit, limit_remain,
        transfer, billing_code,
        password_changed_at, createdAt
    ) VALUES (
        :firstname, :lastname, :email, :phone, :password,
        :acct_no, :acct_type, :currency, :status,
        :country, :dob, :pin,
        :acct_balance, :avail_balance, 0,
        :acct_limit, :limit_remain,
        1, 0,
        NOW(), NOW()
    )'
);
$insert->execute([
    'firstname'     => $firstname,
    'lastname'      => $lastname,
    'email'         => $emailNormalized,
    'phone'         => $phoneDigits,
    'password'      => $hash,
    'acct_no'       => $acctNo,
    'acct_type'     => $acctType,
    'currency'      => $currency,
    'status'        => $defaultStatus,
    'country'       => $country,
    'dob'           => $dob,
    'pin'           => $pin,
    'acct_balance'  => $defaultBalance,
    'avail_balance' => $defaultAvailBalance,
    'acct_limit'    => $defaultAcctLimit,
    'limit_remain'  => $defaultLimitRemain,
]);

// ─── Notify (best-effort — never fail signup because of mail issues) ───
try {
    $fullName = trim($firstname . ' ' . $lastname);
    $userMsg = $sendMail->regMsgUser(
        $fullName,
        $acctNo,
        $defaultStatus,
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

// Status-aware confirmation message so instant-active accounts don't
// wait for an email that will never come.
$confirmation = $defaultStatus === 'active'
    ? 'Account created — you can sign in now.'
    : 'Account created. It is pending review — you\'ll get an email once approved.';

auth_json(201, [
    'ok' => true,
    'message' => $confirmation,
    'data' => [
        'acct_no' => $acctNo,
        'acct_status' => $defaultStatus,
        'next_route' => '/login',
    ],
]);
