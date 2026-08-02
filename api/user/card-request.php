<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

if (in_array(strtolower((string)($user['acct_status'] ?? '')), ['hold', 'blocked', 'suspended'], true)) {
  api_json(403, ['ok' => false, 'message' => 'Card requests are unavailable for this account']);
}

$existingCard = $conn->prepare('SELECT 1 FROM card WHERE user_id=:user_id LIMIT 1');
$existingCard->execute(['user_id' => $user['id']]);
if ($existingCard->fetchColumn()) {
  api_json(409, ['ok' => false, 'message' => 'A card is already linked to this account']);
}

$existingRequest = $conn->prepare('SELECT 1 FROM card_request WHERE user_id=:user_id AND card_request_status IN (0, 2) LIMIT 1');
$existingRequest->execute(['user_id' => $user['id']]);
if ($existingRequest->fetchColumn()) {
  api_json(409, ['ok' => false, 'message' => 'A card request is already in progress']);
}

$payload = api_payload();
api_require($payload, ['card_type', 'card_reason']);

$cardType = api_field($payload, 'card_type');
$cardReason = api_field($payload, 'card_reason');
if (!in_array($cardType, ['VISA', 'MASTERCARD'], true)) {
  api_json(422, ['ok' => false, 'message' => 'Select a supported card type']);
}
if (strlen($cardReason) < 5 || strlen($cardReason) > 500) {
  api_json(422, ['ok' => false, 'message' => 'Card request reason must be between 5 and 500 characters']);
}

$referenceId = 'CARD-' . strtoupper(bin2hex(random_bytes(6)));

$stmt = $conn->prepare('INSERT INTO card_request (reference_id,user_id,card_type,card_reason) VALUES (:reference_id,:user_id,:card_type,:card_reason)');
$stmt->execute([
  'reference_id' => $referenceId,
  'user_id' => $user['id'],
  'card_type' => $cardType,
  'card_reason' => $cardReason
]);

$email_message = new message();
$sendMail = new emailMessage();
if (!empty($user['acct_email'])) {
  $cardStmt = $conn->prepare('SELECT card_number FROM card WHERE user_id=:user_id LIMIT 1');
  $cardStmt->execute(['user_id' => $user['id']]);
  $card = $cardStmt->fetch(PDO::FETCH_ASSOC);
  $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
  $msg = $sendMail->CardMsg($fullName, $card['card_number'] ?? '', WEB_TITLE);
  $email_message->send_to_both($user['acct_email'], $msg, 'Card Status - ' . WEB_TITLE);
}

api_json(200, ['ok' => true, 'message' => 'Card request submitted', 'data' => ['reference_id' => $referenceId]]);
