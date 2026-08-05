<?php
include_once("./layout/header.php");

/**
 * Admin notification inbox + push-to-customer composer.
 *
 * Reads `notifications` (SQL File/migrations/2026_08_05_02_notifications.sql).
 * Writes go exclusively through notify_user() in include/notifications.php —
 * this page never INSERTs directly, so the markdown-source-not-HTML rule and
 * the opts normalisation live in one place.
 *
 * Three things on this page that are easy to get wrong:
 *
 *   1. read_at on an audience='admin' row is SHARED across every admin. This
 *      is a team inbox. The UI deliberately says "unread" and never "unread
 *      by you", and there is no per-admin state anywhere — adding it would
 *      need a join table the schema intentionally does not have.
 *
 *   2. "Scheduled" is not a status column. A row with a future scheduled_at
 *      is simply filtered out of every read by notify_visible_sql(), which is
 *      how scheduling works with no cron on cPanel. The Scheduled panel below
 *      is the single place that inverts that predicate, because a pending
 *      outbound message is exactly the thing an operator cannot otherwise
 *      see. It is a separate query from the inbox on purpose; the inbox is
 *      never allowed to show a not-yet-due row.
 *
 *   3. Everything echoed here is admin-typed free text — titles, bodies,
 *      customer names — so every single one goes through htmlspecialchars().
 *      The stored body is markdown SOURCE. It is never rendered as HTML
 *      server-side; the composer's live preview renders it client-side
 *      through marked + DOMPurify, the same pair the customer portal uses.
 *
 * Defensive on schema, like admin/audit-log.php: a missing table renders a
 * migration notice rather than taking the panel down.
 */

$tableReady = admin_notify_table_ready($conn);

// How far ahead a push may be scheduled. A typo of "2226" in the year field
// would otherwise create a row that is invisible forever and can only be
// found by someone reading the table by hand.
$maxScheduleDays = 365;

$flashError   = '';
$flashSuccess = '';

// Sticky state for the composer. Re-populated from $_POST on a validation
// failure so a rejected send never costs the operator their typing.
$pushForm = array(
    'user_id'      => '',
    'title'        => '',
    'body'         => '',
    'severity'     => 'info',
    'link'         => '',
    'send_mode'    => 'now',
    'scheduled_at' => '',
);
$pushErrors = array();
$pushModalOpen = false;

// ─────────────────────────────────────────────────────────────────────────
// POST handlers. CSRF is already enforced panel-wide by layout/header.php
// (its admin_csrf_valid() gate runs before this file's body), and the hidden
// token field is injected into every method="post" form by the output-buffer
// stamper — so nothing here has to re-check it.
// ─────────────────────────────────────────────────────────────────────────

if ($tableReady && isset($_POST['mark_read'])) {
    $markId = (int)($_POST['notification_id'] ?? 0);
    if (admin_notify_mark_read($conn, $markId)) {
        toast_flash('success', 'Notification marked as read.', 'Done');
    }
    header('Location: ./notifications.php?' . http_build_query($_GET));
    exit;
}

if ($tableReady && isset($_POST['mark_all_read'])) {
    $marked = admin_notify_mark_all_read($conn);
    toast_flash('success', $marked === 1
        ? '1 notification marked as read.'
        : $marked . ' notifications marked as read.', 'Done');
    header('Location: ./notifications.php?' . http_build_query($_GET));
    exit;
}

if ($tableReady && isset($_POST['cancel_scheduled'])) {
    $cancelId = (int)($_POST['notification_id'] ?? 0);

    // Snapshot before the DELETE — once the row is gone the audit entry is
    // the only surviving record of what was cancelled.
    $snapshot = array();
    try {
        $peek = $conn->prepare(
            "SELECT audience, user_id, event, title, severity, scheduled_at
             FROM notifications
             WHERE id = :id AND source = 'admin' AND scheduled_at IS NOT NULL AND scheduled_at > NOW()
             LIMIT 1"
        );
        $peek->execute(array('id' => $cancelId));
        $snapshot = $peek->fetch(PDO::FETCH_ASSOC) ?: array();
    } catch (Throwable $e) {
        error_log('[admin-notify] cancel snapshot failed: ' . $e->getMessage());
    }

    if (admin_notify_cancel_scheduled($conn, $cancelId)) {
        if (function_exists('audit_log')) {
            audit_log('notification.schedule_cancelled', 'notification', (string)$cancelId, array(
                'audience'     => $snapshot['audience'] ?? null,
                'user_id'      => isset($snapshot['user_id']) ? (int)$snapshot['user_id'] : null,
                'event'        => $snapshot['event'] ?? null,
                'title'        => $snapshot['title'] ?? null,
                'scheduled_at' => $snapshot['scheduled_at'] ?? null,
            ));
        }
        notify_admin(
            $conn,
            'admin.notification_cancelled',
            'Scheduled notification cancelled',
            "A scheduled message was cancelled before delivery.\n\n"
            . '- **Title:** ' . (string)($snapshot['title'] ?? '(unknown)') . "\n"
            . '- **Was due:** ' . (string)($snapshot['scheduled_at'] ?? '(unknown)') . "\n"
            . '- **Cancelled by:** ' . (string)($_SESSION['admin'] ?? 'unknown'),
            array(
                'severity'            => 'warning',
                'source'              => 'admin',
                'created_by_admin_id' => (int)($_SESSION['admin_id'] ?? 0),
                'link'                => '/admin/notifications.php',
                'meta'                => array('cancelled_id' => $cancelId),
            )
        );
        toast_flash('success', 'Scheduled notification cancelled. It will not be delivered.', 'Cancelled');
    } else {
        toast_flash('warning', 'Nothing was cancelled — that message has already been delivered, or it no longer exists.', 'No change');
    }
    header('Location: ./notifications.php?' . http_build_query($_GET));
    exit;
}

