<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/smtp.php';
require_once __DIR__ . '/../../include/transfer_otp.php';
require_once __DIR__ . '/../../include/admin_alerts.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
$pin = api_field($payload, 'pin');
if (($_SESSION['transfer_verification_stage'] ?? '') !== 'otp') {
  api_json(409, ['ok' => false, 'message' => 'Complete the required transfer verification steps first']);
}

$pendingTransferId = (int)($_SESSION['pending_transfer_id'] ?? 0);

security_enforce_verify_lock($conn, (int)$user['id'], 'api_json');

$otpState = transfer_otp_check($conn, $user, $pendingTransferId, $pin);

// An expired window is not a wrong code, and must not be reported as one or
// counted against the lockout budget — the customer typed exactly what we
// emailed them and deserves a resend, not an accusation.
if ($otpState === 'expired') {
  api_json(410, [
    'ok' => false,
    'message' => 'Your verification code has expired. Request a new one to continue.',
    'data' => ['expired' => true, 'can_resend' => true],
  ]);
}

if ($otpState === 'missing') {
  api_json(409, [
    'ok' => false,
    'message' => 'No transfer is awaiting verification. Please start the transfer again.',
    'data' => ['next_route' => '/dashboard'],
  ]);
}

if ($otpState !== 'ok') {
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
  // Single-use is enforced by the `SELECT ... FOR UPDATE` on temp_trans above
  // combined with the DELETE below: a concurrent request blocks on the row
  // lock, then finds the row gone and aborts with 'No pending transfer'. The
  // guard used to hang off users.acct_otp, which could not distinguish
  // between two pending transfers belonging to the same customer.
  $update = $conn->prepare('UPDATE users SET limit_remain=:limit_remain,acct_balance=:acct_balance,acct_otp=NULL WHERE id=:id');
  $update->execute([
    'limit_remain' => $transferLimit - $amount,
    'acct_balance' => $acctAmount - $amount,
    'id' => $lockedUser['id'],
  ]);

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

  // Alert the operators: the balance is already debited and the request now
  // sits in the approval queue, which nothing else tells anyone about.
  //
  // Wrapped in its own catch even though admin_notify() swallows its own
  // errors, because we are INSIDE the handler-wide `catch (Throwable)` below
  // and admin_notify's ARGUMENTS are evaluated before it can protect anything.
  // The commit on line 92 has already run, so an exception escaping from the
  // template render would fall through to that catch, find no open transaction
  // to roll back, and answer 422 "Unable to verify or submit this transfer" —
  // for a transfer whose money has already moved, with the pending_transfer_id
  // session keys unset so the customer cannot even retry. A notification must
  // never be able to turn a committed money movement into a failure response.
  try {
    $beneficiary = [
      'Bank name' => (string)($temp['bank_name'] ?? ''),
      'Account name' => (string)($temp['acct_name_id'] ?? ''),
      'Account number' => (string)($temp['acct_number'] ?? ''),
    ];
    admin_notify(
      (new AdminAlert)->adminPendingTransferSubmittedMsg(
        trim((string)($lockedUser['firstname'] ?? '') . ' ' . (string)($lockedUser['lastname'] ?? '')),
        $isDomestic ? 'domestic transfer' : 'wire transfer',
        user_currency_symbol($lockedUser),
        $amount,
        $beneficiary,
        $ref
      ),
      'Transfer awaiting approval'
    );
  } catch (Throwable $notifyError) {
    error_log('[transfer-verify-pin] pending-transfer alert failed: ' . $notifyError->getMessage());
  }

  api_json(200, ['ok' => true, 'message' => 'Transfer submitted successfully', 'data' => ['next_route' => '/transfer-success']]);
} catch (Throwable $e) {
  if ($conn->inTransaction()) $conn->rollBack();
  api_json(422, ['ok' => false, 'message' => 'Unable to verify or submit this transfer']);
}
