<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
$pin = api_field($payload, 'pin');
if (($_SESSION['transfer_verification_stage'] ?? '') !== 'otp') {
  api_json(409, ['ok' => false, 'message' => 'Complete the required transfer verification steps first']);
}

$pendingTransferId = (int)($_SESSION['pending_transfer_id'] ?? 0);
if ($pendingTransferId < 1 || (int)($_SESSION['pending_transfer_created_at'] ?? 0) < time() - 900) {
  api_json(422, ['ok' => false, 'message' => 'Incorrect OTP code']);
}

security_enforce_verify_lock($conn, (int)$user['id'], 'api_json');

if ($pin === '' || !hash_equals((string)($user['acct_otp'] ?? ''), $pin)) {
  $result = security_record_verify_failure($conn, (int)$user['id']);
  api_json(422, [
    'ok' => false,
    'message' => 'Incorrect OTP code',
    'data' => ['attempts_remaining' => $result['attempts_remaining']],
  ]);
}

security_reset_verify_attempts($conn, (int)$user['id']);

$conn->beginTransaction();
try {
  // Lock the account so concurrent requests cannot spend the same balance.
  $lockedUserStmt = $conn->prepare('SELECT * FROM users WHERE id=:id FOR UPDATE');
  $lockedUserStmt->execute(['id' => $user['id']]);
  $lockedUser = $lockedUserStmt->fetch(PDO::FETCH_ASSOC);
  if (!$lockedUser || (string)($lockedUser['acct_status'] ?? '') !== 'active' || (string)($lockedUser['transfer'] ?? '1') !== '1' || (string)($settings['transfer'] ?? '1') !== '1') {
    throw new RuntimeException('Transfer disabled');
  }

  $stmt = $conn->prepare('SELECT * FROM temp_trans WHERE wire_id=:wire_id AND acct_id=:acct_id LIMIT 1 FOR UPDATE');
  $stmt->execute(['wire_id' => $pendingTransferId, 'acct_id' => $lockedUser['id']]);
  $temp = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$temp) {
    throw new RuntimeException('No pending transfer');
  }

  $amount = (float)$temp['amount'];
  $acctAmount = (float)($lockedUser['acct_balance'] ?? 0);
  $transferLimit = (float)($lockedUser['limit_remain'] ?? 0);
  if ($amount <= 0 || $amount > $acctAmount || $amount > $transferLimit) {
    throw new RuntimeException('Insufficient balance or transfer limit');
  }

  $isDomestic = ($temp['trans_type'] ?? '') === 'domestic transfer';
  $ref = bin2hex(random_bytes(16));
  $update = $conn->prepare('UPDATE users SET limit_remain=:limit_remain,acct_balance=:acct_balance,acct_otp=NULL WHERE id=:id AND acct_otp=:otp');
  $update->execute([
    'limit_remain' => $transferLimit - $amount,
    'acct_balance' => $acctAmount - $amount,
    'id' => $lockedUser['id'],
    'otp' => $pin,
  ]);
  if ($update->rowCount() !== 1) {
    throw new RuntimeException('OTP already consumed');
  }

  if ($isDomestic) {
    $insert = $conn->prepare('INSERT INTO domestic_transfer (acct_id,amount,bank_name,acct_name,acct_number,acct_type,acct_remarks,refrence_id) VALUES(:acct_id,:amount,:bank_name,:acct_name,:acct_number,:acct_type,:acct_remarks,:refrence_id)');
    $insert->execute([
      'acct_id' => $lockedUser['id'], 'amount' => $amount, 'bank_name' => $temp['bank_name'],
      'acct_name' => $temp['acct_name_id'], 'acct_number' => $temp['acct_number'], 'acct_type' => $temp['acct_type'],
      'acct_remarks' => $temp['acct_remarks'], 'refrence_id' => $ref,
    ]);
    $_SESSION['dom_transfer'] = $ref;
  } else {
    $insert = $conn->prepare('INSERT INTO wire_transfer (amount,acct_id,refrence_id,bank_name,acct_name,acct_number,acct_type,acct_country,acct_swift,acct_routing,acct_remarks) VALUES(:amount,:acct_id,:refrence_id,:bank_name,:acct_name,:acct_number,:acct_type,:acct_country,:acct_swift,:acct_routing,:acct_remarks)');
    $insert->execute([
      'amount' => $amount, 'acct_id' => $lockedUser['id'], 'refrence_id' => $ref, 'bank_name' => $temp['bank_name'],
      'acct_name' => $temp['acct_name_id'], 'acct_number' => $temp['acct_number'], 'acct_type' => $temp['acct_type'],
      'acct_country' => $temp['acct_country'] ?? '', 'acct_swift' => $temp['acct_swift'] ?? '',
      'acct_routing' => $temp['acct_routing'] ?? '', 'acct_remarks' => $temp['acct_remarks'],
    ]);
    $_SESSION['wire_transfer'] = $ref;
  }

  $delete = $conn->prepare('DELETE FROM temp_trans WHERE wire_id=:wire_id AND acct_id=:acct_id');
  $delete->execute(['wire_id' => $pendingTransferId, 'acct_id' => $lockedUser['id']]);
  $conn->commit();
  unset($_SESSION['pending_transfer_id'], $_SESSION['pending_transfer_created_at'], $_SESSION['transfer_verification_stage']);
  api_json(200, ['ok' => true, 'message' => 'Transfer submitted successfully', 'data' => ['next_route' => '/transfer-success']]);
} catch (Throwable $e) {
  if ($conn->inTransaction()) $conn->rollBack();
  api_json(422, ['ok' => false, 'message' => 'Unable to verify or submit this transfer']);
}
