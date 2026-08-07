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
// Pre-write snapshot of the customer's name, used to identify the account in
// the operator alerts below (the delete handler wipes the row it comes from).
$customerName = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
// Attribution for the operator feed. Every notify_admin() below is admin
// activity only — nothing on this page emits notify_user(), because none of
// these handlers changes a customer-visible outcome (see the notifications
// report): they change back-office state, credentials the customer is told
// about by email, or access flags whose effect the customer meets in the app.
$actorAdminId = isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null;

// A single closure so every section handler emits the same shape of diff.
// Only the fields the operator actually changed reach audit_log / notify_admin,
// so an operator who opens the page and saves without editing anything doesn't
// spam the feed with rows full of unchanged values.
$diffChanged = function (array $before, array $post, array $tracked): array {
    $changed = [];
    foreach ($tracked as $f) {
        $old = isset($before[$f]) ? (string)$before[$f] : '';
        $new = isset($post[$f])  ? (string)$post[$f]  : '';
        if ($old !== $new) {
            $changed[$f] = ['old' => $old, 'new' => $new];
        }
    }
    return $changed;
};

// Post-Redirect-Get destination. Staying on the same page after every save is
// friendlier than dumping the operator back to the users list every time.
$backHere = static function ($id, $saved) {
    header("Location: ./view_users.php?id=" . (int)$id . "&saved=" . rawurlencode((string)$saved));
    die;
};

// Allowed enum values. Kept here so a hand-crafted POST can't stash something
// the customer-side code doesn't know how to render.
$allowedGender   = ['male', 'female'];
$allowedMarital  = ['single', 'married', 'divorced', 'widowed', 'separated', 'prefer_not_to_say'];
$allowedStatus   = ['active', 'hold', 'pending', 'suspended', 'blocked', 'inactive'];
$allowedCurrency = ['USD', 'Euro', 'Yuan', 'GBP', 'CAD'];
$allowedAcctType = ['Savings', 'Current', 'Checking', 'Fixed Deposit', 'Non Resident', 'Online Banking', 'Domicilary Account', 'Joint Account'];

if (isset($_POST['upload_picture'])) {
    // Was: destination built from the raw client filename ($acct_no . $name),
    // which let an operator-supplied "x.php" land executable in the web root,
    // against a CWD-relative folder that does not resolve to the document root
    // under PHP-FPM. A failed move fell through with no message at all.
    $stored = admin_store_upload(
        $_FILES['image'] ?? [],
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'profile',
        ['png', 'jpg', 'jpeg', 'gif', 'webp'],
        2 * 1024 * 1024,
        'user-' . $id
    );
    if (!$stored['ok']) {
        toast_alert('error', $stored['error'], 'Not uploaded');
    } else {
        $stmt = $conn->prepare("UPDATE users SET image=:image WHERE id =:acct_id");
        $stmt->execute(['image' => $stored['name'], 'acct_id' => $id]);
        toast_flash('success', 'Image Uploaded Successfully', 'Thanks!');
        $backHere($id, 'avatar');
    }
}

if (isset($_POST['identity_save'])) {
    $gender  = strtolower(trim((string)($_POST['acct_gender'] ?? '')));
    $marital = strtolower(trim((string)($_POST['marital_status'] ?? '')));
    if ($gender !== '' && !in_array($gender, $allowedGender, true)) {
        toast_alert('error', 'Unknown gender value', 'Rejected');
    } elseif ($marital !== '' && !in_array($marital, $allowedMarital, true)) {
        toast_alert('error', 'Unknown marital status value', 'Rejected');
    } else {
        $stmt = $conn->prepare(
            "UPDATE users SET firstname=:firstname, lastname=:lastname, acct_username=:acct_username, " .
            "acct_dob=:acct_dob, acct_gender=:acct_gender, marital_status=:marital_status, ssn=:ssn WHERE id=:id"
        );
        $stmt->execute([
            'firstname'      => (string)($_POST['firstname'] ?? ''),
            'lastname'       => (string)($_POST['lastname']  ?? ''),
            'acct_username'  => (string)($_POST['acct_username'] ?? ''),
            'acct_dob'       => (string)($_POST['acct_dob'] ?? ''),
            'acct_gender'    => $gender,
            'marital_status' => $marital,
            'ssn'            => (string)($_POST['ssn'] ?? ''),
            'id'             => $id,
        ]);
        $tracked = ['firstname','lastname','acct_username','acct_dob','acct_gender','marital_status','ssn'];
        // Normalise the posted enums to the same lowercased form we just wrote,
        // otherwise a case-only "Male" -> "male" round-trip would look like a
        // change every time.
        $normalised = $_POST;
        $normalised['acct_gender']    = $gender;
        $normalised['marital_status'] = $marital;
        $changed = $diffChanged($row, $normalised, $tracked);
        if (!empty($changed) && function_exists('audit_log')) {
            audit_log('user.identity_updated', 'user', (string)$id, [
                'user_email' => $row['acct_email'] ?? null,
                'changed'    => $changed,
            ]);
        }
        if (!empty($changed)) {
            notify_admin(
                $conn,
                'admin.user_profile_edited',
                'Customer identity edited',
                admin_actor_name() . ' updated identity fields on '
                    . ($customerName !== '' ? $customerName : 'a customer') . '. Changed: '
                    . implode(', ', array_keys($changed)) . '.',
                array(
                    'severity'            => isset($changed['firstname']) || isset($changed['lastname']) || isset($changed['acct_username']) || isset($changed['ssn']) ? 'warning' : 'info',
                    'link'                => '/admin/view_users.php?id=' . $id,
                    'source'              => 'admin',
                    'created_by_admin_id' => $actorAdminId,
                    'meta'                => array('user_id' => $id, 'changed' => array_keys($changed)),
                )
            );
        }
        $backHere($id, 'identity');
    }
}

