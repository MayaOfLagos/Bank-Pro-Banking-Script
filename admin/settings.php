<?php
include_once("./layout/header.php");

if (isset($_POST['upload_picture']) && isset($_FILES['image']) && $_FILES['image']['name']) {
    $file = $_FILES['image'];
    $name = $file['name'];
    $folder = "../assets/settings/";
    $destination = $folder . $name;
    if (!is_dir($folder)) {
        @mkdir($folder, 0775, true);
    }
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $stmt = $conn->prepare("UPDATE settings SET image=:image WHERE id ='1'");
        $stmt->execute(['image' => $name]);
        toast_alert('success', 'Logo uploaded successfully', 'Thanks!');
    }
}

if (isset($_POST['upload_favicon']) && isset($_FILES['favicon']) && $_FILES['favicon']['name']) {
    $file = $_FILES['favicon'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['ico', 'png', 'svg', 'jpg', 'jpeg'];
    $folder = "../assets/settings/";
    if (!in_array($ext, $allowed, true)) {
        toast_alert('error', 'Favicon must be .ico, .png, .svg, .jpg, or .jpeg', 'Invalid');
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        toast_alert('error', 'Favicon must be smaller than 2 MB', 'Too large');
    } else {
        // Standardise the on-disk name so repeated uploads always overwrite
        // the current favicon instead of piling up.
        $name = 'favicon.' . $ext;
        $destination = $folder . $name;
        if (!is_dir($folder)) {
            @mkdir($folder, 0775, true);
        }
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $stmt = $conn->prepare("UPDATE settings SET favicon=:favicon WHERE id='1'");
            $stmt->execute(['favicon' => $name]);
            toast_alert('success', 'Favicon uploaded successfully', 'Thanks!');
        } else {
            toast_alert('error', 'Failed to save favicon', 'Upload error');
        }
    }
}

if (isset($_POST['test_email'])) {
    $to       = trim($_POST['test_email_to'] ?? WEB_EMAIL);
    $subject  = "[SMTP TEST] " . ($pageTitle ?? 'Bank Admin');
    $body     = "<p>This is a test email sent " . date('Y-m-d H:i:s') . " from the admin panel.</p>"
              . "<p>Host: <code>" . htmlspecialchars(SMTP_HOST) . "</code><br>"
              . "From: <code>" . htmlspecialchars(SMTP_FROM_EMAIL) . "</code><br>"
              . "Port: <code>" . (int)SMTP_PORT . "</code> (" . htmlspecialchars(SMTP_SECURE) . ")</p>";

    if (SMTP_HOST === '#' || SMTP_USERNAME === '#' || SMTP_PASSWORD === '#') {
        toast_alert('warning', 'SMTP is not configured yet. Set SMTP_HOST/USERNAME/PASSWORD in include/config.php (or env vars).', 'Not configured');
    } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        toast_alert('error', 'Provide a valid recipient email.', 'Invalid');
    } elseif ($email_message->send_mail($to, $body, $subject)) {
        toast_alert('success', "Test email sent to $to.", 'Sent');
    } else {
        toast_alert('error', "Failed to send. Check error_log for details.", 'Failed');
    }
}

if (isset($_POST['save_terms'])) {
    // Whitelist a small block-level HTML vocabulary so admins can format
    // the copy without opening an XSS hole on the public /terms and
    // /privacy pages. strip_tags's second argument is inclusive — any
    // tag not on this list is stripped, attribute-scrubbing on kept
    // tags is handled implicitly (strip_tags rejects on* handlers).
    $allowedTags = '<p><br><strong><em><ul><ol><li><a><h2><h3>';
    $terms   = trim(strip_tags((string)($_POST['terms_of_service_html'] ?? ''), $allowedTags));
    $privacy = trim(strip_tags((string)($_POST['privacy_policy_html']   ?? ''), $allowedTags));

    $stmt = $conn->prepare("UPDATE settings SET terms_of_service_html=:terms, privacy_policy_html=:privacy WHERE id=1");
    $stmt->execute([
        'terms'   => $terms,
        'privacy' => $privacy,
    ]);
    toast_alert('success', 'Legal documents updated successfully', 'Saved');

    $reload = $conn->query("SELECT * FROM settings WHERE id=1");
    $page   = $reload->fetch(PDO::FETCH_ASSOC);
}

