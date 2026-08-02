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

  $method = strtolower(trim((string)($rowW['withdraw_method'] ?? '')));
  $isCrypto = $method === 'crypto' || !empty($rowW['wallet_address']);
  $rowW['method'] = $isCrypto ? 'crypto' : 'bank';
  if ($isCrypto) {
    $addr = (string)($rowW['wallet_address'] ?? '');
    $rowW['description'] = 'Crypto withdrawal';
    $rowW['destination'] = $addr !== '' ? substr($addr, 0, 6) . '...' . substr($addr, -4) : '';
  } else {
    $bank = trim((string)($rowW['bankname'] ?? ''));
    $acct = (string)($rowW['account_number'] ?? '');
    $last4 = $acct !== '' ? substr($acct, -4) : '';
    $rowW['description'] = $bank !== '' ? 'Bank withdrawal - ' . $bank : 'Bank withdrawal';
    $rowW['destination'] = $last4 !== '' ? '***' . $last4 : '';
  }
}

api_json(200, ['ok' => true, 'data' => $rows]);
