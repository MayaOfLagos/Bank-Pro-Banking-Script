<?php
include_once("./layout/header.php");

if (isset($_POST['credit'])) {
    $user_id      = $_POST['user_id'];
    $sender_name  = $_POST['sender_name'];
    $amount       = $_POST['amount'];
    $description  = $_POST['description'];
    $created_at   = $_POST['created_at'];
    $time_created = $_POST['time_created'];

    $trans_type   = 1;
    $trans_status = 1;

    $checkUser = $conn->prepare("SELECT * FROM users WHERE id =:user_id");
    $checkUser->execute(['user_id' => $user_id]);
    $result = $checkUser->fetch(PDO::FETCH_ASSOC);

    $available_balance = $amount + $result['acct_balance'];

    $addUp = $conn->prepare("UPDATE users SET acct_balance=:available_balance WHERE id=:user_id");
    $addUp->execute(['available_balance' => $available_balance, 'user_id' => $user_id]);

    $trans_id = uniqid();
    $stmt = $conn->prepare("INSERT INTO transactions (user_id,sender_name,amount,description,created_at,time_created,trans_type,refrence_id,trans_status) VALUES(:user_id,:sender_name,:amount,:description,:created_at,:time_created,:trans_type,:refrence_id,:trans_status)");
    $stmt->execute([
        'user_id'      => $user_id,
        'sender_name'  => $sender_name,
        'amount'       => $amount,
        'description'  => $description,
        'created_at'   => $created_at,
        'time_created' => $time_created,
        'trans_type'   => $trans_type,
        'refrence_id'  => $trans_id,
        'trans_status' => $trans_status
    ]);

    $APP_NAME = $pageTitle;
    $currency = currency($result);
    $email    = $result['acct_email'];
    $fullName = $result['firstname'] . " " . $result['lastname'];

    $message = $sendMail->FundUsers($fullName, $currency, $sender_name, $amount, $available_balance, $description, $created_at, $trans_type, $APP_NAME);
    $email_message->send_to_both($email, $message, "[CREDIT NOTIFICATION] - $APP_NAME");

    // Alert the operators: an admin wrote a customer balance by hand, which no
    // customer action can produce, so it must always leave an audit trail.
    admin_notify(
        (new AdminAlert)->adminManualBalanceAdjustmentMsg(admin_actor_name(), 'credit', $fullName, $currency, $amount, $result['acct_balance'], $available_balance, $trans_id, $description, admin_actor_ip()),
        'Customer account credited manually'
    );

    toast_alert('success', 'Account Funded Successfully', 'Approved');
} elseif (isset($_POST['debit'])) {
    $user_id      = $_POST['user_id'];
    $sender_name  = $_POST['sender_name'];
    $amount       = $_POST['amount'];
    $description  = $_POST['description'];
    $created_at   = $_POST['created_at'];
    $time_created = $_POST['time_created'];

    $trans_type   = 2;
    $trans_status = 1;

    $checkUser = $conn->prepare("SELECT * FROM users WHERE id =:user_id");
    $checkUser->execute(['user_id' => $user_id]);
    $result = $checkUser->fetch(PDO::FETCH_ASSOC);

    if ($amount > $result['acct_balance']) {
        toast_alert('error', 'Insufficient Balance');
    } else {
        $available_balance = $result['acct_balance'] - $amount;

        $addUp = $conn->prepare("UPDATE users SET acct_balance=:available_balance WHERE id=:user_id");
        $addUp->execute(['available_balance' => $available_balance, 'user_id' => $user_id]);

        $trans_id = uniqid();
        $stmt = $conn->prepare("INSERT INTO transactions (user_id,sender_name,amount,description,created_at,time_created,trans_type,refrence_id,trans_status) VALUES(:user_id,:sender_name,:amount,:description,:created_at,:time_created,:trans_type,:refrence_id,:trans_status)");
        $stmt->execute([
            'user_id'      => $user_id,
            'sender_name'  => $sender_name,
            'amount'       => $amount,
            'description'  => $description,
            'created_at'   => $created_at,
            'time_created' => $time_created,
            'trans_type'   => $trans_type,
            'refrence_id'  => $trans_id,
            'trans_status' => $trans_status
        ]);

        $APP_NAME = $pageTitle;
        $currency = currency($result);
        $email    = $result['acct_email'];
        $fullName = $result['firstname'] . " " . $result['lastname'];

        $message = $sendMail->FundUsers($fullName, $currency, $sender_name, $amount, $available_balance, $description, $created_at, $trans_type, $APP_NAME);
        $email_message->send_to_both($email, $message, "[DEBIT NOTIFICATION] - $APP_NAME");

        // Alert the operators: an admin wrote a customer balance by hand, which no
        // customer action can produce, so it must always leave an audit trail.
        admin_notify(
            (new AdminAlert)->adminManualBalanceAdjustmentMsg(admin_actor_name(), 'debit', $fullName, $currency, $amount, $result['acct_balance'], $available_balance, $trans_id, $description, admin_actor_ip()),
            'Customer account debited manually'
        );

        toast_alert('success', 'Account Debited Successfully', 'Approved');
    }
}
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Credit / Debit User</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Credit / Debit</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">Adjust User Balance</h3></div>
            <form method="post">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>User</label>
                                <select name="user_id" class="form-control select2" required>
                                    <option value="">Select User</option>
                                    <?php
                                    $stmt = $conn->prepare("SELECT * FROM users ORDER BY id ASC");
                                    $stmt->execute();
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $fullName = ucwords($row['firstname'] . " " . $row['lastname']);
                                        echo '<option value="' . (int)$row['id'] . '">' . htmlspecialchars($fullName) . ' (' . htmlspecialchars($row['acct_no']) . ')</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>From (Sender)</label>
                                <input type="text" name="sender_name" class="form-control" placeholder="Sender" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" class="form-control" placeholder="Description" required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="created_at" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Time</label>
                                <input type="time" name="time_created" class="form-control" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6"><button type="submit" name="credit" class="btn btn-success btn-block"><i class="fas fa-arrow-up"></i> Credit User</button></div>
                        <div class="col-md-6"><button type="submit" name="debit" class="btn btn-danger btn-block"><i class="fas fa-arrow-down"></i> Debit User</button></div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
