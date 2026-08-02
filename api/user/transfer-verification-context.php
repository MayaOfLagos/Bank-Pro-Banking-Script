<?php
require_once __DIR__ . '/_bootstrap.php';

$pendingTransferId = (int)($_SESSION['pending_transfer_id'] ?? 0);
if ($pendingTransferId < 1) {
  api_json(404, ['ok' => false, 'message' => 'No pending transfer verification context']);
}

$stmt = $conn->prepare('SELECT wire_id, amount, trans_type, bank_name, acct_name_id, acct_number, acct_country, acct_type FROM temp_trans WHERE wire_id=:wire_id AND acct_id=:acct_id LIMIT 1');
$stmt->execute(['wire_id' => $pendingTransferId, 'acct_id' => $user['id']]);
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
    'phone' => $user['acct_phone'] ?? '',
    'email' => $user['acct_email'] ?? '',
    'transfer' => [
      'id' => (int)$temp['wire_id'],
      'amount' => (float)$temp['amount'],
      'type' => $type,
      'bank_name' => $temp['bank_name'] ?? '',
      'beneficiary_name' => $temp['acct_name_id'] ?? '',
      'account_last4' => substr((string)($temp['acct_number'] ?? ''), -4),
      'account_type' => $temp['acct_type'] ?? '',
      'country' => $temp['acct_country'] ?? '',
    ]
  ]
]);
