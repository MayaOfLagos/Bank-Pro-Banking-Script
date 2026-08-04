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
    header("Location:./users.php"); die;
}

if (isset($_POST['status_delete'])) {
    $stmt = $conn->prepare("DELETE FROM users WHERE id=:id");
    $stmt->execute(['id' => $id]);
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
        toast_alert('success', 'Password Changed Successfully', 'Approved');
    }
}

if (isset($_POST['status_submit'])) {
    // Whitelist status values so a hand-crafted POST can't stash arbitrary
    // strings in acct_status (login gating is driven by exact-string
    // comparison in _bootstrap.php — an unknown value would silently lock
    // the user out with the fallback message).
    $allowedStatus = ['active', 'hold', 'pending', 'suspended', 'blocked', 'inactive'];
    $newStatus = strtolower(trim((string)($_POST['acct_status'] ?? '')));
    if (!in_array($newStatus, $allowedStatus, true)) {
        toast_alert('error', 'Unknown status value', 'Rejected');
    } else {
        // Trim + length-cap the reason. Stored in TEXT but we don't want an
        // admin pasting a novel and blowing out the display row.
        $rawReason = (string)($_POST['acct_status_reason'] ?? '');
        $reason = trim($rawReason);
        if (strlen($reason) > 2000) {
            $reason = substr($reason, 0, 2000);
        }
        $stmt = $conn->prepare("UPDATE users SET acct_status=:acct_status, acct_status_reason=:acct_status_reason, acct_status_changed_at=NOW() WHERE id =:acct_id");
        $stmt->execute([
            'acct_status'        => $newStatus,
            'acct_status_reason' => $reason !== '' ? $reason : null,
            'acct_id'            => $id,
        ]);
        header("Location:./users.php"); die;
    }
}

if (isset($_POST['billing_code'])) {
    $stmt = $conn->prepare("UPDATE users SET billing_code=:billing_code WHERE id=:acct_id");
    $stmt->execute(['billing_code' => $_POST['billing_type'], 'acct_id' => $id]);
    header("Location:./users.php"); die;
}

if (isset($_POST['transfer'])) {
    $stmt = $conn->prepare("UPDATE users SET transfer=:transfer WHERE id=:acct_id");
    $stmt->execute(['transfer' => $_POST['transfer_type'], 'acct_id' => $id]);
    header("Location:./users.php"); die;
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
                                    <p>Status: <span class="badge badge-info"><?= htmlspecialchars(ucwords((string)$row['acct_status'])) ?></span></p>
                                    <?php if (!empty($row['acct_status_reason']) || !empty($row['acct_status_changed_at'])): ?>
                                        <p class="small text-muted mb-2">
                                            <?php if (!empty($row['acct_status_changed_at'])): ?>
                                                <strong>Last change:</strong> <?= htmlspecialchars((string)$row['acct_status_changed_at']) ?><br>
                                            <?php endif; ?>
                                            <?php if (!empty($row['acct_status_reason'])): ?>
                                                <strong>Reason:</strong> <?= nl2br(htmlspecialchars((string)$row['acct_status_reason'])) ?>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="form-group">
                                        <select name="acct_status" class="form-control" required>
                                            <?php $curStatus = strtolower((string)$row['acct_status']); ?>
                                            <?php foreach (['active','hold','pending','suspended','blocked','inactive'] as $optVal): ?>
                                                <option value="<?= $optVal ?>" <?= $curStatus === $optVal ? 'selected' : '' ?>><?= ucfirst($optVal) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="small mb-1">Reason / note (optional but recommended when suspending or blocking)</label>
                                        <textarea name="acct_status_reason" class="form-control" rows="2" maxlength="2000" placeholder="e.g. flagged for suspicious activity 2026-08-04"></textarea>
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
