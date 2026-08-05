<?php
include_once("./layout/header.php");

$id   = $_GET['id'] ?? '';
$stmt = $conn->prepare("SELECT * FROM wire_transfer LEFT JOIN users ON wire_transfer.acct_id = users.id WHERE refrence_id =:id");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger">Wire transfer not found.</div></div></section>';
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

if (isset($_POST['accept'])) {
    // Approve only from Processing (0) or Hold (2). Idempotent: repeat clicks are no-ops.
    $upd = $conn->prepare("UPDATE wire_transfer SET wire_status=1 WHERE refrence_id=:id AND wire_status IN (0, 2)");
    $upd->execute(['id' => $id]);
    if ($upd->rowCount() === 1) {
        $message = $sendMail->wireMsg($currency, $row['amount'], $row['acct_balance'], 'Wire Transfer', $fullName, $APP_NAME, 'Approved', $row['refrence_id']);
        $email_message->send_to_both($email, $message, "[WIRE TRANSFER APPROVED] - $APP_NAME");
        // Alert operators when money out clears the configured review ceiling.
        // A missing/zero trans_limit_max means no threshold is configured, so stay
        // silent rather than mailing on every small approval.
        $largeThreshold = (float)($page['trans_limit_max'] ?? 0);
        if ($largeThreshold > 0 && (float)$row['amount'] >= $largeThreshold) {
            admin_notify(
                (new AdminAlert)->adminLargeValueApprovalMsg(admin_actor_name(), 'wire transfer', $fullName, $currency, $row['amount'], (string)$row['refrence_id'], $largeThreshold, admin_actor_ip()),
                'Large wire transfer approved'
            );
        }
        toast_alert('success', 'Wire Transfer Approved', 'Approved');
    } else {
        toast_alert('info', 'Wire transfer already finalised', 'No change');
    }
}

if (isset($_POST['decline'])) {
    // Decline + refund only from Processing (0) or Hold (2). Refund runs at most once
    // by combining the status transition with a locked balance read in one transaction.
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("UPDATE wire_transfer SET wire_status=3 WHERE refrence_id=:id AND wire_status IN (0, 2)");
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() !== 1) {
            $conn->rollBack();
            toast_alert('info', 'Wire transfer already finalised', 'No change');
        } else {
            $lock = $conn->prepare('SELECT acct_balance FROM users WHERE id=:id FOR UPDATE');
            $lock->execute(['id' => $user_id]);
            $currentBalance = (float)$lock->fetchColumn();
            $amount_balance = $currentBalance + (float)$row['amount'];
            $upd = $conn->prepare('UPDATE users SET acct_balance=:b WHERE id=:id');
            $upd->execute(['b' => $amount_balance, 'id' => $user_id]);
            $conn->commit();
            $message = $sendMail->wireMsg($currency, $row['amount'], $amount_balance, 'Wire Transfer', $fullName, $APP_NAME, 'Declined', $row['refrence_id']);
            $email_message->send_to_both($email, $message, "[WIRE TRANSFER DECLINED] - $APP_NAME");
            toast_alert('success', 'Wire Transfer Declined', 'Decline');
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        toast_alert('error', 'Unable to decline wire transfer', 'Error');
    }
}

if (isset($_POST['hold'])) {
    // Hold only from Processing (0).
    $upd = $conn->prepare("UPDATE wire_transfer SET wire_status=2 WHERE refrence_id=:id AND wire_status = 0");
    $upd->execute(['id' => $id]);
    if ($upd->rowCount() === 1) {
        $message = $sendMail->wireMsg($currency, $row['amount'], $row['acct_balance'], 'Wire Transfer', $fullName, $APP_NAME, 'On Hold', $row['refrence_id']);
        $email_message->send_to_both($email, $message, "[WIRE TRANSFER ON HOLD] - $APP_NAME");
        toast_alert('success', 'Wire Transfer Placed on Hold', 'Hold');
    } else {
        toast_alert('info', 'Wire transfer already finalised', 'No change');
    }
}

if (isset($_POST['trans_delete'])) {
    // Snapshot the row before it is destroyed: the delete is permanent, refunds
    // nothing, and otherwise leaves no record of what the transfer held.
    $statusText = ['0' => 'Processing', '1' => 'Approved', '2' => 'Hold', '3' => 'Cancelled'][(string)$row['wire_status']] ?? 'Unknown';
    $snapshot = [
        'Amount'           => MailBrand::money($currency, $row['amount']),
        'Status'           => $statusText,
        'Beneficiary'      => $row['acct_name'],
        'Beneficiary bank' => $row['bank_name'],
        'Account number'   => $row['acct_number'],
        'Created'          => $row['created_at'],
    ];
    $stmt = $conn->prepare("DELETE FROM wire_transfer WHERE refrence_id=:id");
    $stmt->execute(['id' => $id]);
    admin_notify(
        (new AdminAlert)->adminMoneyRecordDeletedMsg(admin_actor_name(), 'wire transfer', (string)$row['refrence_id'], $snapshot, $fullName, admin_actor_ip()),
        'Wire transfer record deleted'
    );
    header('Location:./wire-trans.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM wire_transfer LEFT JOIN users ON wire_transfer.acct_id = users.id WHERE refrence_id =:id");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$tran_status = $statusMap[(string)$row['wire_status']] ?? '<span class="badge badge-light">Unknown</span>';
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Wire Transfer Details</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="./wire-trans.php">Wire</a></li>
                    <li class="breadcrumb-item active">View</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Wire Transaction</h3></div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr><th>Name</th><td><?= ucwords($fullName) ?></td></tr>
                        <tr><th>Reference ID</th><td><?= htmlspecialchars($row['refrence_id']) ?></td></tr>
                        <tr><th>Amount</th><td><?= $currency . htmlspecialchars($row['amount']) ?></td></tr>
                        <tr><th>Bank Name</th><td><?= htmlspecialchars($row['bank_name']) ?></td></tr>
                        <tr><th>Account Name</th><td><?= htmlspecialchars($row['acct_name']) ?></td></tr>
                        <tr><th>Account No</th><td><?= htmlspecialchars($row['acct_number']) ?></td></tr>
                        <tr><th>Country</th><td><?= htmlspecialchars($row['acct_country']) ?></td></tr>
                        <tr><th>Swift</th><td><?= htmlspecialchars($row['acct_swift']) ?></td></tr>
                        <tr><th>Routing</th><td><?= htmlspecialchars($row['acct_routing']) ?></td></tr>
                        <tr><th>Description</th><td><?= htmlspecialchars($row['acct_remarks']) ?></td></tr>
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
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this transfer?')">
                    <button class="btn btn-danger" name="trans_delete"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
