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

$cardStmt = $conn->prepare('SELECT card_number, card_status FROM card WHERE user_id=:user_id LIMIT 1');
$cardStmt->execute(['user_id' => $user['id']]);
$card = $cardStmt->fetch(PDO::FETCH_ASSOC);
if (!$card) {
  api_json(404, ['ok' => false, 'message' => 'No card is linked to this account']);
}

$currentStatus = (int)($card['card_status'] ?? 0);
if ($action === 'pause' && $currentStatus !== 1) {
  api_json(409, ['ok' => false, 'message' => 'Only an active card can be paused']);
}
if ($action === 'active' && $currentStatus !== 4) {
  api_json(409, ['ok' => false, 'message' => 'Only a paused card can be activated']);
}

$status = $action === 'pause' ? 4 : 1;
$stmt = $conn->prepare('UPDATE card SET card_status=:card_status WHERE user_id=:user_id AND card_status=:current_status');
$stmt->execute(['card_status' => $status, 'user_id' => $user['id'], 'current_status' => $currentStatus]);

$email_message = new message();
$sendMail = new emailMessage($settings);
if (!empty($user['acct_email'])) {
  $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
  $msg = $sendMail->CardMsg($fullName, $card['card_number'] ?? '', WEB_TITLE);
  $email_message->send_to_both($user['acct_email'], $msg, 'Card Status - ' . WEB_TITLE);
}

api_json(200, ['ok' => true, 'message' => $action === 'pause' ? 'Card paused successfully' : 'Card activated successfully']);
