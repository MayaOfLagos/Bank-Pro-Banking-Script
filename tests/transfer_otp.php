<?php
/**
 * Exercises the transfer OTP lifecycle against the real schema.
 *
 * Guards the bug this was written for: a *correct* code submitted after the
 * window closed used to come back as "Incorrect OTP code" and burn a lockout
 * attempt. Expiry and mismatch must stay distinguishable.
 */
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/transfer_otp.php';

$conn = dbConnect();
$pass = 0; $fail = 0;
function ok(string $label, bool $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; echo "  FAIL  {$label}\n"; }
}

$userRow = $conn->query('SELECT id, acct_no, acct_email, firstname, lastname FROM users LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$userRow) { echo "No users row to test against\n"; exit(1); }

function makePending(PDO $conn, array $user, string $otp): int {
    $stmt = $conn->prepare('INSERT INTO temp_trans (amount, trans_id, acct_id, bank_name, acct_name_id, acct_number, acct_type, trans_otp) VALUES (1, :tid, :uid, :bank, :name, :num, :type, :otp)');
    $stmt->execute([
        'tid' => bin2hex(random_bytes(8)), 'uid' => $user['id'],
        'bank' => 'Test Bank', 'name' => 'Test Payee', 'num' => '0000000000',
        'type' => 'savings', 'otp' => $otp,
    ]);
    return (int)$conn->lastInsertId();
}

$created = [];
try {
    // 1. Correct code inside the window.
    $id = makePending($conn, $userRow, '111111'); $created[] = $id;
    $_SESSION['pending_transfer_created_at'] = time();
    ok('correct code inside window -> ok', transfer_otp_check($conn, $userRow, $id, '111111') === 'ok');

    // 2. Wrong code inside the window.
    ok('wrong code inside window -> mismatch', transfer_otp_check($conn, $userRow, $id, '999999') === 'mismatch');

    // 3. The regression: correct code, window closed.
    $_SESSION['pending_transfer_created_at'] = time() - (TRANSFER_OTP_TTL_SECONDS + 60);
    $state = transfer_otp_check($conn, $userRow, $id, '111111');
    ok('correct code past window -> expired, NOT mismatch', $state === 'expired');
    ok('expired state is not reported as mismatch', $state !== 'mismatch');

    // 4. A second pending transfer must not invalidate the first one's code.
    $_SESSION['pending_transfer_created_at'] = time();
    $second = makePending($conn, $userRow, '222222'); $created[] = $second;
    ok('first transfer code still valid after a second is opened', transfer_otp_check($conn, $userRow, $id, '111111') === 'ok');
    ok('second transfer has its own code', transfer_otp_check($conn, $userRow, $second, '222222') === 'ok');
    ok('codes are not interchangeable between transfers', transfer_otp_check($conn, $userRow, $second, '111111') === 'mismatch');

    // 5. Unknown / foreign transfer id.
    ok('unknown transfer id -> missing', transfer_otp_check($conn, $userRow, 99999999, '111111') === 'missing');
    $foreign = $userRow; $foreign['id'] = ((int)$userRow['id']) + 999999;
    ok('another customer cannot verify this transfer', transfer_otp_check($conn, $foreign, $id, '111111') === 'missing');

    // 6. Issue writes a fresh 6-digit code to temp_trans and restarts the clock.
    $_SESSION['pending_transfer_created_at'] = time() - 5000;
    transfer_otp_issue($conn, $userRow, [], $id);
    $fresh = $conn->prepare('SELECT trans_otp FROM temp_trans WHERE wire_id = :id');
    $fresh->execute(['id' => $id]);
    $code = (string)$fresh->fetchColumn();
    ok('issue wrote a 6-digit code', preg_match('/^\d{6}$/', $code) === 1);
    ok('issue replaced the previous code', $code !== '111111');
    ok('issue restarted the expiry window', (int)$_SESSION['pending_transfer_created_at'] > time() - 60);
    ok('the newly issued code verifies', transfer_otp_check($conn, $userRow, $id, $code) === 'ok');
} finally {
    foreach ($created as $id) {
        $conn->prepare('DELETE FROM temp_trans WHERE wire_id = :id')->execute(['id' => $id]);
    }
}

echo "\n{$pass} assertions passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
