<?php
include_once("./layout/header.php");

$id = (int)($_GET['id'] ?? 0);
$data = $conn->prepare("SELECT * FROM users WHERE id =:id");
$data->execute(['id' => $id]);
$row = $data->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo '<section class="content"><div class="container-fluid"><div class="alert alert-danger">User not found.</div></div></section>';
    include_once("./layout/footer.php");
    exit;
}

$billing  = $row['billing_code'] == '1' ? 'ACTIVE' : 'DEACTIVATE';
$transfer = $row['transfer'] == '1' ? 'ACTIVE' : 'DEACTIVATE';

if (isset($_POST['upload_picture']) && isset($_FILES['image']) && $_FILES['image']['name']) {
    $file = $_FILES['image'];
    $name = $file['name'];
    $folder = "../assets/profile/";
    $n = $row['acct_no'] . $name;
    $destination = $folder . $n;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $stmt = $conn->prepare("UPDATE users SET image=:image WHERE id =:acct_id");
        $stmt->execute(['image' => $n, 'acct_id' => $id]);
        toast_alert('success', 'Image Uploaded Successfully', 'Thanks!');
        header("Location:./users.php"); die;
    }
}

if (isset($_POST['profile_save'])) {
    $limiBalance = $_POST['acct_limit'] + $row['limit_remain'];
    $limit       = $row['acct_limit'] + $_POST['acct_limit'];
    $stmt = $conn->prepare("UPDATE users SET acct_no=:acct_no, acct_type=:acct_type,acct_email=:acct_email,acct_dob=:acct_dob,acct_occupation=:acct_occupation,acct_phone=:acct_phone,acct_gender=:acct_gender,marital_status=:marital_status,acct_limit=:acct_limit,acct_cot=:acct_cot,acct_tax=:acct_tax,acct_imf=:acct_imf,acct_balance=:acct_balance,limit_remain=:limit_remain WHERE id=:id");
    $stmt->execute([
        'acct_no'         => $_POST['acct_no'],
        'acct_type'       => $_POST['acct_type'],
        'acct_email'      => $_POST['acct_email'],
        'acct_dob'        => $_POST['acct_dob'],
        'acct_occupation' => $_POST['acct_occupation'],
        'acct_phone'      => $_POST['acct_phone'],
        'acct_gender'     => $_POST['acct_gender'],
        'acct_tax'        => $_POST['acct_tax'],
        'acct_cot'        => $_POST['acct_cot'],
        'acct_imf'        => $_POST['acct_imf'],
        'marital_status'  => $_POST['marital_status'],
        'acct_limit'      => $limit,
        'acct_balance'    => $_POST['acct_balance'],
        'limit_remain'    => $limiBalance,
        'id'              => $id
    ]);
    if (function_exists('audit_log')) {
        // Diff the tracked fields — most user-profile edits only touch a
        // subset, no point spamming the log with unchanged rows.
        $tracked = ['acct_no','acct_type','acct_email','acct_dob','acct_occupation','acct_phone','acct_gender','acct_tax','acct_cot','acct_imf','marital_status','acct_balance'];
        $changed = [];
        foreach ($tracked as $f) {
            $old = isset($row[$f]) ? (string)$row[$f] : '';
            $new = isset($_POST[$f]) ? (string)$_POST[$f] : '';
            if ($old !== $new) {
                $changed[$f] = ['old' => $old, 'new' => $new];
            }
        }
        audit_log('user.profile_updated', 'user', (string)$id, [
            'user_email' => $row['acct_email'] ?? null,
            'changed'    => $changed,
        ]);
    }
    header("Location:./users.php"); die;
}

if (isset($_POST['status_delete'])) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id=:id");
    $stmt->execute(['id' => $id]);
    if (function_exists('audit_log')) {
        audit_log('user.deleted', 'user', (string)$id, [
            'user_email' => $row['acct_email'] ?? null,
            'acct_no'    => $row['acct_no'] ?? null,
            'name'       => trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? '')),
        ]);
    }
    header("Location:./users.php"); die;
}

