<?php
require_once __DIR__ . '/_bootstrap.php';

$stmt = $conn->prepare('SELECT card_name, card_number, card_expiration, card_limit, card_limit_remain, card_status FROM card WHERE user_id=:user_id LIMIT 1');
$stmt->execute(['user_id' => $user['id']]);
$card = $stmt->fetch(PDO::FETCH_ASSOC);

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

$safeCard = null;
if ($card) {
  $number = preg_replace('/\D+/', '', (string)($card['card_number'] ?? ''));
  $statusLabels = [1 => 'Active', 2 => 'Processing', 3 => 'Hold', 4 => 'Paused'];
  $safeCard = [
    'card_name' => $card['card_name'] ?? '',
    'card_type' => api_card_type_from_number($number),
    'last4' => strlen($number) >= 4 ? substr($number, -4) : '',
    'masked_number' => strlen($number) >= 4 ? '•••• •••• •••• ' . substr($number, -4) : '•••• •••• •••• ••••',
    'expiry' => $card['card_expiration'] ?? '',
    'card_status' => $statusLabels[(int)($card['card_status'] ?? 0)] ?? 'Unknown',
    'card_limit' => (float)($card['card_limit'] ?? 0),
    'card_limit_remain' => (float)($card['card_limit_remain'] ?? 0),
    'currency' => user_currency_symbol($user),
  ];
}

$hasRequestInProgress = count(array_filter($requests, static fn(array $request): bool => in_array((int)$request['card_request_status'], [0, 2], true))) > 0;
$accountStatus = strtolower((string)($user['acct_status'] ?? ''));
$canRequest = !$safeCard && !$hasRequestInProgress && !in_array($accountStatus, ['hold', 'blocked', 'suspended'], true);

api_json(200, [
  'ok' => true,
  'data' => [
    'acct_status' => $user['acct_status'] ?? '',
    'card' => $safeCard,
    'requests' => $requests,
    'has_request_in_progress' => $hasRequestInProgress,
    'can_request' => $canRequest,
  ]
]);
