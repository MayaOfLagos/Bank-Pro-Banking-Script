<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/userClass.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
api_require($payload, ['action']);
$action = api_field($payload, 'action');

if (!in_array($action, ['pause', 'active'], true)) {
  api_json(422, ['ok' => false, 'message' => 'Invalid action']);
}

$status = $action === 'pause' ? 4 : 1;
$stmt = $conn->prepare('UPDATE card SET card_status=:card_status WHERE user_id=:user_id');
$stmt->execute(['card_status' => $status, 'user_id' => $user['id']]);

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

api_json(200, ['ok' => true, 'message' => $action === 'pause' ? 'Card paused successfully' : 'Card activated successfully']);
