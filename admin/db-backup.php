<?php
/**
 * Admin utility: on-demand full database dump + download.
 *
 * Layout:
 *   1. Handle POST first, before any HTML shell output, so that the
 *      download handler can stream the file with a fresh Content-Type.
 *      The layout header still uses ob_start(), but starting a stream
 *      inside a live buffer is unnecessarily awkward — bail out early.
 *   2. For non-download POSTs (create / delete) fall through to the
 *      normal AdminLTE layout so we can flash a toast alert.
 *
 * Security posture:
 *   - PDO prepared statements only. Filenames are validated against a
 *     strict regex before touching disk or the DB. Directory traversal
 *     is impossible because we resolve every path against the fixed
 *     backup directory via realpath()/basename() checks.
 *   - mysqldump is invoked with proc_open + an argv array so shell
 *     metacharacters in credentials (env-provided) can't inject.
 */

require_once __DIR__ . '/include/session.php';
require_once __DIR__ . '/include/adminloginFunction.php';
require_once __DIR__ . '/include/adminClass.php';
// The download branch below returns before layout/header.php, which is where
// every other page picks up the notification helpers — so require them here.
// __DIR__-relative because PHP-FPM's CWD is not the script directory.
require_once __DIR__ . '/../include/notifications.php';

if (empty($_SESSION['admin'])) {
    header('Location: ./login.php');
    exit;
}

$backupDir = realpath(__DIR__ . '/../assets') . DIRECTORY_SEPARATOR . 'backups';
$backupFilenamePattern = '/^backup-\d{4}-\d{2}-\d{2}-\d{6}\.sql$/';

/**
 * Resolve the singleton DB connection creds. dbConnect() is defined in
 * include/config.php which reads env vars — we mirror the same lookup
 * so mysqldump gets the same DB the app is talking to. Falling back to
 * the same defaults keeps the local XAMPP dev flow working out of the
 * box.
 */
function backup_db_credentials(): array
{
    return [
        'host'     => getenv('DB_HOST') ?: '127.0.0.1',
        'port'     => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_NAME') ?: 'bankpro',
        'username' => getenv('DB_USERNAME') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
    ];
}

/**
 * Locate a working mysqldump binary. Environment override wins so a
 * production host can point at whatever the cPanel installer left
 * behind. Local XAMPP path is the second option, then PATH lookup.
 */
function backup_locate_mysqldump(): ?string
{
    $candidates = array_filter([
        getenv('MYSQLDUMP_BIN'),
        'C:/xampp/mysql/bin/mysqldump.exe',
        '/usr/bin/mysqldump',
        '/usr/local/bin/mysqldump',
    ]);
    foreach ($candidates as $candidate) {
        if (is_string($candidate) && $candidate !== '' && is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }
    }
    // Fall back to a bare command name so exec() searches PATH. On PHP
    // for Windows this still works when mysqldump.exe is on PATH.
    return 'mysqldump';
}

function backup_ensure_dir(string $dir): bool
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    if (!is_dir($dir)) {
        return false;
    }
    // assets/ is inside the document root, so every dump written here is
    // reachable over plain HTTP by anyone who can guess the timestamped
    // filename — and a dump contains every password hash, PIN and balance in
    // the product. Drop a deny-all next to the files so Apache refuses to
    // serve them; downloads still work because they are streamed by PHP in
    // the handler below, never fetched directly.
    $guard = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!is_file($guard)) {
        @file_put_contents(
            $guard,
            "# Database dumps: never serve these directly.\n"
            . "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n"
        );
    }
    return is_writable($dir);
}

/**
 * Run mysqldump against the credentials, streaming stdout into the
 * given output file. Uses proc_open so the DB password is passed as a
 * literal argv element (never through the shell) and stderr is
 * captured for surfacing failures back to the admin.
 */
