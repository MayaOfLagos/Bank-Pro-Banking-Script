<?php
include_once("./layout/header.php");

$id   = $_GET['id'] ?? '';
$stmt = $conn->prepare("SELECT * FROM withdrawal LEFT JOIN users ON withdrawal.user_id = users.id WHERE withdrawal.reference_id =:id");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger">Withdrawal not found.</div></div></section>';
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

$mailWithdraw = function ($balance) use ($sendMail, $email_message, $email, $APP_NAME, $currency, $row, $fullName) {
    $message = $sendMail->WithdrawMsg($currency, $row['amount'], $balance, $fullName, $APP_NAME);
    $email_message->send_to_both($email, $message, "Withdrawal Status - $APP_NAME");
};

if (isset($_POST['accept'])) {
    // Approve only from Processing (0) or Hold (2). Idempotent: repeat clicks are no-ops.
    $upd = $conn->prepare("UPDATE withdrawal SET status=1 WHERE reference_id=:id AND status IN (0, 2)");
    $upd->execute(['id' => $id]);
    if ($upd->rowCount() === 1) {
        $mailWithdraw($row['acct_balance']);
        toast_alert('success', 'Withdrawal Approved', 'Approved');
    } else {
        toast_alert('info', 'Withdrawal already finalised', 'No change');
    }
}

if (isset($_POST['decline'])) {
    // Decline + refund only from Processing (0) or Hold (2). Refund runs at most once
    // by combining the status transition with a locked balance read in one transaction.
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("UPDATE withdrawal SET status=3 WHERE reference_id=:id AND status IN (0, 2)");
        $stmt->execute(['id' => $id]);
        if ($stmt->rowCount() !== 1) {
            $conn->rollBack();
            toast_alert('info', 'Withdrawal already finalised', 'No change');
        } else {
            $lock = $conn->prepare('SELECT acct_balance FROM users WHERE id=:id FOR UPDATE');
            $lock->execute(['id' => $user_id]);
            $currentBalance = (float)$lock->fetchColumn();
            $amount_balance = $currentBalance + (float)$row['amount'];
            $upd = $conn->prepare('UPDATE users SET acct_balance=:b WHERE id=:id');
            $upd->execute(['b' => $amount_balance, 'id' => $user_id]);
            $conn->commit();
            $mailWithdraw($amount_balance);
            toast_alert('success', 'Withdrawal Declined', 'Decline');
        }
    } catch (Throwable $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        toast_alert('error', 'Unable to decline withdrawal', 'Error');
    }
}

if (isset($_POST['hold'])) {
    // Hold only from Processing (0).
    $upd = $conn->prepare("UPDATE withdrawal SET status=2 WHERE reference_id=:id AND status = 0");
    $upd->execute(['id' => $id]);
    if ($upd->rowCount() === 1) {
        toast_alert('success', 'Withdrawal Placed on Hold', 'Hold');
    } else {
        toast_alert('info', 'Withdrawal already finalised', 'No change');
    }
}

if (isset($_POST['trans_delete'])) {
    $stmt = $conn->prepare("DELETE FROM withdrawal WHERE reference_id=:id");
    $stmt->execute(['id' => $id]);
    header('Location:./withdraw-trans.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM withdrawal LEFT JOIN users ON withdrawal.user_id = users.id WHERE withdrawal.reference_id =:id");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$tran_status = $statusMap[(string)$row['status']] ?? '<span class="badge badge-light">Unknown</span>';
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Withdrawal Details</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="./withdraw-trans.php">Withdrawals</a></li>
                    <li class="breadcrumb-item active">View</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Withdrawal Transaction</h3></div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr><th>Name</th><td><?= ucwords($fullName) ?></td></tr>
                        <tr><th>Email</th><td><?= htmlspecialchars($row['acct_email']) ?></td></tr>
                        <tr><th>Reference ID</th><td><?= htmlspecialchars($row['reference_id']) ?></td></tr>
                        <tr><th>Amount</th><td><?= $currency . htmlspecialchars($row['amount']) ?></td></tr>
                        <?php if (!empty($row['bankname'])): ?>
                            <tr><th>Bank Name</th><td><?= htmlspecialchars($row['bankname']) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($row['wallet_address'])): ?>
                            <tr><th>Wallet Address</th><td><?= htmlspecialchars($row['wallet_address']) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($row['routineno'])): ?>
                            <tr><th>Routing No</th><td><?= htmlspecialchars($row['routineno']) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($row['acctname'])): ?>
                            <tr><th>Account Name</th><td><?= htmlspecialchars($row['acctname']) ?></td></tr>
                        <?php endif; ?>
                        <tr><th>Status</th><td><?= $tran_status ?></td></tr>
                        <tr><th>Created At</th><td><?= htmlspecialchars($row['createdAt']) ?></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer">
                <form method="post" class="d-inline">
                    <button class="btn btn-success" name="accept"><i class="fas fa-check"></i> Approve</button>
                    <button class="btn btn-warning" name="hold"><i class="fas fa-pause"></i> Hold</button>
                    <button class="btn btn-secondary" name="decline"><i class="fas fa-times"></i> Decline</button>
                </form>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this withdrawal?')">
                    <button class="btn btn-danger" name="trans_delete"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
