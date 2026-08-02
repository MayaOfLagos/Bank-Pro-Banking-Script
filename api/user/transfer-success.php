<?php
require_once __DIR__ . '/_bootstrap.php';

if (!empty($_SESSION['wire_transfer'])) {
  $stmt = $conn->prepare('SELECT * FROM wire_transfer WHERE acct_id=:acct_id ORDER BY wire_id DESC LIMIT 1');
  $stmt->execute(['acct_id' => $user['id']]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $row['type'] = 'wire';
    $row['currency'] = user_currency_symbol($user);
    $row['status_label'] = api_wire_status((string)($row['status'] ?? '0'));
    api_json(200, ['ok' => true, 'data' => $row]);
  }
}

if (!empty($_SESSION['dom_transfer'])) {
  $stmt = $conn->prepare('SELECT * FROM domestic_transfer WHERE acct_id=:acct_id ORDER BY dom_id DESC LIMIT 1');
  $stmt->execute(['acct_id' => $user['id']]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $row['type'] = 'domestic';
    $row['currency'] = user_currency_symbol($user);
    $row['status_label'] = api_domestic_status((string)($row['status'] ?? '0'));
    api_json(200, ['ok' => true, 'data' => $row]);
  }
}

api_json(404, ['ok' => false, 'message' => 'No recent transfer found']);
