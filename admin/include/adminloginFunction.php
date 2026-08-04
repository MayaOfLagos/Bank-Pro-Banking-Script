<?php
session_start();
require_once("../include/config.php");
require_once __DIR__ . "/../../include/auth_flow.php";
require_once "adminFunction.php";
require_once __DIR__ . "/adminClass.php";

$conn = dbConnect();


if(isset($_POST['admin_login'])){
    // IP allow/block-list is enforced BEFORE credentials are checked.
    // Same policy as the customer login endpoint — a banned IP shouldn't
    // even find out whether an admin account matches the address it
    // supplied.
    $adminIpCheck = auth_flow_ip_allowed($conn, auth_flow_client_ip());
    if (!$adminIpCheck['allowed']) {
        toast_alert('error', auth_flow_ip_denied_message());
        return;
    }

    $admin_email = inputValidation($_POST['admin_email']);
    $admin_password = inputValidation($_POST['admin_password']);

    $sql = "SELECT * FROM admin WHERE admin_email=:admin_email";
    $stmt = $conn->prepare($sql);
    $stmt->execute([
       'admin_email' => $admin_email
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if($stmt->rowCount() === 0){
        toast_alert('error', 'Incorrect password or email address.');
    }else{
        $validPassword = password_verify($admin_password, $row['admin_password']);

        if ($validPassword === false){
            toast_alert('error', 'Incorrect password or email address.');
        }else{
            // Build login meta
            $adminName  = trim($row['firstname'] . ' ' . $row['lastname']);
            $adminEmail = $row['admin_email'];
            $ip         = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $userAgent  = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $loginTime  = date('D, d M Y  H:i:s T');

            // Attempt geo-location via ip-api.com (non-blocking; fall back gracefully)
            $location = 'Unknown';
            if ($ip !== '127.0.0.1' && $ip !== '::1' && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE)) {
                $geo = @file_get_contents('http://ip-api.com/json/' . urlencode($ip) . '?fields=city,regionName,country');
                if ($geo) {
                    $geoData = json_decode($geo, true);
                    if (!empty($geoData['city'])) {
                        $location = htmlspecialchars($geoData['city'] . ', ' . $geoData['regionName'] . ', ' . $geoData['country']);
                    }
                }
            } elseif ($ip === '127.0.0.1' || $ip === '::1') {
                $location = 'Localhost';
            }

            // Send login alert email to admin only
            $mailer  = new message();
            $emailTpl = new emailMessage();
            $appName = defined('APP_NAME') ? APP_NAME : WEB_TITLE;
            $emailHtml = $emailTpl->adminLoginAlertMsg($adminName, $ip, $location, $userAgent, $loginTime, $appName);
            $mailer->send_mail($adminEmail, $emailHtml, 'Admin Login Alert – ' . $appName);

            // Set session and welcome toast
            $_SESSION['admin'] = $adminEmail;
            $_SESSION['admin_welcome_name'] = $adminName;

            header('Location: ./dashboard.php');
            exit;
        }
    }
}