if (isset($_POST['contact_save'])) {
    $stmt = $conn->prepare(
        "UPDATE users SET acct_email=:acct_email, acct_phone=:acct_phone, country=:country, " .
        "state=:state, acct_address=:acct_address WHERE id=:id"
    );
    $stmt->execute([
        'acct_email'   => (string)($_POST['acct_email'] ?? ''),
        'acct_phone'   => (string)($_POST['acct_phone'] ?? ''),
        'country'      => (string)($_POST['country'] ?? ''),
        'state'        => (string)($_POST['state'] ?? ''),
        'acct_address' => (string)($_POST['acct_address'] ?? ''),
        'id'           => $id,
    ]);
    $tracked = ['acct_email','acct_phone','country','state','acct_address'];
    $changed = $diffChanged($row, $_POST, $tracked);
    if (!empty($changed) && function_exists('audit_log')) {
        audit_log('user.contact_updated', 'user', (string)$id, [
            'user_email' => $row['acct_email'] ?? null,
            'changed'    => $changed,
        ]);
    }
    // Email is the recovery channel — a hand-edit here bypasses the customer
    // verification flow, so it needs the loud email alert as well as the bell.
    if (!empty($changed) && isset($changed['acct_email'])) {
        admin_notify(
            (new AdminAlert)->adminCustomerBalanceOrEmailEditedMsg(admin_actor_name(), $customerName, $changed, admin_actor_ip()),
            'Customer email edited by an operator'
        );
    }
    if (!empty($changed)) {
        notify_admin(
            $conn,
            'admin.user_profile_edited',
            'Customer contact details edited',
            admin_actor_name() . ' updated contact details on '
                . ($customerName !== '' ? $customerName : 'a customer') . '. Changed: '
                . implode(', ', array_keys($changed)) . '.',
            array(
                'severity'            => isset($changed['acct_email']) ? 'danger' : 'info',
                'link'                => '/admin/view_users.php?id=' . $id,
                'source'              => 'admin',
                'created_by_admin_id' => $actorAdminId,
                'meta'                => array('user_id' => $id, 'changed' => array_keys($changed)),
            )
        );
    }
    $backHere($id, 'contact');
}

if (isset($_POST['cancel_pending_email'])) {
    $stmt = $conn->prepare(
        "UPDATE users SET pending_email=NULL, pending_email_otp=NULL, " .
        "pending_email_expires=NULL, pending_email_attempts=0 WHERE id=:id"
    );
    $stmt->execute(['id' => $id]);
    if (function_exists('audit_log')) {
        audit_log('user.pending_email_cancelled', 'user', (string)$id, [
            'user_email'    => $row['acct_email'] ?? null,
            'pending_email' => $row['pending_email'] ?? null,
        ]);
    }
    notify_admin(
        $conn,
        'admin.user_profile_edited',
        'Customer pending email change cancelled',
        admin_actor_name() . ' cancelled an in-flight email change on '
            . ($customerName !== '' ? $customerName : 'a customer') . '.',
        array(
            'severity'            => 'warning',
            'link'                => '/admin/view_users.php?id=' . $id,
            'source'              => 'admin',
            'created_by_admin_id' => $actorAdminId,
            'meta'                => array('user_id' => $id, 'pending_email' => (string)($row['pending_email'] ?? '')),
        )
    );
    toast_flash('success', 'Pending email change cancelled.', 'Cleared');
    $backHere($id, 'contact');
}

if (isset($_POST['money_save'])) {
    $currency = (string)($_POST['acct_currency'] ?? '');
    if ($currency !== '' && !in_array($currency, $allowedCurrency, true)) {
        toast_alert('error', 'Unknown currency value', 'Rejected');
    } else {
        // The Account Limit input is pre-filled with the *current* acct_limit, so
        // adding it to the stored value doubled the limit on every save and grew
        // limit_remain by the whole limit each time — an operator who opened the
        // profile and clicked Save without editing anything handed the customer
        // an ever-increasing transfer allowance. Treat the posted value as the
        // new limit and move the remaining allowance by the delta, which
        // preserves how much of the limit has already been consumed.
        $newLimit    = (float)($_POST['acct_limit'] ?? $row['acct_limit']);
        $limitDelta  = $newLimit - (float)$row['acct_limit'];
        $limit       = max(0, $newLimit);
        $limiBalance = max(0, (float)$row['limit_remain'] + $limitDelta);
        $stmt = $conn->prepare(
            "UPDATE users SET acct_currency=:acct_currency, acct_balance=:acct_balance, " .
            "avail_balance=:avail_balance, acct_limit=:acct_limit, limit_remain=:limit_remain, " .
            "week_budget_limit=:week_budget_limit WHERE id=:id"
        );
        $stmt->execute([
            'acct_currency'     => $currency,
            'acct_balance'      => (string)($_POST['acct_balance'] ?? '0'),
            'avail_balance'     => (string)($_POST['avail_balance'] ?? '0'),
            'acct_limit'        => $limit,
            'limit_remain'      => $limiBalance,
            'week_budget_limit' => (string)($_POST['week_budget_limit'] ?? '0'),
            'id'                => $id,
        ]);
        $tracked = ['acct_currency','acct_balance','avail_balance','acct_limit','week_budget_limit'];
        $changed = $diffChanged($row, $_POST, $tracked);
        if (!empty($changed) && function_exists('audit_log')) {
            audit_log('user.money_updated', 'user', (string)$id, [
                'user_email' => $row['acct_email'] ?? null,
                'changed'    => $changed,
            ]);
        }
        // A balance hand-edit writes no ledger row, so mail the diff for the
        // same reason the old profile_save did.
        if (!empty($changed) && isset($changed['acct_balance'])) {
            admin_notify(
                (new AdminAlert)->adminCustomerBalanceOrEmailEditedMsg(admin_actor_name(), $customerName, $changed, admin_actor_ip()),
                'Customer balance edited by an operator'
            );
        }
        if (!empty($changed)) {
            notify_admin(
                $conn,
                'admin.user_profile_edited',
                'Customer money settings edited',
                admin_actor_name() . ' updated money fields on '
                    . ($customerName !== '' ? $customerName : 'a customer') . '. Changed: '
                    . implode(', ', array_keys($changed)) . '.',
                array(
                    'severity'            => isset($changed['acct_balance']) || isset($changed['avail_balance']) ? 'danger' : 'warning',
                    'link'                => '/admin/view_users.php?id=' . $id,
                    'source'              => 'admin',
                    'created_by_admin_id' => $actorAdminId,
                    'meta'                => array('user_id' => $id, 'changed' => array_keys($changed)),
                )
            );
        }
        $backHere($id, 'money');
    }
}

