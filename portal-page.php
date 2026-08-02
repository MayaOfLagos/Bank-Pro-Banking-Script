<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/include/config.php';

if (empty($_SESSION['acct_no'])) {
    header('Location:/login');
    exit;
}

$conn = dbConnect();
$stmt = $conn->prepare("SELECT url_name FROM settings WHERE id='1' LIMIT 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);
$appName = $settings['url_name'] ?? WEB_TITLE;
$pageTitle = $pageTitle ?? 'Portal';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars($appName) ?> - <?= htmlspecialchars($pageTitle) ?></title>
  <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico"/>
  <link rel="stylesheet" href="/assets/user-app/app.css" />
</head>
<body>
  <div id="app"></div>
  <script type="module" src="/assets/user-app/app.js"></script>
</body>
</html>
