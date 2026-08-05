<?php
/**
 * Exercises the notification foundation against the real local database:
 * schema shape, emitter defaults, allowlist fallbacks, cron-free scheduling
 * visibility, and — the one that actually matters — cross-account isolation.
 *
 * Run: php tests/notifications.php
 *
 * Writes only to `notifications`, and only rows whose event key starts with
 * the throwaway prefix below. Every one of them is deleted in the shutdown
 * handler, including on a fatal, so a failed run cannot leave litter behind.
 * Do not point this at production.
 */
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/notifications.php';

$pass = 0; $fail = 0;
function ok(string $label, bool $cond) {
    global $pass, $fail;
    if ($cond) { $pass++; } else { $fail++; echo "  FAIL  {$label}\n"; }
}

try {
    $conn = dbConnect();
} catch (Throwable $e) {
    echo "Could not connect to the local database: " . $e->getMessage() . "\n";
    exit(1);
}

// Synthetic user ids far above anything a real users table will hold. We
// never insert into `users` — the notifications table has no FK by design,
// which is exactly what lets this test stay self-contained.
$PREFIX = 'test.notif';
$USER_A = 990000001;
$USER_B = 990000002;

$cleanup = function () use ($conn, $PREFIX) {
    try {
        $conn->prepare('DELETE FROM notifications WHERE event LIKE :p')
             ->execute(['p' => $PREFIX . '%']);
    } catch (Throwable $e) {
        echo "  WARN  cleanup failed: " . $e->getMessage() . "\n";
    }
};
register_shutdown_function($cleanup);
$cleanup(); // in case a previous run died before its shutdown handler

// ─── Schema ───────────────────────────────────────────────────────────────
echo "Schema\n";
$tableExists = false;
try {
    $tableExists = (bool)$conn->query("SHOW TABLES LIKE 'notifications'")->fetchColumn();
} catch (Throwable $e) {
    $tableExists = false;
}
ok('notifications table exists (apply 2026_08_05_02_notifications.sql)', $tableExists);
if (!$tableExists) {
    echo "\n{$pass} assertions passed, {$fail} failed\n";
    exit(1);
}

$columns = [];
foreach ($conn->query('SHOW COLUMNS FROM notifications')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    $columns[$col['Field']] = $col;
}
$expected = [
    'id', 'audience', 'user_id', 'event', 'title', 'body', 'severity', 'link',
    'meta', 'source', 'created_by_admin_id', 'scheduled_at', 'read_at', 'created_at',
];
foreach ($expected as $name) {
    ok("column {$name} exists", isset($columns[$name]));
}
ok('user_id is nullable (admin rows carry none)', isset($columns['user_id']) && $columns['user_id']['Null'] === 'YES');
ok('audience defaults to user', isset($columns['audience']) && $columns['audience']['Default'] === 'user');
ok('severity defaults to info', isset($columns['severity']) && $columns['severity']['Default'] === 'info');
ok('source defaults to system', isset($columns['source']) && $columns['source']['Default'] === 'system');
ok('scheduled_at is nullable (NULL = visible now)', isset($columns['scheduled_at']) && $columns['scheduled_at']['Null'] === 'YES');

$indexes = [];
foreach ($conn->query('SHOW INDEX FROM notifications')->fetchAll(PDO::FETCH_ASSOC) as $idx) {
    $indexes[$idx['Key_name']] = true;
}
foreach (['PRIMARY', 'idx_user_feed', 'idx_user_unread', 'idx_event_created'] as $idx) {
    ok("index {$idx} exists", isset($indexes[$idx]));
}

// ─── Helpers ──────────────────────────────────────────────────────────────
/** Latest row for an event key, or null. */
function latest(PDO $conn, string $event) {
    $s = $conn->prepare('SELECT * FROM notifications WHERE event = :e ORDER BY id DESC LIMIT 1');
    $s->execute(['e' => $event]);
    $row = $s->fetch(PDO::FETCH_ASSOC);
    return $row === false ? null : $row;
}

