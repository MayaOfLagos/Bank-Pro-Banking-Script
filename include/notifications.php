<?php
/**
 * In-app notification emitter.
 *
 * Three functions, deliberately small:
 *
 *   notify_user($conn, $userId, $event, $title, $body, $opts)  -> bool
 *   notify_admin($conn, $event, $title, $body, $opts)          -> bool
 *   notify_visible_sql($alias)                                 -> string
 *
 * $opts accepts:
 *   severity            info|success|warning|danger   (default 'info')
 *   link                in-app route, e.g. '/transactions/91'
 *   meta                array — JSON-encoded on the way in
 *   source              system|admin                  (default 'system')
 *   created_by_admin_id int
 *   scheduled_at        'Y-m-d H:i:s' string, or null for "visible now"
 *
 * Body is stored as markdown SOURCE. Never pass HTML in, never render HTML
 * out — the client sanitises and renders. See the migration header.
 *
 * ── Why these functions return false instead of throwing ──────────────────
 *
 * A notification is an observation of something that already happened. By the
 * time notify_user() is called the wire transfer has been committed, the money
 * has moved, the customer's balance is already down. If the notification INSERT
 * throws — table not migrated yet, column drift, transient DB blip — and that
 * exception escapes, it lands in the caller's `catch (Throwable)`, which will
 * roll back (or worse, report a failure for) a money movement that already
 * succeeded. api/user/transfer-verify-pin.php has a long comment about exactly
 * this hazard. So: catch, log, return false, and let the business action stand.
 *
 * ── But never silently ────────────────────────────────────────────────────
 *
 * This codebase was previously burned by a `catch (\Throwable)` that turned a
 * fatal into a bare `return false` with no logging: the UI showed a green
 * success toast for an email that never sent, and nobody found out for weeks.
 * Every failure path below therefore calls error_log('[notify] ...') BEFORE
 * returning false. If you add a new early return, add a log line with it.
 * "Best effort" means the caller is unaffected — it does not mean invisible.
 */

if (!function_exists('notify_visible_sql')) {
    /**
     * The single definition of "this notification is visible now".
     *
     * Scheduling is cron-free: a row with a future scheduled_at is simply
     * filtered out of every read until its time arrives. Every reader — the
     * customer feed, the unread badge, the admin inbox — must apply this
     * predicate, or a scheduled broadcast leaks early somewhere.
     *
     * notify_visible_sql('n') => "(n.scheduled_at IS NULL OR n.scheduled_at <= NOW())"
     * notify_visible_sql()    => "(scheduled_at IS NULL OR scheduled_at <= NOW())"
     *
     * @param string $alias table alias, without the trailing dot
     */
    function notify_visible_sql(string $alias = ''): string
    {
        // Alias is a code-supplied identifier, never request data, but strip it
        // to a safe identifier charset anyway so this can never become an
        // injection point if someone later wires it to a variable.
        $alias = preg_replace('/[^A-Za-z0-9_]/', '', (string)$alias);
        $prefix = $alias !== '' ? $alias . '.' : '';
        return '(' . $prefix . 'scheduled_at IS NULL OR ' . $prefix . 'scheduled_at <= NOW())';
    }
}

if (!function_exists('notify_allowed_severity')) {
    /**
     * @return string one of info|success|warning|danger
     */
    function notify_allowed_severity($value)
    {
        $value = strtolower(trim((string)$value));
        $allowed = array('info', 'success', 'warning', 'danger');
        return in_array($value, $allowed, true) ? $value : 'info';
    }
}

if (!function_exists('notify_allowed_source')) {
    /**
     * @return string one of system|admin
     */
    function notify_allowed_source($value)
    {
        $value = strtolower(trim((string)$value));
        $allowed = array('system', 'admin');
        return in_array($value, $allowed, true) ? $value : 'system';
    }
}

if (!function_exists('notify_allowed_audience')) {
    /**
     * @return string one of user|admin
     */
    function notify_allowed_audience($value)
    {
        $value = strtolower(trim((string)$value));
        $allowed = array('user', 'admin');
        return in_array($value, $allowed, true) ? $value : 'user';
    }
}

