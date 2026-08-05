<?php
declare(strict_types=1);

/**
 * Web migration console — apply schema migrations without phpMyAdmin.
 *
 * Security posture (this endpoint can ALTER the production database, so
 * every one of these matters):
 *   - Admin session required. Anonymous hits redirect to admin/login.php
 *     and never touch the database.
 *   - POST is CSRF-checked against the session token from api/_security.php.
 *   - There is NO free-text SQL input. The only thing that can be executed
 *     is a .sql file that already exists inside SQL File/, resolved with
 *     realpath() and verified to sit under that directory, so a crafted
 *     "file" parameter cannot escape via ../ or an absolute path.
 *   - Every run is written to admin_audit_log (best-effort; the helper
 *     no-ops when the table does not exist yet, which is the normal state
 *     before the very first migration).
 *
 * Deliberately self-contained rather than reusing the AdminLTE layout:
 * this page has to stay usable against a database that has NOT been
 * migrated yet, so it avoids depending on columns the migration adds.
 */

require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/audit.php';
// smtp.php declares `class message`, which admin_notify() checks for before it
// will attempt delivery. This page has no other include that reaches it — unlike
// the admin pages, which pick it up via adminClass.php — so without this the
// migration alert below would silently log "mailer unavailable" and never send.
require_once __DIR__ . '/include/smtp.php';
require_once __DIR__ . '/include/admin_alerts.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

require_once __DIR__ . '/api/_security.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

if (empty($_SESSION['admin'])) {
    header('Location: admin/login.php');
    exit;
}

const MIGRATION_SQL_ROOT = 'SQL File';
const MIGRATION_RECOMMENDED = 'catchup-from-2026-08-03-dump.sql';

/**
 * Statement kinds a migration is allowed to contain.
 *
 * An allowlist, not a blocklist, so anything unanticipated fails closed.
 */
const MIGRATION_ALLOWED_PREFIXES = [
    'SET ', 'PREPARE ', 'EXECUTE ', 'DEALLOCATE ',
    'CREATE TABLE ', 'ALTER TABLE ', 'CREATE INDEX ', 'CREATE UNIQUE INDEX ',
    'START TRANSACTION', 'COMMIT', 'SELECT ',
];

/** Verbs that write or destroy data. Named individually for clear messages. */
const MIGRATION_FORBIDDEN_VERBS = [
    'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'TRUNCATE', 'DROP',
    'LOCK TABLES', 'UNLOCK TABLES', 'GRANT', 'REVOKE', 'CREATE USER', 'LOAD DATA',
];

/**
 * Why this file must not be executed, or null when it is safe.
 *
 * Gating on *content* rather than filename is what stops the full database
 * dumps in SQL File/ from being runnable: they open with DROP TABLE and
 * carry INSERTs, so pointing this console at one would wipe production and
 * replace it with a snapshot. It also structurally enforces the rule that
 * migrations are schema-only and never seed data.
 */
function migration_rejection_reason(string $sql): ?string
{
    $statements = migration_split($sql);
    if (!$statements) {
        return 'contains no executable statements';
    }

    foreach ($statements as $statement) {
        $normalized = strtoupper(trim(preg_replace('/\s+/', ' ', $statement) ?? ''));

        foreach (MIGRATION_FORBIDDEN_VERBS as $verb) {
            if (strpos($normalized, $verb . ' ') === 0 || $normalized === $verb) {
                $article = strpos('AEIOU', $verb[0]) !== false ? 'an' : 'a';
                return 'contains ' . $article . ' ' . $verb . ' statement — this console only applies schema changes';
            }
        }
        // ALTER TABLE ... DROP COLUMN destroys data even though it is DDL.
        if (strpos($normalized, 'ALTER TABLE ') === 0 && preg_match('/\bDROP\b/', $normalized)) {
            return 'contains ALTER TABLE ... DROP, which would destroy column data';
        }

        $recognised = false;
        foreach (MIGRATION_ALLOWED_PREFIXES as $prefix) {
            if (strpos($normalized, $prefix) === 0) {
                $recognised = true;
                break;
            }
        }
        if (!$recognised) {
            return 'contains an unrecognised statement: ' . mb_strimwidth($statement, 0, 60, '…');
        }
    }

    return null;
}

