<?php
include_once("./layout/header.php");

/**
 * Branding asset directory.
 *
 * Absolute, derived from __DIR__ — NOT the "../assets/settings/" relative path
 * both handlers below used to use. A relative path is resolved against the
 * process working directory, which equals the script directory under mod_php
 * (so it worked on the developer's XAMPP box) but is the server root or an
 * arbitrary directory under PHP-FPM / FastCGI / suPHP, which is what cPanel
 * runs. There, "../assets/settings/" pointed at a directory that does not
 * exist; mkdir() was suppressed with @, move_uploaded_file() failed, and the
 * logo branch reported nothing at all while the favicon branch's failure
 * message was the only clue.
 */
$brandingDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'settings';

/**
 * Persist a branding filename and confirm the row actually moved.
 *
 * settings.favicon arrived in SQL File/migrations/2026_08_04_01_settings_favicon.sql.
 * On an installation where that migration has not been applied the UPDATE
 * throws (PDO::ERRMODE_EXCEPTION), which previously produced a blank 500 with
 * the file already written to disk — the operator saw neither a success nor a
 * useful failure. Catch it and name the migration instead.
 *
 * @return array{ok:bool,error:string}
 */
$saveBrandingColumn = static function (PDO $conn, string $column, string $value): array {
    $allowed = ['image', 'favicon'];
    if (!in_array($column, $allowed, true)) {
        return ['ok' => false, 'error' => 'Unknown branding column.'];
    }
    try {
        // $column is whitelisted above, never interpolated from request data.
        $stmt = $conn->prepare("UPDATE settings SET {$column} = :value WHERE id = 1");
        $stmt->execute(['value' => $value]);
    } catch (Throwable $e) {
        if (stripos($e->getMessage(), 'unknown column') !== false) {
            return ['ok' => false, 'error' => 'The settings.' . $column . ' column does not exist yet. Apply SQL File/migrations/2026_08_04_01_settings_favicon.sql via the migration console, then upload again.'];
        }
        error_log('[admin settings] branding save failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'The file was saved but the database rejected the update. See error_log.'];
    }

    // rowCount() is 0 both when nothing matched AND when the stored value was
    // already identical, so confirm by reading the column back rather than
    // trusting the affected-row count.
    $check = $conn->prepare("SELECT {$column} FROM settings WHERE id = 1");
    $check->execute();
    $stored = $check->fetchColumn();
    if ($stored === false) {
        return ['ok' => false, 'error' => 'There is no settings row with id = 1 to update.'];
    }
    if ((string)$stored !== $value) {
        return ['ok' => false, 'error' => 'The database did not retain the new filename.'];
    }

    return ['ok' => true, 'error' => ''];
};

if (isset($_POST['upload_picture'])) {
    // Hardened to the same standard as the favicon handler below. Previously
    // this branch had no extension allowlist, no size ceiling, no upload-error
    // check, and built its destination from the RAW client filename — an
    // unrestricted file upload that would happily write `evil.php` into a
    // web-served directory.
    $stored = admin_store_upload(
        $_FILES['image'] ?? [],
        $brandingDir,
        ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'],
        2 * 1024 * 1024,
        'logo'
    );

    if (!$stored['ok']) {
        toast_alert('error', $stored['error'], 'Logo not uploaded');
    } else {
        $saved = $saveBrandingColumn($conn, 'image', $stored['name']);
        if (!$saved['ok']) {
            toast_alert('error', $saved['error'], 'Logo not saved');
        } else {
            $page['image'] = $stored['name'];
            if (function_exists('audit_log')) {
                audit_log('settings.logo_uploaded', 'settings', '1', ['filename' => $stored['name']]);
            }
            toast_alert('success', 'Logo uploaded and saved as ' . $stored['name'] . '.', 'Done');
        }
    }
}

if (isset($_POST['upload_favicon'])) {
    // The on-disk name is standardised so repeated uploads overwrite the
    // current favicon instead of piling up. Note the extension still varies,
    // so a .png upload after a .ico leaves the stale .ico behind — the DB
    // column decides which one is served, and stale siblings are cleaned up
    // below so resolve_favicon()'s legacy favicon.png fallback can't resurrect
    // an old file.
    $stored = admin_store_upload(
        $_FILES['favicon'] ?? [],
        $brandingDir,
        ['ico', 'png', 'svg', 'jpg', 'jpeg'],
        2 * 1024 * 1024,
        'favicon'
    );

    if (!$stored['ok']) {
        toast_alert('error', $stored['error'], 'Favicon not uploaded');
    } else {
        $saved = $saveBrandingColumn($conn, 'favicon', $stored['name']);
        if (!$saved['ok']) {
            toast_alert('error', $saved['error'], 'Favicon not saved');
        } else {
            foreach (['ico', 'png', 'svg', 'jpg', 'jpeg'] as $staleExt) {
                $stale = $brandingDir . DIRECTORY_SEPARATOR . 'favicon.' . $staleExt;
                if ('favicon.' . $staleExt !== $stored['name'] && is_file($stale)) {
                    @unlink($stale);
                }
            }
            $page['favicon'] = $stored['name'];
            if (function_exists('audit_log')) {
                audit_log('settings.favicon_uploaded', 'settings', '1', ['filename' => $stored['name']]);
            }
            toast_alert('success', 'Favicon uploaded and saved as ' . $stored['name'] . '. Browsers cache favicons hard — force-reload if the tab icon looks unchanged.', 'Done');
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

    // Capture the previous row so audit_log can diff old→new for each field.
    $prevSettings = $page ?: [];

    // Signup starting balances/limits. Clamp to [0, 10_000_000] on the
    // server side — the input has max=10000000 but a hand-crafted POST
    // could bypass that.
    $balanceCap = 10000000.00;
    $clampSetting = static function ($value) use ($balanceCap) {
        $n = is_numeric($value) ? (float)$value : 0.0;
        if ($n < 0)           { return 0.0; }
        if ($n > $balanceCap) { return $balanceCap; }
        return $n;
    };
    $defaultBalance      = $clampSetting($_POST['default_balance']       ?? 0);
    $defaultAvailBalance = $clampSetting($_POST['default_avail_balance'] ?? 0);
    $defaultAcctLimit    = $clampSetting($_POST['default_acct_limit']    ?? 0);
    $defaultLimitRemain  = $clampSetting($_POST['default_limit_remain']  ?? 0);

    // Normalise the free-text domain/country lists before saving so the
    // signup API doesn't have to defend against garbage rows.
    $normaliseDomains = static function (string $raw): string {
        $out = [];
        foreach (preg_split('/[\s,]+/', $raw) as $entry) {
            $entry = strtolower(trim((string)$entry));
            if ($entry !== '' && $entry[0] === '@') {
                $entry = substr($entry, 1);
            }
            if ($entry !== '' && preg_match('/^[a-z0-9.\-]+\.[a-z]{2,}$/', $entry)) {
                $out[$entry] = true;
            }
        }
        return implode(', ', array_keys($out));
    };
    $normaliseCountries = static function (string $raw): string {
        $out = [];
        foreach (preg_split('/[\s,]+/', $raw) as $entry) {
            $entry = strtoupper(trim((string)$entry));
            if (preg_match('/^[A-Z]{2}$/', $entry)) {
                $out[$entry] = true;
            }
        }
        return implode(', ', array_keys($out));
    };
    $emailBlocklist   = $normaliseDomains((string)($_POST['signup_email_blocklist']   ?? ''));
    $emailAllowlist   = $normaliseDomains((string)($_POST['signup_email_allowlist']   ?? ''));
    $countryBlocklist = $normaliseCountries((string)($_POST['signup_country_blocklist'] ?? ''));

    $stmt = $conn->prepare("UPDATE settings SET url_name=:url_name, url_tel=:url_tel, about_us=:about_us, url_email=:url_email, livechat=:livechat, trans_limit_min=:trans_limit_min, trans_limit_max=:trans_limit_max, transfer=:transfer, billing_code=:billing_code, bank_deposit=:bank_deposit, registration_enabled=:registration_enabled, signup_default_status=:signup_default_status, default_balance=:default_balance, default_avail_balance=:default_avail_balance, default_acct_limit=:default_acct_limit, default_limit_remain=:default_limit_remain, signup_email_blocklist=:signup_email_blocklist, signup_email_allowlist=:signup_email_allowlist, signup_country_blocklist=:signup_country_blocklist WHERE id=1");
    $stmt->execute([
        'url_name'                 => $_POST['url_name'],
        'url_tel'                  => $_POST['url_tel'],
        'about_us'                 => $_POST['about_us'],
        'url_email'                => $_POST['url_email'],
        'livechat'                 => $_POST['livechat'],
        'trans_limit_min'          => $_POST['trans_limit_min'],
        'trans_limit_max'          => $_POST['trans_limit_max'],
        'transfer'                 => $_POST['transfer'],
        'billing_code'             => $_POST['billing_code'],
        'bank_deposit'             => $_POST['bank_deposit'],
        'registration_enabled'     => (int)($_POST['registration_enabled'] ?? 1),
        'signup_default_status'    => $defaultStatus,
        'default_balance'          => $defaultBalance,
        'default_avail_balance'    => $defaultAvailBalance,
        'default_acct_limit'       => $defaultAcctLimit,
        'default_limit_remain'     => $defaultLimitRemain,
        'signup_email_blocklist'   => $emailBlocklist   !== '' ? $emailBlocklist   : null,
        'signup_email_allowlist'   => $emailAllowlist   !== '' ? $emailAllowlist   : null,
        'signup_country_blocklist' => $countryBlocklist !== '' ? $countryBlocklist : null,
    ]);
    toast_alert('success', 'Settings updated successfully', 'Approved');

    // Best-effort audit trail. Only include fields that actually changed so
    // the log stays terse. about_us is truncated to keep the JSON small.
    $trackedFields = [
        'url_name', 'url_tel', 'about_us', 'url_email', 'livechat',
        'trans_limit_min', 'trans_limit_max', 'transfer', 'billing_code', 'bank_deposit',
        'registration_enabled', 'signup_default_status',
        'default_balance', 'default_avail_balance', 'default_acct_limit', 'default_limit_remain',
        'signup_email_blocklist', 'signup_email_allowlist', 'signup_country_blocklist',
    ];
    $changed = [];
    foreach ($trackedFields as $field) {
        $old = isset($prevSettings[$field]) ? (string)$prevSettings[$field] : '';
        $new = isset($_POST[$field]) ? (string)$_POST[$field] : '';
        if ($old !== $new) {
            $changed[$field] = [
                'old' => $field === 'about_us' ? substr($old, 0, 200) : $old,
                'new' => $field === 'about_us' ? substr($new, 0, 200) : $new,
            ];
        }
    }
    if (function_exists('audit_log')) {
        audit_log('settings.updated', 'settings', '1', ['changed' => $changed]);
    }

    // Same diff, mailed out: transfer limits, the global transfer switch and
    // the signup defaults all move customer money, so a silent edit is not ok.
    if ($changed !== []) {
        admin_notify(
            (new AdminAlert)->adminSettingsChangedMsg(admin_actor_name(), $changed, admin_actor_ip()),
            'Platform settings changed'
        );
    }

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
                        <?php // ?v= keys off the file's mtime. Uploads now overwrite a stable
                              // filename, so without this the preview would keep showing the
                              // previous image and make a successful upload look like a no-op.
                              $logoName = trim((string)($page['image'] ?? '')); ?>
                        <?php if ($logoName !== '' && is_file($brandingDir . DIRECTORY_SEPARATOR . $logoName)): ?>
                            <img src="../assets/settings/<?= htmlspecialchars($logoName) ?>?v=<?= (int)filemtime($brandingDir . DIRECTORY_SEPARATOR . $logoName) ?>" alt="logo" class="img-fluid mb-3" style="max-height:160px;">
                        <?php else: ?>
                            <p class="text-muted small mb-3">No logo uploaded yet.</p>
                        <?php endif; ?>
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
                        <?php // is_file() on a CWD-relative path is the same trap the upload handler
                              // fell into: under PHP-FPM it can resolve outside the document root,
                              // so the preview reported "no favicon" for one that exists. Absolute.
                              ?>
                        <?php if ($faviconName !== '' && is_file($brandingDir . DIRECTORY_SEPARATOR . $faviconName)): ?>
                            <img src="../assets/settings/<?= htmlspecialchars($faviconName) ?>?v=<?= (int)filemtime($brandingDir . DIRECTORY_SEPARATOR . $faviconName) ?>" alt="favicon" class="mb-3" style="max-height:64px; max-width:64px; image-rendering:auto;">
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
                            <h6 class="mt-3">Signup defaults</h6>
                            <p class="text-muted small">Written into the new user row when someone completes /register. Cap: 10,000,000 per field. Leave at 0 to keep accounts empty until an admin funds them.</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Starting balance</label>
                                        <input type="number" step="0.01" min="0" max="10000000" name="default_balance" class="form-control" value="<?= htmlspecialchars((string)($page['default_balance'] ?? '0.00')) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Starting available (pending) balance</label>
                                        <input type="number" step="0.01" min="0" max="10000000" name="default_avail_balance" class="form-control" value="<?= htmlspecialchars((string)($page['default_avail_balance'] ?? '0.00')) ?>">
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Starting account limit</label>
                                        <input type="number" step="0.01" min="0" max="10000000" name="default_acct_limit" class="form-control" value="<?= htmlspecialchars((string)($page['default_acct_limit'] ?? '0.00')) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Starting limit remaining</label>
                                        <input type="number" step="0.01" min="0" max="10000000" name="default_limit_remain" class="form-control" value="<?= htmlspecialchars((string)($page['default_limit_remain'] ?? '0.00')) ?>">
                                    </div>
                                </div>
                            </div>

                            <h6 class="mt-3">Email domain filters</h6>
                            <p class="text-muted small">
                                Comma- or space-separated bare domains (e.g. <code>gmail.com, tempmail.com</code>).
                                <strong>Allowlist wins</strong>: if you set an allowlist, ONLY addresses whose domain is on it can register — everyone else is rejected. Leave allowlist empty to allow all except entries on the blocklist. Both lists reject with the same generic message so probing can't leak which list caught the address.
                            </p>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Email allowlist</label>
                                        <textarea name="signup_email_allowlist" class="form-control" rows="3" placeholder="e.g. yourbank.com, corp.example"><?= htmlspecialchars((string)($page['signup_email_allowlist'] ?? '')) ?></textarea>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Email blocklist</label>
                                        <textarea name="signup_email_blocklist" class="form-control" rows="3" placeholder="e.g. tempmail.com, mailinator.com"><?= htmlspecialchars((string)($page['signup_email_blocklist'] ?? '')) ?></textarea>
                                    </div>
                                </div>
                            </div>

                            <h6 class="mt-3">Country blocklist</h6>
                            <p class="text-muted small">Two-letter ISO codes, comma-separated (e.g. <code>RU, IR, KP</code>). Signups from these countries will be rejected with a specific "not supported" message. Full country names are also accepted at signup, so the check normalises to the ISO code before comparing.</p>
                            <div class="form-group">
                                <textarea name="signup_country_blocklist" class="form-control" rows="2" placeholder="e.g. RU, IR, KP"><?= htmlspecialchars((string)($page['signup_country_blocklist'] ?? '')) ?></textarea>
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