// ─────────────────────────────────────────────────────────────────────────
// POST: push a notification to one customer.
// ─────────────────────────────────────────────────────────────────────────
if ($tableReady && isset($_POST['push_send'])) {
    $pushModalOpen = true;

    // NOTE: deliberately NOT run through inputValidation(). That helper
    // htmlspecialchars()+htmlentities() its argument, which is the panel's
    // legacy escape-on-input habit. Here it would store `&lt;` sequences as
    // literal markdown source, and the customer portal — which sanitises on
    // render — would then display the escape sequences to the customer.
    // Escaping happens on output, in every echo below and in the portal.
    $pushForm['user_id']      = trim((string)($_POST['user_id'] ?? ''));
    $pushForm['title']        = trim((string)($_POST['title'] ?? ''));
    $pushForm['body']         = (string)($_POST['body'] ?? '');
    $pushForm['severity']     = strtolower(trim((string)($_POST['severity'] ?? 'info')));
    $pushForm['link']         = trim((string)($_POST['link'] ?? ''));
    $pushForm['send_mode']    = ($_POST['send_mode'] ?? 'now') === 'later' ? 'later' : 'now';
    $pushForm['scheduled_at'] = trim((string)($_POST['scheduled_at'] ?? ''));

    // ── Recipient ──
    $targetUser = null;
    $targetId   = (int)$pushForm['user_id'];
    if ($targetId <= 0) {
        $pushErrors['user_id'] = 'Choose a customer.';
    } else {
        $lookup = $conn->prepare("SELECT id, firstname, lastname, acct_no, acct_email FROM users WHERE id = :id LIMIT 1");
        $lookup->execute(array('id' => $targetId));
        $targetUser = $lookup->fetch(PDO::FETCH_ASSOC);
        if (!$targetUser) {
            $pushErrors['user_id'] = 'That customer no longer exists.';
        }
    }

    // ── Title ──
    $titleLength = function_exists('mb_strlen')
        ? mb_strlen($pushForm['title'], 'UTF-8')
        : strlen($pushForm['title']);
    if ($pushForm['title'] === '') {
        $pushErrors['title'] = 'A title is required.';
    } elseif ($titleLength > 200) {
        $pushErrors['title'] = 'Titles are capped at 200 characters (this one is ' . $titleLength . ').';
    }

    // ── Body (optional, but capped so one paste cannot fill a TEXT column) ──
    $bodyLength = function_exists('mb_strlen')
        ? mb_strlen($pushForm['body'], 'UTF-8')
        : strlen($pushForm['body']);
    if ($bodyLength > 8000) {
        $pushErrors['body'] = 'The message body is capped at 8,000 characters (this one is ' . $bodyLength . ').';
    }

    // ── Severity ──
    if (!in_array($pushForm['severity'], array('info', 'success', 'warning', 'danger'), true)) {
        $pushErrors['severity'] = 'Pick a valid severity.';
    }

    // ── Link ──
    // In-app routes only. The value is handed to the customer SPA's router and
    // ends up in an href there, so `javascript:`, `data:` and protocol-relative
    // `//evil.tld` are all refused here rather than relied on being caught
    // downstream. An absolute http(s) URL is refused too: this is an in-app
    // deep link, not an outbound redirector.
    if ($pushForm['link'] !== '') {
        if (strlen($pushForm['link']) > 255) {
            $pushErrors['link'] = 'The link is too long (255 characters maximum).';
        } elseif (substr($pushForm['link'], 0, 1) !== '/' || substr($pushForm['link'], 0, 2) === '//') {
            $pushErrors['link'] = 'Use an in-app path beginning with a single "/", e.g. /transactions.';
        } elseif (!preg_match('#^/[A-Za-z0-9/_.\-~%?=&+]*$#', $pushForm['link'])) {
            $pushErrors['link'] = 'That path contains characters that are not allowed in a route.';
        }
    }

    // ── Schedule ──
    // Sending "now" is scheduled_at = null, which notify_visible_sql() treats
    // as immediately visible. Scheduling is just a future timestamp; there is
    // no cron and nothing to run.
    $scheduledAt = null;
    // Same instant as $scheduledAt, expressed on MySQL's clock. Everything an
    // operator reads uses this; only notify_user() gets $scheduledAt.
    $scheduledDisplay = null;
    if ($pushForm['send_mode'] === 'later') {
        if ($pushForm['scheduled_at'] === '') {
            $pushErrors['scheduled_at'] = 'Pick a date and time, or switch back to "Send now".';
        } else {
            // <input type="datetime-local"> submits 2026-08-06T09:30 (and
            // sometimes with seconds). strtotime handles both.
            $ts = strtotime(str_replace('T', ' ', $pushForm['scheduled_at']));
            // Compared against PHP's time(), NOT the database clock, and that
            // is deliberate. notify_normalize_scheduled_at() in
            // include/notifications.php converts whatever we hand it into a
            // DELTA of `strtotime($value) - time()` and the INSERT resolves it
            // as NOW() + INTERVAL n SECOND. So the posted string is only ever
            // interpreted on PHP's clock. Validating it against MySQL's clock
            // instead — which differs by an hour on this machine — shifted
            // every schedule by that difference. The two clocks are reconciled
            // once, in the helper; this side must simply match it.
            if ($ts === false) {
                $pushErrors['scheduled_at'] = 'That date and time could not be read.';
            } elseif ($ts <= time()) {
                $pushErrors['scheduled_at'] = 'Pick a time in the future — a past timestamp would deliver immediately.';
            } elseif ($ts > time() + ($maxScheduleDays * 86400)) {
                $pushErrors['scheduled_at'] = 'Schedule at most ' . $maxScheduleDays . ' days ahead.';
            } else {
                $scheduledAt = date('Y-m-d H:i:s', $ts);
                // $scheduledAt is on PHP's clock, which is correct for the
                // helper (it derives a delta from time()) and wrong for every
                // human-facing string. The stored row lands on MySQL's clock,
                // which is what the Scheduled panel below prints — so echoing
                // $scheduledAt into the toast and the audit entry showed the
                // same schedule at two times an hour apart. Re-express the
                // instant on MySQL's clock once, here, for display only.
                $scheduledDisplay = date('Y-m-d H:i:s', admin_notify_now_ts($conn) + ($ts - time()));
            }
        }
    }

    if (empty($pushErrors)) {
        $recipientName = trim((string)($targetUser['firstname'] ?? '') . ' ' . (string)($targetUser['lastname'] ?? ''));
        $adminId       = (int)($_SESSION['admin_id'] ?? 0);

        $sent = notify_user(
            $conn,
            $targetId,
            'admin.message',
            $pushForm['title'],
            $pushForm['body'],
            array(
                'severity'            => $pushForm['severity'],
                'link'                => $pushForm['link'] !== '' ? $pushForm['link'] : null,
                'source'              => 'admin',
                'created_by_admin_id' => $adminId,
                'scheduled_at'        => $scheduledAt,
                'meta'                => array(
                    'sent_by' => (string)($_SESSION['admin'] ?? ''),
                ),
            )
        );

        if ($sent) {
            // Explicitly requested: an operator sending a message to a named
            // customer must be traceable in the audit log. The body is
            // truncated — the full text is in the notifications row, this is
            // an index into it, not a second copy.
            if (function_exists('audit_log')) {
                audit_log('notification.pushed', 'user', (string)$targetId, array(
                    'recipient'    => $recipientName,
                    'acct_no'      => $targetUser['acct_no'] ?? null,
                    'title'        => $pushForm['title'],
                    'severity'     => $pushForm['severity'],
                    'link'         => $pushForm['link'] !== '' ? $pushForm['link'] : null,
                    'scheduled_at' => $scheduledDisplay,
                    'body_excerpt' => admin_notify_excerpt($pushForm['body'], 200),
                ));
            }

            // Team-inbox record so the other operators see that a customer was
            // contacted out of band. Body is markdown, same as everything else.
            notify_admin(
                $conn,
                'admin.notification_pushed',
                ($scheduledAt === null ? 'Message sent to ' : 'Message scheduled for ') . ($recipientName !== '' ? $recipientName : ('customer #' . $targetId)),
                '- **Title:** ' . $pushForm['title'] . "\n"
                . '- **Severity:** ' . $pushForm['severity'] . "\n"
                . ($scheduledAt === null ? "- **Delivery:** immediate\n" : '- **Delivery:** ' . $scheduledDisplay . "\n")
                . '- **Sent by:** ' . (string)($_SESSION['admin'] ?? 'unknown'),
                array(
                    'severity'            => 'info',
                    'source'              => 'admin',
                    'created_by_admin_id' => $adminId,
                    'link'                => '/admin/notifications.php',
                    'meta'                => array(
                        'recipient_user_id' => $targetId,
                        'scheduled_at'      => $scheduledDisplay,
                    ),
                )
            );

            toast_flash(
                'success',
                $scheduledAt === null
                    ? 'Notification delivered to ' . ($recipientName !== '' ? $recipientName : ('customer #' . $targetId)) . '.'
                    : 'Notification scheduled for ' . $scheduledDisplay . '. It stays invisible to the customer until then, and can be cancelled from the Scheduled panel.',
                $scheduledAt === null ? 'Sent' : 'Scheduled'
            );
            header('Location: ./notifications.php');
            exit;
        }

        // notify_user() logs its own reason to error_log and returns false
        // rather than throwing — surface that honestly instead of a green
        // toast for a message that was never stored.
        $flashError = 'The notification could not be saved. Nothing was sent. Check the PHP error log for a [notify] entry.';
    }
}