/**
 * Every .sql file under SQL File/, mapped to its rejection reason (or null
 * when runnable). Paths are relative so nothing absolute reaches the browser.
 */
function migration_file_index(): array
{
    static $index = null;
    if ($index !== null) {
        return $index;
    }

    $root = realpath(__DIR__ . '/' . MIGRATION_SQL_ROOT);
    if ($root === false) {
        return $index = [];
    }

    $paths = array_merge(glob($root . '/*.sql') ?: [], glob($root . '/migrations/*.sql') ?: []);
    $index = [];
    foreach ($paths as $path) {
        $relative = basename(dirname($path)) === 'migrations'
            ? 'migrations/' . basename($path)
            : basename($path);
        $index[$relative] = migration_rejection_reason((string)file_get_contents($path));
    }
    ksort($index);
    return $index;
}

function migration_runnable_files(): array
{
    return array_keys(array_filter(migration_file_index(), function ($reason) { return $reason === null; }));
}

/**
 * What the scanner actually saw on disk.
 *
 * An empty dropdown has several unrelated causes — the directory never
 * reached the server, the deploy is behind, the directory is unreadable by
 * the PHP user, or every file was rejected by the content gate — and they
 * need opposite fixes. The page previously rendered an empty <select> and
 * a disabled button with no explanation for any of them, which is
 * indistinguishable from "the console is broken" on a box where there is
 * no shell to go and look.
 */
function migration_scan_report(): array
{
    $expected = __DIR__ . '/' . MIGRATION_SQL_ROOT;
    $root = realpath($expected);
    $index = migration_file_index();

    return [
        'expected'  => $expected,
        'root'      => $root === false ? null : $root,
        'readable'  => $root !== false && is_readable($root),
        'subdir'    => $root !== false && is_dir($root . '/migrations'),
        'seen'      => count($index),
        'runnable'  => count(migration_runnable_files()),
    ];
}

/**
 * Resolve a user-supplied relative name to an absolute path, or null.
 *
 * Containment is checked on the *resolved* path, so symlinks, "../" and
 * absolute paths all fail closed — and the name must appear in the runnable
 * set, so a forged POST cannot reach a dump or a rejected file.
 */
function migration_resolve(string $relative): ?string
{
    $root = realpath(__DIR__ . '/' . MIGRATION_SQL_ROOT);
    if ($root === false || $relative === '') {
        return null;
    }
    if (!in_array($relative, migration_runnable_files(), true)) {
        return null;
    }

    $path = realpath($root . '/' . $relative);
    if ($path === false || !is_file($path)) {
        return null;
    }
    if (strncmp($path, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) !== 0) {
        return null;
    }
    return $path;
}

/**
 * Split a .sql file into executable statements.
 *
 * Naive explode(';') breaks on this codebase's guarded migrations, whose
 * ALTER statements are embedded as single-quoted SQL literals. Tracks
 * quoting ('' and backslash escapes), backtick identifiers, and strips
 * comments so an apostrophe inside a comment cannot unbalance the parser.
 */
function migration_split(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $inSingle = false;
    $inDouble = false;
    $inTick = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if (!$inSingle && !$inDouble && !$inTick) {
            // -- line comment (requires trailing space/newline per SQL spec)
            if ($char === '-' && $next === '-') {
                $stop = strpos($sql, "\n", $i);
                $i = $stop === false ? $length : $stop;
                continue;
            }
            // # line comment
            if ($char === '#') {
                $stop = strpos($sql, "\n", $i);
                $i = $stop === false ? $length : $stop;
                continue;
            }
            // /* block comment */
            if ($char === '/' && $next === '*') {
                $stop = strpos($sql, '*/', $i + 2);
                $i = $stop === false ? $length : $stop + 1;
                continue;
            }
            if ($char === ';') {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $buffer = '';
                continue;
            }
        }

        if ($char === '\\' && ($inSingle || $inDouble)) {
            // Escape sequence: consume the escaped character verbatim.
            $buffer .= $char . $next;
            $i++;
            continue;
        }
        if ($char === "'" && !$inDouble && !$inTick) {
            $inSingle = !$inSingle;
        } elseif ($char === '"' && !$inSingle && !$inTick) {
            $inDouble = !$inDouble;
        } elseif ($char === '`' && !$inSingle && !$inDouble) {
            $inTick = !$inTick;
        }

        $buffer .= $char;
    }

    $trimmed = trim($buffer);
    if ($trimmed !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}