if (isset($_POST['charges_save'])) {
    // acct_type also lives here because customer-facing labels (Savings /
    // Checking / Fixed Deposit) usually get changed together with the fee
    // codes that go with each product tier.
    $acctType = (string)($_POST['acct_type'] ?? '');
    if ($acctType !== '' && !in_array($acctType, $allowedAcctType, true)) {
        toast_alert('error', 'Unknown account type', 'Rejected');
    } else {
        $stmt = $conn->prepare(
            "UPDATE users SET acct_no=:acct_no, acct_type=:acct_type, acct_cot=:acct_cot, " .
            "acct_imf=:acct_imf, acct_tax=:acct_tax, acct_occupation=:acct_occupation WHERE id=:id"
        );
        $stmt->execute([
            'acct_no'         => (string)($_POST['acct_no'] ?? ''),
            'acct_type'       => $acctType,
            'acct_cot'        => (string)($_POST['acct_cot'] ?? ''),
            'acct_imf'        => (string)($_POST['acct_imf'] ?? ''),
            'acct_tax'        => (string)($_POST['acct_tax'] ?? ''),
            'acct_occupation' => (string)($_POST['acct_occupation'] ?? ''),
            'id'              => $id,
        ]);
        $tracked = ['acct_no','acct_type','acct_cot','acct_imf','acct_tax','acct_occupation'];
        $changed = $diffChanged($row, $_POST, $tracked);
        if (!empty($changed) && function_exists('audit_log')) {
            audit_log('user.account_updated', 'user', (string)$id, [
                'user_email' => $row['acct_email'] ?? null,
                'changed'    => $changed,
            ]);
        }
        if (!empty($changed)) {
            notify_admin(
                $conn,
                'admin.user_profile_edited',
                'Customer account settings edited',
                admin_actor_name() . ' updated account fields on '
                    . ($customerName !== '' ? $customerName : 'a customer') . '. Changed: '
                    . implode(', ', array_keys($changed)) . '.',
                array(
                    'severity'            => 'info',
                    'link'                => '/admin/view_users.php?id=' . $id,
                    'source'              => 'admin',
                    'created_by_admin_id' => $actorAdminId,
                    'meta'                => array('user_id' => $id, 'changed' => array_keys($changed)),
                )
            );
        }
        $backHere($id, 'charges');
    }
}

if (isset($_POST['manager_save'])) {
    $stmt = $conn->prepare(
        "UPDATE users SET mgr_name=:mgr_name, mgr_no=:mgr_no, mgr_email=:mgr_email, mgr_id=:mgr_id WHERE id=:id"
    );
    $stmt->execute([
        'mgr_name'  => (string)($_POST['mgr_name']  ?? ''),
        'mgr_no'    => (string)($_POST['mgr_no']    ?? ''),
        'mgr_email' => (string)($_POST['mgr_email'] ?? ''),
        'mgr_id'    => (string)($_POST['mgr_id']    ?? ''),
        'id'        => $id,
    ]);
    $tracked = ['mgr_name','mgr_no','mgr_email','mgr_id'];
    $changed = $diffChanged($row, $_POST, $tracked);
    if (!empty($changed) && function_exists('audit_log')) {
        audit_log('user.manager_updated', 'user', (string)$id, [
            'user_email' => $row['acct_email'] ?? null,
            'changed'    => $changed,
        ]);
    }
    if (!empty($changed)) {
        notify_admin(
            $conn,
            'admin.user_profile_edited',
            'Customer account manager edited',
            admin_actor_name() . ' updated account manager fields on '
                . ($customerName !== '' ? $customerName : 'a customer') . '. Changed: '
                . implode(', ', array_keys($changed)) . '.',
            array(
                'severity'            => 'info',
                'link'                => '/admin/view_users.php?id=' . $id,
                'source'              => 'admin',
                'created_by_admin_id' => $actorAdminId,
                'meta'                => array('user_id' => $id, 'changed' => array_keys($changed)),
            )
        );
    }
    $backHere($id, 'manager');
}

if (isset($_POST['manager_image_upload'])) {
    $stored = admin_store_upload(
        $_FILES['mgr_image_file'] ?? [],
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'profile',
        ['png', 'jpg', 'jpeg', 'gif', 'webp'],
        2 * 1024 * 1024,
        'manager-user-' . $id
    );
    if (!$stored['ok']) {
        toast_alert('error', $stored['error'], 'Not uploaded');
    } else {
        $stmt = $conn->prepare("UPDATE users SET mgr_image=:mgr_image WHERE id=:id");
        $stmt->execute(['mgr_image' => $stored['name'], 'id' => $id]);
        if (function_exists('audit_log')) {
            audit_log('user.manager_image_updated', 'user', (string)$id, [
                'user_email' => $row['acct_email'] ?? null,
                'filename'   => $stored['name'],
            ]);
        }
        toast_flash('success', 'Manager image uploaded.', 'Saved');
        $backHere($id, 'manager');
    }
}