// ─────────────────────────────────────────────────────────────────────────
// Filters (GET). Modelled on admin/audit-log.php.
// ─────────────────────────────────────────────────────────────────────────
$filterUnread   = isset($_GET['unread']) && $_GET['unread'] === '1';
$filterSeverity = isset($_GET['severity']) ? strtolower(trim((string)$_GET['severity'])) : '';
$filterEvent    = isset($_GET['event'])    ? trim((string)$_GET['event'])    : '';
$filterDateFrom = isset($_GET['date_from'])? trim((string)$_GET['date_from']): '';
$filterDateTo   = isset($_GET['date_to'])  ? trim((string)$_GET['date_to'])  : '';
$highlightId    = isset($_GET['highlight'])? (int)$_GET['highlight'] : 0;

if (!in_array($filterSeverity, array('info', 'success', 'warning', 'danger'), true)) {
    $filterSeverity = '';
}
if ($filterDateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateFrom)) {
    $filterDateFrom = '';
}
if ($filterDateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateTo)) {
    $filterDateTo = '';
}

$perPage = 25;
$pageNo  = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset  = ($pageNo - 1) * $perPage;

// notify_visible_sql() is non-negotiable here: without it a scheduled push
// would appear in the inbox before it is due.
$where  = array("audience = 'admin'", notify_visible_sql());
$params = array();

