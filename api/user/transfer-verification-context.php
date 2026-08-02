<?php
require_once __DIR__ . '/_bootstrap.php';

$stmt = $conn->prepare('SELECT * FROM temp_trans WHERE acct_id=:acct_id ORDER BY wire_id DESC LIMIT 1');
$stmt->execute(['acct_id' => $user['id']]);
$temp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$temp) {
  api_json(404, ['ok' => false, 'message' => 'No pending transfer verification context']);
}

$type = 'wire';
if (!empty($temp['trans_type']) && stripos((string)$temp['trans_type'], 'dom') !== false) {
  $type = 'domestic';
}

api_json(200, [
  'ok' => true,
  'data' => [
    'type' => $type,
    'acct_phone' => $user['acct_phone'] ?? '',
    'acct_email' => $user['acct_email'] ?? '',
    'temp_transfer' => $temp
  ]
]);
