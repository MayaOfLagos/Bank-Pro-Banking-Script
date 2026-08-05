<?php
require_once("./include/adminloginFunction.php");
require_once("./include/session.php");

if (@$_SESSION['admin']) {
    header('Location:./dashboard.php');
    exit;
}
header('Location:./login.php');
exit;