function backup_run_mysqldump(string $binary, array $creds, string $outputPath): array
{
    $args = [
        $binary,
        '-h', $creds['host'],
        '-P', (string)$creds['port'],
        '-u', $creds['username'],
        '--single-transaction',
        '--no-tablespaces',
        '--column-statistics=0',
        '--default-character-set=utf8mb4',
    ];
    if ($creds['password'] !== '') {
        // -p<password> as a single token so proc_open doesn't split.
        $args[] = '-p' . $creds['password'];
    }
    $args[] = $creds['database'];

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', $outputPath, 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = @proc_open($args, $descriptors, $pipes);
    if (!is_resource($process)) {
        return ['ok' => false, 'error' => 'Unable to launch mysqldump binary'];
    }
    fclose($pipes[0]);
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0) {
        // Wipe the (likely partial / empty) output so we don't record a
        // ghost backup that would fail on download.
        @unlink($outputPath);
        return ['ok' => false, 'error' => trim($stderr) ?: ('mysqldump exited with code ' . $exit)];
    }
    return ['ok' => true];
}

// ─────────────────────────────────────────────────────────────────────────
// POST: Download — stream file with Content-Disposition. Runs BEFORE the
// layout header so we don't spill HTML into the download stream.
// ─────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_backup'])) {
    // This branch deliberately returns before layout/header.php is included, so
    // it never reaches the panel-wide CSRF gate that lives there. Without this
    // explicit check the single most sensitive action in the product — handing
    // out a full database dump — would be the one POST with no token at all.
    if (!admin_csrf_valid()) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Request blocked: missing or stale security token.';
        exit;
    }
    $filename = (string)($_POST['filename'] ?? '');
    if (!preg_match($backupFilenamePattern, $filename)) {
        http_response_code(400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Invalid backup filename';
        exit;
    }
    $absolute = $backupDir . DIRECTORY_SEPARATOR . $filename;
    // realpath collapses traversal attempts to their true target — any
    // filename that escapes $backupDir will resolve outside it.
    $resolved = realpath($absolute);
    if ($resolved === false || strncmp($resolved, $backupDir . DIRECTORY_SEPARATOR, strlen($backupDir) + 1) !== 0 || !is_file($resolved)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Backup not found';
        exit;
    }
    $downloadBytes = (int)filesize($resolved);
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $downloadBytes);
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-transform, no-store');
    readfile($resolved);
    flush();

    // A full dump leaving the server is the highest-signal exfiltration event
    // in the product, so it must be alerted — but only once the bytes are on
    // the wire. admin_notify() does a DB read plus a synchronous SMTP delivery
    // per recipient, and include/smtp.php sets no Timeout, so PHPMailer's
    // 300-second default applies: running it BEFORE the headers meant an
    // unreachable mail host could stall the request past max_execution_time and
    // fail the operator's download outright. After readfile()+flush() the
    // response is already delivered, and this still runs before the exit.
    admin_notify(
        (new AdminAlert)->adminBackupDownloadedMsg(
            admin_actor_name(),
            $filename,
            number_format($downloadBytes) . ' bytes',
            admin_actor_ip()
        ),
        'Database backup downloaded'
    );

    // $conn comes from adminloginFunction.php's dbConnect(), not from
    // layout/header.php — this branch never reaches the header.
    if (isset($conn) && $conn instanceof PDO) {
        notify_admin(
            $conn,
            'admin.backup_downloaded',
            'Database backup downloaded',
            admin_actor_name() . ' downloaded ' . $filename . ' ('
                . number_format($downloadBytes) . ' bytes) from ' . admin_actor_ip() . '.',
            array(
                'severity'            => 'danger',
                'link'                => '/admin/db-backup.php',
                'source'              => 'admin',
                'created_by_admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
                'meta'                => array('filename' => $filename, 'bytes' => $downloadBytes),
            )
        );
    }
    exit;
}

// Everything below wants the AdminLTE chrome. layout/header.php calls
// ob_start() and pulls in $conn via adminClass.php.
include_once __DIR__ . '/layout/header.php';

