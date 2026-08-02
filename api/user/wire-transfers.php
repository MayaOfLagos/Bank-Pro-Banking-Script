<?php
require_once __DIR__ . '/_bootstrap.php';

$stmt = $conn->prepare('SELECT * FROM wire_transfer WHERE acct_id=:acct_id ORDER BY wire_id DESC');
$stmt->execute(['acct_id' => $user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currency = user_currency_symbol($user);
foreach ($rows as &$rowWire) {
  $rowWire['currency'] = $currency;
  $rowWire['status_label'] = api_wire_status((string)($rowWire['status'] ?? '0'));
}

api_json(200, ['ok' => true, 'data' => $rows]);