/**
 * Read the objects a guarded migration intends to create.
 *
 * Parsed out of the file itself rather than hard-coded, so the status
 * panel cannot drift from the SQL. Files without INFORMATION_SCHEMA
 * guards simply report nothing and the UI says so.
 */
function migration_expected_objects(string $sql): array
{
    $tables = [];
    $columns = [];

    if (preg_match_all('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+`([^`]+)`/i', $sql, $m)) {
        $tables = array_values(array_unique($m[1]));
    }
    if (preg_match_all("/TABLE_NAME='([^']+)'\s+AND\s+COLUMN_NAME='([^']+)'/i", $sql, $m, PREG_SET_ORDER)) {
        foreach ($m as $hit) {
            $columns[$hit[1] . '.' . $hit[2]] = ['table' => $hit[1], 'column' => $hit[2]];
        }
    }

    return ['tables' => $tables, 'columns' => array_values($columns)];
}

/**
 * Compare the expected objects against what the database actually has.
 */
function migration_status(PDO $conn, array $expected): array
{
    $present = ['tables' => [], 'columns' => []];
    $missing = ['tables' => [], 'columns' => []];

    foreach ($expected['tables'] as $table) {
        $stmt = $conn->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
        );
        $stmt->execute(['t' => $table]);
        if ((int)$stmt->fetchColumn() > 0) {
            $present['tables'][] = $table;
        } else {
            $missing['tables'][] = $table;
        }
    }

    foreach ($expected['columns'] as $col) {
        $stmt = $conn->prepare(
            'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c'
        );
        $stmt->execute(['t' => $col['table'], 'c' => $col['column']]);
        $label = $col['table'] . '.' . $col['column'];
        if ((int)$stmt->fetchColumn() > 0) {
            $present['columns'][] = $label;
        } else {
            $missing['columns'][] = $label;
        }
    }

    return ['present' => $present, 'missing' => $missing];
}

/**
 * Execute the statements in order, stopping at the first failure.
 *
 * No wrapping transaction: MySQL implicitly commits on DDL, so a
 * transaction here would imply a rollback guarantee that does not exist.
 * Instead we report exactly how far the run got.
 */
function migration_execute(PDO $conn, array $statements): array
{
    $results = [];
    foreach ($statements as $index => $sql) {
        try {
            $stmt = $conn->query($sql);
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }
            $results[] = ['n' => $index + 1, 'sql' => $sql, 'ok' => true, 'error' => null];
        } catch (Throwable $e) {
            $results[] = ['n' => $index + 1, 'sql' => $sql, 'ok' => false, 'error' => $e->getMessage()];
            break;
        }
    }
    return $results;
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// ---------------------------------------------------------------------
// Request handling
// ---------------------------------------------------------------------

$files = migration_runnable_files();
$blocked = array_filter(migration_file_index(), function ($reason) { return $reason !== null; });
$selected = (string)($_REQUEST['file'] ?? MIGRATION_RECOMMENDED);
if (!in_array($selected, $files, true)) {
    $selected = in_array(MIGRATION_RECOMMENDED, $files, true) ? MIGRATION_RECOMMENDED : (string)($files[0] ?? '');
}

$csrfToken = api_csrf_token();
$runResults = null;
$runTotal = 0;
$flash = null;
$flashType = 'error';

