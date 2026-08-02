<?php
require_once __DIR__ . '/_bootstrap.php';

$authState = 'guest';
$nextRoute = '/login';

if (!empty($_SESSION['acct_no'])) {
    $authState = 'authenticated';
    $nextRoute = '/dashboard';
} elseif (!empty($_SESSION['login'])) {
    $authState = 'pending_pin';
    $nextRoute = '/pin';
}

auth_json(200, [
    'ok' => true,
    'message' => 'Auth status loaded',
    'data' => [
        'state' => $authState,
        'is_authenticated' => $authState === 'authenticated',
        'is_pending_pin' => $authState === 'pending_pin',
        'next_route' => $nextRoute,
        'csrf_token' => api_csrf_token(),
    ],
]);
