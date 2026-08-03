<?php
require_once __DIR__ . '/_bootstrap.php';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
if ($limit < 1) $limit = 1;
if ($limit > 500) $limit = 500;

$currency = user_currency_symbol($user);

// Ledger-committed rows.
$sql = "SELECT trans_id, refrence_id, amount, trans_type, sender_name, description, trans_status, created_at, time_created
        FROM transactions
        WHERE user_id=:acct_id
        ORDER BY trans_id DESC
        LIMIT {$limit}";
$stmt = $conn->prepare($sql);
$stmt->execute(['acct_id' => $user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$statusMap = [0 => 'Processing', 1 => 'Completed', 2 => 'Hold', 3 => 'Cancelled'];

foreach ($rows as &$rowTx) {
    $rowTx['currency'] = $currency;
    $rowTx['trans_type'] = (int)($rowTx['trans_type'] ?? 0);
    $rowTx['trans_status'] = (int)($rowTx['trans_status'] ?? 0);
    $rowTx['type_label'] = $rowTx['trans_type'] === 1 ? 'Credit' : 'Debit';
    $rowTx['status_label'] = $statusMap[$rowTx['trans_status']] ?? 'Unknown';
    $rowTx['source'] = 'transaction';
    $rowTx['reference_id'] = $rowTx['refrence_id'];
}
unset($rowTx);

// Pending deposits — surface as read-only "Processing" credits so the user
// can see them immediately after submission, before admin approval creates
// the real ledger row. crypto_status 1 = approved (a matching transactions
// row already exists), 3 = declined (hide from activity).
$depStmt = $conn->prepare("SELECT d.d_id, d.refrence_id, d.amount, d.crypto_status, d.created_at, c.crypto_name
                           FROM deposit d
                           LEFT JOIN crypto_currency c ON c.id = d.crypto_id
                           WHERE d.user_id = :acct_id AND d.crypto_status IN (0, 2)
                           ORDER BY d.d_id DESC");
$depStmt->execute(['acct_id' => $user['id']]);
$deposits = $depStmt->fetchAll(PDO::FETCH_ASSOC);

$depositRows = [];
foreach ($deposits as $dep) {
    $status = (int)($dep['crypto_status'] ?? 0);
    $depositRows[] = [
        // No `trans_id` — the client uses that field to decide clickability.
        // A stable synthetic id keeps :key unique.
        'id' => 'd-' . (int)$dep['d_id'],
        'currency' => $currency,
        'amount' => (float)$dep['amount'],
        'trans_type' => 1,
        'type_label' => 'Credit',
        'trans_status' => $status,
        'status_label' => $status === 2 ? 'Hold' : 'Processing',
        'sender_name' => 'Crypto Deposit',
        'description' => 'Deposit — ' . ($dep['crypto_name'] ?? 'crypto'),
        'refrence_id' => $dep['refrence_id'],
        'reference_id' => $dep['refrence_id'],
        'created_at' => $dep['created_at'],
        'time_created' => '',
        'source' => 'deposit',
    ];
}

$merged = array_merge($rows, $depositRows);

// Newest first — deposits carry a datetime, transactions carry text; sort
// on parsed timestamps so the merged list is chronologically clean.
usort($merged, static function ($a, $b): int {
    $ta = strtotime((string)($a['created_at'] ?? '')) ?: 0;
    $tb = strtotime((string)($b['created_at'] ?? '')) ?: 0;
    return $tb <=> $ta;
});

$merged = array_slice($merged, 0, $limit);

api_json(200, ['ok' => true, 'data' => $merged]);