$conn = null;
$connectionError = null;
try {
    $conn = dbConnect();
    if (!$conn instanceof PDO) {
        $connectionError = 'dbConnect() did not return a PDO connection. Check include/config.php.';
    }
} catch (Throwable $e) {
    $connectionError = 'Could not connect to the database: ' . $e->getMessage();
}

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
    $provided = (string)($_POST['csrf_token'] ?? '');
    if ($provided === '' || !hash_equals($csrfToken, $provided)) {
        $flash = 'Your security token expired. Reload the page and try again.';
    } elseif ($conn === null) {
        $flash = $connectionError ?? 'No database connection.';
    } else {
        $path = migration_resolve((string)($_POST['file'] ?? ''));
        if ($path === null) {
            $flash = 'That migration file is not in the allowed list.';
        } else {
            $sql = (string)file_get_contents($path);
            $statements = migration_split($sql);
            $runTotal = count($statements);
            $runResults = migration_execute($conn, $statements);

            $failed = array_filter($runResults, function ($r) { return !$r['ok']; });
            $okCount = count($runResults) - count($failed);

            if ($failed) {
                $flash = 'Stopped after ' . $okCount . ' of ' . count($statements)
                    . ' statements — see the error below. Nothing after the failure was run.';
            } else {
                $flash = 'Applied cleanly: ' . $okCount . ' statement' . ($okCount === 1 ? '' : 's') . ' executed.';
                $flashType = 'ok';
            }

            audit_log('migration.run', 'migration', basename($path), [
                'file' => $_POST['file'],
                'statements' => count($statements),
                'succeeded' => $okCount,
                'failed' => count($failed),
            ]);

            // The audit row only helps someone who thinks to look. Schema DDL
            // applied to production out of band is a compromise signal, so it
            // is pushed to the operators as well.
            //
            // $okCount, not count($statements): a run that dies on statement 1
            // of 12 would otherwise mail "Statements: 12" and read as a clean
            // schema change, contradicting the audit row written directly
            // above. A partial run is still worth alerting on — arguably more
            // so — hence no success-only guard, but the subject says which it
            // was.
            admin_notify(
                (new AdminAlert)->adminMigrationRunMsg(admin_actor_name(), (string)$_POST['file'], $okCount, admin_actor_ip()),
                $failed
                    ? 'Database migration FAILED part-way (' . $okCount . ' of ' . count($statements) . ' applied)'
                    : 'Database migration executed'
            );
        }
    }
}

$expected = ['tables' => [], 'columns' => []];
$status = null;
$fileSql = '';
$selectedPath = migration_resolve($selected);
if ($selectedPath !== null) {
    $fileSql = (string)file_get_contents($selectedPath);
    $expected = migration_expected_objects($fileSql);
    if ($conn !== null && ($expected['tables'] || $expected['columns'])) {
        try {
            $status = migration_status($conn, $expected);
        } catch (Throwable $e) {
            $connectionError = 'Could not read schema state: ' . $e->getMessage();
        }
    }
}

