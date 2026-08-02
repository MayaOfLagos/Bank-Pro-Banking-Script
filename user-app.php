<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/include/config.php';

/**
 * Front controller for the Vue-owned authentication and customer portal.
 * The admin panel deliberately does not pass through this file.
 */
$routeTitles = [
    '/login' => 'Login',
    '/pin' => 'PIN Verification',
    '/reset-password' => 'Reset Password',
    '/update-password' => 'Update Password',
    '/dashboard' => 'Dashboard',
    '/transactions' => 'Transactions',
    '/wire-transfer' => 'Wire Transfer',
    '/domestic-transfer' => 'Domestic Transfer',
    '/withdrawals' => 'Withdrawals',
    '/cards' => 'Cards',
    '/loans' => 'Loans',
    '/tickets' => 'Support',
    '/profile' => 'Profile',
    '/transfer-verify' => 'Verify Transfer',
    '/transfer-cot' => 'COT Verification',
    '/transfer-tax' => 'TAX Verification',
    '/transfer-imf' => 'IMF Verification',
    '/transfer-success' => 'Transfer Complete',
];

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = '/' . trim(rawurldecode($requestPath), '/');
$requestPath = $requestPath === '//' ? '/' : $requestPath;

if (!isset($routeTitles[$requestPath])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'User portal route not found.';
    exit;
}

$guestOnlyRoutes = ['/login', '/reset-password', '/update-password'];
$pendingPinRoutes = ['/pin'];
$isAuthenticated = !empty($_SESSION['acct_no']);
$isPendingPin = !empty($_SESSION['login']);

if (in_array($requestPath, $guestOnlyRoutes, true)) {
    if ($isAuthenticated) {
        header('Location: /dashboard');
        exit;
    }
    if ($isPendingPin) {
        header('Location: /pin');
        exit;
    }
} elseif (in_array($requestPath, $pendingPinRoutes, true)) {
    if ($isAuthenticated) {
        header('Location: /dashboard');
        exit;
    }
    if (!$isPendingPin) {
        header('Location: /login');
        exit;
    }
} elseif (!$isAuthenticated) {
    header('Location: ' . ($isPendingPin ? '/pin' : '/login'));
    exit;
}

$javascriptPath = __DIR__ . '/assets/user-app/app.js';
$stylesheetPath = __DIR__ . '/assets/user-app/app.css';
if (!is_file($javascriptPath) || filesize($javascriptPath) === 0 || !is_file($stylesheetPath) || filesize($stylesheetPath) === 0) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'The customer portal assets are unavailable. Run the Vue production build before serving this route.';
    exit;
}

$conn = dbConnect();
$stmt = $conn->prepare("SELECT url_name FROM settings WHERE id='1' LIMIT 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$appName = (string)($settings['url_name'] ?? WEB_TITLE);
$pageTitle = $routeTitles[$requestPath];
$assetVersion = (string)max((int)filemtime($javascriptPath), (int)filemtime($stylesheetPath));

header('Cache-Control: no-store, private');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'");
?>
<!doctype html>
<html lang="en" data-app-name="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex,nofollow" />
  <meta name="application-name" content="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>" />
  <title><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> - <?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico" />
  <link rel="stylesheet" href="/assets/user-app/app.css?v=<?= rawurlencode($assetVersion) ?>" />
</head>
<body>
  <div id="app"></div>
  <script type="module" src="/assets/user-app/app.js?v=<?= rawurlencode($assetVersion) ?>"></script>
</body>
</html>