if (isset($_POST['change_pin'])) {
    $current_pin = inputValidation($_POST['current_pin']);
    $new_pin     = inputValidation($_POST['new_pin']);
    $confirm_pin = inputValidation($_POST['confirm_pin']);
    if ($current_pin !== $row['acct_pin']) {
        toast_alert('error', 'Invalid Old Pin');
    } elseif ($new_pin !== $confirm_pin) {
        toast_alert('error', 'Confirm Pin not Matched');
    } elseif ($new_pin == $row['acct_pin']) {
        toast_alert('error', 'New Pin matches Old Pin');
    } else {
        $stmt = $conn->prepare("UPDATE users SET acct_pin=:acct_pin WHERE id =:acct_id");
        $stmt->execute(['acct_pin' => $new_pin, 'acct_id' => $id]);
        if (function_exists('audit_log')) {
            // Do NOT log the PIN itself — just the fact it changed.
            audit_log('user.pin_changed', 'user', (string)$id, [
                'user_email' => $row['acct_email'] ?? null,
            ]);
        }
        toast_alert('success', 'PIN Changed Successfully', 'Approved');
    }
}

if (isset($_POST['change_password'])) {
    $old_password     = inputValidation($_POST['old_password']);
    $new_password     = inputValidation($_POST['new_password']);
    $confirm_password = inputValidation($_POST['confirm_password']);
    if (!password_verify($old_password, $row['acct_password'])) {
        toast_alert('error', 'Incorrect Old Password');
    } elseif ($new_password !== $confirm_password) {
        toast_alert('error', 'Confirm Password not matched');
    } elseif ($new_password == $old_password) {
        toast_alert('error', 'New Password matches Old Password');
    } else {
        $stmt = $conn->prepare("UPDATE users SET acct_password=:acct_password WHERE id =:acct_id");
        $stmt->execute(['acct_password' => password_hash((string)$new_password, PASSWORD_BCRYPT), 'acct_id' => $id]);
        if (function_exists('audit_log')) {
            // Never log the actual password.
            audit_log('user.password_changed', 'user', (string)$id, [
                'user_email' => $row['acct_email'] ?? null,
            ]);
        }
        toast_alert('success', 'Password Changed Successfully', 'Approved');
    }
}

if (isset($_POST['status_submit'])) {
    $stmt = $conn->prepare("UPDATE users SET acct_status=:acct_status WHERE id =:acct_id");
    $stmt->execute(['acct_status' => $_POST['acct_status'], 'acct_id' => $id]);
    if (function_exists('audit_log')) {
        audit_log('user.status_changed', 'user', (string)$id, [
            'user_email' => $row['acct_email'] ?? null,
            'old'        => $row['acct_status'] ?? null,
            'new'        => $_POST['acct_status'] ?? null,
        ]);
    }
    header("Location:./users.php"); die;
}

if (isset($_POST['billing_code'])) {
    $stmt = $conn->prepare("UPDATE users SET billing_code=:billing_code WHERE id=:acct_id");
    $stmt->execute(['billing_code' => $_POST['billing_type'], 'acct_id' => $id]);
    if (function_exists('audit_log')) {
        audit_log('user.billing_toggled', 'user', (string)$id, [
            'user_email' => $row['acct_email'] ?? null,
            'old'        => $row['billing_code'] ?? null,
            'new'        => $_POST['billing_type'] ?? null,
        ]);
    }
    header("Location:./users.php"); die;
}

if (isset($_POST['transfer'])) {
    $stmt = $conn->prepare("UPDATE users SET transfer=:transfer WHERE id=:acct_id");
    $stmt->execute(['transfer' => $_POST['transfer_type'], 'acct_id' => $id]);
    if (function_exists('audit_log')) {
        audit_log('user.transfer_toggled', 'user', (string)$id, [
            'user_email' => $row['acct_email'] ?? null,
            'old'        => $row['transfer'] ?? null,
            'new'        => $_POST['transfer_type'] ?? null,
        ]);
    }
    header("Location:./users.php"); die;
}