// KYC images. Columns exist in the schema but no other flow writes to them —
// this page is where they finally get populated. Kept in `assets/idcard/`
// because the deploy script (.cpanel.yml) already chmods that folder for
// PHP writes.
foreach (['front' => ['frontID', 'kyc_front_upload', 'kyc_front_file'],
          'back'  => ['backID',  'kyc_back_upload',  'kyc_back_file']] as $side => $meta) {
    list($column, $postName, $fileField) = $meta;
    if (isset($_POST[$postName])) {
        $stored = admin_store_upload(
            $_FILES[$fileField] ?? [],
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'idcard',
            ['png', 'jpg', 'jpeg', 'webp'],
            4 * 1024 * 1024,
            'user-' . $id . '-' . $side
        );
        if (!$stored['ok']) {
            toast_alert('error', $stored['error'], 'Not uploaded');
        } else {
            $stmt = $conn->prepare("UPDATE users SET " . $column . "=:val WHERE id=:id");
            $stmt->execute(['val' => $stored['name'], 'id' => $id]);
            if (function_exists('audit_log')) {
                audit_log('user.kyc_updated', 'user', (string)$id, [
                    'user_email' => $row['acct_email'] ?? null,
                    'side'       => $side,
                    'filename'   => $stored['name'],
                ]);
            }
            // KYC replacements are a fraud vector — the operator can substitute
            // a legitimate customer's ID for their own. Bell + louder severity
            // so it doesn't get lost in the profile-edit stream.
            notify_admin(
                $conn,
                'admin.user_profile_edited',
                'Customer KYC document replaced',
                admin_actor_name() . ' replaced the ' . $side . '-ID document on '
                    . ($customerName !== '' ? $customerName : 'a customer') . '.',
                array(
                    'severity'            => 'warning',
                    'link'                => '/admin/view_users.php?id=' . $id,
                    'source'              => 'admin',
                    'created_by_admin_id' => $actorAdminId,
                    'meta'                => array('user_id' => $id, 'side' => $side, 'filename' => $stored['name']),
                )
            );
            toast_flash('success', ucfirst($side) . ' ID uploaded.', 'Saved');
            $backHere($id, 'kyc');
        }
    }
}

if (isset($_POST['clear_otp_lockout'])) {
    $stmt = $conn->prepare("UPDATE users SET verify_attempts=0, verify_locked_until=NULL WHERE id=:id");
    $stmt->execute(['id' => $id]);
    if (function_exists('audit_log')) {
        audit_log('user.otp_lockout_cleared', 'user', (string)$id, [
            'user_email' => $row['acct_email'] ?? null,
            'was_locked' => $row['verify_locked_until'] ?? null,
        ]);
    }
    notify_admin(
        $conn,
        'admin.user_access_changed',
        'Customer OTP lockout cleared',
        admin_actor_name() . ' cleared the OTP lockout on '
            . ($customerName !== '' ? $customerName : 'a customer') . "'s account.",
        array(
            'severity'            => 'warning',
            'link'                => '/admin/view_users.php?id=' . $id,
            'source'              => 'admin',
            'created_by_admin_id' => $actorAdminId,
            'meta'                => array('user_id' => $id),
        )
    );
    toast_flash('success', 'OTP lockout cleared. Customer can retry immediately.', 'Cleared');
    $backHere($id, 'lockout');
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
    // Hard DELETE with no soft-delete flag and no undo — the operators need to
    // hear about a customer of record disappearing.
    admin_notify(
        (new AdminAlert)->adminCustomerAccountLifecycleMsg(admin_actor_name(), $customerName, 'deleted', '', admin_actor_ip()),
        'Customer account deleted'
    );

    notify_admin(
        $conn,
        'admin.user_deleted',
        'Customer account deleted',
        admin_actor_name() . ' permanently deleted ' . ($customerName !== '' ? $customerName : 'a customer')
            . ' (' . (string)($row['acct_no'] ?? 'unknown account') . '). This is not reversible.',
        array(
            'severity'            => 'danger',
            'link'                => '/admin/users.php',
            'source'              => 'admin',
            'created_by_admin_id' => $actorAdminId,
            'meta'                => array('user_id' => $id, 'acct_no' => (string)($row['acct_no'] ?? '')),
        )
    );
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
        // The operator now knows a live transaction PIN for an account they do
        // not own — anything done with it looks like ordinary customer activity.
        admin_notify(
            (new AdminAlert)->adminCustomerCredentialsResetMsg(admin_actor_name(), $customerName, 'transaction PIN', admin_actor_ip()),
            'Customer credentials reset by an operator'
        );

        // The value itself is never carried into the notification body — the
        // bell renders on every admin page.
        notify_admin(
            $conn,
            'admin.user_credentials_reset',
            'Customer transaction PIN reset',
            admin_actor_name() . ' set a new transaction PIN on '
                . ($customerName !== '' ? $customerName : 'a customer') . "'s account.",
            array(
                'severity'            => 'danger',
                'link'                => '/admin/view_users.php?id=' . $id,
                'source'              => 'admin',
                'created_by_admin_id' => $actorAdminId,
                'meta'                => array('user_id' => $id, 'credential' => 'transaction_pin'),
            )
        );
        toast_alert('success', 'PIN Changed Successfully', 'Approved');
    }
}

if (isset($_POST['change_password'])) {
    // Admin override: no current password required. Operators legitimately
    // reset accounts for customers who forgot theirs, so the flow deliberately
    // does not confirm the existing password. The account-takeover risk this
    // creates is covered by the credentials-reset email + bell notification
    // emitted below.
    $new_password     = inputValidation($_POST['new_password']);
    $confirm_password = inputValidation($_POST['confirm_password']);
    if ($new_password === '') {
        toast_alert('error', 'New password is required');
    } elseif ($new_password !== $confirm_password) {
        toast_alert('error', 'Confirm Password not matched');
    } elseif (password_verify($new_password, (string)$row['acct_password'])) {
        toast_alert('error', 'New Password matches the current one');
    } else {
        $stmt = $conn->prepare("UPDATE users SET acct_password=:acct_password WHERE id =:acct_id");
        $stmt->execute(['acct_password' => password_hash((string)$new_password, PASSWORD_BCRYPT), 'acct_id' => $id]);
        if (function_exists('audit_log')) {
            // Never log the actual password.
            audit_log('user.password_changed', 'user', (string)$id, [
                'user_email' => $row['acct_email'] ?? null,
            ]);
        }
        // Same account-takeover risk as the PIN reset: an operator holds a
        // working customer credential from here on.
        admin_notify(
            (new AdminAlert)->adminCustomerCredentialsResetMsg(admin_actor_name(), $customerName, 'password', admin_actor_ip()),
            'Customer credentials reset by an operator'
        );

        notify_admin(
            $conn,
            'admin.user_credentials_reset',
            'Customer password reset',
            admin_actor_name() . ' set a new sign-in password on '
                . ($customerName !== '' ? $customerName : 'a customer') . "'s account.",
            array(
                'severity'            => 'danger',
                'link'                => '/admin/view_users.php?id=' . $id,
                'source'              => 'admin',
                'created_by_admin_id' => $actorAdminId,
                'meta'                => array('user_id' => $id, 'credential' => 'password'),
            )
        );
        toast_alert('success', 'Password Changed Successfully', 'Approved');
    }
}

