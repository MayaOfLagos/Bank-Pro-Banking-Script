<?php
/**
 * Read-side helpers for the admin notification inbox.
 *
 * The WRITE side lives in include/notifications.php (notify_admin(),
 * notify_user(), notify_visible_sql()) and is owned by the shared backend.
 * Nothing in this file inserts rows — it only reads, counts and marks read,
 * plus a couple of pure presentation helpers.
 *
 * Three consumers, which is why this is a file and not a block inside a page:
 *
 *   * admin/layout/header.php        — first paint of the navbar bell
 *   * admin/notifications-feed.php   — the JSON endpoint the bell polls
 *   * admin/notifications.php        — the full inbox page
 *
 * Two rules every function here obeys:
 *
 *   1. Visibility is ALWAYS notify_visible_sql(). A row whose scheduled_at is
 *      still in the future does not exist as far as any reader is concerned —
 *      that is the entire cron-free scheduling mechanism. Inlining the
 *      predicate anywhere would eventually let a scheduled push leak early
 *      from whichever reader forgot it. The one deliberate exception is
 *      admin_notify_pending_scheduled(), which exists precisely to list the
 *      not-yet-due rows so an operator can cancel them; it says so in its own
 *      docblock.
 *
 *   2. read_at on an audience='admin' row is SHARED. This is a team inbox:
 *      one admin marking an alert read marks it read for everybody. No
 *      function here takes an admin id, and none should — per-admin read
 *      state would need a separate join table (see the migration header).
 *
 * Every function is wrapped in function_exists() to match the convention in
 * admin/include/adminFunction.php, and every DB call is try/catch'd so a
 * missing table (migration not yet applied on a given host) degrades the bell
 * to "no notifications" rather than fatalling every page in the panel.
 */

require_once __DIR__ . '/../../include/notifications.php';

if (!function_exists('admin_notify_table_ready')) {
    /**
     * Is the `notifications` table present?
     *
     * Cached per-request in a static: the bell renders on every page and the
     * feed endpoint polls, so this would otherwise be a SHOW TABLES on every
     * single hit. Mirrors the defensive probe admin/audit-log.php does.
     *
     * @param PDO $conn
     * @return bool
     */
    function admin_notify_table_ready($conn)
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $ready = false;
        try {
            if ($conn instanceof PDO) {
                $probe = $conn->query("SHOW TABLES LIKE 'notifications'");
                $ready = ($probe && $probe->fetch() !== false);
            }
        } catch (Throwable $e) {
            error_log('[admin-notify] table probe failed: ' . $e->getMessage());
            $ready = false;
        }
        return $ready;
    }
}

