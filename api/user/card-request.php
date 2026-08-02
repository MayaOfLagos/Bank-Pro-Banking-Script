<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
api_require($payload, ['card_type', 'card_reason']);

$cardType = api_field($payload, 'card_type');
$cardReason = api_field($payload, 'card_reason');
$referenceId = uniqid('card', false);

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
