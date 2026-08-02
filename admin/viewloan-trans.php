<?php
include_once("./layout/header.php");

$id   = $_GET['id'] ?? '';
$stmt = $conn->prepare("SELECT * FROM loan LEFT JOIN users ON loan.acct_id = users.id WHERE loan_reference_id =:id");
$stmt->execute(['id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger">Loan not found.</div></div></section>';
    include_once("./layout/footer.php");
    exit;
}

$email    = $row['acct_email'];
$currency = currency($row);
$acct_no  = $row['acct_no'];
$fullName = $row['firstname'] . " " . $row['lastname'];
$APP_NAME = $pageTitle;

$statusMap = [
    '0' => '<span class="badge badge-secondary">Processing</span>',
    '1' => '<span class="badge badge-success">Approved</span>',
    '2' => '<span class="badge badge-warning">Hold</span>',
    '3' => '<span class="badge badge-danger">Declined</span>',
];

if (isset($_POST['loan_submit'])) {
    $loan_message = $_POST['loan_message'];
    $loan_status  = (int)$_POST['loan_status'];

    $stmt = $conn->prepare("UPDATE loan SET loan_status=:s, loan_message=:m WHERE loan_reference_id=:id");
    $stmt->execute(['s' => $loan_status, 'm' => $loan_message, 'id' => $id]);

    $available_loan = $row['loan_balance'];
    if ($loan_status === 1) {
        $available_loan = $row['loan_balance'] + $row['amount'];
        $upd = $conn->prepare("UPDATE users SET loan_balance=:b WHERE acct_no=:id");
        $upd->execute(['b' => $available_loan, 'id' => $acct_no]);
    }

    $statusLabel = ['1' => 'Approved', '2' => 'Hold', '3' => 'Declined'][$loan_status] ?? 'Updated';
    $message = $sendMail->loanMsg($currency, $row['amount'], $row['acct_balance'], $available_loan, $APP_NAME, $statusLabel, $loan_message);
    $email_message->send_to_both($email, $message, "[LOAN " . strtoupper($statusLabel) . "] - $APP_NAME");
    toast_alert('success', "Loan $statusLabel Successfully", $statusLabel);

    $stmt = $conn->prepare("SELECT * FROM loan LEFT JOIN users ON loan.acct_id = users.id WHERE loan_reference_id =:id");
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
}

$tran_status = $statusMap[(string)$row['loan_status']] ?? '<span class="badge badge-light">Unknown</span>';
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Loan Request Details</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="./loan-trans.php">Loans</a></li>
                    <li class="breadcrumb-item active">View</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header"><h3 class="card-title">Loan Request</h3></div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tbody>
                        <tr><th>Name</th><td><?= ucwords($fullName) ?></td></tr>
                        <tr><th>Amount Requested</th><td><?= $currency . htmlspecialchars($row['amount']) ?></td></tr>
                        <tr><th>Loan Remarks</th><td><?= htmlspecialchars($row['loan_remarks']) ?></td></tr>
                        <tr><th>Created At</th><td><?= htmlspecialchars($row['created_at']) ?></td></tr>
                        <tr><th>Account No</th><td><?= htmlspecialchars($row['acct_no']) ?></td></tr>
                        <tr><th>Account Balance</th><td><?= $currency . htmlspecialchars($row['acct_balance']) ?></td></tr>
                        <tr><th>Loan Balance</th><td><?= $currency . htmlspecialchars($row['loan_balance']) ?></td></tr>
                        <tr><th>Status</th><td><?= $tran_status ?></td></tr>
                    </tbody>
                </table>
            </div>
            <form method="post">
                <div class="card-body">
                    <div class="form-group">
                        <label class="text-info">Suggested Message</label>
                        <textarea class="form-control text-muted" rows="2" readonly>Dear <?= htmlspecialchars($fullName) ?>, your loan of <?= $currency . htmlspecialchars($row['amount']) ?> has been processed.</textarea>
                    </div>
                    <div class="form-group">
                        <label>Loan Message</label>
                        <textarea class="form-control" rows="3" name="loan_message" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <select class="form-control" name="loan_status" required>
                                <option value="">Select Action</option>
                                <option value="1">Approve</option>
                                <option value="2">Hold</option>
                                <option value="3">Decline</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-primary btn-block" name="loan_submit">Submit</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
