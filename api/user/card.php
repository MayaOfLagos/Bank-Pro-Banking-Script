<?php
require_once __DIR__ . '/_bootstrap.php';

$stmt = $conn->prepare('SELECT id, card_name, card_number, card_expiration, card_limit, card_limit_remain, card_status, card_kind FROM card WHERE user_id=:user_id ORDER BY id ASC');
$stmt->execute(['user_id' => $user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$requestStmt = $conn->prepare('SELECT reference_id, card_type, card_reason, card_request_status FROM card_request WHERE user_id=:user_id ORDER BY id DESC');
$requestStmt->execute(['user_id' => $user['id']]);
$requests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($requests as &$request) {
  $request['card_request_status'] = (int)($request['card_request_status'] ?? 0);
  $request['status'] = match ($request['card_request_status']) {
    0, 2 => 'Processing',
    1 => 'Approved',
    3 => 'Declined',
    default => 'Unknown',
  };
}
unset($request);

$statusLabels = [1 => 'Active', 2 => 'Processing', 3 => 'Hold', 4 => 'Paused'];
$currencySymbol = user_currency_symbol($user);

$cards = [];
foreach ($rows as $row) {
  $number = preg_replace('/\D+/', '', (string)($row['card_number'] ?? ''));
  $l4 = strlen($number) >= 4 ? substr($number, -4) : '';
  $cards[] = [
    'id' => (int)$row['id'],
    // Full PAN is returned to the authenticated card holder so the detail
    // view can render it. Masking on the client is opt-in; tightening this
    // behind a PIN re-verify is a follow-up.
    'card_number' => $number,
    'card_name' => $row['card_name'] ?? '',
    'network' => api_card_type_from_number($number),
    'type' => strtolower((string)($row['card_kind'] ?? 'debit')),
    'last4' => $l4,
    'masked_number' => $l4 !== '' ? '•••• •••• •••• ' . $l4 : '•••• •••• •••• ••••',
    'card_expiration' => $row['card_expiration'] ?? '',
    'expiry' => $row['card_expiration'] ?? '',
    'card_status' => $statusLabels[(int)($row['card_status'] ?? 0)] ?? 'Unknown',
    'card_limit' => (float)($row['card_limit'] ?? 0),
    'card_limit_remain' => (float)($row['card_limit_remain'] ?? 0),
    // Convenience: what the carousel renders as "balance". For debit we
    // treat card_limit_remain as balance; for credit, remaining credit line.
    'balance' => (float)($row['card_limit_remain'] ?? 0),
    'currency' => $currencySymbol,
  ];
}

$safeCard = $cards[0] ?? null;

$hasRequestInProgress = count(array_filter($requests, static fn(array $request): bool => in_array((int)$request['card_request_status'], [0, 2], true))) > 0;
$accountStatus = strtolower((string)($user['acct_status'] ?? ''));
$canRequest = !$hasRequestInProgress && !in_array($accountStatus, ['hold', 'blocked', 'suspended'], true);

api_json(200, [
  'ok' => true,
  'data' => [
    'acct_status' => $user['acct_status'] ?? '',
    // `card` retained for existing single-card consumers; `cards` is the
    // canonical array shape.
    'card' => $safeCard,
    'cards' => $cards,
    'requests' => $requests,
    'has_request_in_progress' => $hasRequestInProgress,
    'can_request' => $canRequest,
  ]
]);
