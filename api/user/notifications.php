<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../include/notifications.php';

/**
 * Customer notification feed.
 *
 *   GET  /api/user/notifications.php?limit=10                  drawer feed
 *   GET  /api/user/notifications.php?view=all&page=1&per_page=20   full page
 *   POST { "action": "read", "id": 123 }
 *   POST { "action": "read_all" }
 *
 * Scoping rules that must never be relaxed:
 *
 *   1. Every statement carries `audience = 'user' AND user_id = :uid`, and
 *      :uid is bound from the session-resolved $user['id']. There is no code
 *      path where a request parameter can influence which rows are touched —
 *      that is what stops customer A reading (or marking read) customer B's
 *      notification, and what stops an audience='admin' row ever reaching a
 *      customer.
 *   2. Every read applies notify_visible_sql('n'), so a notification scheduled
 *      for later is invisible — including to unread_count. A badge that counts
 *      a row the list refuses to show is a support ticket waiting to happen.
 *   3. `body` is returned as raw markdown source. No server-side rendering, no
 *      htmlspecialchars(): this is JSON, json_encode() already escapes for the
 *      transport, and pre-escaping would corrupt the markdown the client is
 *      about to sanitise and render.
 */

$uid = (int)$user['id'];

/**
 * Count the caller's unread, visible notifications.
 */
function notifications_unread_count(PDO $conn, int $uid): int
{
    $sql = 'SELECT COUNT(*) FROM notifications n
             WHERE n.audience = :audience
               AND n.user_id = :uid
               AND n.read_at IS NULL
               AND ' . notify_visible_sql('n');
    $stmt = $conn->prepare($sql);
    $stmt->execute(array('audience' => 'user', 'uid' => $uid));
    return (int)$stmt->fetchColumn();
}

/**
 * Map a DB row to the frozen wire shape. Exactly these keys, no extras —
 * meta / scheduled_at / audience stay server-side.
 */
function notifications_shape(array $row): array
{
    return array(
        'id'         => (int)$row['id'],
        'event'      => (string)$row['event'],
        'title'      => (string)$row['title'],
        // Raw markdown source; the client sanitises + renders it.
        'body'       => $row['body'] !== null ? (string)$row['body'] : '',
        'severity'   => (string)$row['severity'],
        'link'       => ($row['link'] !== null && $row['link'] !== '') ? (string)$row['link'] : null,
        // A real boolean, not the timestamp. The drawer branches on it.
        'read'       => $row['read_at'] !== null,
        'created_at' => (string)$row['created_at'],
        // created_at is a *naive* MySQL-local datetime. Handing only that to
        // the browser meant `new Date('2026-08-05T14:30:00')` was read in the
        // customer's timezone, so a sign-in alert fired seconds ago could be
        // labelled hours old — on a fraud alert that is the field that matters.
        // UNIX_TIMESTAMP() resolves it against the DB's own timezone, so this
        // is an unambiguous instant and the client never has to guess.
        'created_ts' => isset($row['created_ts']) ? (int)$row['created_ts'] : null,
    );
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));

// ─── POST: mark read ──────────────────────────────────────────────────────
if ($method === 'POST') {
    $payload = api_payload();
    $action = strtolower(trim((string)($payload['action'] ?? '')));

    if ($action === 'read') {
        $id = (int)($payload['id'] ?? 0);
        if ($id <= 0) {
            api_json(422, ['ok' => false, 'message' => 'A notification id is required']);
        }
        // The user_id predicate is the isolation guarantee: a guessed id
        // belonging to another account (or to the admin inbox) matches zero
        // rows and the UPDATE is a no-op. We deliberately do NOT 404 on a
        // zero-row result — telling the caller whether id 91 exists is exactly
        // the enumeration oracle this predicate exists to close.
        $sql = 'UPDATE notifications n
                   SET n.read_at = NOW()
                 WHERE n.id = :id
                   AND n.audience = :audience
                   AND n.user_id = :uid
                   AND n.read_at IS NULL
                   AND ' . notify_visible_sql('n');
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('id' => $id, 'audience' => 'user', 'uid' => $uid));

        api_json(200, ['ok' => true, 'data' => ['unread_count' => notifications_unread_count($conn, $uid)]]);
    }

    if ($action === 'read_all') {
        // Same scoping. A scheduled-for-later row stays unread — it is not
        // visible yet, so the customer cannot have read it.
        $sql = 'UPDATE notifications n
                   SET n.read_at = NOW()
                 WHERE n.audience = :audience
                   AND n.user_id = :uid
                   AND n.read_at IS NULL
                   AND ' . notify_visible_sql('n');
        $stmt = $conn->prepare($sql);
        $stmt->execute(array('audience' => 'user', 'uid' => $uid));

        api_json(200, ['ok' => true, 'data' => ['unread_count' => notifications_unread_count($conn, $uid)]]);
    }

    api_json(422, ['ok' => false, 'message' => 'Unsupported action']);
}

if ($method !== 'GET' && $method !== 'HEAD') {
    api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

// ─── GET: feed ────────────────────────────────────────────────────────────
$viewAll = strtolower(trim((string)($_GET['view'] ?? ''))) === 'all';

if ($viewAll) {
    $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
    $page    = isset($_GET['page']) ? (int)$_GET['page'] : 1;
} else {
    $perPage = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $page    = 1;
}

if ($perPage < 1)  { $perPage = 1; }
if ($perPage > 50) { $perPage = 50; }
if ($page < 1)     { $page = 1; }
$offset = ($page - 1) * $perPage;

$countSql = 'SELECT COUNT(*) FROM notifications n
              WHERE n.audience = :audience
                AND n.user_id = :uid
                AND ' . notify_visible_sql('n');
$countStmt = $conn->prepare($countSql);
$countStmt->execute(array('audience' => 'user', 'uid' => $uid));
$total = (int)$countStmt->fetchColumn();

// Newest first. id is the tiebreaker so two rows created in the same second
// keep a stable order across pages — without it, pagination can drop or
// duplicate a row between requests.
$listSql = 'SELECT n.id, n.event, n.title, n.body, n.severity, n.link, n.read_at, n.created_at,
                   UNIX_TIMESTAMP(n.created_at) AS created_ts
              FROM notifications n
             WHERE n.audience = :audience
               AND n.user_id = :uid
               AND ' . notify_visible_sql('n') . '
             ORDER BY n.created_at DESC, n.id DESC
             LIMIT :limit OFFSET :offset';
$listStmt = $conn->prepare($listSql);
$listStmt->bindValue(':audience', 'user', PDO::PARAM_STR);
$listStmt->bindValue(':uid', $uid, PDO::PARAM_INT);
// LIMIT/OFFSET bound as integers rather than interpolated — clamped ints are
// safe on paper, but the house rule is that nothing gets concatenated into SQL.
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();

$items = array();
while ($row = $listStmt->fetch(PDO::FETCH_ASSOC)) {
    $items[] = notifications_shape($row);
}

api_json(200, [
    'ok' => true,
    'data' => [
        'notifications' => $items,
        'unread_count'  => notifications_unread_count($conn, $uid),
        'page'          => $page,
        'per_page'      => $perPage,
        'total'         => $total,
        'has_more'      => ($offset + count($items)) < $total,
    ],
]);