if (isset($_POST['status_submit'])) {
    // Whitelist status values so a hand-crafted POST can't stash arbitrary
    // strings in acct_status — login gating uses exact-string comparison in
    // _bootstrap.php and an unknown value would silently lock the user out.
    $newStatus = strtolower(trim((string)($_POST['acct_status'] ?? '')));
    if (!in_array($newStatus, $allowedStatus, true)) {
        toast_alert('error', 'Unknown status value', 'Rejected');
    } else {
        $rawReason = (string)($_POST['acct_status_reason'] ?? '');
        $reason = trim($rawReason);
        if (strlen($reason) > 2000) {
            $reason = substr($reason, 0, 2000);
        }
        $oldStatus = $row['acct_status'] ?? null;
        $stmt = $conn->prepare("UPDATE users SET acct_status=:acct_status, acct_status_reason=:acct_status_reason, acct_status_changed_at=NOW() WHERE id =:acct_id");
        $stmt->execute([
            'acct_status'        => $newStatus,
            'acct_status_reason' => $reason !== '' ? $reason : null,
            'acct_id'            => $id,
        ]);
        if (function_exists('audit_log')) {
            audit_log('user.status_changed', 'user', (string)$id, [
                'user_email' => $row['acct_email'] ?? null,
                'old'        => $oldStatus,
                'new'        => $newStatus,
                'reason'     => $reason !== '' ? $reason : null,
            ]);
        }
        // Anything other than "active" cuts the customer off from their money,
        // so carry the new status and the operator's reason to the inbox.
        admin_notify(
            (new AdminAlert)->adminCustomerAccountLifecycleMsg(admin_actor_name(), $customerName, $newStatus, $reason, admin_actor_ip()),
            'Customer account ' . $newStatus
        );

        notify_admin(
            $conn,
            'admin.user_status_changed',
            'Customer account status changed',
            admin_actor_name() . ' moved ' . ($customerName !== '' ? $customerName : 'a customer')
                . ' from ' . (string)$oldStatus . ' to ' . $newStatus
                . ($reason !== '' ? '. Reason: ' . $reason : '.'),
            array(
                'severity'            => $newStatus === 'active' ? 'info' : 'danger',
                'link'                => '/admin/view_users.php?id=' . $id,
                'source'              => 'admin',
                'created_by_admin_id' => $actorAdminId,
                'meta'                => array('user_id' => $id, 'old' => (string)$oldStatus, 'new' => $newStatus),
            )
        );
        $backHere($id, 'status');
    }
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
    notify_admin(
        $conn,
        'admin.user_access_changed',
        'Customer billing code toggled',
        admin_actor_name() . ' set the billing code on '
            . ($customerName !== '' ? $customerName : 'a customer') . "'s account to "
            . (((string)($_POST['billing_type'] ?? '')) === '1' ? 'ACTIVE' : 'DEACTIVATE') . '.',
        array(
            'severity'            => 'warning',
            'link'                => '/admin/view_users.php?id=' . $id,
            'source'              => 'admin',
            'created_by_admin_id' => $actorAdminId,
            'meta'                => array('user_id' => $id, 'billing_code' => (string)($_POST['billing_type'] ?? '')),
        )
    );
    $backHere($id, 'billing');
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
    // A cleared flag silently strands the customer's funds with no customer-side
    // notice, so mail the exact flag set that was written.
    admin_notify(
        (new AdminAlert)->adminCustomerAccessRevokedMsg(admin_actor_name(), $customerName, $flags, admin_actor_ip()),
        'Customer access changed'
    );

    $offFlags = array_keys(array_filter($flags, static function ($v) { return $v === 0; }));
    notify_admin(
        $conn,
        'admin.user_access_changed',
        'Customer feature access changed',
        admin_actor_name() . ' saved feature access for '
            . ($customerName !== '' ? $customerName : 'a customer') . '. '
            . ($offFlags ? 'Disabled: ' . implode(', ', $offFlags) . '.' : 'All features enabled.'),
        array(
            'severity'            => $offFlags ? 'warning' : 'info',
            'link'                => '/admin/view_users.php?id=' . $id,
            'source'              => 'admin',
            'created_by_admin_id' => $actorAdminId,
            'meta'                => array('user_id' => $id, 'flags' => $flags),
        )
    );
    $backHere($id, 'features');
}

// Force-logout: stamps users.sessions_invalidated_at = NOW(). The next
// authenticated API call this user makes will fail the check in
// api/user/_bootstrap.php and tear down the session with a 401.
if (isset($_POST['force_logout_user'])) {
    $stmt = $conn->prepare("UPDATE users SET sessions_invalidated_at = NOW() WHERE id = :id");
    $stmt->execute(['id' => $id]);
    // Every live session for this customer dies at once — worth an operator
    // record in case it was not the account owner asking for it.
    admin_notify(
        (new AdminAlert)->adminCustomerAccessRevokedMsg(admin_actor_name(), $customerName, ['all sessions' => 'terminated'], admin_actor_ip()),
        'Customer access changed'
    );

    notify_admin(
        $conn,
        'admin.user_sessions_revoked',
        'Customer sessions force-revoked',
        admin_actor_name() . ' signed ' . ($customerName !== '' ? $customerName : 'a customer')
            . ' out of every active session.',
        array(
            'severity'            => 'warning',
            'link'                => '/admin/view_users.php?id=' . $id,
            'source'              => 'admin',
            'created_by_admin_id' => $actorAdminId,
            'meta'                => array('user_id' => $id),
        )
    );

    toast_alert('success', 'User signed out of all active sessions. Their next request will require re-authentication.', 'Sessions revoked');
    // Re-read so the on-page timestamp reflects the new value below.
    $refresh = $conn->prepare("SELECT sessions_invalidated_at FROM users WHERE id = :id LIMIT 1");
    $refresh->execute(['id' => $id]);
    $refreshed = $refresh->fetch(PDO::FETCH_ASSOC);
    if ($refreshed && isset($refreshed['sessions_invalidated_at'])) {
        $row['sessions_invalidated_at'] = $refreshed['sessions_invalidated_at'];
    }
}