$dbName = '';
$dbVersion = '';
if ($conn !== null) {
    try {
        $dbName = (string)$conn->query('SELECT DATABASE()')->fetchColumn();
        $dbVersion = (string)$conn->query('SELECT VERSION()')->fetchColumn();
    } catch (Throwable $e) {
        $dbName = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Migration console</title>
<style>
:root { color-scheme: light dark; }
* { box-sizing: border-box; }
body { margin:0; padding:32px 20px; font:15px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; background:#0f1419; color:#e6edf3; }
.wrap { max-width:960px; margin:0 auto; }
h1 { font-size:22px; margin:0 0 4px; }
.sub { color:#8b949e; margin:0 0 24px; font-size:14px; }
.card { background:#161b22; border:1px solid #30363d; border-radius:10px; padding:20px; margin-bottom:18px; }
.card h2 { font-size:15px; margin:0 0 14px; text-transform:uppercase; letter-spacing:.06em; color:#8b949e; }
.meta { display:flex; flex-wrap:wrap; gap:10px 28px; font-size:14px; }
.meta div { min-width:140px; }
.meta span { display:block; color:#8b949e; font-size:12px; }
code, pre { font-family:ui-monospace,SFMono-Regular,Consolas,monospace; }
select, button { font:inherit; }
select { background:#0d1117; color:#e6edf3; border:1px solid #30363d; border-radius:6px; padding:9px 12px; min-width:340px; max-width:100%; }
button { background:#238636; color:#fff; border:0; border-radius:6px; padding:10px 18px; cursor:pointer; font-weight:600; }
button:hover { background:#2ea043; }
button[disabled] { background:#30363d; color:#8b949e; cursor:not-allowed; }
.flash { padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:14px; }
.flash.ok { background:#0f2f1a; border:1px solid #238636; color:#7ee787; }
.flash.error { background:#3a1418; border:1px solid #a5303b; color:#ff9aa4; }
.pill { display:inline-block; padding:2px 9px; border-radius:999px; font-size:12px; font-weight:600; }
.pill.ok { background:#0f2f1a; color:#7ee787; }
.pill.miss { background:#3a2a10; color:#ffc680; }
ul.objs { list-style:none; padding:0; margin:8px 0 0; display:flex; flex-wrap:wrap; gap:6px; }
ul.objs li { background:#0d1117; border:1px solid #30363d; border-radius:5px; padding:3px 9px; font-size:12.5px; font-family:ui-monospace,Consolas,monospace; }
ul.objs li.miss { border-color:#7a5a20; color:#ffc680; }
.run { border-top:1px solid #30363d; padding:10px 0; font-size:13px; display:flex; gap:10px; align-items:flex-start; }
.run:first-of-type { border-top:0; }
.run .n { color:#8b949e; min-width:28px; }
.run pre { margin:0; white-space:pre-wrap; word-break:break-word; flex:1; font-size:12.5px; color:#c9d1d9; }
.run .err { color:#ff9aa4; margin-top:6px; }
.note { color:#8b949e; font-size:13px; margin-top:14px; }
details { margin-top:10px; }
summary { cursor:pointer; color:#58a6ff; font-size:13px; padding:4px 0; }
a { color:#58a6ff; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Migration console</h1>
  <p class="sub">Applies schema migrations to the live database using the connection from <code>include/config.php</code>.</p>

  <?php if ($flash !== null): ?>
    <div class="flash <?= h($flashType) ?>"><?= h($flash) ?></div>
  <?php endif; ?>
  <?php if ($connectionError !== null): ?>
    <div class="flash error"><?= h($connectionError) ?></div>
  <?php endif; ?>

  <div class="card">
    <h2>Connection</h2>
    <div class="meta">
      <div><span>Database</span><?= $dbName !== '' ? h($dbName) : '—' ?></div>
      <div><span>Server</span><?= $dbVersion !== '' ? h($dbVersion) : '—' ?></div>
      <div><span>Signed in as</span><?= h((string)$_SESSION['admin']) ?></div>
    </div>
  </div>

  <div class="card">
    <h2>Run a migration</h2>
    <?php if (!$files): ?>
      <?php $scan = migration_scan_report(); ?>
      <div class="flash error">No migration is available to run.</div>
      <div class="meta" style="margin-bottom:12px">
        <div><span>Looking in</span><code><?= h($scan['expected']) ?></code></div>
        <div><span>Directory</span><?= $scan['root'] === null ? 'not found' : ($scan['readable'] ? 'found' : 'found but unreadable') ?></div>
        <div><span>migrations/ subfolder</span><?= $scan['subdir'] ? 'present' : 'missing' ?></div>
        <div><span>.sql files seen</span><?= (int)$scan['seen'] ?></div>
      </div>
      <p class="note">
        <?php if ($scan['root'] === null): ?>
          The <code><?= h(MIGRATION_SQL_ROOT) ?></code> directory is not on this server. It is
          committed to the repository, so this almost always means the deploy has not run since
          the migrations were added — trigger <em>Update from Remote and Deploy HEAD</em> in
          cPanel &gt; Git Version Control, then reload this page.
        <?php elseif (!$scan['readable']): ?>
          The directory exists but PHP cannot read it. Check its permissions (755) and owner.
        <?php elseif (!$scan['subdir']): ?>
          The directory is present but its <code>migrations/</code> subfolder is not, so the deploy
          is behind the repository. Trigger <em>Update from Remote and Deploy HEAD</em> in
          cPanel &gt; Git Version Control, then reload this page.
        <?php elseif ($scan['seen'] > 0): ?>
          All <?= (int)$scan['seen'] ?> file<?= $scan['seen'] === 1 ? ' was' : 's were' ?> rejected by
          the content gate — see “Not runnable here” below for the reason on each.
        <?php else: ?>
          The directory is present and readable but holds no <code>.sql</code> files, so the deploy
          copied an empty folder. Trigger <em>Update from Remote and Deploy HEAD</em> in
          cPanel &gt; Git Version Control, then reload this page.
        <?php endif; ?>
      </p>
    <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
      <select name="file" onchange="this.form.action=''; this.form.method='get'; this.form.submit();">
        <?php foreach ($files as $file): ?>
          <option value="<?= h($file) ?>" <?= $file === $selected ? 'selected' : '' ?>>
            <?= h($file) ?><?= $file === MIGRATION_RECOMMENDED ? '  (recommended)' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <p class="note">
        Safe to run more than once — every statement in the recommended file is guarded,
        so re-running changes nothing. Take a backup from
        <a href="admin/db-backup.php">Database backup</a> first if you want a restore point.
        Reading <?= (int)count($files) ?> runnable file<?= count($files) === 1 ? '' : 's' ?> from
        <code><?= h(MIGRATION_SQL_ROOT) ?></code>.
      </p>
      <button type="submit" name="action" value="apply" <?= ($conn === null || $selectedPath === null) ? 'disabled' : '' ?>>
        Apply <?= h($selected) ?>
      </button>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($status !== null): ?>
    <div class="card">
      <h2>Schema status</h2>
      <?php
        $missingCount = count($status['missing']['tables']) + count($status['missing']['columns']);
        $totalCount = $missingCount + count($status['present']['tables']) + count($status['present']['columns']);
      ?>
      <?php if ($missingCount === 0): ?>
        <p><span class="pill ok">Up to date</span> All <?= (int)$totalCount ?> objects from this file are already present.</p>
      <?php else: ?>
        <p><span class="pill miss"><?= (int)$missingCount ?> pending</span> of <?= (int)$totalCount ?> objects in this file.</p>
        <?php if ($status['missing']['tables']): ?>
          <p class="note">Missing tables</p>
          <ul class="objs"><?php foreach ($status['missing']['tables'] as $t): ?><li class="miss"><?= h($t) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <?php if ($status['missing']['columns']): ?>
          <p class="note">Missing columns</p>
          <ul class="objs"><?php foreach ($status['missing']['columns'] as $c): ?><li class="miss"><?= h($c) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php elseif ($selectedPath !== null && $conn !== null): ?>
    <div class="card">
      <h2>Schema status</h2>
      <p class="note">This file has no <code>INFORMATION_SCHEMA</code> guards, so its state cannot be detected in advance. It may error if it has already been applied.</p>
    </div>
  <?php endif; ?>

  <?php if ($blocked): ?>
    <div class="card">
      <h2>Not runnable here</h2>
      <p class="note">These <?= count($blocked) ?> file<?= count($blocked) === 1 ? '' : 's' ?> in <code>SQL File/</code> are deliberately excluded — this console applies schema changes only, so anything that writes or destroys rows is refused. Restore a full dump with phpMyAdmin or <code>mysql</code> if you genuinely need one.</p>
      <?php foreach ($blocked as $file => $reason): ?>
        <div class="run">
          <div style="flex:1"><pre><?= h($file) ?></pre><div class="err"><?= h($reason) ?></div></div>
          <span class="pill miss">blocked</span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($runResults !== null): ?>
    <div class="card">
      <h2>Run output</h2>
      <?php
        $bad = array_values(array_filter($runResults, function ($r) { return !$r['ok']; }));
        $good = array_values(array_filter($runResults, function ($r) { return $r['ok']; }));
        $skipped = $runTotal - count($runResults);
      ?>
      <p>
        <span class="pill <?= $bad ? 'miss' : 'ok' ?>"><?= count($good) ?> of <?= (int)$runTotal ?> succeeded</span>
        <?php if ($skipped > 0): ?>
          <span class="note"><?= (int)$skipped ?> not attempted after the failure</span>
        <?php endif; ?>
      </p>
      <?php foreach ($bad as $r): ?>
        <div class="run">
          <span class="n"><?= (int)$r['n'] ?></span>
          <div style="flex:1">
            <pre><?= h(mb_strimwidth(preg_replace('/\s+/', ' ', $r['sql']) ?? '', 0, 220, '…')) ?></pre>
            <div class="err"><?= h($r['error']) ?></div>
          </div>
          <span class="pill miss">failed</span>
        </div>
      <?php endforeach; ?>
      <?php if ($good): ?>
        <details>
          <summary>Show the <?= count($good) ?> statement<?= count($good) === 1 ? '' : 's' ?> that ran</summary>
          <?php foreach ($good as $r): ?>
            <div class="run">
              <span class="n"><?= (int)$r['n'] ?></span>
              <pre><?= h(mb_strimwidth(preg_replace('/\s+/', ' ', $r['sql']) ?? '', 0, 220, '…')) ?></pre>
            </div>
          <?php endforeach; ?>
        </details>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <p class="note">
    Signed in as an administrator. <a href="admin/dashboard.php">Back to admin</a> ·
    <a href="admin/logout.php">Log out</a>
  </p>
</div>
</body>
</html>
