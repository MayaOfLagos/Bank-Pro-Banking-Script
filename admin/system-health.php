<?php
include_once("./layout/header.php");

/**
 * System health dashboard.
 *
 * Read-only operational snapshot for admins:
 *   - Database connection + version + size + top tables
 *   - SMTP configuration status + test-send button
 *   - Disk usage on the server volume that hosts this file
 *   - Recent PHP error log tail
 *   - Session file count
 *   - Recent admin activity (audit rows in last hour)
 *
 * Every probe is wrapped in try/catch so a single failing check can't
 * take down the whole page.
 */

// -------- SMTP test send handler (mirrors admin/settings.php pattern) ------
if (isset($_POST['test_email'])) {
    $to      = trim($_POST['test_email_to'] ?? WEB_EMAIL);
    $subject = "[SMTP TEST] " . ($pageTitle ?? 'Bank Admin') . " — system-health";
    $body    = "<p>System-health test email sent " . date('Y-m-d H:i:s') . ".</p>"
             . "<p>Host: <code>" . htmlspecialchars(SMTP_HOST) . "</code><br>"
             . "From: <code>" . htmlspecialchars(SMTP_FROM_EMAIL) . "</code><br>"
             . "Port: <code>" . (int)SMTP_PORT . "</code> (" . htmlspecialchars(SMTP_SECURE) . ")</p>";

    if (SMTP_HOST === '#' || SMTP_USERNAME === '#' || SMTP_PASSWORD === '#') {
        toast_alert('warning', 'SMTP is not configured yet. Set SMTP_HOST/USERNAME/PASSWORD in include/config.php.', 'Not configured');
    } elseif (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        toast_alert('error', 'Provide a valid recipient email.', 'Invalid');
    } elseif ($email_message->send_mail($to, $body, $subject)) {
        toast_alert('success', "Test email sent to $to.", 'Sent');
        if (function_exists('audit_log')) {
            audit_log('smtp.test_email_sent', 'smtp', null, ['recipient' => $to]);
        }
    } else {
        toast_alert('error', "Failed to send. Check error_log for details.", 'Failed');
    }
}

