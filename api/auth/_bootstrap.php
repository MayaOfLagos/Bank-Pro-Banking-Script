<?php
require_once __DIR__ . '/../../session.php';
require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/smtp.php';
require_once __DIR__ . '/../_security.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function auth_json(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}

function auth_payload(): array {
    $raw = file_get_contents('php://input');
    if (!empty($raw)) {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
    }

    return $_POST ?? [];
}

function auth_field(array $payload, string $key, string $default = ''): string {
    // Do not HTML-encode transport values; that mutates passwords before verification.
    return trim((string)($payload[$key] ?? $default));
}

function auth_require(array $payload, array $fields): void {
    foreach ($fields as $field) {
        if (!isset($payload[$field]) || trim((string)$payload[$field]) === '') {
            auth_json(422, ['ok' => false, 'message' => "Missing required field: {$field}"]);
        }
    }
}

api_enforce_csrf('auth_json');

$conn = dbConnect();
$settingsStmt = $conn->prepare("SELECT * FROM settings WHERE id='1' LIMIT 1");
$settingsStmt->execute();
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$appName = $settings['url_name'] ?? WEB_TITLE;
$appUrl = WEB_URL;
$bankPhone = $settings['url_tel'] ?? '';

$mailer = new message();

function auth_send_login_email(array $user, string $device, string $ipAddress, string $dateTime, string $appName, string $appUrl, string $bankPhone, message $mailer): void {
    $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
    $to = (string)($user['acct_email'] ?? '');

    if ($to === '') {
        return;
    }

    $subject = "Login Notification - {$appName}";
    $body = "
        <div style=\"font-family:Arial,sans-serif;font-size:14px;color:#111\">
            <h3 style=\"margin-bottom:10px\">Hello {$fullName},</h3>
            <p>A login was detected on your account.</p>
            <ul>
                <li><strong>Date:</strong> {$dateTime}</li>
                <li><strong>IP Address:</strong> {$ipAddress}</li>
                <li><strong>Device:</strong> {$device}</li>
            </ul>
            <p>If this wasn't you, contact support immediately at {$bankPhone}.</p>
            <p><a href=\"{$appUrl}\">{$appName}</a></p>
        </div>
    ";

    $mailer->send_to_both($to, $body, $subject);
}

function auth_send_reset_email(array $user, string $token, string $appName, string $appUrl, message $mailer): void {
    $email = (string)($user['acct_email'] ?? '');
    if ($email === '') {
        return;
    }

    $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
    $acctNo = (string)($user['acct_no'] ?? '');
    $resetUrl = rtrim($appUrl, '/') . '/update-password?email=' . urlencode($email) . '&reset_token=' . urlencode($token);

    $subject = "Password Reset - {$appName}";
    $body = "
        <div style=\"font-family:Arial,sans-serif;font-size:14px;color:#111\">
            <h3 style=\"margin-bottom:10px\">Hi {$fullName},</h3>
            <p>We received a password reset request for account <strong>{$acctNo}</strong>.</p>
            <p><a href=\"{$resetUrl}\">Click here to reset your password</a>.</p>
            <p>If you did not request this, you can ignore this email.</p>
        </div>
    ";

    $mailer->send_mail($email, $body, $subject);
}

function auth_send_password_changed_email(array $user, string $appName, message $mailer): void {
    $email = (string)($user['acct_email'] ?? '');
    if ($email === '') {
        return;
    }

    $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
    $subject = "Password Updated - {$appName}";
    $body = "
        <div style=\"font-family:Arial,sans-serif;font-size:14px;color:#111\">
            <h3 style=\"margin-bottom:10px\">Hi {$fullName},</h3>
            <p>Your account password was changed successfully.</p>
            <p>If this wasn't you, contact support immediately.</p>
        </div>
    ";

    $mailer->send_to_both($email, $body, $subject);
}
