<?php
require_once __DIR__ . '/_bootstrap.php';

// Card issuance is an administrative operation. Customers may request a card
// through card-request.php, but must never supply PAN/CVV data themselves.
api_json(403, ['ok' => false, 'message' => 'Card issuance is managed by support']);
require_once __DIR__ . '/../../include/userClass.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
api_require($payload, ['card_name', 'card_number', 'card_expiration', 'security']);

$cardName = api_field($payload, 'card_name');
$cardNumber = api_field($payload, 'card_number');
$cardExpiration = api_field($payload, 'card_expiration');
$security = api_field($payload, 'security');

$seriaKey = uniqid('CARD', false);
$stmt = $conn->prepare('INSERT INTO card SET card_name=:card_name,card_number=:card_number,card_expiration=:card_expiration,card_security=:card_security,user_id=:user_id,seria_key=:seria_key');
$stmt->execute([
  'card_name' => $cardName,
  'card_number' => $cardNumber,
  'card_expiration' => $cardExpiration,
  'card_security' => $security,
  'user_id' => $user['id'],
  'seria_key' => $seriaKey
]);

$email_message = new message();
$sendMail = new emailMessage($settings);
if (!empty($user['acct_email'])) {
  $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
  $msg = $sendMail->CardGenMsg($fullName, $cardName, $cardNumber, $cardExpiration, $security, WEB_TITLE);
  $email_message->send_to_both($user['acct_email'], $msg, 'Card Status - ' . WEB_TITLE);
}

api_json(200, ['ok' => true, 'message' => 'Card submitted successfully']);
