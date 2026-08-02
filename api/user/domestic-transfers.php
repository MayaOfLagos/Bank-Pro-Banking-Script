<?php
require_once __DIR__ . '/_bootstrap.php';

$stmt = $conn->prepare('SELECT * FROM domestic_transfer WHERE acct_id=:acct_id ORDER BY dom_id DESC');
$stmt->execute(['acct_id' => $user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currency = user_currency_symbol($user);
foreach ($rows as &$rowDom) {
  $rowDom['currency'] = $currency;
  $rowDom['status_code'] = (int)($rowDom['dom_status'] ?? 0);
  $rowDom['status'] = api_domestic_status((string)$rowDom['status_code']);
  $rowDom['status_label'] = $rowDom['status'];
}

api_json(200, ['ok' => true, 'data' => $rows]);
