<?php
require_once __DIR__ . '/_bootstrap.php';

$stmt = $conn->prepare('SELECT * FROM card WHERE user_id=:user_id LIMIT 1');
$stmt->execute(['user_id' => $user['id']]);
$card = $stmt->fetch(PDO::FETCH_ASSOC);

$requestStmt = $conn->prepare('SELECT * FROM card_request WHERE user_id=:user_id ORDER BY id DESC');
$requestStmt->execute(['user_id' => $user['id']]);
$requests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);

if ($card) {
  $card['card_type'] = api_card_type_from_number((string)($card['card_number'] ?? ''));
  $card['currency'] = user_currency_symbol($user);
}

api_json(200, [
  'ok' => true,
  'data' => [
    'acct_status' => $user['acct_status'] ?? '',
    'card' => $card ?: null,
    'requests' => $requests,
    'has_request_in_progress' => count($requests) > 0
  ]
]);