if (!function_exists('admin_notify_unread_count')) {
    /**
     * Unread, currently-visible admin notifications. Shared across all admins.
     *
     * @param PDO $conn
     * @return int
     */
    function admin_notify_unread_count($conn)
    {
        if (!admin_notify_table_ready($conn)) {
            return 0;
        }
        try {
            $sql = "SELECT COUNT(*) FROM notifications
                    WHERE audience = 'admin' AND read_at IS NULL AND " . notify_visible_sql();
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (Throwable $e) {
            error_log('[admin-notify] unread count failed: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('admin_notify_recent')) {
    /**
     * The N newest visible admin notifications, newest first.
     *
     * @param PDO $conn
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    function admin_notify_recent($conn, $limit = 10)
    {
        if (!admin_notify_table_ready($conn)) {
            return array();
        }
        $limit = (int)$limit;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 50) {
            $limit = 50;
        }
        try {
            $sql = "SELECT id, event, title, body, severity, link, read_at, created_at
                    FROM notifications
                    WHERE audience = 'admin' AND " . notify_visible_sql() . "
                    ORDER BY created_at DESC, id DESC
                    LIMIT :lim";
            $stmt = $conn->prepare($sql);
            // Bound as PDO::PARAM_INT so LIMIT receives an integer literal
            // rather than a quoted string, which is a syntax error. Do not
            // read anything else into this: dbConnect() sets only ATTR_ERRMODE,
            // so ATTR_EMULATE_PREPARES is left at MySQL's default of ON. The
            // typed bind is what makes this correct under either setting.
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : array();
        } catch (Throwable $e) {
            error_log('[admin-notify] recent fetch failed: ' . $e->getMessage());
            return array();
        }
    }
}

if (!function_exists('admin_notify_mark_read')) {
    /**
     * Mark one admin notification read. Scoped to audience='admin' so an id
     * belonging to a customer's inbox can never be touched from here, and to
     * the visibility predicate so a not-yet-due row cannot be marked read
     * before it has been seen.
     *
     * @param PDO $conn
     * @param int $id
     * @return bool true when a row actually changed
     */
    function admin_notify_mark_read($conn, $id)
    {
        if (!admin_notify_table_ready($conn)) {
            return false;
        }
        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }
        try {
            $sql = "UPDATE notifications SET read_at = NOW()
                    WHERE id = :id AND audience = 'admin' AND read_at IS NULL AND " . notify_visible_sql();
            $stmt = $conn->prepare($sql);
            $stmt->execute(array('id' => $id));
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[admin-notify] mark read failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('admin_notify_mark_all_read')) {
    /**
     * Mark every currently-visible admin notification read.
     *
     * @param PDO $conn
     * @return int rows affected
     */
    function admin_notify_mark_all_read($conn)
    {
        if (!admin_notify_table_ready($conn)) {
            return 0;
        }
        try {
            $sql = "UPDATE notifications SET read_at = NOW()
                    WHERE audience = 'admin' AND read_at IS NULL AND " . notify_visible_sql();
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            return (int)$stmt->rowCount();
        } catch (Throwable $e) {
            error_log('[admin-notify] mark all read failed: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('admin_notify_pending_scheduled')) {
    /**
     * The one reader that deliberately inverts notify_visible_sql().
     *
     * These are admin-authored messages that have been composed but are not
     * due yet — pending outbound post, not inbox. The operator needs to see
     * them (and be able to cancel one) precisely because they are invisible
     * everywhere else until they fire. Restricted to source='admin' so system
     * emitters, which never schedule, can never show up here.
     *
     * The LEFT JOIN resolves the recipient's name for display; there is no FK
     * on user_id by design (see the migration header), so a deleted customer
     * yields NULLs rather than dropping the row.
     *
     * @param PDO $conn
     * @param int $limit
     * @return array<int,array<string,mixed>>
     */
    function admin_notify_pending_scheduled($conn, $limit = 50)
    {
        if (!admin_notify_table_ready($conn)) {
            return array();
        }
        $limit = (int)$limit;
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 200) {
            $limit = 200;
        }
        try {
            $sql = "SELECT n.id, n.audience, n.user_id, n.event, n.title, n.body, n.severity,
                           n.link, n.scheduled_at, n.created_at, n.created_by_admin_id,
                           u.firstname, u.lastname, u.acct_no
                    FROM notifications n
                    LEFT JOIN users u ON u.id = n.user_id
                    WHERE n.source = 'admin'
                      AND n.scheduled_at IS NOT NULL
                      AND n.scheduled_at > NOW()
                    ORDER BY n.scheduled_at ASC, n.id ASC
                    LIMIT :lim";
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : array();
        } catch (Throwable $e) {
            error_log('[admin-notify] pending scheduled fetch failed: ' . $e->getMessage());
            return array();
        }
    }
}

if (!function_exists('admin_notify_cancel_scheduled')) {
    /**
     * Delete a scheduled push that has not fired yet.
     *
     * The `scheduled_at > NOW()` clause is the safety rail: it makes it
     * impossible to delete a message that has already been delivered to a
     * customer's inbox, even if the operator's browser was showing a stale
     * page when they clicked Cancel. source='admin' likewise keeps system
     * rows out of reach.
     *
     * @param PDO $conn
     * @param int $id
     * @return bool
     */
    function admin_notify_cancel_scheduled($conn, $id)
    {
        if (!admin_notify_table_ready($conn)) {
            return false;
        }
        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }
        try {
            $sql = "DELETE FROM notifications
                    WHERE id = :id AND source = 'admin'
                      AND scheduled_at IS NOT NULL AND scheduled_at > NOW()";
            $stmt = $conn->prepare($sql);
            $stmt->execute(array('id' => $id));
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            error_log('[admin-notify] cancel scheduled failed: ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('admin_notify_severity_class')) {
    /**
     * Bootstrap contextual suffix for a severity value.
     *
     * Returns a value from a fixed allowlist, so callers can interpolate the
     * result straight into a class attribute. It is NEVER derived from the
     * stored string — an unknown severity falls back to 'secondary' rather
     * than being echoed.
     *
     * @param mixed $severity
     * @return string
     */
    function admin_notify_severity_class($severity)
    {
        $map = array(
            'info'    => 'info',
            'success' => 'success',
            'warning' => 'warning',
            'danger'  => 'danger',
        );
        $key = strtolower(trim((string)$severity));
        return isset($map[$key]) ? $map[$key] : 'secondary';
    }
}

if (!function_exists('admin_notify_severity_icon')) {
    /**
     * Font Awesome class for a severity. Same allowlist discipline as
     * admin_notify_severity_class(): fixed strings only.
     *
     * @param mixed $severity
     * @return string
     */
    function admin_notify_severity_icon($severity)
    {
        $map = array(
            'info'    => 'fas fa-info-circle',
            'success' => 'fas fa-check-circle',
            'warning' => 'fas fa-exclamation-triangle',
            'danger'  => 'fas fa-times-circle',
        );
        $key = strtolower(trim((string)$severity));
        return isset($map[$key]) ? $map[$key] : 'far fa-bell';
    }
}

if (!function_exists('admin_notify_excerpt')) {
    /**
     * Flatten markdown source to a short plain-text preview.
     *
     * The dropdown has one line per notification, so rendering markdown there
     * would be noise. This strips the syntax characters only — it does NOT
     * make the result safe. The caller still has to htmlspecialchars() it,
     * because the underlying body is admin-typed and may contain
     * `<img src=x onerror=...>`, which markdown passes through untouched.
     *
     * @param mixed $markdown
     * @param int   $length
     * @return string plain text, still unescaped
     */
    function admin_notify_excerpt($markdown, $length = 120)
    {
        $text = (string)$markdown;
        if ($text === '') {
            return '';
        }

        // Order matters: fenced blocks before inline code, images before
        // links (an image is a link with a leading !).
        $patterns = array(
            '/```.*?```/s'                  => ' ',
            '/`([^`]*)`/'                   => '$1',
            '/!\[[^\]]*\]\([^)]*\)/'        => ' ',
            '/\[([^\]]*)\]\([^)]*\)/'       => '$1',
            '/^[ \t]{0,3}#{1,6}[ \t]*/m'    => '',
            '/^[ \t]{0,3}>[ \t]?/m'         => '',
            '/^[ \t]{0,3}([*+-]|\d+\.)[ \t]+/m' => '',
            '/^[ \t]{0,3}([-*_])(?:[ \t]*\1){2,}[ \t]*$/m' => ' ',
            '/(\*\*|__|~~|\*|_)/'           => '',
        );
        foreach ($patterns as $pattern => $replacement) {
            $next = preg_replace($pattern, $replacement, $text);
            // preg_replace returns null on a backtrack-limit blowout; keep the
            // previous value rather than emptying the preview.
            if ($next !== null) {
                $text = $next;
            }
        }

        $collapsed = preg_replace('/\s+/', ' ', $text);
        $text = trim($collapsed === null ? $text : $collapsed);

        $length = (int)$length;
        if ($length < 10) {
            $length = 10;
        }
        if (function_exists('mb_strlen')) {
            if (mb_strlen($text, 'UTF-8') > $length) {
                return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . '…';
            }
            return $text;
        }
        if (strlen($text) > $length) {
            return rtrim(substr($text, 0, $length)) . '...';
        }
        return $text;
    }
}

if (!function_exists('admin_notify_now_ts')) {
    /**
     * "Now" as MySQL sees it, expressed on PHP's clock.
     *
     * Every created_at / scheduled_at in this table is written by MySQL NOW()
     * or compared against it, and nothing in this codebase calls
     * date_default_timezone_set() — so PHP and MySQL can and do disagree. On
     * this machine PHP runs UTC while MySQL runs local (+1), which made a
     * notification inserted a second ago render as "in 59 minutes".
     *
     * The fix is not to convert timezones, it is to stop mixing clocks: read
     * MySQL's wall clock as a string and parse it with the same strtotime()
     * that parses created_at. Both then sit in PHP's timezone and the
     * subtraction is correct no matter what either side is configured to.
     *
     * Cached per request — this is called once per rendered row.
     *
     * @param PDO $conn
     * @return int
     */
    function admin_notify_now_ts($conn)
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $cached = time();
        try {
            if ($conn instanceof PDO) {
                $value = $conn->query('SELECT NOW()')->fetchColumn();
                $parsed = $value ? strtotime((string)$value) : false;
                if ($parsed !== false) {
                    $cached = $parsed;
                }
            }
        } catch (Throwable $e) {
            error_log('[admin-notify] clock read failed: ' . $e->getMessage());
        }
        return $cached;
    }
}

if (!function_exists('admin_notify_relative_time')) {
    /**
     * "3 minutes ago" style label for a MySQL DATETIME.
     *
     * Falls back to the raw stored string when the value will not parse, so a
     * malformed timestamp shows something rather than "1 Jan 1970".
     *
     * $nowTs should come from admin_notify_now_ts($conn) so the comparison is
     * against MySQL's clock rather than PHP's; it defaults to time() only so
     * the helper stays usable without a connection.
     *
     * @param mixed    $datetime
     * @param int|null $nowTs
     * @return string
     */
    function admin_notify_relative_time($datetime, $nowTs = null)
    {
        $raw = trim((string)$datetime);
        if ($raw === '') {
            return '';
        }
        $ts = strtotime($raw);
        if ($ts === false) {
            return $raw;
        }
        $delta = ($nowTs === null ? time() : (int)$nowTs) - $ts;
        if ($delta < 0) {
            // A row dated in the future (clock skew, or a just-due scheduled
            // push). "in a moment" beats "-4 seconds ago".
            $delta = abs($delta);
            $prefix = 'in ';
            $suffix = '';
        } else {
            $prefix = '';
            $suffix = ' ago';
        }

        if ($delta < 45) {
            return $prefix === '' ? 'just now' : 'in a moment';
        }

        // seconds-per-unit => singular label, largest last. The first unit
        // whose count is at least 1 wins.
        $units = array(
            31557600 => 'year',
            2629800  => 'month',
            604800   => 'week',
            86400    => 'day',
            3600     => 'hour',
            60       => 'minute',
        );
        foreach ($units as $seconds => $label) {
            $n = (int)floor($delta / $seconds);
            if ($n >= 1) {
                return $prefix . $n . ' ' . $label . ($n === 1 ? '' : 's') . $suffix;
            }
        }
        return $prefix . $delta . ' seconds' . $suffix;
    }
}
