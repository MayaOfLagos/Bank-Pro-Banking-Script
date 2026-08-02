<?php

declare(strict_types=1);

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($uriPath);

$root = __DIR__;

// Let the built-in server handle existing files and directories directly.
$absolute = $root . $path;
if ($path !== '/' && (is_file($absolute) || is_dir($absolute))) {
    return false;
}

// Canonical alias: /update-password -> /updatePassword.php
if ($path === '/update-password') {
    require $root . '/updatePassword.php';
    return true;
}

// Root extensionless routing: /login -> /login.php
if ($path !== '/') {
    $candidate = $root . $path . '.php';
    if (is_file($candidate)) {
        require $candidate;
        return true;
    }

    // Legacy user fallback: /dashboard -> /user/dashboard.php (when root wrapper doesn't exist)
    $userCandidate = $root . '/user' . $path . '.php';
    if (is_file($userCandidate)) {
        require $userCandidate;
        return true;
    }
}

// Fallback to homepage.
require $root . '/index.php';
return true;
