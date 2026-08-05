<?php
/**
 * Terminate the admin session.
 *
 * The previous version unset four session keys and returned. That left the
 * session record itself alive on the server with its ID still in the browser,
 * so anything else stored in it (admin_csrf, audit actor, welcome flash)
 * survived, and the same session ID could be reused. It also emitted no exit
 * after header(), so the rest of the file would keep executing.
 */
session_start();

// Wipe the contents, then the server-side record.
$_SESSION = [];

// A session cookie left in the browser keeps pointing at the destroyed record;
// expire it explicitly, reusing the parameters it was created with.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

setcookie('firstVisit', '', time() - 42000, '/');

header('Location: ./login.php');
exit;
