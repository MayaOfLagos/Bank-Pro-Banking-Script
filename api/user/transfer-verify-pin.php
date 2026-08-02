<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
$pin = api_field($payload, 'pin');

if ($pin !== (string)($user['acct_otp'] ?? '')) {
  api_json(422, ['ok' => false, 'message' => 'Incorrect OTP code']);
}

// Use server-side temp_trans record — never trust client-supplied amount or account ID
$stmt = $conn->prepare('SELECT * FROM temp_trans WHERE acct_id=:acct_id ORDER BY wire_id DESC LIMIT 1');
$stmt->execute(['acct_id' => $user['id']]);
$temp = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$temp) {
  api_json(422, ['ok' => false, 'message' => 'No pending transfer found']);
}

$amount = (float)$temp['amount'];
$acctAmount = (float)($user['acct_balance'] ?? 0);
$transferLimit = (float)($user['limit_remain'] ?? 0);

if ($amount > $acctAmount) {
  api_json(422, ['ok' => false, 'message' => 'Insufficient Balance']);
}

$tBalance = $transferLimit - $amount;
$aBalance = $acctAmount - $amount;

$isDomestic = isset($temp['trans_type']) && $temp['trans_type'] === 'domestic transfer';

$conn->beginTransaction();
try {
  $update = $conn->prepare('UPDATE users SET limit_remain=:limit_remain,acct_balance=:acct_balance WHERE id=:id');
  $update->execute([
    'limit_remain' => $tBalance,
    'acct_balance' => $aBalance,
    'id' => $user['id']
  ]);

  $ref = uniqid();

  if ($isDomestic) {
    $insert = $conn->prepare('INSERT INTO domestic_transfer (acct_id,amount,bank_name,acct_name,acct_number,acct_type,acct_remarks,refrence_id) VALUES(:acct_id,:amount,:bank_name,:acct_name,:acct_number,:acct_type,:acct_remarks,:refrence_id)');
    $insert->execute([
      'acct_id' => $user['id'],
      'amount' => $amount,
      'bank_name' => $temp['bank_name'],
      'acct_name' => $temp['acct_name_id'],
      'acct_number' => $temp['acct_number'],
      'acct_type' => $temp['acct_type'],
      'acct_remarks' => $temp['acct_remarks'],
      'refrence_id' => $ref
    ]);
    $_SESSION['dom_transfer'] = $ref;
  } else {
    $insert = $conn->prepare('INSERT INTO wire_transfer (amount,acct_id,refrence_id,bank_name,acct_name,acct_number,acct_type,acct_country,acct_swift,acct_routing,acct_remarks) VALUES(:amount,:acct_id,:refrence_id,:bank_name,:acct_name,:acct_number,:acct_type,:acct_country,:acct_swift,:acct_routing,:acct_remarks)');
    $insert->execute([
      'amount' => $amount,
      'acct_id' => $user['id'],
      'refrence_id' => $ref,
      'bank_name' => $temp['bank_name'],
      'acct_name' => $temp['acct_name_id'],
      'acct_number' => $temp['acct_number'],
      'acct_type' => $temp['acct_type'],
      'acct_country' => $temp['acct_country'] ?? '',
      'acct_swift' => $temp['acct_swift'] ?? '',
      'acct_routing' => $temp['acct_routing'] ?? '',
      'acct_remarks' => $temp['acct_remarks']
    ]);
    $_SESSION['wire_transfer'] = $ref;
  }

  $conn->commit();
  api_json(200, ['ok' => true, 'message' => 'Transfer submitted successfully', 'data' => ['next_route' => '/transfer-success']]);
} catch (Throwable $e) {
  $conn->rollBack();
  api_json(500, ['ok' => false, 'message' => 'Unable to submit transfer']);
}
