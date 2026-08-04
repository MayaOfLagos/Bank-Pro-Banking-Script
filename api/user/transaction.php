<?php
require_once __DIR__ . '/_bootstrap.php';

// Detail for any row in the unified ledger. `source` selects the table;
// omitting it keeps the pre-ledger `?id=` contract working.
$source = strtolower(trim((string)($_GET['source'] ?? 'transaction')));
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    api_json(422, ['ok' => false, 'message' => 'Missing transaction id']);
}

$sources = ['transaction', 'deposit', 'wire', 'domestic', 'withdrawal'];
if (!in_array($source, $sources, true)) {
    api_json(422, ['ok' => false, 'message' => 'Unknown transaction source']);
}

$acctId = (int)$user['id'];
$currency = user_currency_symbol($user);
$statusMap = [0 => 'Processing', 1 => 'Completed', 2 => 'Hold', 3 => 'Cancelled'];

// Each source names its own PK and owner column; everything else is uniform.
$queries = [
    'transaction' => ['transactions', 'trans_id', 'user_id'],
    'deposit'     => ['deposit', 'd_id', 'user_id'],
    'wire'        => ['wire_transfer', 'wire_id', 'acct_id'],
    'domestic'    => ['domestic_transfer', 'dom_id', 'acct_id'],
    'withdrawal'  => ['withdrawal', 'id', 'user_id'],
];
list($table, $pk, $ownerColumn) = $queries[$source];