// ─────────────────────────────────────────────────────────────────────────
// POST: Create backup
// ─────────────────────────────────────────────────────────────────────────
if (isset($_POST['create_backup'])) {
    if (!backup_ensure_dir($backupDir)) {
        toast_alert('error', 'The assets/backups/ directory is missing or not writable. Create it and grant PHP write access.', 'Cannot write backup');
    } else {
        $filename = 'backup-' . date('Y-m-d-His') . '.sql';
        $absolute = $backupDir . DIRECTORY_SEPARATOR . $filename;
        $binary = backup_locate_mysqldump();
        $result = backup_run_mysqldump($binary, backup_db_credentials(), $absolute);
        if (!$result['ok']) {
            toast_alert('error', 'Backup failed: ' . $result['error'], 'Failed');
        } else {
            $sizeBytes = (int)@filesize($absolute);
            $insert = $conn->prepare("INSERT INTO db_backups (filename, size_bytes, created_at) VALUES (:filename, :size_bytes, NOW())");
            $insert->execute([
                'filename'   => $filename,
                'size_bytes' => $sizeBytes,
            ]);
            // New recovery point recorded in the ledger — notify so the
            // dump's existence on disk is never a surprise.
            admin_notify(
                (new AdminAlert)->adminBackupLifecycleMsg(admin_actor_name(), 'created', $filename, admin_actor_ip()),
                'Database backup created'
            );
            notify_admin(
                $conn,
                'admin.backup_created',
                'Database backup created',
                admin_actor_name() . ' created ' . $filename . ' ('
                    . number_format($sizeBytes) . ' bytes).',
                array(
                    'severity'            => 'info',
                    'link'                => '/admin/db-backup.php',
                    'source'              => 'admin',
                    'created_by_admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
                    'meta'                => array('filename' => $filename, 'bytes' => $sizeBytes),
                )
            );
            toast_alert('success', 'Backup created: ' . $filename . ' (' . number_format($sizeBytes) . ' bytes)', 'Success');
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────
// POST: Delete backup
// ─────────────────────────────────────────────────────────────────────────
if (isset($_POST['delete_backup'])) {
    $filename = (string)($_POST['filename'] ?? '');
    if (!preg_match($backupFilenamePattern, $filename)) {
        toast_alert('error', 'Refusing to delete: invalid filename pattern.', 'Rejected');
    } else {
        $absolute = $backupDir . DIRECTORY_SEPARATOR . $filename;
        $resolved = realpath($absolute);
        $insideBackupDir = $resolved !== false
            && strncmp($resolved, $backupDir . DIRECTORY_SEPARATOR, strlen($backupDir) + 1) === 0;
        // Always drop the DB row even if the file is already gone — that
        // is exactly the "manual delete on disk" case the ledger exists
        // to reconcile.
        $del = $conn->prepare("DELETE FROM db_backups WHERE filename = :filename");
        $del->execute(['filename' => $filename]);
        $rowRemoved  = $del->rowCount() > 0;
        $fileRemoved = false;
        if ($insideBackupDir && is_file($resolved)) {
            $fileRemoved = @unlink($resolved);
        }
        // Destroying a recovery point is a common precursor to tampering,
        // so it gets the same treatment as creation — but only when something
        // was actually destroyed. A POST naming a well-formed filename that has
        // neither a ledger row nor a file on disk would otherwise mail a
        // "backup deleted" alert for a no-op, and an alert that cries wolf is
        // worse than none on an event this serious.
        if ($rowRemoved || $fileRemoved) {
            admin_notify(
                (new AdminAlert)->adminBackupLifecycleMsg(admin_actor_name(), 'deleted', $filename, admin_actor_ip()),
                'Database backup deleted'
            );
            notify_admin(
                $conn,
                'admin.backup_deleted',
                'Database backup deleted',
                admin_actor_name() . ' deleted ' . $filename . '. A recovery point is gone.',
                array(
                    'severity'            => 'danger',
                    'link'                => '/admin/db-backup.php',
                    'source'              => 'admin',
                    'created_by_admin_id' => isset($_SESSION['admin_id']) ? (int)$_SESSION['admin_id'] : null,
                    'meta'                => array('filename' => $filename, 'row_removed' => $rowRemoved, 'file_removed' => $fileRemoved),
                )
            );
        }
        // Report what actually happened. The old unconditional success toast
        // claimed the dump was gone even when unlink() was refused by
        // permissions — leaving a downloadable database dump on disk that the
        // operator had every reason to believe was destroyed.
        if ($insideBackupDir && is_file($resolved) && !$fileRemoved) {
            toast_alert('error', 'The ledger entry was removed but ' . $filename . ' could not be deleted from assets/backups/ — check file permissions, the dump is still on disk.', 'Partly removed');
        } elseif ($rowRemoved || $fileRemoved) {
            toast_alert('success', 'Backup deleted: ' . $filename, 'Removed');
        } else {
            toast_alert('info', 'Nothing to delete: ' . $filename . ' has no ledger entry and no file on disk.', 'No change');
        }
    }
}

// ─────────────────────────────────────────────────────────────────────────
// Load current ledger for display. Left-join disk state so a manually
// deleted file surfaces as "missing" instead of silently disappearing.
// ─────────────────────────────────────────────────────────────────────────
backup_ensure_dir($backupDir);
$rows = [];
$stmt = $conn->prepare("SELECT id, filename, size_bytes, created_at FROM db_backups ORDER BY created_at DESC, id DESC");
$stmt->execute();
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $absolute = $backupDir . DIRECTORY_SEPARATOR . $row['filename'];
    $row['exists_on_disk'] = is_file($absolute);
    $row['size_on_disk'] = $row['exists_on_disk'] ? (int)@filesize($absolute) : 0;
    $rows[] = $row;
}

$backupDirWritable = is_dir($backupDir) && is_writable($backupDir);
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Database Backup</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Database Backup</li>
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
                    <div class="card-header"><h3 class="card-title">Create Backup</h3></div>
                    <div class="card-body">
                        <p class="small text-muted">
                            Produces a full <code>mysqldump</code> of the application database into
                            <code>assets/backups/</code>. Rows are added to <code>db_backups</code> so
                            the list persists across page loads.
                        </p>
                        <p class="small mb-3">
                            Backup directory:
                            <?php if ($backupDirWritable): ?>
                                <span class="badge badge-success">Writable</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Not writable</span>
                                <br><small class="text-muted">Create <code>assets/backups/</code> and give PHP write access.</small>
                            <?php endif; ?>
                        </p>
                        <form method="post" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').innerHTML='<i class=\'fas fa-spinner fa-spin\'></i> Working…';">
                            <button class="btn btn-primary btn-block" name="create_backup" <?= $backupDirWritable ? '' : 'disabled' ?>>
                                <i class="fas fa-database"></i> Create Backup Now
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><h3 class="card-title">Existing Backups</h3></div>
                    <div class="card-body p-0">
                        <?php if (empty($rows)): ?>
                            <div class="p-3 text-muted small">No backups yet. Click <strong>Create Backup Now</strong> to make one.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Filename</th>
                                        <th>Size</th>
                                        <th>Created</th>
                                        <th class="text-right" style="width:1%; white-space:nowrap;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <tr>
                                        <td>
                                            <code><?= htmlspecialchars($r['filename']) ?></code>
                                            <?php if (!$r['exists_on_disk']): ?>
                                                <br><span class="badge badge-warning">Missing on disk</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= number_format((int)$r['size_bytes']) ?> B</td>
                                        <td><small><?= htmlspecialchars($r['created_at']) ?></small></td>
                                        <td class="text-right">
                                            <?php if ($r['exists_on_disk']): ?>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="filename" value="<?= htmlspecialchars($r['filename']) ?>">
                                                    <button class="btn btn-sm btn-success" name="download_backup" title="Download">
                                                        <i class="fas fa-download"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Delete this backup?');">
                                                <input type="hidden" name="filename" value="<?= htmlspecialchars($r['filename']) ?>">
                                                <button class="btn btn-sm btn-danger" name="delete_backup" title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once __DIR__ . '/layout/footer.php'; ?>
