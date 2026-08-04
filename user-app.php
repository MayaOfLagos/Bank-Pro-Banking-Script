<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/include/config.php';
require_once __DIR__ . '/include/branding.php';

/**
 * Front controller for the Vue-owned authentication and customer portal.
 * The admin panel deliberately does not pass through this file.
 */
$routeTitles = [
    '/login' => 'Login',
    '/register' => 'Create Account',
    '/pin' => 'PIN Verification',
    '/reset-password' => 'Reset Password',
    '/update-password' => 'Update Password',
    '/terms' => 'Terms of Service',
    '/privacy' => 'Privacy Policy',
    '/dashboard' => 'Dashboard',
    '/transactions' => 'Transactions',
    '/deposits' => 'Deposits',
    '/wire-transfer' => 'Wire Transfer',
    '/domestic-transfer' => 'Domestic Transfer',
    '/withdrawals' => 'Withdrawals',
    '/cards' => 'Cards',
    '/loans' => 'Loans',
    '/tickets' => 'Support',
    '/profile' => 'Profile',
    '/profile/edit' => 'Edit Personal Details',
    '/profile/security' => 'Security',
    '/profile/manager' => 'Account Manager',
    '/transfer-verify' => 'Verify Transfer',
    '/transfer-cot' => 'COT Verification',
    '/transfer-tax' => 'TAX Verification',
    '/transfer-imf' => 'IMF Verification',
    '/transfer-success' => 'Transfer Complete',
];

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = '/' . trim(rawurldecode($requestPath), '/');
$requestPath = $requestPath === '//' ? '/' : $requestPath;

// Dynamic detail routes: the SPA owns the exact rendering; the PHP shell
// only needs to resolve a page title for the initial <head>.
if (preg_match('#^/transactions/(?:(?:transaction|deposit|wire|domestic|withdrawal)/)?\d+$#', $requestPath)) {
    $requestPath = '/transactions';
}

if (preg_match('#^/loans/LOAN-[0-9A-Fa-f]{12}$#', $requestPath)) {
    $requestPath = '/loans';
}

if (!isset($routeTitles[$requestPath])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'User portal route not found.';
    exit;
}

$guestOnlyRoutes = ['/login', '/register', '/reset-password', '/update-password'];
$pendingPinRoutes = ['/pin'];
// Public informational pages — readable regardless of auth state so a
// visitor can review /terms and /privacy from the register wizard before
// they have an account.
$publicRoutes = ['/terms', '/privacy'];
$isAuthenticated = !empty($_SESSION['acct_no']);
$isPendingPin = !empty($_SESSION['login']);

if (in_array($requestPath, $publicRoutes, true)) {
    // No auth gate — every visitor may read the legal copy.
} elseif (in_array($requestPath, $guestOnlyRoutes, true)) {
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

// Vite writes the hashed entry filenames here at build time. Reading them
// back means the <script> tag and the "../app-<hash>.js" import inside every
// lazy chunk are the same URL, so the bundle is fetched and evaluated once.
$assetDirectory = __DIR__ . '/assets/user-app';
$manifest = is_file($assetDirectory . '/manifest.json')
    ? json_decode((string)file_get_contents($assetDirectory . '/manifest.json'), true)
    : null;
// cssCodeSplit is off, so the one stylesheet is its own manifest record
// rather than an entry dependency. Match on shape, not on a source filename.
$javascriptFile = '';
$stylesheetFile = '';
foreach (is_array($manifest) ? $manifest : [] as $record) {
    if (!is_array($record) || empty($record['file'])) {
        continue;
    }
    $file = (string)$record['file'];
    if (!empty($record['isEntry']) && substr($file, -3) === '.js') {
        $javascriptFile = $file;
    } elseif (substr($file, -4) === '.css') {
        $stylesheetFile = $file;
    }
}

$assetsUsable = true;
foreach ([$javascriptFile, $stylesheetFile] as $assetFile) {
    if ($assetFile === '' || !is_file($assetDirectory . '/' . $assetFile) || filesize($assetDirectory . '/' . $assetFile) === 0) {
        $assetsUsable = false;
    }
}
if (!$assetsUsable) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'The customer portal assets are unavailable. Run the Vue production build before serving this route.';
    exit;
}

$conn = dbConnect();
$stmt = $conn->prepare("SELECT url_name, favicon FROM settings WHERE id='1' LIMIT 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$appName = (string)($settings['url_name'] ?? WEB_TITLE);
$pageTitle = $routeTitles[$requestPath];
$favicon = resolve_favicon($settings, __DIR__);
$faviconHref = $favicon['href'];
$faviconType = $favicon['type'];

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
  <link rel="icon" type="<?= htmlspecialchars($faviconType, ENT_QUOTES, 'UTF-8') ?>" href="<?= htmlspecialchars($faviconHref, ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="stylesheet" href="/assets/user-app/<?= htmlspecialchars($stylesheetFile, ENT_QUOTES, 'UTF-8') ?>" />
</head>
<body>
  <div id="app"></div>
  <script type="module" src="/assets/user-app/<?= htmlspecialchars($javascriptFile, ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