$stmt = $conn->prepare("SELECT * FROM `{$table}` WHERE `{$pk}` = :id AND `{$ownerColumn}` = :owner LIMIT 1");
$stmt->execute(['id' => $id, 'owner' => $acctId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    api_json(404, ['ok' => false, 'message' => 'Transaction not found']);
}

$extra = [];
$timeCreated = '';

switch ($source) {
    case 'transaction':
        $status = (int)($row['trans_status'] ?? 0);
        $direction = (int)($row['trans_type'] ?? 0) === 1 ? 1 : 2;
        $statusLabel = $statusMap[$status] ?? 'Unknown';
        $title = (string)($row['sender_name'] ?? '');
        $description = (string)($row['description'] ?? '');
        $reference = (string)($row['refrence_id'] ?? '');
        $createdAt = (string)($row['created_at'] ?? '');
        $timeCreated = (string)($row['time_created'] ?? '');
        break;

    case 'deposit':
        $status = (int)($row['crypto_status'] ?? 0);
        $direction = 1;
        $statusLabel = $status === 3 ? 'Declined' : ($statusMap[$status] ?? 'Unknown');
        $cryptoStmt = $conn->prepare('SELECT crypto_name FROM crypto_currency WHERE id = :id LIMIT 1');
        $cryptoStmt->execute(['id' => (int)($row['crypto_id'] ?? 0)]);
        $cryptoName = (string)($cryptoStmt->fetchColumn() ?: 'crypto');
        $title = 'Deposit';
        $description = 'Deposit - ' . $cryptoName;
        $reference = (string)($row['refrence_id'] ?? '');
        $createdAt = (string)($row['created_at'] ?? '');
        $extra[] = ['label' => 'Method', 'value' => $cryptoName];
        if (!empty($row['wallet_address'])) {
            $extra[] = ['label' => 'Paid to wallet', 'value' => (string)$row['wallet_address'], 'mono' => true];
        }
        break;

    case 'wire':
        $status = (int)($row['wire_status'] ?? 0);
        $direction = 2;
        $statusLabel = api_wire_status((string)$status);
        $title = 'Wire Transfer';
        $beneficiary = trim((string)($row['acct_name'] ?? ''));
        $description = 'Wire to ' . ($beneficiary !== '' ? $beneficiary : 'beneficiary');
        $reference = (string)($row['refrence_id'] ?? '');
        $createdAt = (string)($row['createdAt'] ?? '');
        $extra[] = ['label' => 'Beneficiary', 'value' => $beneficiary ?: '—'];
        $extra[] = ['label' => 'Bank', 'value' => (string)($row['bank_name'] ?? '') ?: '—'];
        $extra[] = ['label' => 'Account number', 'value' => (string)($row['acct_number'] ?? '') ?: '—', 'mono' => true];
        $extra[] = ['label' => 'Account type', 'value' => (string)($row['acct_type'] ?? '') ?: '—'];
        $extra[] = ['label' => 'Country', 'value' => (string)($row['acct_country'] ?? '') ?: '—'];
        $extra[] = ['label' => 'SWIFT / BIC', 'value' => (string)($row['acct_swift'] ?? '') ?: '—', 'mono' => true];
        $extra[] = ['label' => 'Routing', 'value' => (string)($row['acct_routing'] ?? '') ?: '—', 'mono' => true];
        if (!empty($row['acct_remarks'])) {
            $extra[] = ['label' => 'Remarks', 'value' => (string)$row['acct_remarks']];
        }
        break;

    case 'domestic':
        $status = (int)($row['dom_status'] ?? 0);
        $direction = 2;
        $statusLabel = api_domestic_status((string)$status);
        $title = 'Domestic Transfer';
        $beneficiary = trim((string)($row['acct_name'] ?? ''));
        $description = 'Transfer to ' . ($beneficiary !== '' ? $beneficiary : 'beneficiary');
        $reference = (string)($row['refrence_id'] ?? '');
        $createdAt = (string)($row['created_at'] ?? '');
        $extra[] = ['label' => 'Beneficiary', 'value' => $beneficiary ?: '—'];
        $extra[] = ['label' => 'Bank', 'value' => (string)($row['bank_name'] ?? '') ?: '—'];
        $extra[] = ['label' => 'Account number', 'value' => (string)($row['acct_number'] ?? '') ?: '—', 'mono' => true];
        $extra[] = ['label' => 'Account type', 'value' => (string)($row['acct_type'] ?? '') ?: '—'];
        if (!empty($row['acct_remarks'])) {
            $extra[] = ['label' => 'Remarks', 'value' => (string)$row['acct_remarks']];
        }
        break;

    default: // withdrawal
        $status = (int)($row['status'] ?? 0);
        $direction = 2;
        $statusLabel = api_wire_status((string)$status);
        $title = 'Withdrawal';
        $isCrypto = trim((string)($row['wallet_address'] ?? '')) !== '';
        $reference = (string)($row['reference_id'] ?? '');
        $createdAt = (string)($row['createdAt'] ?? '');
        if ($isCrypto) {
            $description = 'Crypto withdrawal';
            $extra[] = ['label' => 'Destination wallet', 'value' => (string)$row['wallet_address'], 'mono' => true];
        } else {
            $bank = trim((string)($row['bankname'] ?? ''));
            $description = 'Bank withdrawal' . ($bank !== '' ? ' - ' . $bank : '');
            $extra[] = ['label' => 'Bank', 'value' => $bank ?: '—'];
            $extra[] = ['label' => 'Account name', 'value' => (string)($row['acctname'] ?? '') ?: '—'];
            $extra[] = ['label' => 'Account number', 'value' => (string)($row['account_number'] ?? '') ?: '—', 'mono' => true];
            $extra[] = ['label' => 'Routing', 'value' => (string)($row['routineno'] ?? '') ?: '—', 'mono' => true];
        }
        break;
}

api_json(200, ['ok' => true, 'data' => [
    'source'       => $source,
    'record_id'    => $id,
    'currency'     => $currency,
    'amount'       => (float)($row['amount'] ?? 0),
    'trans_type'   => $direction,
    'type_label'   => $direction === 1 ? 'Credit' : 'Debit',
    'trans_status' => $status,
    'status_label' => $statusLabel,
    'sender_name'  => $title,
    'description'  => $description,
    'refrence_id'  => $reference,
    'reference_id' => $reference,
    'created_at'   => $createdAt,
    'time_created' => $timeCreated,
    'extra'        => $extra,
]]);