// Whitelist banner for the just-saved section, if any.
$savedSection = isset($_GET['saved']) ? (string)$_GET['saved'] : '';
$savedLabels = [
    'avatar'   => 'Profile photo',
    'identity' => 'Identity',
    'contact'  => 'Contact & address',
    'money'    => 'Money & currency',
    'charges'  => 'Account & charges',
    'manager'  => 'Account manager',
    'kyc'      => 'KYC document',
    'status'   => 'Account status',
    'billing'  => 'Billing code',
    'features' => 'Feature access',
    'lockout'  => 'OTP lockout',
];
$savedLabel = isset($savedLabels[$savedSection]) ? $savedLabels[$savedSection] : '';

$featureFlags = [
    'transfer'         => (int)($row['transfer'] ?? 1) === 1,
    'can_deposit'      => (int)($row['can_deposit'] ?? 1) === 1,
    'can_withdraw'     => (int)($row['can_withdraw'] ?? 1) === 1,
    'can_request_card' => (int)($row['can_request_card'] ?? 1) === 1,
];

// Resolved KYC image paths — same guard as admin_profile_src() so a stray
// slash or a missing file falls back to the default rather than 404ing.
$kycImg = static function ($name) {
    $name = trim((string)$name);
    $dir  = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'idcard' . DIRECTORY_SEPARATOR;
    if ($name === '' || strpos($name, '/') !== false || strpos($name, '\\') !== false || !is_file($dir . $name)) {
        return '';
    }
    return '../assets/idcard/' . rawurlencode($name) . '?v=' . (int)filemtime($dir . $name);
};
$frontIDsrc = $kycImg($row['frontID'] ?? '');
$backIDsrc  = $kycImg($row['backID']  ?? '');
$mgrImgSrc  = admin_profile_src($row['mgr_image'] ?? '');

$pendingEmail          = trim((string)($row['pending_email']         ?? ''));
$pendingEmailExpires   = trim((string)($row['pending_email_expires'] ?? ''));
$pendingEmailAttempts  = (int)($row['pending_email_attempts']         ?? 0);

