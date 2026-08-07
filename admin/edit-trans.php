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
    // Keep the pre-update values of the fields this form rewrites, so the alert
    // below can show an old -> new diff ($row is the row as it stands now).
    $before = [
        'amount'       => $row['amount'],
        'sender_name'  => $row['sender_name'],
        'description'  => $row['description'],
        'created_at'   => $row['created_at'],
        'time_created' => $row['time_created'],
    ];

    $stmt = $conn->prepare("UPDATE transactions SET amount=:amount, sender_name=:sender_name, description=:description, created_at=:created_at, time_created=:time_created WHERE trans_id=:id");
    $stmt->execute([
        'amount'       => $_POST['amount'],
        'sender_name'  => $_POST['sender_name'],
        'description'  => $_POST['description'],
        'created_at'   => $_POST['created_at'],
        'time_created' => $_POST['time_created'],
        'id'           => $id,
    ]);

    // Ledger integrity: a posted transaction was rewritten after the fact, so
    // the operators are told which fields moved, by whom and from where.
    // Compare on a normalised form, not the raw values. MySQL returns a TIME
    // column as "14:30:00" while <input type="time"> submits "14:30", and a
    // DECIMAL as "500.00" against a posted "500" — so a straight string compare
    // reported those two fields as changed on every save. That would fire an
    // error-severity "a settled transaction was rewritten" alert on edits that
    // touched neither, which is exactly how operators learn to ignore an alert.
    $normalise = static function (string $field, $value): string {
        $value = (string)$value;
        if ($field === 'time_created') {
            return substr($value, 0, 5);
        }
        if ($field === 'amount' && is_numeric($value)) {
            return (string)(float)$value;
        }
        return trim($value);
    };

    $changes = [];
    foreach ($before as $field => $old) {
        $new = $_POST[$field] ?? '';
        if ($normalise($field, $old) !== $normalise($field, $new)) {
            $changes[$field] = ['from' => $old, 'to' => $new];
        }
    }
    $reference = !empty($row['refrence_id']) ? (string)$row['refrence_id'] : (string)$row['trans_id'];
    $customer  = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
    admin_notify(
        (new AdminAlert)->adminTransactionEditedMsg(admin_actor_name(), $reference, $changes, $customer, admin_actor_ip()),
        'Posted transaction edited'
    );

    // Operator feed only — no notify_user(). Rewriting a settled row is a
    // back-office ledger correction; the customer's balance is untouched by
    // this form, so there is no customer-visible outcome to announce.
    notify_admin(
        $conn,
        'admin.transaction_edited',
        'Posted transaction edited',
        admin_actor_name() . ' edited transaction ' . $reference
            . ($customer !== '' ? ' on ' . $customer . "'s account" : '')
            . '. Fields changed: ' . ($changes ? implode(', ', array_keys($changes)) : 'none') . '.',
        array(
            'severity'            => $changes ? 'warning' : 'info',
            'link'                => '/admin/edit-trans.php?id=' . $id,
            'source'              => 'admin',
            'created_by_admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
            'meta'                => array('reference' => $reference, 'changes' => $changes),
        )
    );

    // toast_flash, not toast_alert: the header() call below tears down the
    // current render before AdminLTE has a chance to draw the toast, so the
    // message needs to survive the redirect via the session queue.
    toast_flash('success', 'Transaction updated successfully', 'Approved');
    header('Location:' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
    exit;
}

if (isset($_POST['trans_delete'])) {
    // The delete is permanent and un-reversed, so snapshot the row first — the
    // alert becomes the only remaining record of what was destroyed.
    $snapshot = [
        'Amount'      => (string)$row['amount'],
        // (string) cast is load-bearing: trans_type is an INT column and
        // include/config.php sets PDO::ATTR_EMULATE_PREPARES => false, so PDO
        // hands back a real int and `=== '1'` never matches. Without the cast
        // every deleted credit would be recorded as "Debit" in the one message
        // that is the only surviving record of the destroyed row.
        'Type'        => ((string)$row['trans_type'] === '1') ? 'Credit' : 'Debit',
        'Sender'      => (string)$row['sender_name'],
        'Description' => (string)$row['description'],
        'Date'        => trim($row['created_at'] . ' ' . $row['time_created']),
    ];
    $reference = !empty($row['refrence_id']) ? (string)$row['refrence_id'] : (string)$row['trans_id'];
    $customer  = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));

    $stmt = $conn->prepare("DELETE FROM transactions WHERE trans_id=:id");
    $stmt->execute(['id' => $id]);

    // Ledger integrity: a transaction row was hard-deleted from the ledger.
    admin_notify(
        (new AdminAlert)->adminTransactionDeletedMsg(admin_actor_name(), $reference, $snapshot, $customer, admin_actor_ip()),
        'Transaction deleted'
    );

    // Operator feed only. Same reasoning as the edit branch: this is ledger
    // maintenance, and telling a customer "a transaction disappeared" without
    // a balance change behind it would generate support calls, not clarity.
    notify_admin(
        $conn,
        'admin.transaction_deleted',
        'Transaction deleted from the ledger',
        admin_actor_name() . ' permanently deleted transaction ' . $reference
            . ($customer !== '' ? ' on ' . $customer . "'s account" : '')
            . ' (' . $snapshot['Type'] . ' ' . $snapshot['Amount'] . ').',
        array(
            'severity'            => 'danger',
            'link'                => '/admin/credit_debit_trans.php',
            'source'              => 'admin',
            'created_by_admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
            'meta'                => array('reference' => $reference, 'snapshot' => $snapshot),
        )
    );

    toast_flash('success', 'Transaction deleted from the ledger.', 'Removed');
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