if (isset($_POST['save_settings'])) {
    // Whitelist signup_default_status so the value written to the DB is
    // always something the register endpoint will accept.
    $allowedDefault = ['hold', 'active', 'pending'];
    $defaultStatus = in_array($_POST['signup_default_status'] ?? '', $allowedDefault, true)
        ? $_POST['signup_default_status']
        : 'hold';

    $stmt = $conn->prepare("UPDATE settings SET url_name=:url_name, url_tel=:url_tel, about_us=:about_us, url_email=:url_email, livechat=:livechat, trans_limit_min=:trans_limit_min, trans_limit_max=:trans_limit_max, transfer=:transfer, billing_code=:billing_code, bank_deposit=:bank_deposit, registration_enabled=:registration_enabled, signup_default_status=:signup_default_status WHERE id=1");
    $stmt->execute([
        'url_name'              => $_POST['url_name'],
        'url_tel'               => $_POST['url_tel'],
        'about_us'              => $_POST['about_us'],
        'url_email'             => $_POST['url_email'],
        'livechat'              => $_POST['livechat'],
        'trans_limit_min'       => $_POST['trans_limit_min'],
        'trans_limit_max'       => $_POST['trans_limit_max'],
        'transfer'              => $_POST['transfer'],
        'billing_code'          => $_POST['billing_code'],
        'bank_deposit'          => $_POST['bank_deposit'],
        'registration_enabled'  => (int)($_POST['registration_enabled'] ?? 1),
        'signup_default_status' => $defaultStatus,
    ]);
    toast_alert('success', 'Settings updated successfully', 'Approved');

    $reload = $conn->query("SELECT * FROM settings WHERE id=1");
    $page   = $reload->fetch(PDO::FETCH_ASSOC);
}
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Site Settings</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Settings</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-4">
                <div class="card card-primary card-outline">
                    <div class="card-header"><h3 class="card-title">Logo</h3></div>
                    <div class="card-body text-center">
                        <img src="../assets/settings/<?= htmlspecialchars($page['image']) ?>" alt="logo" class="img-fluid mb-3" style="max-height:160px;">
                        <form method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <input type="file" name="image" class="form-control-file" accept="image/*">
                            </div>
                            <button class="btn btn-primary btn-block" name="upload_picture">Upload Logo</button>
                        </form>
                    </div>
                </div>

                <div class="card card-info card-outline">
                    <div class="card-header"><h3 class="card-title">Favicon</h3></div>
                    <div class="card-body text-center">
                        <?php $faviconName = trim((string)($page['favicon'] ?? '')); ?>
                        <?php if ($faviconName !== '' && is_file('../assets/settings/' . $faviconName)): ?>
                            <img src="../assets/settings/<?= htmlspecialchars($faviconName) ?>" alt="favicon" class="mb-3" style="max-height:64px; max-width:64px; image-rendering:auto;">
                        <?php else: ?>
                            <p class="text-muted small mb-3">No favicon uploaded yet.</p>
                        <?php endif; ?>
                        <form method="post" enctype="multipart/form-data">
                            <div class="form-group">
                                <input type="file" name="favicon" class="form-control-file" accept=".ico,.png,.svg,image/x-icon,image/png,image/svg+xml">
                            </div>
                            <button class="btn btn-info btn-block" name="upload_favicon">Upload Favicon</button>
                            <p class="small text-muted mt-2 mb-0">Recommended: 32×32 or 64×64 <code>.ico</code>/<code>.png</code>. Max 2&nbsp;MB.</p>
                        </form>
                    </div>
                </div>

                <?php $smtpReady = SMTP_HOST !== '#' && SMTP_USERNAME !== '#' && SMTP_PASSWORD !== '#'; ?>
                <div class="card card-outline card-<?= $smtpReady ? 'success' : 'warning' ?>">
                    <div class="card-header"><h3 class="card-title">SMTP Test</h3></div>
                    <div class="card-body">
                        <p class="small mb-2">
                            Status:
                            <?php if ($smtpReady): ?>
                                <span class="badge badge-success">Configured</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Not configured</span>
                                <br><small class="text-muted">Set <code>SMTP_HOST</code>, <code>SMTP_USERNAME</code>, <code>SMTP_PASSWORD</code> in <code>include/config.php</code> or as env vars.</small>
                            <?php endif; ?>
                        </p>
                        <p class="small text-muted mb-2">
                            Host: <code><?= htmlspecialchars(SMTP_HOST) ?></code><br>
                            Port: <code><?= (int)SMTP_PORT ?></code> (<?= htmlspecialchars(SMTP_SECURE) ?>)<br>
                            From: <code><?= htmlspecialchars(SMTP_FROM_EMAIL) ?></code>
                        </p>
                        <form method="post">
                            <div class="form-group">
                                <input type="email" name="test_email_to" class="form-control form-control-sm" placeholder="recipient@example.com" value="<?= htmlspecialchars(WEB_EMAIL) ?>" required>
                            </div>
                            <button class="btn btn-secondary btn-sm btn-block" name="test_email" <?= $smtpReady ? '' : 'disabled' ?>>
                                <i class="fas fa-paper-plane"></i> Send Test Email
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Bank Settings</h3></div>
                    <form method="post">
                        <div class="card-body">
                            <div class="form-group">
                                <label>Bank Name</label>
                                <input type="text" name="url_name" class="form-control" value="<?= htmlspecialchars($page['url_name']) ?>">
                            </div>
                            <div class="form-group">
                                <label>About Us</label>
                                <textarea name="about_us" class="form-control" rows="3"><?= htmlspecialchars($page['about_us']) ?></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>Bank Phone</label><input type="text" name="url_tel" class="form-control" value="<?= htmlspecialchars($page['url_tel']) ?>"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Bank Email</label><input type="email" name="url_email" class="form-control" value="<?= htmlspecialchars($page['url_email']) ?>"></div></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6"><div class="form-group"><label>Min Deposit Limit</label><input type="number" name="trans_limit_min" class="form-control" value="<?= htmlspecialchars($page['trans_limit_min']) ?>"></div></div>
                                <div class="col-md-6"><div class="form-group"><label>Max Deposit Limit</label><input type="number" name="trans_limit_max" class="form-control" value="<?= htmlspecialchars($page['trans_limit_max']) ?>"></div></div>
                            </div>
                            <hr>
                            <p class="text-muted small">Toggles: 1 = Active, 0 = Inactive</p>
                            <div class="row">
                                <div class="col-md-3"><div class="form-group"><label>Transfer</label><select name="transfer" class="form-control"><option value="1" <?= $page['transfer']=='1'?'selected':'' ?>>Active</option><option value="0" <?= $page['transfer']=='0'?'selected':'' ?>>Inactive</option></select></div></div>
                                <div class="col-md-3"><div class="form-group"><label>Billing</label><select name="billing_code" class="form-control"><option value="1" <?= $page['billing_code']=='1'?'selected':'' ?>>Active</option><option value="0" <?= $page['billing_code']=='0'?'selected':'' ?>>Inactive</option></select></div></div>
                                <div class="col-md-3"><div class="form-group"><label>Bank Deposit</label><select name="bank_deposit" class="form-control"><option value="1" <?= $page['bank_deposit']=='1'?'selected':'' ?>>Active</option><option value="0" <?= $page['bank_deposit']=='0'?'selected':'' ?>>Inactive</option></select></div></div>
                            </div>

                            <hr>
                            <h5>New signups</h5>
                            <p class="text-muted small">Controls the customer register page. Turning "Allow signups" off hides the page and rejects every register API call.</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Allow signups</label>
                                        <select name="registration_enabled" class="form-control">
                                            <option value="1" <?= (int)($page['registration_enabled'] ?? 1) === 1 ? 'selected' : '' ?>>Open — anyone can register</option>
                                            <option value="0" <?= (int)($page['registration_enabled'] ?? 1) === 0 ? 'selected' : '' ?>>Closed — /register redirects to /login</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Default status for new accounts</label>
                                        <select name="signup_default_status" class="form-control">
                                            <?php $sds = (string)($page['signup_default_status'] ?? 'hold'); ?>
                                            <option value="hold"    <?= $sds === 'hold'    ? 'selected' : '' ?>>Hold — must approve before user can sign in (recommended)</option>
                                            <option value="pending" <?= $sds === 'pending' ? 'selected' : '' ?>>Pending — same as Hold, kept for legacy naming</option>
                                            <option value="active"  <?= $sds === 'active'  ? 'selected' : '' ?>>Active — user can sign in immediately (skips review)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Tawk.to Livechat URL</label>
                                <input type="text" name="livechat" class="form-control" value="<?= htmlspecialchars($page['livechat']) ?>">
                            </div>
                        </div>
                        <div class="card-footer"><button class="btn btn-primary" name="save_settings"><i class="fas fa-save"></i> Save Settings</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card card-secondary card-outline">
                    <div class="card-header"><h3 class="card-title">Legal documents</h3></div>
                    <form method="post">
                        <div class="card-body">
                            <p class="text-muted small mb-3">
                                These blocks power the customer <code>/terms</code> and <code>/privacy</code> pages.
                                Basic HTML is allowed:
                                <code>&lt;p&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>,
                                <code>&lt;ul&gt;</code>, <code>&lt;ol&gt;</code>, <code>&lt;li&gt;</code>,
                                <code>&lt;a href&gt;</code>, <code>&lt;br&gt;</code>,
                                <code>&lt;h2&gt;</code>, <code>&lt;h3&gt;</code>.
                                Anything else is stripped on save.
                            </p>
                            <div class="form-group">
                                <label for="terms_of_service_html">Terms of Service</label>
                                <textarea id="terms_of_service_html" name="terms_of_service_html" class="form-control" rows="20" placeholder="<h2>Terms of Service</h2><p>...</p>"><?= htmlspecialchars((string)($page['terms_of_service_html'] ?? '')) ?></textarea>
                            </div>
                            <div class="form-group">
                                <label for="privacy_policy_html">Privacy Policy</label>
                                <textarea id="privacy_policy_html" name="privacy_policy_html" class="form-control" rows="20" placeholder="<h2>Privacy Policy</h2><p>...</p>"><?= htmlspecialchars((string)($page['privacy_policy_html'] ?? '')) ?></textarea>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-secondary" name="save_terms"><i class="fas fa-save"></i> Save Legal Documents</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