/** Mirrors api/user/notifications.php: visible rows for one user. */
function feed(PDO $conn, int $uid, string $prefix): array {
    $sql = 'SELECT n.id, n.event, n.title, n.read_at FROM notifications n
             WHERE n.audience = :aud AND n.user_id = :uid AND n.event LIKE :p
               AND ' . notify_visible_sql('n') . '
             ORDER BY n.created_at DESC, n.id DESC';
    $s = $conn->prepare($sql);
    $s->execute(['aud' => 'user', 'uid' => $uid, 'p' => $prefix . '%']);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

/** Mirrors notifications_unread_count(). */
function unread(PDO $conn, int $uid, string $prefix): int {
    $sql = 'SELECT COUNT(*) FROM notifications n
             WHERE n.audience = :aud AND n.user_id = :uid AND n.read_at IS NULL
               AND n.event LIKE :p AND ' . notify_visible_sql('n');
    $s = $conn->prepare($sql);
    $s->execute(['aud' => 'user', 'uid' => $uid, 'p' => $prefix . '%']);
    return (int)$s->fetchColumn();
}

/** Mirrors the POST action=read UPDATE, including its scoping predicate. */
function mark_read(PDO $conn, int $uid, int $id): int {
    $sql = 'UPDATE notifications n SET n.read_at = NOW()
             WHERE n.id = :id AND n.audience = :aud AND n.user_id = :uid
               AND n.read_at IS NULL AND ' . notify_visible_sql('n');
    $s = $conn->prepare($sql);
    $s->execute(['id' => $id, 'aud' => 'user', 'uid' => $uid]);
    return $s->rowCount();
}

/** Mirrors the POST action=read_all UPDATE. */
function mark_read_all(PDO $conn, int $uid, string $prefix): int {
    $sql = 'UPDATE notifications n SET n.read_at = NOW()
             WHERE n.audience = :aud AND n.user_id = :uid AND n.read_at IS NULL
               AND n.event LIKE :p AND ' . notify_visible_sql('n');
    $s = $conn->prepare($sql);
    $s->execute(['aud' => 'user', 'uid' => $uid, 'p' => $prefix . '%']);
    return $s->rowCount();
}

// ─── notify_visible_sql ───────────────────────────────────────────────────
echo "\nnotify_visible_sql\n";
ok('aliased form', notify_visible_sql('n') === '(n.scheduled_at IS NULL OR n.scheduled_at <= NOW())');
ok('unprefixed form', notify_visible_sql() === '(scheduled_at IS NULL OR scheduled_at <= NOW())');
ok('junk alias is stripped to a bare identifier', strpos(notify_visible_sql("n; DROP TABLE users--"), ';') === false);

// ─── notify_plain (untrusted text into markdown) ──────────────────────────
echo "\nnotify_plain\n";
ok('plain text survives', notify_plain('Mozilla 5.0 (Windows NT 10.0)') === 'Mozilla 5.0 (Windows NT 10.0)');
ok('raw HTML tags are stripped', strpos(notify_plain('<img src=x onerror=alert(1)>'), '<') === false);
ok('markdown link syntax is stripped', notify_plain('[click](http://evil.test)') === 'click(http://evil.test)');
ok('image bang is stripped', strpos(notify_plain('![x](http://evil.test/x.png)'), '!') === false);
ok('emphasis markers are stripped', notify_plain('**bold** _under_ ~strike~') === 'bold under strike');
ok('backticks are stripped', strpos(notify_plain('`code`'), '`') === false);
ok('backslash escapes are stripped', strpos(notify_plain('a\\b'), '\\') === false);
ok('newlines collapse to a single space', notify_plain("line1\n\nline2") === 'line1 line2');
ok('length is capped', mb_strlen(notify_plain(str_repeat('A', 500), 40), 'UTF-8') === 40);
ok('empty input stays empty', notify_plain('') === '');
// Sanitising must not mangle ordinary names — a stripped name is still a name,
// but an unrecognisable one would be a support problem of its own.
ok('accented names survive intact', notify_plain('Zoë Ştefan') === 'Zoë Ştefan');
ok('apostrophes and hyphens survive', notify_plain("O'Brien-Smith") === "O'Brien-Smith");

// ─── Emitter defaults ─────────────────────────────────────────────────────
echo "\nEmitter defaults\n";
ok('notify_user returns true', notify_user($conn, $USER_A, $PREFIX . '.basic', 'Basic title', 'Some **markdown** body') === true);
$row = latest($conn, $PREFIX . '.basic');
ok('row landed', $row !== null);
ok('audience defaults to user', $row && $row['audience'] === 'user');
ok('user_id is the caller', $row && (int)$row['user_id'] === $USER_A);
ok('severity defaults to info', $row && $row['severity'] === 'info');
ok('source defaults to system', $row && $row['source'] === 'system');
ok('scheduled_at defaults to NULL (visible now)', $row && $row['scheduled_at'] === null);
ok('read_at starts NULL', $row && $row['read_at'] === null);
ok('body is stored as raw markdown, unescaped', $row && $row['body'] === 'Some **markdown** body');
ok('link defaults to NULL', $row && $row['link'] === null);
ok('created_by_admin_id defaults to NULL', $row && $row['created_by_admin_id'] === null);

ok('notify_admin returns true', notify_admin($conn, $PREFIX . '.admin', 'Admin title', 'Body') === true);
$arow = latest($conn, $PREFIX . '.admin');
ok('admin row has audience=admin', $arow && $arow['audience'] === 'admin');
ok('admin row has NULL user_id (team inbox)', $arow && $arow['user_id'] === null);

notify_user($conn, $USER_A, $PREFIX . '.opts', 'Opts title', 'Body', [
    'severity' => 'danger', 'link' => '/transactions/91', 'source' => 'admin',
    'created_by_admin_id' => 7, 'meta' => ['reference' => 'REF1', 'amount' => 12.5],
]);
$orow = latest($conn, $PREFIX . '.opts');
ok('severity honoured', $orow && $orow['severity'] === 'danger');
ok('link honoured', $orow && $orow['link'] === '/transactions/91');
ok('source honoured', $orow && $orow['source'] === 'admin');
ok('created_by_admin_id honoured', $orow && (int)$orow['created_by_admin_id'] === 7);
ok('meta is json-encoded', $orow && is_array(json_decode((string)$orow['meta'], true)) && json_decode((string)$orow['meta'], true)['reference'] === 'REF1');

// ─── Allowlists + truncation ──────────────────────────────────────────────
echo "\nAllowlists and truncation\n";
notify_user($conn, $USER_A, $PREFIX . '.junk', 'Junk opts', 'Body', [
    'severity' => 'catastrophic', 'source' => 'hacker',
]);
$jrow = latest($conn, $PREFIX . '.junk');
ok('junk severity falls back to info', $jrow && $jrow['severity'] === 'info');
ok('junk source falls back to system', $jrow && $jrow['source'] === 'system');

notify_user($conn, $USER_A, $PREFIX . '.case', 'Case opts', 'Body', ['severity' => '  DANGER ', 'source' => 'ADMIN']);
$crow = latest($conn, $PREFIX . '.case');
ok('severity is case/space tolerant', $crow && $crow['severity'] === 'danger');
ok('source is case/space tolerant', $crow && $crow['source'] === 'admin');

$longTitle = str_repeat('A', 400);
notify_user($conn, $USER_A, $PREFIX . '.long', $longTitle, 'Body');
$lrow = latest($conn, $PREFIX . '.long');
ok('title truncated to 200 rather than erroring', $lrow && strlen((string)$lrow['title']) === 200);

ok('empty title is refused', notify_user($conn, $USER_A, $PREFIX . '.empty', '   ', 'Body') === false);
ok('empty event is refused', notify_user($conn, $USER_A, '', 'Has a title', 'Body') === false);
ok('user notification with id 0 is refused', notify_user($conn, 0, $PREFIX . '.noid', 'Title', 'Body') === false);

notify_admin($conn, $PREFIX . '.adminnouid', 'Admin, no uid', 'Body', ['severity' => 'success']);
$anrow = latest($conn, $PREFIX . '.adminnouid');
ok('admin rows never carry a user_id', $anrow && $anrow['user_id'] === null);

notify_user($conn, $USER_A, $PREFIX . '.badsched', 'Bad schedule', 'Body', ['scheduled_at' => 'not a date at all']);
$bsrow = latest($conn, $PREFIX . '.badsched');
ok('unparseable scheduled_at becomes NULL, not an error', $bsrow !== null && $bsrow['scheduled_at'] === null);

// ─── Cron-free scheduling ─────────────────────────────────────────────────
echo "\nScheduling (cron-free visibility)\n";
$future = date('Y-m-d H:i:s', time() + 3600);
$past   = date('Y-m-d H:i:s', time() - 3600);

$unreadBefore = unread($conn, $USER_A, $PREFIX);
notify_user($conn, $USER_A, $PREFIX . '.future', 'Scheduled for later', 'Body', ['scheduled_at' => $future]);
$futureId = (int)latest($conn, $PREFIX . '.future')['id'];

$feedIds = array_column(feed($conn, $USER_A, $PREFIX), 'id');
ok('future row is invisible to the feed', !in_array((string)$futureId, array_map('strval', $feedIds), true));
ok('future row does not inflate unread_count', unread($conn, $USER_A, $PREFIX) === $unreadBefore);
ok('mark-read cannot touch a not-yet-visible row', mark_read($conn, $USER_A, $futureId) === 0);

notify_user($conn, $USER_A, $PREFIX . '.due', 'Already due', 'Body', ['scheduled_at' => $past]);
$dueId = (int)latest($conn, $PREFIX . '.due')['id'];
$feedIds = array_map('strval', array_column(feed($conn, $USER_A, $PREFIX), 'id'));
ok('past-dated row IS visible', in_array((string)$dueId, $feedIds, true));
ok('past-dated row counts as unread', unread($conn, $USER_A, $PREFIX) === $unreadBefore + 1);

// The schedule must be resolved against the DATABASE clock, not PHP's. On
// this machine the two are an hour apart, which is exactly the skew that
// would make a "schedule for 9am" fire at 8am if we stored a PHP-formatted
// absolute datetime. Assert the stored value sits ~1h ahead of MySQL's NOW().
$skewStmt = $conn->prepare('SELECT TIMESTAMPDIFF(SECOND, NOW(), scheduled_at) FROM notifications WHERE id = :id');
$skewStmt->execute(['id' => $futureId]);
$skew = (int)$skewStmt->fetchColumn();
ok('scheduled_at is resolved on the DB clock, immune to PHP/MySQL tz skew', $skew > 3500 && $skew <= 3600);

// Bring the future row due by rewriting its scheduled_at — same row, no cron,
// nothing but the clock changed. Written with NOW() so this stays correct
// whatever the web server's timezone is.
$conn->prepare('UPDATE notifications SET scheduled_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE id = :id')
     ->execute(['id' => $futureId]);
$feedIds = array_map('strval', array_column(feed($conn, $USER_A, $PREFIX), 'id'));
ok('the same row becomes visible once due, with no cron run', in_array((string)$futureId, $feedIds, true));
ok('and now counts as unread', unread($conn, $USER_A, $PREFIX) === $unreadBefore + 2);

// ─── Cross-account isolation ──────────────────────────────────────────────
echo "\nCross-account isolation\n";
notify_user($conn, $USER_B, $PREFIX . '.b_private', "B's private notice", 'Body');
$bId = (int)latest($conn, $PREFIX . '.b_private')['id'];
$adminId = (int)latest($conn, $PREFIX . '.admin')['id'];

$aFeedIds = array_map('strval', array_column(feed($conn, $USER_A, $PREFIX), 'id'));
ok("A's feed does not contain B's row", !in_array((string)$bId, $aFeedIds, true));
ok("A's feed does not contain the admin row", !in_array((string)$adminId, $aFeedIds, true));

$bFeed = feed($conn, $USER_B, $PREFIX);
ok("B's feed contains exactly B's own row", count($bFeed) === 1 && (int)$bFeed[0]['id'] === $bId);

ok("A cannot mark B's notification read", mark_read($conn, $USER_A, $bId) === 0);
ok("A cannot mark an admin notification read", mark_read($conn, $USER_A, $adminId) === 0);

$bStillUnread = $conn->prepare('SELECT read_at FROM notifications WHERE id = :id');
$bStillUnread->execute(['id' => $bId]);
ok("B's row is genuinely still unread after A's attempt", $bStillUnread->fetchColumn() === null);

$adminStillUnread = $conn->prepare('SELECT read_at FROM notifications WHERE id = :id');
$adminStillUnread->execute(['id' => $adminId]);
ok('admin row is still unread after A\'s attempt', $adminStillUnread->fetchColumn() === null);

ok("A's unread count excludes B's rows and admin rows",
    unread($conn, $USER_A, $PREFIX) === count(array_filter(feed($conn, $USER_A, $PREFIX), function ($r) { return $r['read_at'] === null; })));

// ─── read / read_all ──────────────────────────────────────────────────────
echo "\nMark read\n";
$aFeed = feed($conn, $USER_A, $PREFIX);
$firstId = (int)$aFeed[0]['id'];
$unreadA = unread($conn, $USER_A, $PREFIX);
ok('read marks exactly one row', mark_read($conn, $USER_A, $firstId) === 1);
ok('unread_count drops by one', unread($conn, $USER_A, $PREFIX) === $unreadA - 1);
ok('re-reading the same row is a no-op', mark_read($conn, $USER_A, $firstId) === 0);
ok('a nonexistent id is a silent no-op', mark_read($conn, $USER_A, 2147483600) === 0);

$bUnreadBefore = unread($conn, $USER_B, $PREFIX);
$expectedAffected = unread($conn, $USER_A, $PREFIX);
ok('read_all affects only the remaining unread rows of the caller',
    mark_read_all($conn, $USER_A, $PREFIX) === $expectedAffected);
ok("A has nothing unread left", unread($conn, $USER_A, $PREFIX) === 0);
ok("B's unread count is untouched by A's read_all", unread($conn, $USER_B, $PREFIX) === $bUnreadBefore);

$adminAfter = $conn->prepare('SELECT read_at FROM notifications WHERE id = :id');
$adminAfter->execute(['id' => $adminId]);
ok("A's read_all did not touch the admin inbox", $adminAfter->fetchColumn() === null);

// ─── Cleanup verification ─────────────────────────────────────────────────
echo "\nCleanup\n";
$cleanup();
$left = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE event LIKE :p');
$left->execute(['p' => $PREFIX . '%']);
ok('no test rows left behind', (int)$left->fetchColumn() === 0);

echo "\n{$pass} assertions passed, {$fail} failed\n";
exit($fail === 0 ? 0 : 1);
