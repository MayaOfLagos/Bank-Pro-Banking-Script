<?php

// Unified activity ledger.
//
// Money moves through five tables that never cross-post to each other:
//   transactions      — admin credits/debits (the only table funduser.php writes)
//   deposit           — customer-submitted funding, credited on admin approval
//   wire_transfer     — outbound wire, balance already deducted at PIN step
//   domestic_transfer — outbound domestic, same
//   withdrawal        — outbound to bank or crypto, same
//
// Because an outbound transfer never produces a `transactions` row, reading
// only that table hid every transfer and withdrawal the customer ever made.
// Both /transactions and the dashboard's recent list build on this so the two
// feeds can never drift apart.

/**
 * Every row is addressed by `source` + `record_id`, which together resolve to
 * the detail endpoint. `id` is a render key only.
 *
 * @return array<int, array<string, mixed>> newest first
 */
function user_ledger(PDO $conn, int $acctId, string $currency): array
{
    $statusMap = [0 => 'Processing', 1 => 'Completed', 2 => 'Hold', 3 => 'Cancelled'];
    $ledger = [];

    $push = static function (
        string $source,
        int $recordId,
        $amount,
        int $direction,
        int $status,
        string $statusLabel,
        string $heading,
        string $description,
        $reference,
        $createdAt,
        string $timeCreated = ''
    ) use (&$ledger, $currency): void {
        $ledger[] = [
            'id'           => $source . '-' . $recordId,
            'source'       => $source,
            'record_id'    => $recordId,
            'currency'     => $currency,
            'amount'       => (float)$amount,
            'trans_type'   => $direction,
            'type_label'   => $direction === 1 ? 'Credit' : 'Debit',
            'trans_status' => $status,
            'status_label' => $statusLabel,
            'sender_name'  => $heading,
            'description'  => $description,
            'refrence_id'  => (string)$reference,
            'reference_id' => (string)$reference,
            'created_at'   => (string)$createdAt,
            'time_created' => $timeCreated,
        ];
    };

    // --- Ledger-committed credits and debits -------------------------------
    $stmt = $conn->prepare(
        'SELECT trans_id, refrence_id, amount, trans_type, sender_name, description,
                trans_status, created_at, time_created
         FROM transactions WHERE user_id = :acct_id ORDER BY trans_id DESC'
    );
    $stmt->execute(['acct_id' => $acctId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = (int)($row['trans_status'] ?? 0);
        $push(
            'transaction',
            (int)$row['trans_id'],
            $row['amount'],
            (int)($row['trans_type'] ?? 0) === 1 ? 1 : 2,
            $status,
            $statusMap[$status] ?? 'Unknown',
            (string)($row['sender_name'] ?? ''),
            (string)($row['description'] ?? ''),
            $row['refrence_id'] ?? '',
            $row['created_at'] ?? '',
            (string)($row['time_created'] ?? '')
        );
    }

    // --- Deposits ----------------------------------------------------------
    // All statuses are surfaced. Approving a deposit credits the balance
    // without writing a `transactions` row, so hiding approved rows would
    // leave the customer with a balance jump and nothing to explain it.
    $stmt = $conn->prepare(
        'SELECT d.d_id, d.refrence_id, d.amount, d.crypto_status, d.created_at, c.crypto_name
         FROM deposit d
         LEFT JOIN crypto_currency c ON c.id = d.crypto_id
         WHERE d.user_id = :acct_id ORDER BY d.d_id DESC'
    );
    $stmt->execute(['acct_id' => $acctId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = (int)($row['crypto_status'] ?? 0);
        $push(
            'deposit',
            (int)$row['d_id'],
            $row['amount'],
            1,
            $status,
            $status === 3 ? 'Declined' : ($statusMap[$status] ?? 'Unknown'),
            'Deposit',
            'Deposit - ' . ($row['crypto_name'] ?: 'crypto'),
            $row['refrence_id'] ?? '',
            $row['created_at'] ?? ''
        );
    }

    // --- Outbound wire transfers -------------------------------------------
    $stmt = $conn->prepare(
        'SELECT wire_id, refrence_id, amount, bank_name, acct_name, wire_status, createdAt
         FROM wire_transfer WHERE acct_id = :acct_id ORDER BY wire_id DESC'
    );
    $stmt->execute(['acct_id' => $acctId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = (int)($row['wire_status'] ?? 0);
        $beneficiary = trim((string)($row['acct_name'] ?? ''));
        $bank = trim((string)($row['bank_name'] ?? ''));
        $push(
            'wire',
            (int)$row['wire_id'],
            $row['amount'],
            2,
            $status,
            api_wire_status((string)$status),
            'Wire Transfer',
            'Wire to ' . ($beneficiary !== '' ? $beneficiary : ($bank !== '' ? $bank : 'beneficiary')),
            $row['refrence_id'] ?? '',
            $row['createdAt'] ?? ''
        );
    }

    // --- Outbound domestic transfers ---------------------------------------
    $stmt = $conn->prepare(
        'SELECT dom_id, refrence_id, amount, bank_name, acct_name, dom_status, created_at
         FROM domestic_transfer WHERE acct_id = :acct_id ORDER BY dom_id DESC'
    );
    $stmt->execute(['acct_id' => $acctId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = (int)($row['dom_status'] ?? 0);
        $beneficiary = trim((string)($row['acct_name'] ?? ''));
        $bank = trim((string)($row['bank_name'] ?? ''));
        $push(
            'domestic',
            (int)$row['dom_id'],
            $row['amount'],
            2,
            $status,
            api_domestic_status((string)$status),
            'Domestic Transfer',
            'Transfer to ' . ($beneficiary !== '' ? $beneficiary : ($bank !== '' ? $bank : 'beneficiary')),
            $row['refrence_id'] ?? '',
            $row['created_at'] ?? ''
        );
    }

    // --- Withdrawals -------------------------------------------------------
    $stmt = $conn->prepare(
        'SELECT id, reference_id, amount, withdraw_method, wallet_address, bankname,
                status, createdAt
         FROM withdrawal WHERE user_id = :acct_id ORDER BY id DESC'
    );
    $stmt->execute(['acct_id' => $acctId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = (int)($row['status'] ?? 0);
        // Bank withdrawals store the literal 'bank'; crypto stores a
        // crypto_currency id, so a non-empty wallet address is the reliable
        // discriminator.
        $isCrypto = trim((string)($row['wallet_address'] ?? '')) !== '';
        $bank = trim((string)($row['bankname'] ?? ''));
        $push(
            'withdrawal',
            (int)$row['id'],
            $row['amount'],
            2,
            $status,
            api_wire_status((string)$status),
            'Withdrawal',
            $isCrypto ? 'Crypto withdrawal' : ('Bank withdrawal' . ($bank !== '' ? ' - ' . $bank : '')),
            $row['reference_id'] ?? '',
            $row['createdAt'] ?? ''
        );
    }

    // Newest first. `transactions` splits date and time across two text columns
    // while every other source carries a full datetime, so recombine first.
    usort($ledger, static function (array $a, array $b): int {
        $stamp = static function (array $row): int {
            return strtotime(trim($row['created_at'] . ' ' . $row['time_created'])) ?: 0;
        };
        return $stamp($b) <=> $stamp($a);
    });

    return $ledger;
}