$lockedUntil = trim((string)($row['verify_locked_until'] ?? ''));
$isLocked = $lockedUntil !== '' && strtotime($lockedUntil) !== false && strtotime($lockedUntil) > time();
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
        <?php if ($savedLabel !== ''): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?= htmlspecialchars($savedLabel) ?> saved.
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-3">
                <div class="card card-primary card-outline">
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <img class="profile-user-img img-fluid img-circle" src="<?= htmlspecialchars(admin_profile_src($row['image'])) ?>" alt="user">
                        </div>
                        <h3 class="profile-username text-center"><?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?></h3>
                        <p class="text-muted text-center"><?= htmlspecialchars($row['acct_type']) ?></p>
                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item"><b>Account No</b> <span class="float-right"><?= htmlspecialchars($row['acct_no']) ?></span></li>
                            <li class="list-group-item"><b>Balance</b> <span class="float-right"><?= currency($row) . number_format((float)$row['acct_balance'], 2) ?></span></li>
                            <li class="list-group-item"><b>Status</b> <span class="float-right"><?= htmlspecialchars($row['acct_status']) ?></span></li>
                            <?php if (!empty($row['createdAt'])): ?>
                                <li class="list-group-item"><b>Joined</b> <span class="float-right"><?= htmlspecialchars((string)$row['createdAt']) ?></span></li>
                            <?php endif; ?>
                            <?php if (!empty($row['password_changed_at'])): ?>
                                <li class="list-group-item"><b>Pwd changed</b> <span class="float-right"><?= htmlspecialchars((string)$row['password_changed_at']) ?></span></li>
                            <?php endif; ?>
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
                <!-- ─── Identity ─────────────────────────────────────────── -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Identity</h3></div>
                    <form method="post">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>First name</label><input type="text" name="firstname" class="form-control" value="<?= htmlspecialchars((string)($row['firstname'] ?? '')) ?>"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Last name</label><input type="text" name="lastname" class="form-control" value="<?= htmlspecialchars((string)($row['lastname'] ?? '')) ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>Username</label><input type="text" name="acct_username" class="form-control" value="<?= htmlspecialchars((string)($row['acct_username'] ?? '')) ?>"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Date of Birth</label><input type="date" name="acct_dob" class="form-control" value="<?= htmlspecialchars((string)($row['acct_dob'] ?? '')) ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group"><label>Gender</label>
                                        <select name="acct_gender" class="form-control">
                                            <option value="">—</option>
                                            <?php foreach ($allowedGender as $g): ?>
                                                <option value="<?= $g ?>" <?= strtolower((string)$row['acct_gender']) === $g ? 'selected' : '' ?>><?= ucfirst($g) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group"><label>Marital status</label>
                                        <select name="marital_status" class="form-control">
                                            <option value="">—</option>
                                            <?php foreach ($allowedMarital as $m): ?>
                                                <option value="<?= $m ?>" <?= strtolower((string)$row['marital_status']) === $m ? 'selected' : '' ?>><?= htmlspecialchars(ucwords(str_replace('_', ' ', $m))) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group"><label>SSN / national ID</label><input type="text" name="ssn" class="form-control" value="<?= htmlspecialchars((string)($row['ssn'] ?? '')) ?>" autocomplete="off">
                                    <small class="text-muted">Stored as-typed. Nothing else in the customer app displays this value.</small></div>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer"><button class="btn btn-primary" name="identity_save" value="1">Save identity</button></div>
                    </form>
                </div>

                <!-- ─── Contact & address ───────────────────────────────── -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Contact &amp; address</h3></div>
                    <?php if ($pendingEmail !== ''): ?>
                        <div class="card-body pb-0">
                            <div class="alert alert-warning mb-0">
                                <strong>Pending email change:</strong> <?= htmlspecialchars($pendingEmail) ?>
                                <?php if ($pendingEmailExpires !== ''): ?>
                                    · expires <?= htmlspecialchars($pendingEmailExpires) ?>
                                <?php endif; ?>
                                · attempts <?= (int)$pendingEmailAttempts ?>
                                <form method="post" class="d-inline float-right" onsubmit="return confirm('Cancel this pending email change?')">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary" name="cancel_pending_email">Cancel</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                    <form method="post">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>Email</label><input type="email" name="acct_email" class="form-control" value="<?= htmlspecialchars((string)($row['acct_email'] ?? '')) ?>"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Phone</label><input type="text" name="acct_phone" class="form-control" value="<?= htmlspecialchars((string)($row['acct_phone'] ?? '')) ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Country</label>
                                        <select name="country" class="form-control select2" data-current="<?= htmlspecialchars((string)($row['country'] ?? '')) ?>">
                                            <option value="">Select Country</option>
                                            <?php include __DIR__ . '/layout/_countries.php'; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6"><div class="form-group"><label>State / Region</label><input type="text" name="state" class="form-control" value="<?= htmlspecialchars((string)($row['state'] ?? '')) ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-12"><div class="form-group"><label>Address</label><textarea name="acct_address" rows="2" class="form-control"><?= htmlspecialchars((string)($row['acct_address'] ?? '')) ?></textarea></div></div>
                            </div>
                        </div>
                        <div class="card-footer"><button class="btn btn-primary" name="contact_save" value="1">Save contact</button></div>
                    </form>
                </div>

                <!-- ─── Money & currency ────────────────────────────────── -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Money &amp; currency</h3></div>
                    <form method="post">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group"><label>Currency</label>
                                        <select name="acct_currency" class="form-control">
                                            <option value="">—</option>
                                            <?php foreach ($allowedCurrency as $c): ?>
                                                <option value="<?= $c ?>" <?= (string)$row['acct_currency'] === $c ? 'selected' : '' ?>><?= $c ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6"><div class="form-group"><label>Weekly budget limit</label><input type="text" name="week_budget_limit" class="form-control" value="<?= htmlspecialchars((string)($row['week_budget_limit'] ?? '')) ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>Account balance</label><input type="text" name="acct_balance" class="form-control" value="<?= htmlspecialchars((string)($row['acct_balance'] ?? '')) ?>"><small class="text-muted">Hand-edits do not write a ledger row. Operators are emailed.</small></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Available balance</label><input type="text" name="avail_balance" class="form-control" value="<?= htmlspecialchars((string)($row['avail_balance'] ?? '')) ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>Transfer limit</label><input type="text" name="acct_limit" class="form-control" value="<?= htmlspecialchars((string)($row['acct_limit'] ?? '')) ?>"><small class="text-muted">Remaining allowance moves by the delta so consumed amount is preserved. Current remaining: <?= htmlspecialchars((string)($row['limit_remain'] ?? '')) ?>.</small></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Loan balance</label><input type="text" class="form-control" value="<?= htmlspecialchars((string)($row['loan_balance'] ?? '')) ?>" disabled><small class="text-muted">Read-only. Updated by the loans ledger.</small></div></div>
                            </div>
                        </div>
                        <div class="card-footer"><button class="btn btn-primary" name="money_save" value="1">Save money</button></div>
                    </form>
                </div>

                <!-- ─── Account & charges ───────────────────────────────── -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Account &amp; charges</h3></div>
                    <form method="post">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>Account No</label><input type="text" name="acct_no" class="form-control" value="<?= htmlspecialchars((string)($row['acct_no'] ?? '')) ?>"></div></div>
                                <div class="col-md-6">
                                    <div class="form-group"><label>Account Type</label>
                                        <select name="acct_type" class="form-control">
                                            <?php foreach ($allowedAcctType as $t): ?>
                                                <option value="<?= htmlspecialchars($t) ?>" <?= (string)$row['acct_type'] === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-12"><div class="form-group"><label>Occupation</label><input type="text" name="acct_occupation" class="form-control" value="<?= htmlspecialchars((string)($row['acct_occupation'] ?? '')) ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-4"><div class="form-group"><label>COT</label><input type="text" name="acct_cot" class="form-control" value="<?= htmlspecialchars((string)($row['acct_cot'] ?? '')) ?>"></div></div>
                                <div class="col-md-4"><div class="form-group"><label>IMF</label><input type="text" name="acct_imf" class="form-control" value="<?= htmlspecialchars((string)($row['acct_imf'] ?? '')) ?>"></div></div>
                                <div class="col-md-4"><div class="form-group"><label>TAX</label><input type="text" name="acct_tax" class="form-control" value="<?= htmlspecialchars((string)($row['acct_tax'] ?? '')) ?>"></div></div>
                            </div>
                        </div>
                        <div class="card-footer"><button class="btn btn-primary" name="charges_save" value="1">Save account &amp; charges</button></div>
                    </form>
                </div>

                <!-- ─── Account manager ─────────────────────────────────── -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Account manager</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <img src="<?= htmlspecialchars($mgrImgSrc) ?>" alt="manager" class="img-circle img-fluid mb-2" style="max-width: 8rem;">
                                <form method="post" enctype="multipart/form-data">
                                    <input type="file" name="mgr_image_file" class="form-control-file mb-2" accept="image/*">
                                    <button class="btn btn-secondary btn-sm btn-block" name="manager_image_upload" value="1">Upload manager photo</button>
                                </form>
                            </div>
                            <div class="col-md-9">
                                <form method="post">
                                    <div class="row">
                                        <div class="col-md-6"><div class="form-group"><label>Manager name</label><input type="text" name="mgr_name" class="form-control" value="<?= htmlspecialchars((string)($row['mgr_name'] ?? '')) ?>"></div></div>
                                        <div class="col-md-6"><div class="form-group"><label>Manager phone</label><input type="text" name="mgr_no" class="form-control" value="<?= htmlspecialchars((string)($row['mgr_no'] ?? '')) ?>"></div></div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6"><div class="form-group"><label>Manager email</label><input type="email" name="mgr_email" class="form-control" value="<?= htmlspecialchars((string)($row['mgr_email'] ?? '')) ?>"></div></div>
                                        <div class="col-md-6"><div class="form-group"><label>Manager ID</label><input type="text" name="mgr_id" class="form-control" value="<?= htmlspecialchars((string)($row['mgr_id'] ?? '')) ?>"></div></div>
                                    </div>
                                    <button class="btn btn-primary" name="manager_save" value="1">Save manager details</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── KYC documents ───────────────────────────────────── -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">KYC documents</h3></div>
                    <div class="card-body">
                        <p class="text-muted">JPG / PNG / WebP up to 4 MB. Uploads land in <code>assets/idcard/</code> — the folder is created on deploy.</p>
                        <div class="row">
                            <div class="col-md-6 text-center">
                                <h5>Front ID</h5>
                                <?php if ($frontIDsrc !== ''): ?>
                                    <a href="<?= htmlspecialchars($frontIDsrc) ?>" target="_blank" rel="noopener">
                                        <img src="<?= htmlspecialchars($frontIDsrc) ?>" alt="Front ID" class="img-fluid mb-2" style="max-height: 12rem;">
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted">No document on file.</p>
                                <?php endif; ?>
                                <form method="post" enctype="multipart/form-data">
                                    <input type="file" name="kyc_front_file" class="form-control-file mb-2" accept="image/png,image/jpeg,image/webp">
                                    <button class="btn btn-secondary btn-sm" name="kyc_front_upload" value="1">Replace front</button>
                                </form>
                            </div>
                            <div class="col-md-6 text-center">
                                <h5>Back ID</h5>
                                <?php if ($backIDsrc !== ''): ?>
                                    <a href="<?= htmlspecialchars($backIDsrc) ?>" target="_blank" rel="noopener">
                                        <img src="<?= htmlspecialchars($backIDsrc) ?>" alt="Back ID" class="img-fluid mb-2" style="max-height: 12rem;">
                                    </a>
                                <?php else: ?>
                                    <p class="text-muted">No document on file.</p>
                                <?php endif; ?>
                                <form method="post" enctype="multipart/form-data">
                                    <input type="file" name="kyc_back_file" class="form-control-file mb-2" accept="image/png,image/jpeg,image/webp">
                                    <button class="btn btn-secondary btn-sm" name="kyc_back_upload" value="1">Replace back</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── Status & lifecycle ──────────────────────────────── -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Status &amp; lifecycle</h3></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
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
                                            <?php foreach ($allowedStatus as $optVal): ?>
                                                <option value="<?= $optVal ?>" <?= $curStatus === $optVal ? 'selected' : '' ?>><?= ucfirst($optVal) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="small mb-1">Reason / note (optional but recommended when suspending or blocking)</label>
                                        <textarea name="acct_status_reason" class="form-control" rows="2" maxlength="2000" placeholder="e.g. flagged for suspicious activity 2026-08-04"></textarea>
                                    </div>
                                    <button class="btn btn-primary btn-block" name="status_submit">Update status</button>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <form method="post">
                                    <p>Billing code: <span class="badge badge-info"><?= $billing ?></span></p>
                                    <div class="form-group">
                                        <select name="billing_type" class="form-control" required>
                                            <option value="">Select</option>
                                            <option value="1">Active</option>
                                            <option value="0">Deactivate</option>
                                        </select>
                                    </div>
                                    <button class="btn btn-primary btn-block" name="billing_code">Update billing</button>
                                </form>

                                <hr>
                                <h5 class="mb-2">OTP lockout</h5>
                                <?php if ($isLocked): ?>
                                    <p class="small text-danger mb-2"><strong>Locked until:</strong> <?= htmlspecialchars($lockedUntil) ?><br>Attempts: <?= (int)($row['verify_attempts'] ?? 0) ?></p>
                                <?php else: ?>
                                    <p class="small text-muted mb-2">No active lockout. Attempts: <?= (int)($row['verify_attempts'] ?? 0) ?>.</p>
                                <?php endif; ?>
                                <form method="post">
                                    <button class="btn btn-warning btn-block" name="clear_otp_lockout" <?= $isLocked ? '' : 'disabled' ?>>Clear OTP lockout</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ─── Feature access ──────────────────────────────────── -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Feature access</h3></div>
                    <form method="post">
                        <div class="card-body">
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

                <!-- ─── Session control ─────────────────────────────────── -->
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Session control</h3></div>
                    <div class="card-body">
                        <?php $sessionsInvalidatedAt = isset($row['sessions_invalidated_at']) ? (string)$row['sessions_invalidated_at'] : ''; ?>
                        <p class="mb-2">
                            Force this user out of every device they're currently signed in on. The next
                            request their browser makes will be rejected with a 401 and they'll be sent
                            back to the sign-in screen.
                        </p>
                        <?php if ($sessionsInvalidatedAt !== ''): ?>
                            <p class="text-muted small mb-3">Last forced sign-out: <strong><?= htmlspecialchars($sessionsInvalidatedAt) ?></strong></p>
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

                <!-- ─── Credentials + Danger ────────────────────────────── -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header"><h3 class="card-title">Change password</h3></div>
                            <form method="post" autocomplete="off">
                                <div class="card-body">
                                    <p class="small text-muted mb-3">Admin override — the customer's current password is not required. They'll be emailed about the reset.</p>
                                    <div class="form-group"><label>New password</label><input type="password" name="new_password" class="form-control" required></div>
                                    <div class="form-group"><label>Confirm password</label><input type="password" name="confirm_password" class="form-control" required></div>
                                </div>
                                <div class="card-footer"><button class="btn btn-primary" name="change_password">Change password</button></div>
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
                                    <button class="btn btn-danger btn-block" name="status_delete">Delete user</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
// The countries partial is a bare list of <option>s with no selected state,
// so mark the current one now that we've drawn it. Also runs after Select2
// rebinds if the theme loads it.
(function () {
    var sel = document.querySelector('select[name="country"][data-current]');
    if (!sel) return;
    var want = sel.getAttribute('data-current') || '';
    if (!want) return;
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === want) { sel.selectedIndex = i; break; }
    }
    if (window.jQuery && typeof jQuery(sel).trigger === 'function') {
        jQuery(sel).trigger('change');
    }
})();
</script>

<?php include_once("./layout/footer.php"); ?>
