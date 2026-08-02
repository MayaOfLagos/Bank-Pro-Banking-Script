<?php
require_once __DIR__ . '/_bootstrap.php';

if (empty($_SESSION['login'])) {
    auth_json(401, ['ok' => false, 'message' => 'Login session missing']);
}

$stmt = $conn->prepare('SELECT firstname, lastname, image, acct_no FROM users WHERE acct_no = :acct_no LIMIT 1');
$stmt->execute(['acct_no' => (string)$_SESSION['login']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    auth_json(404, ['ok' => false, 'message' => 'Account not found']);
}

auth_json(200, [
    'ok' => true,
    'message' => 'PIN context loaded',
    'data' => [
        'fullname' => trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')),
        'image' => '/assets/profile/' . ($user['image'] ?? ''),
        'acct_no' => (string)($user['acct_no'] ?? ''),
    ],
]);
