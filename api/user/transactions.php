<?php
require_once __DIR__ . '/_bootstrap.php';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
if ($limit < 1) $limit = 1;
if ($limit > 500) $limit = 500;

$sql = "SELECT trans_id, amount, trans_type, sender_name, description, created_at, time_created, trans_status FROM transactions WHERE user_id=:acct_id ORDER BY trans_id DESC LIMIT {$limit}";
$stmt = $conn->prepare($sql);
$stmt->execute(['acct_id' => $user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currency = user_currency_symbol($user);
foreach ($rows as &$rowTx) {
    $rowTx['currency'] = $currency;
    $rowTx['trans_type'] = (int)($rowTx['trans_type'] ?? 0);
    $rowTx['trans_status'] = (int)($rowTx['trans_status'] ?? 0);
    $rowTx['type_label'] = $rowTx['trans_type'] === 1 ? 'Credit' : 'Debit';
    $statusMap = [0 => 'Processing', 1 => 'Completed', 2 => 'Hold', 3 => 'Cancelled'];
    $rowTx['status_label'] = $statusMap[$rowTx['trans_status']] ?? 'Unknown';
}

api_json(200, ['ok' => true, 'data' => $rows]);
