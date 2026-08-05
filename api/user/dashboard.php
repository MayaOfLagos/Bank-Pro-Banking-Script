<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_ledger.php';

function dashboard_parse_date(?string $value): ?DateTimeImmutable
{
    if (!$value) {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return (new DateTimeImmutable())->setTimestamp($timestamp);
}

$currency = user_currency_symbol($user);
$userId = (int)$user['id'];

$tx = $conn->prepare('SELECT trans_id, amount, trans_type, sender_name, description, created_at, time_created, trans_status FROM transactions WHERE user_id = :user_id ORDER BY trans_id DESC LIMIT 180');
$tx->execute(['user_id' => $userId]);
$transactions = $tx->fetchAll(PDO::FETCH_ASSOC);

// Same feed /transactions renders, capped to the dashboard preview length, so
// a wire or withdrawal the user just made shows up here too.
$recent = array_slice(user_ledger($conn, $userId, $currency), 0, 8);

$today = new DateTimeImmutable('today');
$weeklyLabels = [];
$weeklyCredit = [];
$weeklyDebit = [];
$weeklyKeys = [];
for ($index = 6; $index >= 0; $index--) {
    $day = $today->modify("-{$index} days");
    $key = $day->format('Y-m-d');
    $weeklyKeys[$key] = count($weeklyLabels);
    $weeklyLabels[] = $day->format('D');
    $weeklyCredit[] = 0;
    $weeklyDebit[] = 0;
}

$monthlyLabels = [];
$monthlyCredit = [];
$monthlyDebit = [];
$monthlyKeys = [];
for ($index = 5; $index >= 0; $index--) {
    $month = $today->modify("first day of -{$index} month");
    $key = $month->format('Y-m');
    $monthlyKeys[$key] = count($monthlyLabels);
    $monthlyLabels[] = $month->format('M');
    $monthlyCredit[] = 0;
    $monthlyDebit[] = 0;
}

$creditVolume = 0.0;
$debitVolume = 0.0;

foreach ($transactions as &$rowTx) {
    $amount = (float)($rowTx['amount'] ?? 0);
    $rowTx['trans_type'] = (int)($rowTx['trans_type'] ?? 0);
    $rowTx['trans_status'] = (int)($rowTx['trans_status'] ?? 0);
    $isCredit = $rowTx['trans_type'] === 1;
    $rowTx['type_label'] = $isCredit ? 'Credit' : 'Debit';
    $rowTx['status_label'] = [0 => 'Processing', 1 => 'Completed', 2 => 'Hold', 3 => 'Cancelled'][$rowTx['trans_status']] ?? 'Unknown';
    $rowTx['currency'] = $currency;

    if ($isCredit) {
        $creditVolume += $amount;
    } else {
        $debitVolume += $amount;
    }

    $parsedDate = dashboard_parse_date((string)($rowTx['created_at'] ?? ''));
    if (!$parsedDate) {
        continue;
    }

    $weekKey = $parsedDate->format('Y-m-d');
    if (isset($weeklyKeys[$weekKey])) {
        $targetIndex = $weeklyKeys[$weekKey];
        if ($isCredit) {
            $weeklyCredit[$targetIndex] += $amount;
        } else {
            $weeklyDebit[$targetIndex] += $amount;
        }
    }

    $monthKey = $parsedDate->format('Y-m');
    if (isset($monthlyKeys[$monthKey])) {
        $targetIndex = $monthlyKeys[$monthKey];
        if ($isCredit) {
            $monthlyCredit[$targetIndex] += $amount;
        } else {
            $monthlyDebit[$targetIndex] += $amount;
        }
    }
}
unset($rowTx);

$audit = $conn->prepare('SELECT device, ipAddress, datenow FROM audit_logs WHERE user_id=:user_id ORDER BY datenow DESC LIMIT 1');
$audit->execute(['user_id' => $userId]);
$lastLogin = $audit->fetch(PDO::FETCH_ASSOC) ?: null;

$wireCountStmt = $conn->prepare('SELECT COUNT(*) FROM wire_transfer WHERE acct_id=:acct_id');
$wireCountStmt->execute(['acct_id' => $userId]);
$wireCount = (int)$wireCountStmt->fetchColumn();

$domCountStmt = $conn->prepare('SELECT COUNT(*) FROM domestic_transfer WHERE acct_id=:acct_id');
$domCountStmt->execute(['acct_id' => $userId]);
$domesticCount = (int)$domCountStmt->fetchColumn();

$withdrawCountStmt = $conn->prepare('SELECT COUNT(*) FROM withdrawal WHERE user_id=:user_id');
$withdrawCountStmt->execute(['user_id' => $userId]);
$withdrawalCount = (int)$withdrawCountStmt->fetchColumn();

$loanCountStmt = $conn->prepare('SELECT COUNT(*) FROM loan WHERE acct_id=:acct_id');
$loanCountStmt->execute(['acct_id' => $userId]);
$loanCount = (int)$loanCountStmt->fetchColumn();

// loan_status 1 is Approved (see admin/viewloan-trans.php); 0, 2 and 3 are
// pending, on hold and declined, none of which the customer owes on yet.
$loanActiveStmt = $conn->prepare('SELECT COUNT(*) FROM loan WHERE acct_id=:acct_id AND loan_status=1');
$loanActiveStmt->execute(['acct_id' => $userId]);
$loanActiveCount = (int)$loanActiveStmt->fetchColumn();

$cardStmt = $conn->prepare('SELECT card_name, card_status FROM card WHERE user_id=:user_id LIMIT 1');
$cardStmt->execute(['user_id' => $userId]);
$card = $cardStmt->fetch(PDO::FETCH_ASSOC) ?: null;

api_json(200, [
    'ok' => true,
    'data' => [
        'currency' => $currency,
        'acct_balance' => round((float)($user['acct_balance'] ?? 0), 2),
        'avail_balance' => round((float)($user['avail_balance'] ?? 0), 2),
        'limit_remain' => round((float)($user['limit_remain'] ?? 0), 2),
        'acct_limit' => round((float)($user['acct_limit'] ?? 0), 2),
        'loan_balance' => round((float)($user['loan_balance'] ?? 0), 2),
        'recent_transactions' => $recent,
        'profile' => [
            'full_name' => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
            'acct_no' => (string)($user['acct_no'] ?? ''),
            'acct_type' => (string)($user['acct_type'] ?? ''),
            'acct_status' => (string)($user['acct_status'] ?? ''),
            'image' => '/assets/profile/' . ($user['image'] ?? 'user.png'),
            'email' => (string)($user['acct_email'] ?? ''),
        ],
        'analytics' => [
            'weekly' => [
                'labels' => $weeklyLabels,
                'credit' => $weeklyCredit,
                'debit' => $weeklyDebit,
            ],
            'monthly' => [
                'labels' => $monthlyLabels,
                'credit' => $monthlyCredit,
                'debit' => $monthlyDebit,
            ],
        ],
        'volume' => [
            'credit' => round($creditVolume, 2),
            'debit' => round($debitVolume, 2),
            'net' => round($creditVolume - $debitVolume, 2),
        ],
        'counters' => [
            'wire_transfers' => $wireCount,
            'domestic_transfers' => $domesticCount,
            'withdrawals' => $withdrawalCount,
            'loans' => $loanCount,
            'loans_active' => $loanActiveCount,
        ],
        'last_login' => $lastLogin,
        'card' => $card ? [
            'card_name' => $card['card_name'] ?? '',
            'card_status' => (int)($card['card_status'] ?? 0),
        ] : null,
        'quick_actions' => [
            // Each toggle reflects the admin-controlled column on users. The
            // SPA hides quick-action buttons when a flag is false so the
            // customer never sees a control the backend will just 403.
            'can_transfer' => (string)($user['transfer'] ?? '1') === '1' && (string)($user['acct_status'] ?? 'hold') === 'active',
            'can_deposit' => (string)($user['can_deposit'] ?? '1') === '1',
            'can_withdraw' => (string)($user['can_withdraw'] ?? '1') === '1',
            'can_request_card' => (string)($user['can_request_card'] ?? '1') === '1',
            'has_card' => $card !== null,
        ],
    ]
]);
