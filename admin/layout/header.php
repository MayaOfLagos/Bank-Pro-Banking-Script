<?php
require_once("./include/adminloginFunction.php");
require_once("./include/session.php");
require_once("./include/adminClass.php");

// Buffering is registered AFTER the includes above so admin_csrf_inject_forms()
// (declared in include/adminFunction.php) exists by the time ob_start() binds
// it. Every byte the panel emits now passes through the callback, which stamps
// the session CSRF token into each method="post" form — no template has to
// remember to do it, and a new page cannot silently ship unprotected.
ob_start('admin_csrf_inject_forms');

if (empty($_SESSION['admin'])) {
    header("location:./login.php");
    die;
}

// Reject any state-changing POST that did not carry the token. Placed after the
// auth gate (so an anonymous POST still just bounces to the login page) and
// before any page-level handler runs, so every mutation in the panel is covered
// by one check rather than twenty.
if (!admin_csrf_valid()) {
    // Distinguish the one benign cause of a missing token: when an upload
    // exceeds post_max_size PHP discards the *entire* request body — $_POST,
    // $_FILES and the injected token with it — and hands the script an empty
    // POST. Reporting that as a security failure would hide the real problem
    // (the file is too big), which is exactly the class of misleading message
    // this panel is being cleaned of.
    $bodyDropped = empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    http_response_code($bodyDropped ? 413 : 403);
    header('Content-Type: text/html; charset=utf-8');
    if ($bodyDropped) {
        $postLimit = ini_get('post_max_size') ?: 'the server limit';
        echo '<!doctype html><meta charset="utf-8"><title>Upload too large</title>'
           . '<div style="font:16px/1.5 system-ui,sans-serif;max-width:38em;margin:4em auto;padding:0 1em">'
           . '<h1 style="font-size:1.3em">Upload too large</h1>'
           . '<p>The request was bigger than <code>post_max_size</code> (' . htmlspecialchars((string)$postLimit) . '), '
           . 'so PHP discarded it before this page ran. Nothing was saved.</p>'
           . '<p>Upload a smaller file, or raise <code>post_max_size</code> and <code>upload_max_filesize</code>.</p>'
           . '<p><a href="./dashboard.php">Return to the dashboard</a>.</p></div>';
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Request blocked</title>'
           . '<div style="font:16px/1.5 system-ui,sans-serif;max-width:38em;margin:4em auto;padding:0 1em">'
           . '<h1 style="font-size:1.3em">Request blocked</h1>'
           . '<p>This action was rejected because its security token was missing or stale. '
           . 'That normally means the page sat open long enough for your session to be renewed.</p>'
           . '<p><a href="./dashboard.php">Return to the dashboard</a> and try again.</p></div>';
    }
    exit;
}

require_once __DIR__ . '/../../include/branding.php';
require_once __DIR__ . '/../../include/audit.php';
// Loaded panel-wide for the same reason audit.php is: every admin page is a
// potential emitter (notify_admin()) and every admin page renders the navbar
// bell, so requiring it here means no individual page can forget to.
require_once __DIR__ . '/../../include/notifications.php';
require_once __DIR__ . '/../include/adminNotifications.php';

$sql = "SELECT * FROM settings WHERE id ='1'";
$stmt = $conn->prepare($sql);
$stmt->execute();
$page = $stmt->fetch(PDO::FETCH_ASSOC);

$title       = $page['url_name'];
$adminFavicon = resolve_favicon($page ?: [], __DIR__ . '/../..');
$adminLogoUrl = resolve_logo_url($page ?: [], __DIR__ . '/../..');
$pageTitle   = $title;
$BANK_PHONE  = $page['url_tel'];

$email_message = new message();
$sendMail      = new emailMessage();

$sql  = "SELECT * FROM admin";
$data = $conn->prepare($sql);
$data->execute();
$row       = $data->fetch(PDO::FETCH_ASSOC);
$adminName = $row['firstname'] . " " . $row['lastname'];

$currentPage = basename($_SERVER['PHP_SELF']);

