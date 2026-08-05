<?php
include_once("./layout/header.php");

$id   = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM transactions LEFT JOIN users ON transactions.user_id = users.id WHERE transactions.trans_id=:id");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger">Transaction not found.</div></div></section>';
    include_once("./layout/footer.php");
    exit;
}

$user_id      = $row['id'];
$currency     = currency($row);
$email        = $row['acct_email'];
$fullName     = $row['firstname'] . " " . $row['lastname'];
$trans_type_label   = $row['trans_type'] === '1' ? '<span class="badge badge-success">Credit</span>' : '<span class="badge badge-danger">Debit</span>';
$transfer_type_text = $row['trans_type'] === '1' ? 'CREDIT' : 'DEBIT';

$statusMap = [
    '0' => '<span class="badge badge-secondary">Processing</span>',
    '1' => '<span class="badge badge-success">Approved</span>',
    '2' => '<span class="badge badge-warning">Hold</span>',
    '3' => '<span class="badge badge-danger">Cancelled</span>',
];
$tran_status = $statusMap[(string)$row['trans_status']] ?? '<span class="badge badge-light">Unknown</span>';

if (isset($_POST['accept'])) {
    $stmt = $conn->prepare("UPDATE transactions SET trans_status=:s WHERE refrence_id=:id");
    $stmt->execute(['s' => 1, 'id' => $row['refrence_id']]);

    if ($row['trans_type'] === '1') {
        $amount_balance = $row['amount'] + $row['acct_balance'];
    } else {
        $amount_balance = $row['acct_balance'] - $row['amount'];
    }
    $upd = $conn->prepare("UPDATE users SET acct_balance=:b WHERE id=:id");
    $upd->execute(['b' => $amount_balance, 'id' => $user_id]);

    $message = $sendMail->creditMsg($currency, $row['amount'], $amount_balance, $transfer_type_text, $fullName, $pageTitle, "Successful", $row['refrence_id']);
    $email_message->send_to_both($email, $message, "[TRANSACTION APPROVED] - $pageTitle");

    toast_alert('success', 'Transaction Approved Successfully', 'Approved');
}

if (isset($_POST['decline'])) {
    $stmt = $conn->prepare("UPDATE transactions SET trans_status=:s WHERE refrence_id=:id");
    $stmt->execute(['s' => 3, 'id' => $row['refrence_id']]);
    $message = $sendMail->creditMsg($currency, $row['amount'], $row['acct_balance'], $transfer_type_text, $fullName, $pageTitle, "Declined", $row['refrence_id']);
    $email_message->send_to_both($email, $message, "[TRANSACTION DECLINED] - $pageTitle");
    toast_alert('success', 'Transaction Declined', 'Decline');
}

if (isset($_POST['hold'])) {
    $stmt = $conn->prepare("UPDATE transactions SET trans_status=:s WHERE refrence_id=:id");
    $stmt->execute(['s' => 2, 'id' => $row['refrence_id']]);
    $message = $sendMail->creditMsg($currency, $row['amount'], $row['acct_balance'], $transfer_type_text, $fullName, $pageTitle, "ON HOLD", $row['refrence_id']);
    $email_message->send_to_both($email, $message, "[TRANSACTION ON HOLD] - $pageTitle");
    toast_alert('success', 'Transaction Placed on Hold', 'Hold');
}

if (isset($_POST['trans_delete'])) {
    // The delete is permanent and un-reversed, so snapshot the row first — the
    // alert becomes the only remaining record of what was destroyed.
    $snapshot = [
        'Amount'      => (string)$row['amount'],
        'Type'        => $transfer_type_text,
        'Sender'      => (string)$row['sender_name'],
        'Description' => (string)$row['description'],
        'Date'        => trim($row['created_at'] . ' ' . $row['time_created']),
    ];
    $reference = !empty($row['refrence_id']) ? (string)$row['refrence_id'] : (string)$row['trans_id'];

    $stmt = $conn->prepare("DELETE FROM transactions WHERE trans_id=:id");
    $stmt->execute(['id' => $id]);

    // Ledger integrity: a transaction row was hard-deleted from the ledger.
    admin_notify(
        (new AdminAlert)->adminTransactionDeletedMsg(admin_actor_name(), $reference, $snapshot, $fullName, admin_actor_ip()),
        'Transaction deleted'
    );

    header('Location:./credit_debit_trans.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM transactions LEFT JOIN users ON transactions.user_id = users.id WHERE transactions.trans_id=:id");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$tran_status = $statusMap[(string)$row['trans_status']] ?? $tran_status;
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Transaction Details</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="./credit_debit_trans.php">Transactions</a></li>
                    <li class="breadcrumb-item active">View</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Credit / Debit Transaction</h3></div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr><th>Name</th><td><?= htmlspecialchars(ucwords($fullName)) ?></td></tr>
                        <tr><th>Transaction ID</th><td><?= htmlspecialchars($row['refrence_id']) ?></td></tr>
                        <tr><th>Amount</th><td><?= $currency . htmlspecialchars($row['amount']) ?></td></tr>
                        <tr><th>Type</th><td><?= $trans_type_label ?></td></tr>
                        <tr><th>Sender</th><td><?= htmlspecialchars($row['sender_name']) ?></td></tr>
                        <tr><th>Description</th><td><?= htmlspecialchars($row['description']) ?></td></tr>
                        <tr><th>Created At</th><td><?= htmlspecialchars($row['created_at']) ?></td></tr>
                        <tr><th>Time</th><td><?= htmlspecialchars($row['time_created']) ?></td></tr>
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
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this transaction?')">
                    <button class="btn btn-danger" name="trans_delete"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
