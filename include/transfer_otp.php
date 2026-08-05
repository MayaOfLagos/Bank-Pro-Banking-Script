<?php
/**
 * Issuing and checking the one-time code that releases a pending transfer.
 *
 * Both halves live here because they have to agree on two things that were
 * previously decided independently in five different files: WHICH column is
 * authoritative for the code, and WHEN the validity window starts.
 *
 * Authority: `temp_trans.trans_otp`, scoped to one pending transfer row.
 * `users.acct_otp` is a single global slot, so starting a second transfer
 * silently overwrote the code the customer had already been emailed for the
 * first — they would then type the code from their inbox and be told it was
 * wrong. The per-transfer column already existed and was already being
 * written; nothing was reading it.
 *
 * Window: the clock restarts when the code is actually SENT, not when the
 * transfer was first submitted. Under the old behaviour an account with COT,
 * TAX and IMF gates enabled burned its entire 15 minutes walking through
 * those three screens, so a correct OTP arrived after the deadline.
 */

require_once __DIR__ . '/userClass.php';
require_once __DIR__ . '/currency.php';

if (!defined('TRANSFER_OTP_TTL_SECONDS')) {
    define('TRANSFER_OTP_TTL_SECONDS', 900);
}

/**
 * Generate a fresh code for a pending transfer, persist it, restart the
 * validity window, and email it to the customer.
 *
 * @return bool Whether the customer was successfully emailed the code.
 */
function transfer_otp_issue(PDO $conn, array $user, array $settings, int $pendingTransferId): bool
{
    $otp = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $store = $conn->prepare('UPDATE temp_trans SET trans_otp = :otp WHERE wire_id = :wire_id AND acct_id = :acct_id');
    $store->execute([
        'otp' => $otp,
        'wire_id' => $pendingTransferId,
        'acct_id' => (int)$user['id'],
    ]);

    if ($store->rowCount() < 1) {
        error_log('[transfer-otp] no pending transfer ' . $pendingTransferId . ' for user ' . (int)$user['id']);
        return false;
    }

    // Mirrored onto the user row purely so existing admin screens that read
    // users.acct_otp keep displaying something sensible. Nothing verifies
    // against it any more.
    $mirror = $conn->prepare('UPDATE users SET acct_otp = :otp WHERE id = :id');
    $mirror->execute(['otp' => $otp, 'id' => (int)$user['id']]);

    // Restart the window at the moment of send.
    $_SESSION['pending_transfer_created_at'] = time();

    $recipient = trim((string)($user['acct_email'] ?? ''));
    if ($recipient === '') {
        error_log('[transfer-otp] user ' . (int)$user['id'] . ' has no email address on file');
        return false;
    }

    $appName = (string)($settings['url_name'] ?? (defined('WEB_TITLE') ? WEB_TITLE : 'Online Banking'));

    $amountStmt = $conn->prepare('SELECT amount FROM temp_trans WHERE wire_id = :wire_id AND acct_id = :acct_id LIMIT 1');
    $amountStmt->execute(['wire_id' => $pendingTransferId, 'acct_id' => (int)$user['id']]);
    $amount = (float)($amountStmt->fetchColumn() ?: 0);

    $mailer = new message();
    $template = new emailMessage($settings);
    $body = $template->pinRequest(
        user_currency_symbol($user),
        $amount,
        trim((string)($user['firstname'] ?? '') . ' ' . (string)($user['lastname'] ?? '')),
        $otp,
        $appName
    );

    $sent = $mailer->send_mail($recipient, $body, '[OTP CODE] - ' . $appName);
    if (!$sent) {
        error_log('[transfer-otp] delivery failed for user ' . (int)$user['id'] . ': ' . $mailer->lastError());
    }

    return $sent;
}

/**
 * Constant-time check of a submitted code against the pending transfer.
 *
 * Returns one of: 'ok', 'expired', 'missing', 'mismatch'. The caller maps
 * these to responses — importantly 'expired' must NOT be reported as a wrong
 * code, which is the bug this separation exists to prevent.
 */
function transfer_otp_check(PDO $conn, array $user, int $pendingTransferId, string $submitted): string
{
    if ($pendingTransferId < 1) {
        return 'missing';
    }

    $issuedAt = (int)($_SESSION['pending_transfer_created_at'] ?? 0);
    if ($issuedAt < 1 || $issuedAt < time() - TRANSFER_OTP_TTL_SECONDS) {
        return 'expired';
    }

    $stmt = $conn->prepare('SELECT trans_otp FROM temp_trans WHERE wire_id = :wire_id AND acct_id = :acct_id LIMIT 1');
    $stmt->execute(['wire_id' => $pendingTransferId, 'acct_id' => (int)$user['id']]);
    $expected = $stmt->fetchColumn();

    if ($expected === false || $expected === null || (string)$expected === '') {
        return 'missing';
    }

    if ($submitted === '' || !hash_equals((string)$expected, $submitted)) {
        return 'mismatch';
    }

    return 'ok';
}
