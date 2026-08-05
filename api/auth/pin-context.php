<?php
require_once __DIR__ . '/_bootstrap.php';

if (empty($_SESSION['login'])) {
    auth_json(401, ['ok' => false, 'message' => 'Login session missing']);
}

$stmt = $conn->prepare('SELECT firstname, lastname, image, acct_no, acct_status FROM users WHERE acct_no = :acct_no LIMIT 1');
$stmt->execute(['acct_no' => (string)$_SESSION['login']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    auth_json(404, ['ok' => false, 'message' => 'Account not found']);
}

// Defence in depth: if the account was frozen after login started (admin
// action mid-session), tear down the pending-PIN session and bounce back
// to /login with the status message.
$blockMessage = auth_account_block_message($user);
if ($blockMessage !== null) {
    session_unset();
    session_destroy();
    auth_json(403, [
        'ok' => false,
        'message' => $blockMessage,
        'data' => ['acct_status' => (string)$user['acct_status'], 'next_route' => '/login'],
    ]);
}

auth_json(200, [
    'ok' => true,
    'message' => 'PIN context loaded',
    'data' => [
        'fullname' => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
        // Empty rather than a bare directory URL when nothing was uploaded, so
        // the PIN screen can tell "no picture" from "picture" and fall back to
        // initials instead of rendering a broken image.
        'image' => trim((string)($user['image'] ?? '')) !== ''
            ? '/assets/profile/' . trim((string)$user['image'])
            : '',
        'acct_no' => (string)($user['acct_no'] ?? ''),
    ],
]);