if ($filterUnread) {
    $where[] = 'read_at IS NULL';
}
if ($filterSeverity !== '') {
    $where[] = 'severity = :severity';
    $params['severity'] = $filterSeverity;
}
if ($filterEvent !== '') {
    $where[] = 'event = :event';
    $params['event'] = $filterEvent;
}
if ($filterDateFrom !== '') {
    $where[] = 'created_at >= :date_from';
    $params['date_from'] = $filterDateFrom . ' 00:00:00';
}
if ($filterDateTo !== '') {
    $where[] = 'created_at <= :date_to';
    $params['date_to'] = $filterDateTo . ' 23:59:59';
}
$whereSql = ' WHERE ' . implode(' AND ', $where);

$rows        = array();
$totalRows   = 0;
$eventList   = array();
$unreadTotal = 0;
$pending     = array();
$queryError  = null;

if ($tableReady) {
    try {
        // Visibility predicate applies here too: without it the filter
        // dropdown could name the event key of a row scheduled for later —
        // a filter offering an option that matches nothing on screen.
        $eventStmt = $conn->prepare(
            "SELECT DISTINCT event FROM notifications
              WHERE audience = 'admin'
                AND " . notify_visible_sql() . "
              ORDER BY event"
        );
        $eventStmt->execute();
        $eventList = $eventStmt->fetchAll(PDO::FETCH_COLUMN);

        $countStmt = $conn->prepare("SELECT COUNT(*) FROM notifications" . $whereSql);
        $countStmt->execute($params);
        $totalRows = (int)$countStmt->fetchColumn();

        $listStmt = $conn->prepare(
            "SELECT id, event, title, body, severity, link, meta, source,
                    created_by_admin_id, read_at, created_at
             FROM notifications" . $whereSql . "
             ORDER BY created_at DESC, id DESC
             LIMIT :lim OFFSET :off"
        );
        foreach ($params as $key => $value) {
            $listStmt->bindValue(':' . $key, $value);
        }
        $listStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $listStmt->bindValue(':off', $offset,  PDO::PARAM_INT);
        $listStmt->execute();
        $rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        $unreadTotal = admin_notify_unread_count($conn);
        $pending     = admin_notify_pending_scheduled($conn, 50);
    } catch (Throwable $e) {
        $queryError = $e->getMessage();
    }
}

$totalPages = (int)max(1, ceil($totalRows / $perPage));

// Customer list for the composer. Server-rendered inside this authenticated
// page, so the roster is never reachable by an unauthenticated request — there
// is no customer-search endpoint to leak. ~105 rows is well within what a
// Select2-backed <select> handles, and the plain <select> still works (and is
// still keyboard accessible) if Select2 fails to load.
$customers = array();
try {
    $custStmt = $conn->prepare(
        "SELECT id, firstname, lastname, acct_no FROM users ORDER BY firstname ASC, lastname ASC"
    );
    $custStmt->execute();
    $customers = $custStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[admin-notify] customer list failed: ' . $e->getMessage());
}

// Query-string rebuilder for the pagination links.
$baseQuery = array(
    'unread'    => $filterUnread ? '1' : '',
    'severity'  => $filterSeverity,
    'event'     => $filterEvent,
    'date_from' => $filterDateFrom,
    'date_to'   => $filterDateTo,
);
$pageLink = function ($p) use ($baseQuery) {
    $q = $baseQuery;
    $q['p'] = $p;
    return '?' . http_build_query(array_filter($q, function ($v) {
        return $v !== '' && $v !== null;
    }));
};

