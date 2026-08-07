<?php
include_once("./layout/header.php");

$id   = $_GET['id'] ?? '';
$stmt = $conn->prepare("SELECT * FROM domestic_transfer LEFT JOIN users ON domestic_transfer.acct_id = users.id WHERE refrence_id =:id");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger">Transfer not found.</div></div></section>';
    include_once("./layout/footer.php");
    exit;
}

$user_id  = $row['id'];
$currency = currency($row);
$email    = $row['acct_email'];
$fullName = $row['firstname'] . " " . $row['lastname'];
$APP_NAME = $pageTitle;

$statusMap = [
    '0' => '<span class="badge badge-secondary">Processing</span>',
    '1' => '<span class="badge badge-success">Approved</span>',
    '2' => '<span class="badge badge-warning">Hold</span>',
    '3' => '<span class="badge badge-danger">Cancelled</span>',
];

$mailDom = function ($status_label) use ($sendMail, $email_message, $email, $APP_NAME, $currency, $row, $fullName) {
    $message = $sendMail->DoMMsg($currency, $row['amount'], $row['acct_balance'], 'Domestic Transfer', $row['bank_name'], $row['acct_name'], $row['acct_number'], $row['acct_type'], $fullName, $APP_NAME, $status_label, $row['refrence_id']);
    $email_message->send_to_both($email, $message, "[DOMESTIC TRANSFER " . strtoupper($status_label) . "] - $APP_NAME");
};

if (isset($_POST['accept'])) {
    // Approve only from Processing (0) or Hold (2). Idempotent: repeat clicks are no-ops.
    $upd = $conn->prepare("UPDATE domestic_transfer SET dom_status=1 WHERE refrence_id=:id AND dom_status IN (0, 2)");
    $upd->execute(['id' => $id]);
    if ($upd->rowCount() === 1) {
        $mailDom('Approved');

        // Alert the operators when an approval releases money at or above the
        // configured review limit. Silent when no limit is set.
        $threshold = (float)($page['trans_limit_max'] ?? 0);
        if ($threshold > 0 && (float)$row['amount'] >= $threshold) {
            admin_notify(
                (new AdminAlert)->adminLargeValueApprovalMsg(admin_actor_name(), 'domestic transfer', $fullName, $currency, $row['amount'], (string)$row['refrence_id'], $threshold, admin_actor_ip()),
                'Large domestic transfer approved'
            );
        }

        toast_alert('success', 'Domestic Transfer Approved', 'Approved');
    } else {
        toast_alert('info', 'Domestic transfer already finalised', 'No change');
    }
}

if (isset($_POST['decline'])) {
    // Decline + refund only from Processing (0) or Hold (2). Refund runs at most once.
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("UPDATE domestic_transfer SET dom_status=3 WHERE refrence_id=:id AND dom_status IN (0, 2)");
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() !== 1) {
            $conn->rollBack();
            toast_alert('info', 'Domestic transfer already finalised', 'No change');
        } else {
            $lock = $conn->prepare('SELECT acct_balance FROM users WHERE id=:id FOR UPDATE');
            $lock->execute(['id' => $user_id]);
            $currentBalance = (float)$lock->fetchColumn();
            $upd = $conn->prepare('UPDATE users SET acct_balance=:b WHERE id=:id');
            $upd->execute(['b' => $currentBalance + (float)$row['amount'], 'id' => $user_id]);
            $conn->commit();
            $mailDom('Declined');
            toast_alert('success', 'Domestic Transfer Declined', 'Decline');
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        toast_alert('error', 'Unable to decline domestic transfer', 'Error');
    }
}

if (isset($_POST['hold'])) {
    $upd = $conn->prepare("UPDATE domestic_transfer SET dom_status=2 WHERE refrence_id=:id AND dom_status = 0");
    $upd->execute(['id' => $id]);
    if ($upd->rowCount() === 1) {
        $mailDom('On Hold');
        toast_alert('success', 'Domestic Transfer Placed on Hold', 'Hold');
    } else {
        toast_alert('info', 'Domestic transfer already finalised', 'No change');
    }
}

if (isset($_POST['trans_delete'])) {
    // The delete is permanent and the debit is never reversed, so snapshot the
    // row first — the alert becomes the only remaining record of it.
    $statusText = ['0' => 'Processing', '1' => 'Approved', '2' => 'Hold', '3' => 'Cancelled'][(string)$row['dom_status']] ?? 'Unknown';
    $snapshot = [
        'Amount'         => MailBrand::money($currency, $row['amount']),
        'Status'         => $statusText,
        'Beneficiary'    => $row['acct_name'],
        'Bank'           => $row['bank_name'],
        'Account number' => $row['acct_number'],
        'Account type'   => $row['acct_type'],
        'Description'    => $row['acct_remarks'],
        'Created'        => $row['created_at'],
    ];

    $stmt = $conn->prepare("DELETE FROM domestic_transfer WHERE refrence_id=:id");
    $stmt->execute(['id' => $id]);

    // Ledger integrity: a domestic transfer record was hard-deleted.
    admin_notify(
        (new AdminAlert)->adminMoneyRecordDeletedMsg(admin_actor_name(), 'domestic transfer', $row['refrence_id'], $snapshot, $fullName, admin_actor_ip()),
        'Domestic transfer record deleted'
    );

    toast_flash('success', 'Domestic transfer record deleted.', 'Removed');
    header('Location:./domestic-trans.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM domestic_transfer LEFT JOIN users ON domestic_transfer.acct_id = users.id WHERE refrence_id =:id");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$tran_status = $statusMap[(string)$row['dom_status']] ?? '<span class="badge badge-light">Unknown</span>';
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Domestic Transfer Details</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="./domestic-trans.php">Domestic</a></li>
                    <li class="breadcrumb-item active">View</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Domestic Transaction</h3></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <tbody>
                        <tr><th>Name</th><td><?= htmlspecialchars(ucwords($fullName)) ?></td></tr>
                        <tr><th>Reference ID</th><td><?= htmlspecialchars($row['refrence_id']) ?></td></tr>
                        <tr><th>Amount</th><td><?= $currency . htmlspecialchars($row['amount']) ?></td></tr>
                        <tr><th>Bank Name</th><td><?= htmlspecialchars($row['bank_name']) ?></td></tr>
                        <tr><th>Account Name</th><td><?= htmlspecialchars($row['acct_name']) ?></td></tr>
                        <tr><th>Account No</th><td><?= htmlspecialchars($row['acct_number']) ?></td></tr>
                        <tr><th>Account Type</th><td><?= htmlspecialchars($row['acct_type']) ?></td></tr>
                        <tr><th>Description</th><td><?= htmlspecialchars($row['acct_remarks']) ?></td></tr>
                        <tr><th>Created At</th><td><?= htmlspecialchars($row['created_at']) ?></td></tr>
                        <tr><th>Available Balance</th><td><?= $currency . htmlspecialchars($row['acct_balance']) ?></td></tr>
                        <tr><th>Status</th><td><?= $tran_status ?></td></tr>
                    </tbody>
                </table>
                </div>
            </div>
            <div class="card-footer">
                <form method="post" class="d-inline">
                    <button class="btn btn-success" name="accept"><i class="fas fa-check"></i> Approve</button>
                    <button class="btn btn-warning" name="hold"><i class="fas fa-pause"></i> Hold</button>
                    <button class="btn btn-secondary" name="decline"><i class="fas fa-times"></i> Decline</button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this transfer?')">
                    <button class="btn btn-danger" name="trans_delete"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
