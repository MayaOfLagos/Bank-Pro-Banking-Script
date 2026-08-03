<?php
require_once __DIR__ . '/_bootstrap.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    api_json(422, ['ok' => false, 'message' => 'Missing transaction id']);
}

$sql = 'SELECT trans_id, refrence_id, amount, trans_type, sender_name, description, trans_status, created_at, time_created
        FROM transactions
        WHERE trans_id = :id AND user_id = :user_id
        LIMIT 1';
$stmt = $conn->prepare($sql);
$stmt->execute(['id' => $id, 'user_id' => (int)$user['id']]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    api_json(404, ['ok' => false, 'message' => 'Transaction not found']);
}

$currency = user_currency_symbol($user);
$row['currency'] = $currency;
$row['trans_type'] = (int)($row['trans_type'] ?? 0);
$row['trans_status'] = (int)($row['trans_status'] ?? 0);
$row['type_label'] = $row['trans_type'] === 1 ? 'Credit' : 'Debit';
$statusMap = [0 => 'Processing', 1 => 'Completed', 2 => 'Hold', 3 => 'Cancelled'];
$row['status_label'] = $statusMap[$row['trans_status']] ?? 'Unknown';
// Consumers expect a stable field name for cross-referencing.
$row['reference_id'] = $row['refrence_id'];

api_json(200, ['ok' => true, 'data' => $row]);