// Local minimum for the datetime picker so the browser refuses an obviously
// past value before the server has to. The server check above is the one that
// actually counts — this is only there to save a round trip.
// PHP's clock, matching the server-side check and
// notify_normalize_scheduled_at() — see the comment on that check. Displayed
// relative times elsewhere on this page use the DB clock instead, because
// those values were written by MySQL; the two are different value spaces.
$scheduleMin = date('Y-m-d\TH:i', time() + 300);
$scheduleMax = date('Y-m-d\TH:i', time() + ($maxScheduleDays * 86400));
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Notifications</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Notifications</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">

        <?php if (!$tableReady): ?>
            <div class="alert alert-warning">
                <h5><i class="icon fas fa-exclamation-triangle"></i> Notifications table is missing.</h5>
                Run the migration <code>SQL File/migrations/2026_08_05_02_notifications.sql</code> on your
                database to create the <code>notifications</code> table, then reload this page.
            </div>
        <?php elseif ($queryError): ?>
            <div class="alert alert-danger">
                <h5><i class="icon fas fa-times-circle"></i> Failed to load notifications.</h5>
                <?= htmlspecialchars($queryError) ?>
            </div>
        <?php else: ?>

            <?php if ($flashError !== ''): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <div class="row mb-3">
                <div class="col-md-8">
                    <p class="text-muted mb-0">
                        This is a <strong>shared team inbox</strong>. Marking a notification read marks it
                        read for every administrator — there is no per-admin read state.
                    </p>
                </div>
                <div class="col-md-4 text-md-right mt-2 mt-md-0">
                    <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#pushNotificationModal">
                        <i class="fas fa-paper-plane"></i> Send to a customer
                    </button>
                </div>
            </div>

            <!-- ── Scheduled, not yet delivered ────────────────────────────
                 The only view in the panel that shows rows notify_visible_sql()
                 hides. These have been composed but are not due, so they exist
                 nowhere else an operator can look. -->
            <?php if (!empty($pending)): ?>
                <div class="card card-outline card-warning">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="far fa-clock"></i>
                            Scheduled &mdash; not delivered yet (<?= count($pending) ?>)
                        </h3>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Due</th>
                                    <th>Recipient</th>
                                    <th>Title</th>
                                    <th>Severity</th>
                                    <th class="text-right" style="width:1%; white-space:nowrap;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pending as $p): ?>
                                <?php
                                $pRecipient = trim((string)($p['firstname'] ?? '') . ' ' . (string)($p['lastname'] ?? ''));
                                if ($p['audience'] === 'admin') {
                                    $pRecipient = 'Admin team';
                                } elseif ($pRecipient === '') {
                                    $pRecipient = 'Customer #' . (int)$p['user_id'] . ' (deleted?)';
                                } elseif (!empty($p['acct_no'])) {
                                    $pRecipient .= ' (' . (string)$p['acct_no'] . ')';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge badge-warning"><?= htmlspecialchars((string)$p['scheduled_at']) ?></span>
                                        <br><small class="text-muted"><?= htmlspecialchars(admin_notify_relative_time($p['scheduled_at'], admin_notify_now_ts($conn))) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($pRecipient) ?></td>
                                    <td>
                                        <?= htmlspecialchars((string)$p['title']) ?>
                                        <?php $pExcerpt = admin_notify_excerpt(isset($p['body']) ? $p['body'] : '', 90); ?>
                                        <?php if ($pExcerpt !== ''): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($pExcerpt) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= htmlspecialchars(admin_notify_severity_class($p['severity'])) ?>">
                                            <?= htmlspecialchars(strtoupper((string)$p['severity'])) ?>
                                        </span>
                                    </td>
                                    <td class="text-right">
                                        <form method="post" class="d-inline"
                                              onsubmit="return confirm('Cancel this scheduled message? It will never be delivered.');">
                                            <input type="hidden" name="notification_id" value="<?= (int)$p['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger" name="cancel_scheduled" value="1">
                                                <i class="fas fa-ban"></i> Cancel
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ── Filters ─────────────────────────────────────────────── -->
            <div class="card card-outline card-primary">
                <div class="card-header"><h3 class="card-title">Filters</h3></div>
                <form method="get" class="card-body">
                    <div class="row">
                        <div class="col-md-2">
                            <label>Severity</label>
                            <select name="severity" class="form-control">
                                <option value="">&mdash; All &mdash;</option>
                                <?php foreach (array('info', 'success', 'warning', 'danger') as $sev): ?>
                                    <option value="<?= $sev ?>" <?= $filterSeverity === $sev ? 'selected' : '' ?>>
                                        <?= ucfirst($sev) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label>Event</label>
                            <select name="event" class="form-control">
                                <option value="">&mdash; All events &mdash;</option>
                                <?php foreach ($eventList as $ev): ?>
                                    <option value="<?= htmlspecialchars((string)$ev) ?>" <?= $filterEvent === $ev ? 'selected' : '' ?>>
                                        <?= htmlspecialchars((string)$ev) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label>Date From</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filterDateFrom) ?>">
                        </div>
                        <div class="col-md-2">
                            <label>Date To</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filterDateTo) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="d-block">&nbsp;</label>
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="checkbox" class="custom-control-input" id="filterUnread"
                                       name="unread" value="1" <?= $filterUnread ? 'checked' : '' ?>>
                                <label class="custom-control-label" for="filterUnread">Unread only</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-primary"><i class="fas fa-filter"></i> Apply</button>
                        <a href="./notifications.php" class="btn btn-default">Reset</a>
                        <span class="text-muted ml-2">
                            <?= (int)$totalRows ?> matching &middot; <?= (int)$unreadTotal ?> unread in total
                        </span>
                    </div>
                </form>
            </div>

            <!-- ── Inbox ───────────────────────────────────────────────── -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Inbox &mdash; page <?= (int)$pageNo ?> of <?= (int)$totalPages ?></h3>
                    <?php if ($unreadTotal > 0): ?>
                        <div class="card-tools">
                            <form method="post" class="d-inline">
                                <button class="btn btn-sm btn-default" name="mark_all_read" value="1">
                                    <i class="far fa-check-circle"></i> Mark all as read
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:1%; white-space:nowrap;">When</th>
                                <th style="width:1%; white-space:nowrap;">Severity</th>
                                <th style="width:1%; white-space:nowrap;">Event</th>
                                <th>Notification</th>
                                <th class="text-right" style="width:1%; white-space:nowrap;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No notifications match these filters.</td></tr>
                        <?php else: foreach ($rows as $r): ?>
                            <?php
                            $isRead      = !empty($r['read_at']);
                            $isHighlight = $highlightId > 0 && (int)$r['id'] === $highlightId;
                            $rowClass    = $isHighlight ? 'table-primary' : ($isRead ? '' : 'font-weight-bold');
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td>
                                    <small><?= htmlspecialchars((string)$r['created_at']) ?></small>
                                    <br><small class="text-muted"><?= htmlspecialchars(admin_notify_relative_time($r['created_at'], admin_notify_now_ts($conn))) ?></small>
                                </td>
                                <td>
                                    <span class="badge badge-<?= htmlspecialchars(admin_notify_severity_class($r['severity'])) ?>">
                                        <?= htmlspecialchars(strtoupper((string)$r['severity'])) ?>
                                    </span>
                                </td>
                                <td><code class="text-sm"><?= htmlspecialchars((string)$r['event']) ?></code></td>
                                <td>
                                    <?php if (!$isRead): ?>
                                        <span class="badge badge-primary mr-1">NEW</span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars((string)$r['title']) ?>
                                    <?php if (!empty($r['body'])): ?>
                                        <?php // Markdown SOURCE, shown as pre-formatted plain text. It is
                                              // never rendered to HTML server-side — the customer portal
                                              // renders it client-side after DOMPurify. ?>
                                        <pre class="mb-0 mt-1 text-muted" style="font-size:12px; white-space:pre-wrap; background:none; border:0; padding:0;"><?= htmlspecialchars((string)$r['body']) ?></pre>
                                    <?php endif; ?>
                                    <?php if (!empty($r['link'])): ?>
                                        <br><small class="text-muted">Link: <code><?= htmlspecialchars((string)$r['link']) ?></code></small>
                                    <?php endif; ?>
                                    <?php if ((string)$r['source'] === 'admin'): ?>
                                        <br><small class="text-muted">
                                            <i class="fas fa-user-shield"></i> raised by an operator
                                            <?php if (!empty($r['created_by_admin_id'])): ?>
                                                (admin #<?= (int)$r['created_by_admin_id'] ?>)
                                            <?php endif; ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?php if (!$isRead): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="notification_id" value="<?= (int)$r['id'] ?>">
                                            <button class="btn btn-sm btn-outline-secondary" name="mark_read" value="1" title="Mark as read">
                                                <i class="far fa-check-circle"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <small class="text-muted" title="Read at <?= htmlspecialchars((string)$r['read_at']) ?>">read</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="card-footer clearfix">
                    <ul class="pagination pagination-sm m-0 float-right">
                        <?php
                        $window = 3;
                        $start  = max(1, $pageNo - $window);
                        $end    = min($totalPages, $pageNo + $window);
                        ?>
                        <li class="page-item <?= $pageNo <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($pageLink(max(1, $pageNo - 1))) ?>">&laquo;</a>
                        </li>
                        <?php if ($start > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($pageLink(1)) ?>">1</a></li>
                            <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                        <?php endif; ?>
                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $pageNo ? 'active' : '' ?>">
                                <a class="page-link" href="<?= htmlspecialchars($pageLink($i)) ?>"><?= (int)$i ?></a>
                            </li>
                        <?php endfor; ?>
                        <?php if ($end < $totalPages): ?>
                            <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= htmlspecialchars($pageLink($totalPages)) ?>"><?= (int)$totalPages ?></a></li>
                        <?php endif; ?>
                        <li class="page-item <?= $pageNo >= $totalPages ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= htmlspecialchars($pageLink(min($totalPages, $pageNo + 1))) ?>">&raquo;</a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Push-to-customer composer ───────────────────────────── -->
            <div class="modal fade" id="pushNotificationModal" tabindex="-1" role="dialog"
                 aria-labelledby="pushNotificationModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg" role="document">
                    <div class="modal-content">
                        <form method="post" id="pushNotificationForm">
                            <div class="modal-header">
                                <h5 class="modal-title" id="pushNotificationModalLabel">
                                    <i class="fas fa-paper-plane"></i> Send a notification to a customer
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">

                                <?php if (!empty($pushErrors)): ?>
                                    <div class="alert alert-danger">
                                        <h6 class="mb-2"><i class="icon fas fa-times-circle"></i> Nothing was sent.</h6>
                                        <ul class="mb-0 pl-3">
                                            <?php foreach ($pushErrors as $err): ?>
                                                <li><?= htmlspecialchars($err) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="form-group">
                                    <label for="pushUserId">Customer</label>
                                    <select name="user_id" id="pushUserId"
                                            class="form-control js-customer-picker <?= isset($pushErrors['user_id']) ? 'is-invalid' : '' ?>"
                                            required>
                                        <option value="">Select a customer&hellip;</option>
                                        <?php foreach ($customers as $c): ?>
                                            <?php
                                            $cLabel = trim((string)$c['firstname'] . ' ' . (string)$c['lastname']);
                                            if ($cLabel === '') {
                                                $cLabel = 'Customer #' . (int)$c['id'];
                                            }
                                            if (!empty($c['acct_no'])) {
                                                $cLabel .= ' (' . (string)$c['acct_no'] . ')';
                                            }
                                            ?>
                                            <option value="<?= (int)$c['id'] ?>"
                                                <?= (string)$pushForm['user_id'] === (string)$c['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($cLabel) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Type to search. The list is rendered with this page &mdash; there is no customer-lookup endpoint to expose.</small>
                                </div>

                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="pushTitle">Title</label>
                                            <input type="text" name="title" id="pushTitle" maxlength="200"
                                                   class="form-control <?= isset($pushErrors['title']) ? 'is-invalid' : '' ?>"
                                                   value="<?= htmlspecialchars($pushForm['title']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="pushSeverity">Severity</label>
                                            <select name="severity" id="pushSeverity" class="form-control">
                                                <?php foreach (array('info' => 'Info', 'success' => 'Success', 'warning' => 'Warning', 'danger' => 'Danger') as $sevKey => $sevLabel): ?>
                                                    <option value="<?= $sevKey ?>" <?= $pushForm['severity'] === $sevKey ? 'selected' : '' ?>>
                                                        <?= $sevLabel ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="pushBody">Message <small class="text-muted">(markdown)</small></label>
                                    <div class="btn-toolbar mb-1" role="toolbar" aria-label="Markdown formatting">
                                        <div class="btn-group btn-group-sm mr-2" role="group">
                                            <button type="button" class="btn btn-default" data-md-wrap="**" title="Bold"><strong>B</strong></button>
                                            <button type="button" class="btn btn-default" data-md-wrap="_" title="Italic"><em>I</em></button>
                                            <button type="button" class="btn btn-default" data-md-wrap="`" title="Code"><i class="fas fa-code"></i></button>
                                        </div>
                                        <div class="btn-group btn-group-sm mr-2" role="group">
                                            <button type="button" class="btn btn-default" data-md-prefix="## " title="Heading"><i class="fas fa-heading"></i></button>
                                            <button type="button" class="btn btn-default" data-md-prefix="- " title="Bullet list"><i class="fas fa-list-ul"></i></button>
                                            <button type="button" class="btn btn-default" data-md-prefix="&gt; " title="Quote"><i class="fas fa-quote-left"></i></button>
                                        </div>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-default" data-md-link="1" title="Link"><i class="fas fa-link"></i></button>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <textarea name="body" id="pushBody" rows="10" maxlength="8000"
                                                      class="form-control text-monospace <?= isset($pushErrors['body']) ? 'is-invalid' : '' ?>"
                                                      style="font-size:13px;"
                                                      placeholder="Markdown is supported. **bold**, _italic_, - lists, [links](/transactions)"><?= htmlspecialchars($pushForm['body']) ?></textarea>
                                            <small class="form-text text-muted">
                                                Stored as markdown source. The customer portal renders it
                                                after sanitising &mdash; raw HTML you type here will not run.
                                                <span data-md-counter></span>
                                            </small>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="text-muted small mb-1">Preview</label>
                                            <div id="pushPreview" class="border rounded p-2 bg-light"
                                                 style="min-height:232px; max-height:232px; overflow:auto; font-size:13px;"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="pushLink">Link <small class="text-muted">(optional, in-app path)</small></label>
                                    <input type="text" name="link" id="pushLink" maxlength="255"
                                           class="form-control <?= isset($pushErrors['link']) ? 'is-invalid' : '' ?>"
                                           placeholder="/transactions"
                                           value="<?= htmlspecialchars($pushForm['link']) ?>">
                                    <small class="form-text text-muted">Must begin with a single &ldquo;/&rdquo;. External URLs are rejected.</small>
                                </div>

                                <div class="form-group mb-1">
                                    <label class="d-block">Delivery</label>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" class="custom-control-input" id="pushSendNow"
                                               name="send_mode" value="now" <?= $pushForm['send_mode'] === 'now' ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="pushSendNow">Send now</label>
                                    </div>
                                    <div class="custom-control custom-radio custom-control-inline">
                                        <input type="radio" class="custom-control-input" id="pushSendLater"
                                               name="send_mode" value="later" <?= $pushForm['send_mode'] === 'later' ? 'checked' : '' ?>>
                                        <label class="custom-control-label" for="pushSendLater">Schedule for later</label>
                                    </div>
                                </div>
                                <div class="form-group" id="pushScheduleWrap" <?= $pushForm['send_mode'] === 'later' ? '' : 'style="display:none"' ?>>
                                    <input type="datetime-local" name="scheduled_at" id="pushScheduledAt"
                                           class="form-control <?= isset($pushErrors['scheduled_at']) ? 'is-invalid' : '' ?>"
                                           min="<?= htmlspecialchars($scheduleMin) ?>"
                                           max="<?= htmlspecialchars($scheduleMax) ?>"
                                           value="<?= htmlspecialchars($pushForm['scheduled_at']) ?>">
                                    <small class="form-text text-muted">
                                        No cron is involved. The message is stored immediately but stays
                                        invisible to the customer until this moment passes, and can be
                                        cancelled at any point before then. Maximum <?= (int)$maxScheduleDays ?> days ahead;
                                        server time is now <?= htmlspecialchars(date('Y-m-d H:i')) ?>.
                                    </small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                <button type="submit" name="push_send" value="1" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</section>

<?php if ($tableReady && !$queryError): ?>
<!-- Markdown editor + preview, served locally.
     No CDN: the customer portal runs under script-src 'self' and a shared-hosting
     deployment must not acquire a runtime dependency on a third-party origin.
     These are the exact browser builds of the two libraries the Vue portal
     already depends on (frontend/user-portal package.json: marked, dompurify),
     copied into admin/plugins/ so the admin preview and the customer render use
     the same parser and the same sanitiser.
     `defer` keeps them in document order and guarantees both are defined before
     DOMContentLoaded fires, without blocking the modal's markup. -->
<script defer src="./plugins/marked/marked.umd.js"></script>
<script defer src="./plugins/dompurify/purify.min.js"></script>
<script>
/**
 * Composer behaviour: markdown toolbar, live preview, schedule toggle and the
 * Select2 upgrade for the customer picker.
 *
 * DOMContentLoaded rather than jQuery's $(function(){}) because this block is
 * parsed before layout/footer.php loads jQuery. By the time DOMContentLoaded
 * fires every parser-inserted script in the footer has already run, so jQuery
 * and Select2 are available here — but nothing below actually requires them,
 * so the composer degrades to a plain (still keyboard-accessible) <select> and
 * an unstyled textarea if either fails to load.
 */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('pushNotificationForm');
    if (!form) { return; }

    var body     = document.getElementById('pushBody');
    var preview  = document.getElementById('pushPreview');
    var counter  = form.querySelector('[data-md-counter]');
    var laterBtn = document.getElementById('pushSendLater');
    var nowBtn   = document.getElementById('pushSendNow');
    var schedule = document.getElementById('pushScheduleWrap');
    var scheduledAt = document.getElementById('pushScheduledAt');

    // ── Live preview ──
    // marked turns the markdown into HTML, DOMPurify strips anything
    // executable out of it before it touches innerHTML. Both steps matter:
    // markdown passes raw HTML straight through, so an operator typing
    // <img src=x onerror=...> into the body would otherwise fire it in their
    // own browser. The stored value is untouched markdown source either way —
    // we never post rendered HTML to the server.
    function renderPreview() {
        if (!preview || !body) { return; }
        var source = body.value || '';
        if (counter) {
            counter.textContent = source.length + ' / 8000 characters.';
        }
        if (source.trim() === '') {
            preview.textContent = 'Nothing to preview yet.';
            preview.classList.add('text-muted');
            return;
        }
        preview.classList.remove('text-muted');
        if (typeof window.marked === 'undefined' || typeof window.DOMPurify === 'undefined') {
            // Libraries missing — show the source as plain text rather than
            // silently injecting unsanitised markup.
            preview.textContent = source;
            return;
        }
        var html = window.marked.parse(source, { breaks: true, gfm: true });
        // Mirrors frontend/user-portal/src/utils/markdown.js. A permissive
        // profile here would show the operator an image or a table that the
        // customer's renderer strips, so the preview would be a promise the
        // portal does not keep. Keep the two lists in step.
        preview.innerHTML = window.DOMPurify.sanitize(html, {
            ALLOWED_TAGS: [
                'p', 'br', 'hr',
                'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'sub', 'sup', 'small',
                'a',
                'ul', 'ol', 'li',
                'blockquote',
                'code', 'pre',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'span'
            ],
            ALLOWED_ATTR: ['href', 'title'],
            ALLOWED_URI_REGEXP: /^(?:https?:\/\/|mailto:|tel:|\/(?![/\\])|#|\?)/i,
            ALLOW_DATA_ATTR: false,
            ALLOW_ARIA_ATTR: false,
            ALLOW_UNKNOWN_PROTOCOLS: false,
            KEEP_CONTENT: true
        });
    }

    if (body) {
        body.addEventListener('input', renderPreview);
        renderPreview();
    }

    // ── Toolbar ──
    function surround(before, after) {
        if (!body) { return; }
        var start = body.selectionStart, end = body.selectionEnd;
        var selected = body.value.substring(start, end);
        body.value = body.value.substring(0, start) + before + selected + after + body.value.substring(end);
        body.focus();
        body.selectionStart = start + before.length;
        body.selectionEnd   = start + before.length + selected.length;
        renderPreview();
    }

    function prefixLines(prefix) {
        if (!body) { return; }
        var start = body.selectionStart, end = body.selectionEnd;
        var lineStart = body.value.lastIndexOf('\n', start - 1) + 1;
        var block = body.value.substring(lineStart, end) || '';
        var prefixed = block.split('\n').map(function (line) { return prefix + line; }).join('\n');
        body.value = body.value.substring(0, lineStart) + prefixed + body.value.substring(end);
        body.focus();
        body.selectionStart = body.selectionEnd = lineStart + prefixed.length;
        renderPreview();
    }

    form.querySelectorAll('[data-md-wrap]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var token = btn.getAttribute('data-md-wrap');
            surround(token, token);
        });
    });
    form.querySelectorAll('[data-md-prefix]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            prefixLines(btn.getAttribute('data-md-prefix'));
        });
    });
    form.querySelectorAll('[data-md-link]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            surround('[', '](/)');
        });
    });

    // ── Schedule toggle ──
    function syncSchedule() {
        if (!schedule || !laterBtn) { return; }
        var later = laterBtn.checked;
        schedule.style.display = later ? '' : 'none';
        if (scheduledAt) {
            // Not `required` in the markup: an always-required hidden input
            // blocks submission with an un-focusable validation bubble when
            // "Send now" is selected.
            scheduledAt.required = later;
        }
    }
    if (laterBtn) { laterBtn.addEventListener('change', syncSchedule); }
    if (nowBtn)   { nowBtn.addEventListener('change', syncSchedule); }
    syncSchedule();

    // ── Searchable customer picker ──
    // Deliberately NOT given the shared `.select2` class that layout/footer.php
    // initialises: a Select2 inside a Bootstrap 4 modal needs dropdownParent
    // set to the modal, otherwise the modal's focus trap steals every
    // keystroke and the search box cannot be typed into.
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
        window.jQuery('.js-customer-picker').select2({
            theme: 'bootstrap4',
            width: '100%',
            dropdownParent: window.jQuery('#pushNotificationModal')
        });
    }

    // Re-open the modal after a server-side validation failure so the operator
    // lands back on their (preserved) input instead of an apparently empty page.
    <?php if ($pushModalOpen && !empty($pushErrors)): ?>
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal) {
        window.jQuery('#pushNotificationModal').modal('show');
    }
    <?php endif; ?>
});
</script>
<?php endif; ?>

<?php include_once("./layout/footer.php"); ?>