// =========================================================================
// DATABASE
// =========================================================================
$dbOk         = false;
$dbVersion    = null;
$dbName       = null;
$dbSizeMb     = null;
$dbTopTables  = [];
$dbError      = null;
try {
    $dbOk      = true;
    $dbVersion = $conn->query('SELECT VERSION()')->fetchColumn();

    // Currently-selected DB name
    $dbName = $conn->query('SELECT DATABASE()')->fetchColumn();

    if ($dbName) {
        $sizeStmt = $conn->prepare("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2)
              FROM information_schema.TABLES
             WHERE table_schema = :db
        ");
        $sizeStmt->execute(['db' => $dbName]);
        $dbSizeMb = $sizeStmt->fetchColumn();

        $topStmt = $conn->prepare("
            SELECT table_name AS name,
                   table_rows AS rows_est,
                   ROUND((data_length + index_length) / 1024 / 1024, 2) AS mb
              FROM information_schema.TABLES
             WHERE table_schema = :db
          ORDER BY (data_length + index_length) DESC
             LIMIT 5
        ");
        $topStmt->execute(['db' => $dbName]);
        $dbTopTables = $topStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (\Throwable $e) {
    $dbOk    = false;
    $dbError = $e->getMessage();
}

// =========================================================================
// SMTP
// =========================================================================
$smtpConfigured = (defined('SMTP_HOST') && SMTP_HOST !== '#'
                && defined('SMTP_USERNAME') && SMTP_USERNAME !== '#'
                && defined('SMTP_PASSWORD') && SMTP_PASSWORD !== '#');

// =========================================================================
// DISK
// =========================================================================
$diskFree = null;
$diskTotal = null;
$diskPct  = null;
try {
    // Use the directory this script lives in — cPanel-style shared hosting
    // usually reports free space for the account quota this way.
    $free  = @disk_free_space(__DIR__);
    $total = @disk_total_space(__DIR__);
    if ($free !== false && $total !== false && $total > 0) {
        $diskFree  = (float)$free;
        $diskTotal = (float)$total;
        $diskPct   = ($diskTotal - $diskFree) / $diskTotal * 100;
    }
} catch (\Throwable $e) {
    // ignore — just leaves the badges empty
}

$fmtBytes = function ($bytes) {
    if ($bytes === null) return '—';
    $units = ['B','KB','MB','GB','TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, 2) . ' ' . $units[$i];
};

// =========================================================================
// PHP ERRORS — tail last 20 lines
// =========================================================================
$errorLogLines = [];
$errorLogPath  = ini_get('error_log');
if (!$errorLogPath || !is_file($errorLogPath)) {
    // Fallback: project-root error_log the cPanel deployment writes to.
    $fallback = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'error_log';
    if (is_file($fallback)) {
        $errorLogPath = $fallback;
    }
}
if ($errorLogPath && is_file($errorLogPath) && is_readable($errorLogPath)) {
    try {
        // Read tail without loading whole file into memory. Cap at 256 KB
        // of the tail — 20 lines will comfortably fit.
        $fp = fopen($errorLogPath, 'rb');
        if ($fp) {
            $size    = filesize($errorLogPath);
            $readLen = min($size, 262144);
            fseek($fp, max(0, $size - $readLen));
            $tail = fread($fp, $readLen);
            fclose($fp);
            $split = preg_split("/\r\n|\n|\r/", (string)$tail);
            // Trim empty tail line if the file ends with a newline.
            if (!empty($split) && trim(end($split)) === '') {
                array_pop($split);
            }
            $errorLogLines = array_slice($split, -20);
        }
    } catch (\Throwable $e) {
        $errorLogLines = ['[system-health] Unable to read error log: ' . $e->getMessage()];
    }
}

// =========================================================================
// SESSIONS — file count in session save path
// =========================================================================
$sessionCount    = null;
$sessionPath     = session_save_path();
$sessionPathReal = $sessionPath;
if ($sessionPath === '' || $sessionPath === false) {
    $sessionPathReal = sys_get_temp_dir();
}
if ($sessionPathReal && is_dir($sessionPathReal) && is_readable($sessionPathReal)) {
    try {
        $count = 0;
        $it = new DirectoryIterator($sessionPathReal);
        foreach ($it as $f) {
            if ($f->isFile() && strncmp($f->getFilename(), 'sess_', 5) === 0) {
                $count++;
            }
        }
        $sessionCount = $count;
    } catch (\Throwable $e) {
        // leave null — will render as "unavailable"
    }
}

// =========================================================================
// RECENT ADMIN ACTIVITY — audit rows in last hour as a coarse uptime proxy
// =========================================================================
$recentAuditCount = null;
try {
    $probe = $conn->query("SHOW TABLES LIKE 'admin_audit_log'");
    if ($probe && $probe->fetch() !== false) {
        $stmt = $conn->query("SELECT COUNT(*) FROM admin_audit_log WHERE created_at >= (NOW() - INTERVAL 1 HOUR)");
        $recentAuditCount = (int)$stmt->fetchColumn();
    }
} catch (\Throwable $e) {
    // leave null
}

// =========================================================================
// OPERATOR ALERT — the probes above only run when an admin opens this page,
// so this is the moment a red probe becomes knowable. Mail one alert for the
// current set of failures, keyed on a hash of that set in the session: a
// refresh re-sends nothing, but a newly-failing probe changes the hash and
// does get through.
// =========================================================================
$healthProblems = [];
if (!$dbOk) {
    $healthProblems['Database'] = 'Connection failed' . ($dbError !== null ? ': ' . $dbError : '');
}
if (!$smtpConfigured) {
    $healthProblems['SMTP'] = 'Not configured — outbound mail, including these alerts, is being dropped';
}
if ($diskPct !== null && $diskPct >= 90) {
    $healthProblems['Disk'] = number_format($diskPct, 1) . '% used, only ' . $fmtBytes($diskFree) . ' free';
}

if (!empty($healthProblems)) {
    // Key on WHICH probes are failing, not on their formatted text. The Disk
    // entry embeds a one-decimal percentage and a byte count, both of which
    // move between requests on a live server — hashing the rendered array meant
    // the key never matched, so every refresh of this dashboard mailed the
    // operators again, from every logged-in admin. Disk is additionally banded
    // in 5% steps so a genuine escalation (91% → 97%) still re-alerts while
    // ordinary drift does not.
    $healthKeyParts = array_keys($healthProblems);
    if (isset($healthProblems['Disk']) && $diskPct !== null) {
        $healthKeyParts[] = 'disk-band-' . (int)($diskPct / 5);
    }
    $healthKey = md5(implode('|', $healthKeyParts));

    if (($_SESSION['health_alert_sent'] ?? null) !== $healthKey) {
        $_SESSION['health_alert_sent'] = $healthKey;
        admin_notify(
            (new AdminAlert)->adminSystemHealthAlertMsg($healthProblems),
            'System health check failed'
        );
    }
}
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>System Health</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">System Health</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">

        <div class="row">
            <!-- Database -->
            <div class="col-md-6">
                <div class="card card-outline card-<?= $dbOk ? 'success' : 'danger' ?>">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-database"></i> Database
                            <?php if ($dbOk): ?>
                                <span class="badge badge-success ml-2">Connected</span>
                            <?php else: ?>
                                <span class="badge badge-danger ml-2">Down</span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (!$dbOk): ?>
                            <p class="text-danger"><?= htmlspecialchars((string)$dbError) ?></p>
                        <?php else: ?>
                            <p class="mb-1"><b>MySQL version:</b> <code><?= htmlspecialchars((string)$dbVersion) ?></code></p>
                            <p class="mb-1"><b>Database:</b> <code><?= htmlspecialchars((string)$dbName) ?></code></p>
                            <p class="mb-3"><b>Total size:</b> <?= $dbSizeMb !== null ? htmlspecialchars((string)$dbSizeMb) . ' MB' : '—' ?></p>

                            <p class="mb-1"><b>Largest 5 tables:</b></p>
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>Table</th><th class="text-right">Rows (est)</th><th class="text-right">Size (MB)</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($dbTopTables)): ?>
                                        <tr><td colspan="3" class="text-muted">No tables reported.</td></tr>
                                    <?php else: foreach ($dbTopTables as $t): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars((string)$t['name']) ?></code></td>
                                            <td class="text-right"><?= (int)$t['rows_est'] ?></td>
                                            <td class="text-right"><?= htmlspecialchars((string)$t['mb']) ?></td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- SMTP -->
            <div class="col-md-6">
                <div class="card card-outline card-<?= $smtpConfigured ? 'success' : 'warning' ?>">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-envelope"></i> SMTP
                            <?php if ($smtpConfigured): ?>
                                <span class="badge badge-success ml-2">Configured</span>
                            <?php else: ?>
                                <span class="badge badge-warning ml-2">Not configured</span>
                            <?php endif; ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <p class="small mb-1"><b>Host:</b> <code><?= htmlspecialchars(defined('SMTP_HOST') ? SMTP_HOST : '') ?></code></p>
                        <p class="small mb-1"><b>Port:</b> <code><?= defined('SMTP_PORT') ? (int)SMTP_PORT : '' ?></code> (<?= htmlspecialchars(defined('SMTP_SECURE') ? SMTP_SECURE : '') ?>)</p>
                        <p class="small mb-1"><b>From:</b> <code><?= htmlspecialchars(defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : '') ?></code></p>
                        <p class="small text-muted mb-3">Configure via <code>include/config.php</code> or SMTP_* env vars.</p>

                        <form method="post">
                            <div class="form-group">
                                <label class="small mb-1">Send test email to</label>
                                <input type="email" name="test_email_to" class="form-control form-control-sm" placeholder="recipient@example.com" value="<?= htmlspecialchars(defined('WEB_EMAIL') ? WEB_EMAIL : '') ?>" required>
                            </div>
                            <button class="btn btn-secondary btn-sm btn-block" name="test_email" <?= $smtpConfigured ? '' : 'disabled' ?>>
                                <i class="fas fa-paper-plane"></i> Send Test Email
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Disk -->
            <div class="col-md-4">
                <div class="card card-outline card-info">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-hdd"></i> Disk</h3></div>
                    <div class="card-body">
                        <?php if ($diskTotal === null): ?>
                            <p class="text-muted small mb-0">Disk stats unavailable on this host.</p>
                        <?php else: ?>
                            <p class="mb-1"><b>Free:</b> <?= htmlspecialchars($fmtBytes($diskFree)) ?></p>
                            <p class="mb-1"><b>Total:</b> <?= htmlspecialchars($fmtBytes($diskTotal)) ?></p>
                            <p class="mb-2"><b>Used:</b> <?= number_format($diskPct, 1) ?>%</p>
                            <div class="progress" style="height: 12px;">
                                <?php
                                    $pctInt = (int)round($diskPct);
                                    $barColor = $pctInt >= 90 ? 'bg-danger' : ($pctInt >= 75 ? 'bg-warning' : 'bg-success');
                                ?>
                                <div class="progress-bar <?= $barColor ?>" role="progressbar"
                                     style="width: <?= $pctInt ?>%"
                                     aria-valuenow="<?= $pctInt ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sessions -->
            <div class="col-md-4">
                <div class="card card-outline card-info">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-users"></i> Sessions</h3></div>
                    <div class="card-body">
                        <p class="mb-1"><b>Active session files:</b>
                            <?php if ($sessionCount === null): ?>
                                <span class="text-muted">unavailable</span>
                            <?php else: ?>
                                <?= (int)$sessionCount ?>
                            <?php endif; ?>
                        </p>
                        <p class="small text-muted mb-0"><b>Path:</b> <code><?= htmlspecialchars((string)$sessionPathReal) ?></code></p>
                        <p class="small text-muted mb-0 mt-2">Counts files matching <code>sess_*</code>. Includes expired sessions until GC runs.</p>
                    </div>
                </div>
            </div>

            <!-- Recent activity -->
            <div class="col-md-4">
                <div class="card card-outline card-info">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-heartbeat"></i> Recent Admin Activity</h3></div>
                    <div class="card-body">
                        <?php if ($recentAuditCount === null): ?>
                            <p class="text-muted small mb-0">Audit log unavailable — run the audit-log migration to enable this metric.</p>
                        <?php else: ?>
                            <p class="mb-1"><b>Audit rows in last hour:</b> <?= (int)$recentAuditCount ?></p>
                            <p class="small text-muted mb-0">Proxy for admin activity; zero for a long stretch may mean nobody's logged in — not necessarily a fault.</p>
                            <a href="./audit-log.php" class="btn btn-sm btn-link p-0 mt-2">View audit log →</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card card-outline card-secondary">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-exclamation-triangle"></i> PHP Error Log (tail)</h3>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">
                            <b>Source:</b>
                            <?= $errorLogPath ? '<code>' . htmlspecialchars($errorLogPath) . '</code>' : '<span class="text-muted">no error log found</span>' ?>
                        </p>
                        <?php if (empty($errorLogLines)): ?>
                            <p class="text-muted small mb-0">No recent log lines to show.</p>
                        <?php else: ?>
                            <pre style="max-height: 320px; overflow:auto; font-size: 12px; background:#f4f6f9; padding:12px; border-radius:4px;"><?php
                                foreach ($errorLogLines as $line) {
                                    echo htmlspecialchars($line) . "\n";
                                }
                            ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
