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
    $amount_balance = $row['amount'] + $result['acct_balance'];
    $stmt = $conn->prepare("UPDATE deposit SET crypto_status=:s WHERE refrence_id=:id");
    $stmt->execute(['s' => 1, 'id' => $id]);
    $upd = $conn->prepare("UPDATE users SET acct_balance=:b WHERE id=:id");
    $upd->execute(['b' => $amount_balance, 'id' => $user_id]);
    $mailDeposit('Approved', $amount_balance);
    toast_alert('success', 'Deposit Approved', 'Approved');
}

if (isset($_POST['decline'])) {
    $stmt = $conn->prepare("UPDATE deposit SET crypto_status=:s WHERE refrence_id=:id");
    $stmt->execute(['s' => 3, 'id' => $id]);
    $mailDeposit('Declined', $result['acct_balance']);
    toast_alert('success', 'Deposit Declined', 'Decline');
}

if (isset($_POST['hold'])) {
    $stmt = $conn->prepare("UPDATE deposit SET crypto_status=:s WHERE refrence_id=:id");
    $stmt->execute(['s' => 2, 'id' => $id]);
    $mailDeposit('On Hold', $result['acct_balance']);
    toast_alert('success', 'Deposit Placed on Hold', 'Hold');
}

if (isset($_POST['trans_delete'])) {
    $stmt = $conn->prepare("DELETE FROM deposit WHERE refrence_id=:id");
    $stmt->execute(['id' => $id]);
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
