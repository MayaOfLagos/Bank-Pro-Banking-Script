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
    $stmt = $conn->prepare("UPDATE wire_transfer SET wire_status=:s WHERE refrence_id=:id");
    $stmt->execute(['s' => 1, 'id' => $id]);
    $message = $sendMail->wireMsg($currency, $row['amount'], $row['acct_balance'], 'Wire Transfer', $fullName, $APP_NAME, 'Approved', $row['refrence_id']);
    $email_message->send_to_both($email, $message, "[WIRE TRANSFER APPROVED] - $APP_NAME");
    toast_alert('success', 'Wire Transfer Approved', 'Approved');
}

if (isset($_POST['decline'])) {
    $stmt = $conn->prepare("UPDATE wire_transfer SET wire_status=:s WHERE refrence_id=:id");
    $stmt->execute(['s' => 3, 'id' => $id]);
    $amount_balance = $row['acct_balance'] + $row['amount'];
    $upd = $conn->prepare("UPDATE users SET acct_balance=:b WHERE id=:id");
    $upd->execute(['b' => $amount_balance, 'id' => $user_id]);
    $message = $sendMail->wireMsg($currency, $row['amount'], $amount_balance, 'Wire Transfer', $fullName, $APP_NAME, 'Declined', $row['refrence_id']);
    $email_message->send_to_both($email, $message, "[WIRE TRANSFER DECLINED] - $APP_NAME");
    toast_alert('success', 'Wire Transfer Declined', 'Decline');
}

if (isset($_POST['hold'])) {
    $stmt = $conn->prepare("UPDATE wire_transfer SET wire_status=:s WHERE refrence_id=:id");
    $stmt->execute(['s' => 2, 'id' => $id]);
    $message = $sendMail->wireMsg($currency, $row['amount'], $row['acct_balance'], 'Wire Transfer', $fullName, $APP_NAME, 'On Hold', $row['refrence_id']);
    $email_message->send_to_both($email, $message, "[WIRE TRANSFER ON HOLD] - $APP_NAME");
    toast_alert('success', 'Wire Transfer Placed on Hold', 'Hold');
}

if (isset($_POST['trans_delete'])) {
    $stmt = $conn->prepare("DELETE FROM wire_transfer WHERE refrence_id=:id");
    $stmt->execute(['id' => $id]);
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
