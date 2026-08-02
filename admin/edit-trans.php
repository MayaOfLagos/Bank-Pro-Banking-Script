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

if (isset($_POST['update_trans'])) {
    $stmt = $conn->prepare("UPDATE transactions SET amount=:amount, sender_name=:sender_name, description=:description, created_at=:created_at, time_created=:time_created WHERE trans_id=:id");
    $stmt->execute([
        'amount'       => $_POST['amount'],
        'sender_name'  => $_POST['sender_name'],
        'description'  => $_POST['description'],
        'created_at'   => $_POST['created_at'],
        'time_created' => $_POST['time_created'],
        'id'           => $id,
    ]);
    toast_alert('success', 'Transaction updated successfully', 'Approved');
    header('Location:' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
    exit;
}

if (isset($_POST['trans_delete'])) {
    $stmt = $conn->prepare("DELETE FROM transactions WHERE trans_id=:id");
    $stmt->execute(['id' => $id]);
    header('Location:./credit_debit_trans.php');
    exit;
}
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Edit Transaction</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="./credit_debit_trans.php">Transactions</a></li>
                    <li class="breadcrumb-item active">Edit</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Edit Credit / Debit Transaction</h3></div>
            <form method="post">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Amount</label><input type="number" step="0.01" name="amount" class="form-control" value="<?= htmlspecialchars($row['amount']) ?>" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Sender Name</label><input type="text" name="sender_name" class="form-control" value="<?= htmlspecialchars($row['sender_name']) ?>" required></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Description</label><input type="text" name="description" class="form-control" value="<?= htmlspecialchars($row['description']) ?>" required></div></div>
                        <div class="col-md-6"><div class="form-group"><label>Date</label><input type="date" name="created_at" class="form-control" value="<?= htmlspecialchars($row['created_at']) ?>" required></div></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6"><div class="form-group"><label>Time</label><input type="time" name="time_created" class="form-control" value="<?= htmlspecialchars($row['time_created']) ?>" required></div></div>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary" name="update_trans"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
            <form method="post" onsubmit="return confirm('Delete this transaction?')">
                <div class="card-footer">
                    <button class="btn btn-danger" name="trans_delete"><i class="fas fa-trash"></i> Delete Transaction</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
