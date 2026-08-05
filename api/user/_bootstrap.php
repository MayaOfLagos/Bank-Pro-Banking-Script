<?php
require_once __DIR__ . '/../../session.php';
require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/currency.php';
require_once __DIR__ . '/../../include/auth_flow.php';
require_once __DIR__ . '/../_security.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function api_json(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

api_enforce_csrf('api_json');

if (!isset($_SESSION['acct_no']) || empty($_SESSION['acct_no'])) {
    api_json(401, ['ok' => false, 'message' => 'Unauthorized']);
}

$conn = dbConnect();

$stmt = $conn->prepare('SELECT * FROM users WHERE acct_no = :acct_no LIMIT 1');
$stmt->execute(['acct_no' => $_SESSION['acct_no']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    api_json(401, ['ok' => false, 'message' => 'Invalid session']);
}

// Kill sessions issued before the current password. Protects against a
// leaked cookie surviving a password reset performed from another device.
$pwChangedAt = !empty($user['password_changed_at']) ? (int)strtotime((string)$user['password_changed_at']) : 0;
$pwSnapshot = (int)($_SESSION['pw_snapshot'] ?? 0);
if ($pwChangedAt > 0 && $pwChangedAt > $pwSnapshot) {
    session_unset();
    session_destroy();
    api_json(401, ['ok' => false, 'message' => 'Session invalidated. Sign in again.']);
}

// Parallel check: an admin can force this user out of every active
// session by stamping users.sessions_invalidated_at = NOW() from the
// per-user view. Any session that was started before that cutoff dies
// on its next authenticated API call. Sessions born AFTER the cutoff
// survive — because session_started_at was stamped later at login.
$sessionStartedAt = (int)($_SESSION['session_started_at'] ?? 0);
if (auth_flow_session_invalidated_for($conn, (int)$user['id'], $sessionStartedAt)) {
    session_unset();
    session_destroy();
    api_json(401, ['ok' => false, 'message' => 'Session ended by administrator. Sign in again.']);
}

$settingsStmt = $conn->prepare("SELECT * FROM settings WHERE id='1' LIMIT 1");
$settingsStmt->execute();
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Login already refuses a non-active account, so this only fires when an
// admin holds an account mid-session. Money-movement endpoints each carry
// their own 403, but this catches the ones that don't — and anything added
// later, which is the failure mode worth guarding.
//
// Reads deliberately stay open: the customer still needs to see their
// balances, the hold banner, and the support contact to get it lifted.
// Every mutation in api/user/ is POST-only, so gating on method blocks all
// of them without an allowlist that would rot.
$accountBlockMessage = auth_account_block_message($user);
$isReadRequest = in_array(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['GET', 'HEAD', 'OPTIONS'], true);
if ($accountBlockMessage !== null && !$isReadRequest) {
    api_json(403, [
        'ok' => false,
        'message' => $accountBlockMessage,
        'data' => [
            'account_hold' => true,
            'acct_status' => auth_account_status($user),
        ],
    ]);
}

// user_currency_symbol() now lives in include/currency.php, required above.

function api_payload(): array {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
    }
    return $_POST ?? [];
}

function api_field(array $payload, string $key, string $default = ''): string {
    // JSON output is escaped at serialization time; preserve input values here.
    return trim((string)($payload[$key] ?? $default));
}

function api_require(array $payload, array $fields): void {
    foreach ($fields as $field) {
        if (!isset($payload[$field]) || trim((string)$payload[$field]) === '') {
            api_json(422, ['ok' => false, 'message' => "Missing required field: {$field}"]);
        }
    }
}

function api_wire_status(string $status): string {
    if ($status === '0') return 'Processing';
    if ($status === '1') return 'Completed';
    if ($status === '2') return 'Hold';
    if ($status === '3') return 'Cancelled';
    return 'Unknown';
}

function api_domestic_status(string $status): string {
    if ($status === '0') return 'Processing';
    if ($status === '1') return 'Completed';
    if ($status === '2') return 'Hold';
    if ($status === '3') return 'Cancelled';
    return 'Unknown';
}

function api_loan_status(string $status): string {
    if ($status === '0') return 'Processing';
    if ($status === '1') return 'Approved';
    if ($status === '2') return 'Hold';
    if ($status === '3') return 'Declined';
    return 'Unknown';
}

function api_card_type_from_number(string $number): string {
    $first2 = substr(str_replace(' ', '', $number), 0, 2);
    if ($first2 === '52') return 'MASTER';
    if ($first2 === '40') return 'VISA';
    if ($first2 === '67') return 'MAESTRO';
    if ($first2 === '30') return 'DINERS';
    if ($first2 === '62') return 'UNIONPAY';
    if ($first2 === '37') return 'AMERICAN EXPRESS';
    if ($first2 === '60') return 'DISCOVER';
    if ($first2 === '35') return 'JCB';
    return 'INVALID';
}
