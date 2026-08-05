<?php
include_once("./layout/header.php");

$id = $_GET['id'] ?? '';

$data = $conn->prepare("SELECT * FROM deposit d LEFT JOIN users u ON d.user_id = u.id WHERE refrence_id =:id");
$data->execute(['id' => $id]);
$result = $data->fetch(PDO::FETCH_ASSOC);

if (!$result) {
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger">Crypto deposit not found.</div></div></section>';
    include_once("./layout/footer.php");
    exit;
}

$user_id  = $result['id'];
$currency = currency($result);
$email    = $result['acct_email'];
$fullName = $result['firstname'] . " " . $result['lastname'];
$APP_NAME = $pageTitle;

$stmt = $conn->prepare("SELECT d.*, c.crypto_name FROM deposit d INNER JOIN crypto_currency c ON d.crypto_id = c.id WHERE refrence_id=:id");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$statusMap = [
    '0' => '<span class="badge badge-secondary">Processing</span>',
    '1' => '<span class="badge badge-success">Approved</span>',
    '2' => '<span class="badge badge-warning">On Hold</span>',
    '3' => '<span class="badge badge-danger">Declined</span>',
];

$mailDeposit = function ($status_label, $balance) use ($sendMail, $email_message, $email, $APP_NAME, $currency, $row, $fullName) {
    $message = $sendMail->depositMsg($currency, $row['amount'], $balance, $row['crypto_name'], $fullName, $APP_NAME, $status_label, $row['refrence_id']);
    $email_message->send_to_both($email, $message, "[DEPOSIT " . strtoupper($status_label) . "] - $APP_NAME");
};

if (isset($_POST['accept'])) {
    // Approve + credit only from Processing (0) or Hold (2). Credit runs at most once
    // by combining the status transition with a locked balance read in one transaction.
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("UPDATE deposit SET crypto_status=1 WHERE refrence_id=:id AND crypto_status IN (0, 2)");
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() !== 1) {
            $conn->rollBack();
            toast_alert('info', 'Deposit already finalised', 'No change');
        } else {
            $lock = $conn->prepare('SELECT acct_balance FROM users WHERE id=:id FOR UPDATE');
            $lock->execute(['id' => $user_id]);
            $currentBalance = (float)$lock->fetchColumn();
            $amount_balance = $currentBalance + (float)$row['amount'];
            $upd = $conn->prepare('UPDATE users SET acct_balance=:b WHERE id=:id');
            $upd->execute(['b' => $amount_balance, 'id' => $user_id]);
            $conn->commit();
            $mailDeposit('Approved', $amount_balance);

            // Alert the operators when an approval credits at or above the
            // configured review limit. Silent when no limit is set.
            $threshold = (float)($page['trans_limit_max'] ?? 0);
            if ($threshold > 0 && (float)$row['amount'] >= $threshold) {
                admin_notify(
                    (new AdminAlert)->adminLargeValueApprovalMsg(admin_actor_name(), 'crypto deposit', $fullName, $currency, $row['amount'], (string)$row['refrence_id'], $threshold, admin_actor_ip()),
                    'Large crypto deposit approved'
                );
            }

            toast_alert('success', 'Deposit Approved', 'Approved');
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        toast_alert('error', 'Unable to approve deposit', 'Error');
    }
}

if (isset($_POST['decline'])) {
    $upd = $conn->prepare("UPDATE deposit SET crypto_status=3 WHERE refrence_id=:id AND crypto_status IN (0, 2)");
    $upd->execute(['id' => $id]);
    if ($upd->rowCount() === 1) {
        $mailDeposit('Declined', $result['acct_balance']);
        toast_alert('success', 'Deposit Declined', 'Decline');
    } else {
        toast_alert('info', 'Deposit already finalised', 'No change');
    }
}

if (isset($_POST['hold'])) {
    $upd = $conn->prepare("UPDATE deposit SET crypto_status=2 WHERE refrence_id=:id AND crypto_status = 0");
    $upd->execute(['id' => $id]);
    if ($upd->rowCount() === 1) {
        $mailDeposit('On Hold', $result['acct_balance']);
        toast_alert('success', 'Deposit Placed on Hold', 'Hold');
    } else {
        toast_alert('info', 'Deposit already finalised', 'No change');
    }
}

if (isset($_POST['trans_delete'])) {
    // The delete is permanent and nothing reverses it, so snapshot the row
    // first — the alert becomes the only remaining record of it.
    $statusText = ['0' => 'Processing', '1' => 'Approved', '2' => 'On Hold', '3' => 'Declined'][(string)$row['crypto_status']] ?? 'Unknown';
    $snapshot = [
        'Amount'         => MailBrand::money($currency, $row['amount']),
        'Status'         => $statusText,
        'Crypto'         => $row['crypto_name'],
        'Wallet address' => $row['wallet_address'],
        'Receipt'        => $row['image'],
        'Created'        => $row['created_at'],
    ];

    $stmt = $conn->prepare("DELETE FROM deposit WHERE refrence_id=:id");
    $stmt->execute(['id' => $id]);

    // Ledger integrity: a crypto deposit record was hard-deleted.
    admin_notify(
        (new AdminAlert)->adminMoneyRecordDeletedMsg(admin_actor_name(), 'crypto deposit', $row['refrence_id'], $snapshot, $fullName, admin_actor_ip()),
        'Crypto deposit record deleted'
    );

    header('Location:./crypto-transaction.php');
    exit;
}

$stmt = $conn->prepare("SELECT d.*, c.crypto_name FROM deposit d INNER JOIN crypto_currency c ON d.crypto_id = c.id WHERE refrence_id=:id");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$tran_status = $statusMap[(string)$row['crypto_status']] ?? '<span class="badge badge-light">Unknown</span>';
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Crypto Deposit Details</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="./crypto-transaction.php">Crypto</a></li>
                    <li class="breadcrumb-item active">View</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Crypto Transaction</h3></div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr><th>Name</th><td><?= ucwords($fullName) ?></td></tr>
                        <tr><th>Amount</th><td><?= $currency . htmlspecialchars($row['amount']) ?></td></tr>
                        <tr><th>Reference ID</th><td><?= htmlspecialchars($row['refrence_id']) ?></td></tr>
                        <tr><th>Wallet Address</th><td><?= htmlspecialchars($row['wallet_address']) ?></td></tr>
                        <tr><th>Crypto</th><td><?= htmlspecialchars($row['crypto_name']) ?></td></tr>
                        <tr>
                            <th>Receipt</th>
                            <td>
                                <?php if (empty($row['image'])): ?>
                                    <p class="text-muted">No receipt submitted</p>
                                <?php else: ?>
                                    <a href="../assets/deposit/<?= htmlspecialchars($row['image']) ?>" target="_blank"><img src="../assets/deposit/<?= htmlspecialchars($row['image']) ?>" width="220" class="img-thumbnail" alt="receipt"></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr><th>Created At</th><td><?= htmlspecialchars($row['created_at']) ?></td></tr>
                        <tr><th>Status</th><td><?= $tran_status ?></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <form method="post" class="d-inline">
                    <button class="btn btn-success" name="accept"><i class="fas fa-check"></i> Approve</button>
                    <button class="btn btn-warning" name="hold"><i class="fas fa-pause"></i> Hold</button>
                    <button class="btn btn-secondary" name="decline"><i class="fas fa-times"></i> Decline</button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this deposit?')">
                    <button class="btn btn-danger" name="trans_delete"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
