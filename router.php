<?php

declare(strict_types=1);

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($uriPath);
$root = __DIR__;

$vueRoutes = [
    '/login',
    '/register',
    '/pin',
    '/reset-password',
    '/update-password',
    '/terms',
    '/privacy',
    '/dashboard',
    '/transactions',
    '/deposits',
    '/wire-transfer',
    '/domestic-transfer',
    '/withdrawals',
    '/cards',
    '/loans',
    '/tickets',
    '/profile',
    '/transfer-verify',
    '/transfer-cot',
    '/transfer-tax',
    '/transfer-imf',
    '/transfer-success',
];

$legacyRedirects = [
    '/login.php' => '/login',
    '/pin.php' => '/pin',
    '/reset-password.php' => '/reset-password',
    '/updatePassword.php' => '/update-password',
    '/update-password.php' => '/update-password',
    '/dashboard.php' => '/dashboard',
    '/transactions.php' => '/transactions',
    '/wire-transfer.php' => '/wire-transfer',
    '/domestic-transfer.php' => '/domestic-transfer',
    '/withdrawals.php' => '/withdrawals',
    '/cards.php' => '/cards',
    '/loans.php' => '/loans',
    '/tickets.php' => '/tickets',
    '/profile.php' => '/profile',
    '/profile-center.php' => '/profile',
    '/profile-center' => '/profile',
    '/portal-page.php' => '/dashboard',
    '/p/forgot-password.php' => '/reset-password',
    '/p/forgot-password' => '/reset-password',
];

$legacyUserRedirects = [
    '/user/card.php' => '/cards',
    '/user/loan.php' => '/loans',
    '/user/loan-transaction.php' => '/loans',
    '/user/viewloantrans.php' => '/loans',
    '/user/profile.php' => '/profile',
    '/user/edit-profile.php' => '/profile',
    '/user/wire-transfer.php' => '/wire-transfer',
    '/user/domestic-transfer.php' => '/domestic-transfer',
    '/user/withdrawal.php' => '/withdrawals',
    '/user/withdrawal-transaction.php' => '/withdrawals',
    '/user/bank-withdraw.php' => '/withdrawals',
    '/user/credit-debit_transaction.php' => '/transactions',
    '/user/wire-transaction.php' => '/transactions',
    '/user/domestic-transaction.php' => '/transactions',
    '/user/account-manager.php' => '/tickets',
    '/user/pin.php' => '/transfer-verify',
    '/user/cot.php' => '/transfer-cot',
    '/user/tax.php' => '/transfer-tax',
    '/user/imf-code.php' => '/transfer-imf',
];

$permanentRedirect = static function (string $target): void {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: ' . $target . ($query !== '' ? '?' . $query : ''), true, 301);
    exit;
};

// Keep the built-in development server aligned with Apache's Vue route boundary.
$normalizedPath = $path !== '/' ? rtrim($path, '/') : $path;
$absolute = $root . $path;
$isExistingDirectory = $path !== '/' && is_dir($absolute);

if ($normalizedPath !== $path && !$isExistingDirectory) {
    $permanentRedirect($normalizedPath);
}

if (isset($legacyRedirects[$normalizedPath])) {
    $permanentRedirect($legacyRedirects[$normalizedPath]);
}

// The legacy customer tree is retained as source-only migration reference.
// Web requests always move to the equivalent Vue route.
if (isset($legacyUserRedirects[$normalizedPath])) {
    $permanentRedirect($legacyUserRedirects[$normalizedPath]);
}

if ($normalizedPath === '/user' || strncmp($normalizedPath, '/user/', 6) === 0) {
    $permanentRedirect('/dashboard');
}

if ($normalizedPath === '/signup' || strncmp($normalizedPath, '/signup/', 8) === 0) {
    $permanentRedirect('/register');
}

// Removed theme bundles must return a real 404 instead of the public homepage.
if (preg_match('#^/(?:plugins|bootstrap|assets/(?:auth|user|css|js))(?:/|$)#', $normalizedPath)) {
    http_response_code(404);
    echo 'Not found';
    return true;
}

// Let the built-in server handle existing files and directories directly.
if ($path !== '/' && (is_file($absolute) || is_dir($absolute))) {
    return false;
}

if (in_array($normalizedPath, $vueRoutes, true)
    || preg_match('#^/transactions/\d+$#', $normalizedPath)) {
    require $root . '/user-app.php';
    return true;
}

// Preserve extensionless URLs for existing public PHP pages.
if ($path !== '/') {
    $candidate = $root . $path . '.php';
    if (is_file($candidate)) {
        require $candidate;
        return true;
    }
}

// Fallback to homepage.
require $root . '/index.php';
return true;
