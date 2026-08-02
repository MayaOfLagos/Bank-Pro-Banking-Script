<?php
require_once __DIR__ . '/../../session.php';
require_once __DIR__ . '/../../include/config.php';
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

$settingsStmt = $conn->prepare("SELECT * FROM settings WHERE id='1' LIMIT 1");
$settingsStmt->execute();
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

function user_currency_symbol(array $user): string {
    $cur = $user['acct_currency'] ?? 'USD';
    if ($cur === 'USD') return '$';
    if ($cur === 'EUR' || $cur === 'Euro') return '€';
    if ($cur === 'Yuan') return '¥';
    if ($cur === 'GBP') return '£';
    if ($cur === 'CAD') return '¢';
    return '$';
}

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