// Feature-access save. Only responds to authenticated admin POSTs. The four
// toggle checkboxes are absent from $_POST when unchecked (browser default)
// so we normalise every value to 1/0 before hitting a single prepared
// statement — no partial updates and no way to smuggle non-boolean values.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feature_access_save'])) {
    $flags = [
        'transfer'         => !empty($_POST['feature_transfer']) ? 1 : 0,
        'can_deposit'      => !empty($_POST['feature_can_deposit']) ? 1 : 0,
        'can_withdraw'     => !empty($_POST['feature_can_withdraw']) ? 1 : 0,
        'can_request_card' => !empty($_POST['feature_can_request_card']) ? 1 : 0,
    ];
    $stmt = $conn->prepare(
        "UPDATE users SET transfer=:transfer, can_deposit=:can_deposit, " .
        "can_withdraw=:can_withdraw, can_request_card=:can_request_card WHERE id=:acct_id"
    );
    $stmt->execute($flags + ['acct_id' => $id]);
    header("Location:./view_users.php?id=" . $id . "&feature_saved=1");
    die;
}

// Refetch the row after mutating the feature toggles so the rendered
// checkboxes reflect the just-saved state without a manual reload.
$featureSaved = isset($_GET['feature_saved']) && $_GET['feature_saved'] === '1';
$featureFlags = [
    'transfer'         => (int)($row['transfer'] ?? 1) === 1,
    'can_deposit'      => (int)($row['can_deposit'] ?? 1) === 1,
    'can_withdraw'     => (int)($row['can_withdraw'] ?? 1) === 1,
    'can_request_card' => (int)($row['can_request_card'] ?? 1) === 1,
];

