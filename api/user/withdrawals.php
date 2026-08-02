<?php
require_once __DIR__ . '/_bootstrap.php';

$stmt = $conn->prepare('SELECT w.*, u.firstname, u.lastname, u.acct_currency FROM withdrawal w LEFT JOIN users u ON w.user_id=u.id WHERE w.user_id=:user_id ORDER BY w.id DESC');
$stmt->execute(['user_id' => $user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as &$rowW) {
  $currency = user_currency_symbol(['acct_currency' => $rowW['acct_currency'] ?? ($user['acct_currency'] ?? 'USD')]);
  $rowW['currency'] = $currency;
  $rowW['full_name'] = trim(($rowW['firstname'] ?? '') . ' ' . ($rowW['lastname'] ?? ''));
  $rowW['status_code'] = (int)($rowW['status'] ?? 0);
  $rowW['status_label'] = api_wire_status((string)$rowW['status_code']);
}

api_json(200, ['ok' => true, 'data' => $rows]);
