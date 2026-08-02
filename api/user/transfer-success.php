<?php
require_once __DIR__ . '/_bootstrap.php';

if (!empty($_SESSION['wire_transfer'])) {
  $stmt = $conn->prepare('SELECT * FROM wire_transfer WHERE acct_id=:acct_id AND refrence_id=:reference_id LIMIT 1');
  $stmt->execute(['acct_id' => $user['id'], 'reference_id' => $_SESSION['wire_transfer']]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $row['type'] = 'wire';
    $row['currency'] = user_currency_symbol($user);
    $row['status_code'] = (int)($row['wire_status'] ?? 0);
    $row['status'] = api_wire_status((string)$row['status_code']);
    $row['status_label'] = $row['status'];
    unset($_SESSION['wire_transfer']);
    api_json(200, ['ok' => true, 'data' => $row]);
  }
}

if (!empty($_SESSION['dom_transfer'])) {
  $stmt = $conn->prepare('SELECT * FROM domestic_transfer WHERE acct_id=:acct_id AND refrence_id=:reference_id LIMIT 1');
  $stmt->execute(['acct_id' => $user['id'], 'reference_id' => $_SESSION['dom_transfer']]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $row['type'] = 'domestic';
    $row['currency'] = user_currency_symbol($user);
    $row['status_code'] = (int)($row['dom_status'] ?? 0);
    $row['status'] = api_domestic_status((string)$row['status_code']);
    $row['status_label'] = $row['status'];
    unset($_SESSION['dom_transfer']);
    api_json(200, ['ok' => true, 'data' => $row]);
  }
}

api_json(404, ['ok' => false, 'message' => 'No recent transfer found']);