if (!function_exists('notify_normalize_scheduled_at')) {
    /**
     * Accepts a 'Y-m-d H:i:s' string (or anything strtotime understands) and
     * returns it as a DELTA IN SECONDS FROM NOW, not as an absolute datetime.
     * Junk becomes null — "visible immediately" — rather than a MySQL error
     * or, worse, a row that is invisible forever because the date landed in
     * the year 2038.
     *
     * Why a delta and not the formatted string the caller handed us:
     *
     * The visibility predicate compares scheduled_at against MySQL's NOW(),
     * which runs on the DATABASE server's clock. A PHP-formatted absolute
     * datetime runs on the WEB server's clock. On this very machine those two
     * are an hour apart (PHP in UTC, MySQL in local time), and on cPanel there
     * is no guarantee they agree either. Storing the absolute string would
     * make every "schedule for 9am" silently fire an hour early or an hour
     * late, which for a bank is a customer-visible correctness bug.
     *
     * So we express the schedule relative to now and let the INSERT resolve it
     * with DATE_ADD(NOW(), INTERVAL :n SECOND). MySQL's clock then becomes the
     * single canonical clock for the whole table — consistent with created_at
     * (CURRENT_TIMESTAMP) and read_at (NOW()), which are already DB-clock.
     *
     * @return int|null seconds from now; negative means "already due"
     */
    function notify_normalize_scheduled_at($value)
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }
        $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
        if ($ts === false || $ts <= 0) {
            error_log('[notify] unparseable scheduled_at, treating as immediate: ' . substr((string)$value, 0, 60));
            return null;
        }
        return $ts - time();
    }
}

if (!function_exists('notify_plain')) {
    /**
     * Neutralise a piece of untrusted text so it can be interpolated into a
     * notification body without being read as markdown.
     *
     * Bodies are markdown SOURCE and the customer portal renders them as HTML.
     * Anything that originates outside our own code — a User-Agent header, a
     * self-chosen display name, a transfer narration — must not be able to
     * smuggle in a link, an image, or raw HTML. DOMPurify on the client is the
     * backstop, not the control: strip the syntax at the point of composition,
     * where we still know which fragment is untrusted.
     *
     * Deliberately a strip, not a backslash-escape: escaped markdown still has
     * to survive two renderers intact, and a User-Agent reads no worse with the
     * punctuation gone.
     */
    function notify_plain($value, $maxLength = 160)
    {
        $value = str_replace(array("\r", "\n", "\t"), ' ', (string)$value);
        // Everything markdown or HTML gives meaning to.
        $value = preg_replace('/[<>\[\]`*_~#|\\\\!]/', '', $value);
        $value = trim(preg_replace('/\s+/', ' ', $value));
        $maxLength = (int)$maxLength;
        if ($maxLength > 0) {
            $value = function_exists('mb_substr')
                ? mb_substr($value, 0, $maxLength, 'UTF-8')
                : substr($value, 0, $maxLength);
        }
        return $value;
    }
}