// Force-logout: stamps users.sessions_invalidated_at = NOW(). The next
// authenticated API call this user makes will fail the check in
// api/user/_bootstrap.php and tear down the session with a 401.
if (isset($_POST['force_logout_user'])) {
    $stmt = $conn->prepare("UPDATE users SET sessions_invalidated_at = NOW() WHERE id = :id");
    $stmt->execute(['id' => $id]);
    toast_alert('success', 'User signed out of all active sessions. Their next request will require re-authentication.', 'Sessions revoked');
    // Re-read so the on-page timestamp reflects the new value below.
    $refresh = $conn->prepare("SELECT sessions_invalidated_at FROM users WHERE id = :id LIMIT 1");
    $refresh->execute(['id' => $id]);
    $refreshed = $refresh->fetch(PDO::FETCH_ASSOC);
    if ($refreshed && isset($refreshed['sessions_invalidated_at'])) {
        $row['sessions_invalidated_at'] = $refreshed['sessions_invalidated_at'];
    }
}
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>View / Edit User</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="./users.php">Users</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <div class="card card-primary card-outline">
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <img class="profile-user-img img-fluid img-circle" src="../assets/profile/<?= htmlspecialchars($row['image']) ?>" alt="user">
                        </div>
                        <h3 class="profile-username text-center"><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></h3>
                        <p class="text-muted text-center"><?= htmlspecialchars($row['acct_type']) ?></p>
                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item"><b>Account No</b> <span class="float-right"><?= htmlspecialchars($row['acct_no']) ?></span></li>
                            <li class="list-group-item"><b>Balance</b> <span class="float-right"><?= currency($row) . number_format((float)$row['acct_balance'], 2) ?></span></li>
                            <li class="list-group-item"><b>Status</b> <span class="float-right"><?= htmlspecialchars($row['acct_status']) ?></span></li>
                        </ul>
                        <form method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <label>Change Picture</label>
                                <input type="file" name="image" class="form-control-file" accept="image/*">
                            </div>
                            <button class="btn btn-primary btn-block" name="upload_picture">Upload</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-9">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">General Information</h3></div>
                    <form method="post">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>Account No</label><input type="text" name="acct_no" class="form-control" value="<?= htmlspecialchars($row['acct_no']) ?>"></div></div>
                                <div class="col-md-6">
                                    <div class="form-group"><label>Account Type</label>
                                        <select name="acct_type" class="form-control">
                                            <?php foreach (['Savings','Current','Checking','Fixed Deposit','Non Resident','Online Banking','Domicilary Account','Joint Account'] as $t): ?>
                                                <option value="<?= $t ?>" <?= $row['acct_type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>Email</label><input type="email" name="acct_email" class="form-control" value="<?= htmlspecialchars($row['acct_email']) ?>"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Date of Birth</label><input type="date" name="acct_dob" class="form-control" value="<?= htmlspecialchars($row['acct_dob']) ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>Occupation</label><input type="text" name="acct_occupation" class="form-control" value="<?= htmlspecialchars($row['acct_occupation']) ?>"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Phone</label><input type="text" name="acct_phone" class="form-control" value="<?= htmlspecialchars($row['acct_phone']) ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-4"><div class="form-group"><label>COT</label><input type="text" name="acct_cot" class="form-control" value="<?= htmlspecialchars($row['acct_cot']) ?>"></div></div>
                                <div class="col-md-4"><div class="form-group"><label>IMF</label><input type="text" name="acct_imf" class="form-control" value="<?= htmlspecialchars($row['acct_imf']) ?>"></div></div>
                                <div class="col-md-4"><div class="form-group"><label>TAX</label><input type="text" name="acct_tax" class="form-control" value="<?= htmlspecialchars($row['acct_tax']) ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group"><label>Gender</label>
                                        <select name="acct_gender" class="form-control">
                                            <option value="male" <?= $row['acct_gender'] === 'male' ? 'selected' : '' ?>>Male</option>
                                            <option value="female" <?= $row['acct_gender'] === 'female' ? 'selected' : '' ?>>Female</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6"><div class="form-group"><label>Marital Status</label><input type="text" name="marital_status" class="form-control" value="<?= htmlspecialchars($row['marital_status']) ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>Account Balance</label><input type="text" name="acct_balance" class="form-control" value="<?= htmlspecialchars($row['acct_balance']) ?>"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Account Limit</label><input type="text" name="acct_limit" class="form-control" value="<?= htmlspecialchars($row['acct_limit']) ?>"></div></div>
                            </div>
                        </div>
                        <div class="card-footer"><button class="btn btn-primary" name="profile_save">Save</button></div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Status &amp; Codes</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <form method="post">
                                    <p>Status: <span class="badge badge-info"><?= ucwords($row['acct_status']) ?></span></p>
                                    <div class="form-group">
                                        <select name="acct_status" class="form-control" required>
                                            <option value="">Select</option>
                                            <option value="active">Active</option>
                                            <option value="hold">Hold</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary btn-block" name="status_submit">Update Status</button>
                                </form>
                            </div>
                            <div class="col-md-4">
                                <form method="post">
                                    <p>Billing Code: <span class="badge badge-info"><?= $billing ?></span></p>
                                    <div class="form-group">
                                        <select name="billing_type" class="form-control" required>
                                            <option value="">Select</option>
                                            <option value="1">Active</option>
                                            <option value="0">Deactivate</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary btn-block" name="billing_code">Update Billing</button>
                                </form>
                            </div>
                            <div class="col-md-4">
                                <form method="post">
                                    <p>Transfer: <span class="badge badge-info"><?= $transfer ?></span></p>
                                    <div class="form-group">
                                        <select name="transfer_type" class="form-control" required>
                                            <option value="">Select</option>
                                            <option value="1">Active</option>
                                            <option value="0">Deactivate</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary btn-block" name="transfer">Update Transfer</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Feature access</h3>
                    </div>
                    <form method="post">
                        <div class="card-body">
                            <?php if ($featureSaved): ?>
                                <div class="alert alert-success">Feature toggles updated.</div>
                            <?php endif; ?>
                            <p class="text-muted mb-3">Uncheck a box to block that action for this customer. Server endpoints honour these flags immediately.</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="custom-control custom-switch mb-2">
                                        <input type="checkbox" class="custom-control-input" id="feature_transfer" name="feature_transfer" value="1" <?= $featureFlags['transfer'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="feature_transfer">Wire / domestic transfers</label>
                                    </div>
                                    <div class="custom-control custom-switch mb-2">
                                        <input type="checkbox" class="custom-control-input" id="feature_can_deposit" name="feature_can_deposit" value="1" <?= $featureFlags['can_deposit'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="feature_can_deposit">Deposits (crypto / bank)</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="custom-control custom-switch mb-2">
                                        <input type="checkbox" class="custom-control-input" id="feature_can_withdraw" name="feature_can_withdraw" value="1" <?= $featureFlags['can_withdraw'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="feature_can_withdraw">Withdrawals</label>
                                    </div>
                                    <div class="custom-control custom-switch mb-2">
                                        <input type="checkbox" class="custom-control-input" id="feature_can_request_card" name="feature_can_request_card" value="1" <?= $featureFlags['can_request_card'] ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="feature_can_request_card">Card requests</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-primary" name="feature_access_save" value="1">Save feature access</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="card-header"><h3 class="card-title">Session control</h3></div>
                    <div class="card-body">
                        <?php
                            $sessionsInvalidatedAt = isset($row['sessions_invalidated_at']) ? (string)$row['sessions_invalidated_at'] : '';
                        ?>
                        <p class="mb-2">
                            Force this user out of every device they're currently signed in on. The next
                            request their browser makes will be rejected with a 401 and they'll be sent
                            back to the sign-in screen.
                        </p>
                        <?php if ($sessionsInvalidatedAt !== ''): ?>
                            <p class="text-muted small mb-3">
                                Last forced sign-out:
                                <strong><?= htmlspecialchars($sessionsInvalidatedAt) ?></strong>
                            </p>
                        <?php else: ?>
                            <p class="text-muted small mb-3">No forced sign-outs on record for this user.</p>
                        <?php endif; ?>
                        <form method="post" onsubmit="return confirm('Sign this user out of every active session? Their next request will require sign-in.')">
                            <button type="submit" name="force_logout_user" class="btn btn-warning">
                                <i class="fas fa-sign-out-alt"></i>
                                Sign this user out of all sessions
                            </button>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Change Password</h3></div>
                            <form method="post">
                                <div class="card-body">
                                    <div class="form-group"><label>Old Password</label><input type="password" name="old_password" class="form-control"></div>
                                    <div class="form-group"><label>New Password</label><input type="password" name="new_password" class="form-control"></div>
                                    <div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" class="form-control"></div>
                                </div>
                                <div class="card-footer"><button class="btn btn-primary" name="change_password">Change Password</button></div>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Change PIN <small class="float-right">Current: <?= htmlspecialchars($row['acct_pin']) ?></small></h3></div>
                            <form method="post" autocomplete="off">
                                <div class="card-body">
                                    <div class="form-group"><label>Current PIN</label><input type="password" name="current_pin" class="form-control"></div>
                                    <div class="form-group"><label>New PIN</label><input type="password" name="new_pin" class="form-control"></div>
                                    <div class="form-group"><label>Confirm PIN</label><input type="password" name="confirm_pin" class="form-control"></div>
                                </div>
                                <div class="card-footer">
                                    <button class="btn btn-primary" name="change_pin">Change PIN</button>
                                </div>
                            </form>
                            <form method="post" onsubmit="return confirm('Delete this user permanently?')">
                                <div class="card-footer">
                                    <button class="btn btn-danger btn-block" name="status_delete">Delete User</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
