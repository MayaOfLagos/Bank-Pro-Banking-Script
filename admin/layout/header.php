<?php
ob_start();
require_once("./include/adminloginFunction.php");
require_once("./include/session.php");
require_once("./include/adminClass.php");

if (!$_SESSION['admin']) {
    header("location:./login.php");
    die;
}

require_once __DIR__ . '/../../include/branding.php';
require_once __DIR__ . '/../../include/audit.php';

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> - Admin Dashboard</title>

    <link rel="icon" type="<?= htmlspecialchars($adminFavicon['type']) ?>" href="<?= htmlspecialchars($adminFavicon['href']) ?>"/>

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
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                    <img src="../assets/profile/<?= htmlspecialchars($row['image']) ?>" class="user-image img-circle elevation-2" alt="Admin">
                    <span class="d-none d-md-inline"><?= htmlspecialchars($adminName) ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <li class="user-header bg-primary">
                        <img src="../assets/profile/<?= htmlspecialchars($row['image']) ?>" class="img-circle elevation-2" alt="Admin">
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