if (!function_exists('notify_insert')) {
    /**
     * Shared insert path for both audiences. Not part of the public contract —
     * call notify_user() / notify_admin().
     *
     * @param PDO      $conn
     * @param string   $audience 'user' | 'admin'
     * @param int|null $userId
     * @return bool true when a row landed
     */
    function notify_insert($conn, $audience, $userId, $event, $title, $body = '', $opts = array())
    {
        try {
            if (!($conn instanceof PDO)) {
                error_log('[notify] no PDO connection supplied for event "' . (string)$event . '"');
                return false;
            }

            $audience = notify_allowed_audience($audience);

            // audience='admin' is a team inbox — it must never carry a user_id,
            // or a customer feed query scoped to that id would surface it.
            if ($audience === 'admin') {
                $userId = null;
            } else {
                $userId = (int)$userId;
                if ($userId <= 0) {
                    error_log('[notify] refusing user notification with no user id, event "' . (string)$event . '"');
                    return false;
                }
            }

            $event = substr(trim((string)$event), 0, 64);
            if ($event === '') {
                error_log('[notify] refusing notification with an empty event key');
                return false;
            }

            // Truncate rather than letting MySQL either error out in STRICT
            // mode or silently amputate the title in non-strict mode. mb_* so
            // a multibyte title is not cut mid-character.
            $title = trim((string)$title);
            if (function_exists('mb_substr')) {
                $title = mb_substr($title, 0, 200, 'UTF-8');
            } else {
                $title = substr($title, 0, 200);
            }
            if ($title === '') {
                error_log('[notify] refusing notification with an empty title, event "' . $event . '"');
                return false;
            }

            $body = (string)$body;
            if ($body === '') {
                $body = null;
            }

            $severity = notify_allowed_severity(isset($opts['severity']) ? $opts['severity'] : 'info');
            $source   = notify_allowed_source(isset($opts['source']) ? $opts['source'] : 'system');

            $link = isset($opts['link']) ? trim((string)$opts['link']) : '';
            $link = $link !== '' ? substr($link, 0, 255) : null;

            $meta = null;
            if (isset($opts['meta']) && is_array($opts['meta']) && !empty($opts['meta'])) {
                $meta = json_encode($opts['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($meta === false) {
                    error_log('[notify] meta failed to json_encode for event "' . $event . '"; storing null');
                    $meta = null;
                } elseif (strlen($meta) > 20000) {
                    // Cap defensively — notification rows should stay small.
                    $meta = substr($meta, 0, 20000);
                }
            }

            $createdByAdminId = null;
            if (isset($opts['created_by_admin_id']) && (int)$opts['created_by_admin_id'] > 0) {
                $createdByAdminId = (int)$opts['created_by_admin_id'];
            }

            $scheduleDelta = notify_normalize_scheduled_at(
                isset($opts['scheduled_at']) ? $opts['scheduled_at'] : null
            );

            $params = array(
                'audience'            => $audience,
                'user_id'             => $userId,
                'event'               => $event,
                'title'               => $title,
                'body'                => $body,
                'severity'            => $severity,
                'link'                => $link,
                'meta'                => $meta,
                'source'              => $source,
                'created_by_admin_id' => $createdByAdminId,
            );

            // Two fixed SQL strings, chosen by a boolean — no variable is ever
            // concatenated into either. See notify_normalize_scheduled_at() for
            // why the schedule is resolved against MySQL's NOW() rather than
            // stored as a PHP-formatted absolute datetime.
            if ($scheduleDelta === null) {
                $sql = 'INSERT INTO notifications
                            (audience, user_id, event, title, body, severity, link, meta,
                             source, created_by_admin_id, scheduled_at)
                        VALUES
                            (:audience, :user_id, :event, :title, :body, :severity, :link, :meta,
                             :source, :created_by_admin_id, NULL)';
            } else {
                $sql = 'INSERT INTO notifications
                            (audience, user_id, event, title, body, severity, link, meta,
                             source, created_by_admin_id, scheduled_at)
                        VALUES
                            (:audience, :user_id, :event, :title, :body, :severity, :link, :meta,
                             :source, :created_by_admin_id,
                             DATE_ADD(NOW(), INTERVAL :schedule_delta SECOND))';
                $params['schedule_delta'] = (int)$scheduleDelta;
            }

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            return true;
        } catch (Throwable $e) {
            // See the file header: log loudly, return false, never rethrow.
            error_log('[notify] ' . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('notify_user')) {
    /**
     * Emit a notification into one customer's inbox.
     *
     * @param PDO    $conn
     * @param int    $userId users.id — always from the server-side session,
     *                       never from a request parameter
     * @param string $event  dotted lowercase key, e.g. 'transfer.submitted'
     * @param string $title  <= 200 chars (truncated, not rejected)
     * @param string $body   markdown source
     * @param array  $opts   see the file header
     * @return bool
     */
    function notify_user(PDO $conn, int $userId, string $event, string $title, string $body = '', array $opts = array()): bool
    {
        return notify_insert($conn, 'user', $userId, $event, $title, $body, $opts);
    }
}

if (!function_exists('notify_admin')) {
    /**
     * Emit a notification into the shared admin team inbox. read_at on these
     * rows is shared — one admin reading it marks it read for everyone.
     *
     * @param PDO    $conn
     * @param string $event dotted lowercase key, e.g. 'transfer.submitted'
     * @param string $title <= 200 chars (truncated, not rejected)
     * @param string $body  markdown source
     * @param array  $opts  see the file header
     * @return bool
     */
    function notify_admin(PDO $conn, string $event, string $title, string $body = '', array $opts = array()): bool
    {
        return notify_insert($conn, 'admin', null, $event, $title, $body, $opts);
    }
}