// First paint of the navbar bell. Rendered server-side rather than left for
// the poller to fill in, so the badge is correct in the very first frame and
// the dropdown still works with JavaScript disabled — dist/js/admin-notifications.js
// only keeps this snapshot fresh. Both helpers degrade to empty/0 when the
// notifications migration has not been applied on this host.
$bellNotifications = admin_notify_recent($conn, 10);
$bellUnreadCount   = admin_notify_unread_count($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> - Admin Dashboard</title>

    <link rel="icon" type="<?= htmlspecialchars($adminFavicon['type']) ?>" href="<?= htmlspecialchars($adminFavicon['href']) ?>"/>

    <!-- CSRF token for XHR. The panel's normal protection is the output-buffer
         form stamper in include/adminFunction.php, which cannot help a fetch()
         call — there is no form. dist/js/admin-notifications.js reads this and
         sends it as an X-Admin-Csrf header, which notifications-feed.php
         verifies with hash_equals(). A custom header is unsettable by a
         cross-origin form post, so this is not a weakening of the scheme. -->
    <meta name="admin-csrf" content="<?= htmlspecialchars(admin_csrf_token(), ENT_QUOTES) ?>">

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="./plugins/fontawesome-free/css/all.min.css">
    <!-- Ionicons (only loaded for icon parity with AdminLTE demo) -->
    <!-- DataTables -->
    <link rel="stylesheet" href="./plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="./plugins/datatables-responsive/css/responsive.bootstrap4.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="./plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    <!-- Select2 -->
    <link rel="stylesheet" href="./plugins/select2/css/select2.min.css">
    <link rel="stylesheet" href="./plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
    <!-- Toastr -->
    <link rel="stylesheet" href="./plugins/toastr/toastr.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="./dist/css/adminlte.min.css">

    <!-- Notification bell poller. `defer` rather than a footer <script> for two
         reasons: it keeps the whole feature in the one file that owns the bell
         markup, and deferred scripts run after every parser-inserted script in
         the body, so nothing here can race layout/footer.php. It has no
         dependency on jQuery either way. -->
    <script defer src="./dist/js/admin-notifications.js"></script>

    <!-- Brand overrides: disband AdminLTE's circular logo wrapper + drop
         the wordmark next to it so the admin-uploaded logo can breathe
         the same way the customer portal login does. -->
    <style>
      .brand-link.brand-link--logo-only {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0.85rem 0.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      }
      .brand-link.brand-link--logo-only .brand-image-free {
        max-height: 46px;
        max-width: 80%;
        width: auto;
        height: auto;
        object-fit: contain;
        opacity: 1;
        margin: 0;
        border-radius: 0;
        box-shadow: none;
        float: none;
      }
      .brand-link.brand-link--logo-only .brand-text-fallback {
        color: #fff;
        font-weight: 500;
        font-size: 1.05rem;
        letter-spacing: 0.01em;
      }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="./dashboard.php" class="nav-link">Home</a>
            </li>
        </ul>

        <ul class="navbar-nav ml-auto">
            <!-- Notification bell. Server-rendered snapshot; refreshed in place
                 by dist/js/admin-notifications.js. The `data-notify-*` hooks are
                 what that script binds to — renaming one silently breaks the
                 refresh while leaving the first paint looking fine, so keep them
                 in step with the selectors at the top of that file. -->
            <li class="nav-item dropdown" id="adminNotifyBell"
                data-feed-url="./notifications-feed.php"
                data-unread="<?= (int)$bellUnreadCount ?>">
                <a class="nav-link" data-toggle="dropdown" href="#" role="button"
                   aria-haspopup="true" aria-expanded="false"
                   aria-label="Notifications (<?= (int)$bellUnreadCount ?> unread)">
                    <i class="far fa-bell"></i>
                    <span class="badge badge-danger navbar-badge" data-notify-badge
                          <?= $bellUnreadCount > 0 ? '' : 'hidden style="display:none"' ?>><?= $bellUnreadCount > 99 ? '99+' : (int)$bellUnreadCount ?></span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-item dropdown-header" data-notify-header>
                        <?= (int)$bellUnreadCount ?> unread notification<?= (int)$bellUnreadCount === 1 ? '' : 's' ?>
                    </span>
                    <div class="dropdown-divider"></div>
                    <div data-notify-list>
                        <?php if (empty($bellNotifications)): ?>
                            <span class="dropdown-item text-muted text-sm">No notifications yet.</span>
                        <?php else: ?>
                            <?php foreach ($bellNotifications as $bellIndex => $bellRow): ?>
                                <?php if ($bellIndex > 0): ?><div class="dropdown-divider"></div><?php endif; ?>
                                <?php
                                // Titles and bodies are admin-typed free text and go
                                // through htmlspecialchars() without exception. The
                                // severity class and icon come from allowlists in
                                // admin/include/adminNotifications.php, never from the
                                // stored string, so nothing user-supplied reaches an
                                // attribute here.
                                $bellRead     = !empty($bellRow['read_at']);
                                $bellExcerpt  = admin_notify_excerpt(isset($bellRow['body']) ? $bellRow['body'] : '', 90);
                                ?>
                                <a href="./notifications.php?highlight=<?= (int)$bellRow['id'] ?>"
                                   class="dropdown-item<?= $bellRead ? '' : ' bg-light' ?>">
                                    <i class="<?= htmlspecialchars(admin_notify_severity_icon($bellRow['severity'])) ?> text-<?= htmlspecialchars(admin_notify_severity_class($bellRow['severity'])) ?> mr-2"></i>
                                    <span class="font-weight-<?= $bellRead ? 'normal' : 'bold' ?>"><?= htmlspecialchars((string)$bellRow['title']) ?></span>
                                    <span class="float-right text-muted text-sm"><?= htmlspecialchars(admin_notify_relative_time($bellRow['created_at'], admin_notify_now_ts($conn))) ?></span>
                                    <?php if ($bellExcerpt !== ''): ?>
                                        <p class="text-sm text-muted mb-0 text-truncate"><?= htmlspecialchars($bellExcerpt) ?></p>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-footer" data-notify-read-all>Mark all as read</a>
                    <div class="dropdown-divider"></div>
                    <a href="./notifications.php" class="dropdown-item dropdown-footer">See all notifications</a>
                </div>
            </li>

            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                    <img src="<?= htmlspecialchars(admin_profile_src($row['image'])) ?>" class="user-image img-circle elevation-2" alt="Admin">
                    <span class="d-none d-md-inline"><?= htmlspecialchars($adminName) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <li class="user-header bg-primary">
                        <img src="<?= htmlspecialchars(admin_profile_src($row['image'])) ?>" class="img-circle elevation-2" alt="Admin">
                        <p>
                            <?= htmlspecialchars($adminName) ?>
                            <small>Administrator</small>
                        </p>
                    </li>
                    <li class="user-footer">
                        <a href="./profile.php" class="btn btn-default btn-flat">Profile</a>
                        <a href="./logout.php" class="btn btn-default btn-flat float-right">Sign out</a>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->

    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
