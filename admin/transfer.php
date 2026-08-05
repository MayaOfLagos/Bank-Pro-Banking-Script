<?php
include_once("./layout/header.php");

if (isset($_POST['transfer'])) {
    $user_id      = $_POST['user_id'];
    $amount       = inputValidation($_POST['amount']);
    $acct_name    = $_POST['acct_name'];
    $bank_name    = $_POST['bank_name'];
    $acct_number  = $_POST['acct_number'];
    $acct_country = $_POST['acct_country'];
    $acct_swift   = $_POST['acct_swift'];
    $acct_routing = $_POST['acct_routing'];
    $acct_type    = $_POST['acct_type'];
    $created_at   = $_POST['created_at'];
    $acct_remarks = $_POST['acct_remarks'];
    $status       = $_POST['status'];

    $checkUser = $conn->prepare("SELECT * FROM users WHERE id =:user_id");
    $checkUser->execute(['user_id' => (int)$user_id]);
    $result = $checkUser->fetch(PDO::FETCH_ASSOC);

    // The result of this SELECT used to be used unchecked: an unknown user_id
    // dereferenced null and the balance comparison ran against NULL.
    // The amount was likewise unvalidated — a negative one passes
    // "$amount > balance" and then computes `balance - (-100)`, crediting the
    // account while booking and emailing an outbound wire.
    // $status is written straight into wire_status and later read back by
    // viewwire-trans.php's status map, so it is pinned to the four values that
    // map actually knows about.
    $amount = is_numeric($amount) ? (float)$amount : -1;
    $validStatuses = ['0', '1', '2', '3'];

    if (!$result) {
        toast_alert('error', 'No such customer — pick a user from the list and try again.', 'Not found');
    } elseif ($amount <= 0) {
        toast_alert('error', 'Amount must be a positive number.', 'Invalid amount');
    } elseif (!in_array((string)$status, $validStatuses, true)) {
        toast_alert('error', 'Pick a valid transfer status.', 'Invalid status');
    } elseif ($amount > (float)$result['acct_balance']) {
        $fullName = $result['firstname'] . " " . $result['lastname'];
        toast_alert('error', 'Insufficient Balance on ' . ucwords($fullName) . ' Account');
    } else {
        $fullName = $result['firstname'] . " " . $result['lastname'];
        $available_balance = (float)$result['acct_balance'] - $amount;

        $reference_id = uniqid();

        // The debit and the wire row are one unit of work. Previously they were
        // two bare statements and the INSERT named a `created_at` column that
        // does not exist — the table's column is `createdAt` — so every
        // admin-originated wire debited the customer and then fatalled, leaving
        // money gone with no wire record and nothing to reconcile against.
        $wireBooked = true;
        $conn->beginTransaction();
        try {
            $addUp = $conn->prepare("UPDATE users SET acct_balance=:available_balance WHERE id=:user_id");
            $addUp->execute(['available_balance' => $available_balance, 'user_id' => $user_id]);

            $stmt = $conn->prepare("INSERT INTO wire_transfer (amount,acct_id,bank_name,acct_name,acct_number,acct_type,acct_country,acct_swift,acct_routing,acct_remarks,createdAt,wire_status,refrence_id) VALUES(:amount,:acct_id,:bank_name,:acct_name,:acct_number,:acct_type,:acct_country,:acct_swift,:acct_routing,:acct_remarks,:created_at,:status,:refrence_id)");
            $stmt->execute([
                'amount'       => $amount,
                'acct_id'      => $user_id,
                'bank_name'    => $bank_name,
                'acct_name'    => $acct_name,
                'acct_number'  => $acct_number,
                'acct_type'    => $acct_type,
                'acct_country' => $acct_country,
                'acct_swift'   => $acct_swift,
                'acct_routing' => $acct_routing,
                'acct_remarks' => $acct_remarks,
                'created_at'   => $created_at,
                'status'       => $status,
                'refrence_id'  => $reference_id
            ]);

            $conn->commit();
        } catch (Throwable $wireError) {
            $conn->rollBack();
            $wireBooked = false;
            error_log('[admin-transfer] wire not booked, balance restored: ' . $wireError->getMessage());
            toast_alert('error', 'The transfer could not be booked. The balance was not changed.', 'Transfer failed');
        }

        // toast_alert() only echoes — it does not stop the request — so the
        // confirmation email, the operator alert and both notifications have to
        // be gated explicitly. Without this a rolled-back transfer would still
        // tell the customer their money had moved.
        if ($wireBooked) {
            $tran_status = ['0' => 'Processing', '1' => 'Complete', '2' => 'On Hold', '3' => 'Cancelled'][$status] ?? 'Cancelled';
            $APP_NAME    = $pageTitle;
            $currency    = currency($result);
            $email       = $result['acct_email'];
            $transfer_type = "Wire Transfer";

            $message = $sendMail->adwireTransfer($currency, $amount, $available_balance, $fullName, $APP_NAME, $tran_status, $bank_name, $acct_name, $acct_number, $acct_country, $created_at, $reference_id, $transfer_type);
            $email_message->send_to_both($email, $message, "[WIRE TRANSACTION] - $APP_NAME");

            // Alert the operators: money left a customer account on an operator's
            // say-so, and a status of Complete skips the approve/hold/decline review.
            $beneficiary = [
                'Bank name'      => $bank_name,
                'Account name'   => $acct_name,
                'Account number' => $acct_number,
                'Country'        => $acct_country
            ];
            admin_notify(
                (new AdminAlert)->adminOriginatedWireMsg(admin_actor_name(), $fullName, $currency, $amount, $beneficiary, $tran_status, $reference_id, admin_actor_ip()),
                'Wire transfer originated by an operator'
            );

            // Beneficiary fields are free-text form input composed into markdown
            // bodies that both inboxes render.
            $wireBankLabel = notify_plain($bank_name, 80);
            $wireAcctLabel = notify_plain($acct_name, 80);
            $wireCustLabel = notify_plain($fullName, 80);

            notify_admin(
                $conn,
                'admin.transfer_executed',
                'Wire transfer originated by an operator',
                admin_actor_name() . ' sent ' . $currency . number_format($amount, 2) . ' from ' . $wireCustLabel
                    . ' to ' . $wireAcctLabel . ' at ' . $wireBankLabel . ' (' . $tran_status . '). Ref ' . $reference_id . '.',
                array(
                    'severity'            => $status === '1' ? 'danger' : 'warning',
                    'link'                => '/admin/viewwire-trans.php',
                    'source'              => 'admin',
                    'created_by_admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
                    'meta'                => array('user_id' => (int)$user_id, 'amount' => $amount, 'reference' => $reference_id, 'status' => $tran_status),
                )
            );

            // Customer-visible outcome: their balance dropped and a wire is on its
            // way in their name. Cancelled transfers still debited nothing they can
            // see differently, so the same message covers all four statuses with
            // the status spelled out.
            notify_user(
                $conn,
                (int)$user_id,
                'transfer.executed',
                'A wire transfer was processed on your account',
                $currency . number_format($amount, 2) . ' to ' . $wireAcctLabel . ' at ' . $wireBankLabel
                    . ' is now ' . $tran_status . '. Reference ' . $reference_id . '. '
                    . 'Your available balance is ' . $currency . number_format($available_balance, 2) . '.',
                array(
                    'severity'            => 'info',
                    'link'                => '/transactions',
                    'source'              => 'admin',
                    'created_by_admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
                    'meta'                => array('reference' => $reference_id, 'amount' => $amount, 'status' => $tran_status),
                )
            );

            toast_alert('success', 'Transfer Successful', 'Approved');
        }
    }
}
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Wire Transfer</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Wire Transfer</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title">New Wire Transfer</h3></div>
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
                                    while ($u = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        echo '<option value="' . (int)$u['id'] . '">' . htmlspecialchars(ucwords($u['firstname'] . ' ' . $u['lastname'])) . ' (' . htmlspecialchars($u['acct_no']) . ')</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Amount</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text"><i class="fas fa-dollar-sign"></i></span></div>
                                    <input type="number" step="0.01" name="amount" class="form-control" placeholder="Amount" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Beneficiary Account Name</label>
                                <input type="text" name="acct_name" class="form-control" placeholder="Beneficiary Account Name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Bank Name</label>
                                <input type="text" name="bank_name" class="form-control" placeholder="Bank Name" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Beneficiary Account No</label>
                                <input type="text" name="acct_number" class="form-control" placeholder="Beneficiary Account No" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Swift Code</label>
                                <input type="text" name="acct_swift" class="form-control" placeholder="Swift Code" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Country</label>
                                <select name="acct_country" class="form-control select2" required>
                                    <option value="">Select Country</option>
                                    <?php include __DIR__ . '/layout/_countries.php'; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Account Type</label>
                                <select name="acct_type" class="form-control" required>
                                    <option value="">Select Account Type</option>
                                    <option value="Savings">Savings</option>
                                    <option value="Current">Current</option>
                                    <option value="Checking">Checking</option>
                                    <option value="Fixed Deposit">Fixed Deposit</option>
                                    <option value="Non Resident">Non Resident</option>
                                    <option value="Online Banking">Online Banking</option>
                                    <option value="Domicilary Account">Domicilary</option>
                                    <option value="Joint Account">Joint Account</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Routing Number</label>
                                <input type="text" name="acct_routing" class="form-control" placeholder="Routing Number" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="created_at" class="form-control" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Narration / Purpose</label>
                                <textarea name="acct_remarks" class="form-control" rows="3" placeholder="Description" required></textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Transfer Status</label>
                                <select name="status" class="form-control" required>
                                    <option value="">Select Status</option>
                                    <option value="0">Processing</option>
                                    <option value="2">Hold</option>
                                    <option value="3">Cancelled</option>
                                    <option value="1">Complete</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" name="transfer" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Submit Transfer</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
