<?php
require_once __DIR__ . '/../../session.php';
require_once __DIR__ . '/../../include/config.php';
require_once __DIR__ . '/../../include/smtp.php';
require_once __DIR__ . '/../../include/auth_flow.php';
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

// Both helpers render through the shared emailMessage layout so the reset and
// password-changed mails carry the same logo, footer and escaping as every
// other customer email. The hand-rolled bodies they replaced interpolated the
// customer's name straight into HTML.
function auth_send_reset_email(array $user, string $token, string $appName, string $appUrl, message $mailer, array $settings = array()): void {
    $email = (string)($user['acct_email'] ?? '');
    if ($email === '') {
        return;
    }

    require_once __DIR__ . '/../../include/userClass.php';
    $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
    $template = new emailMessage($settings);
    $body = $template->ForgotMsg($fullName, $email, (string)($user['acct_no'] ?? ''), $token, $appName, $appUrl);

    if (!$mailer->send_mail($email, $body, "Password Reset - {$appName}")) {
        error_log('[auth] password reset email failed for ' . $email . ': ' . $mailer->lastError());
    }
}

function auth_send_password_changed_email(array $user, string $appName, message $mailer, array $settings = array()): void {
    $email = (string)($user['acct_email'] ?? '');
    if ($email === '') {
        return;
    }

    require_once __DIR__ . '/../../include/userClass.php';
    $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
    $template = new emailMessage($settings);
    $body = $template->PassChange($fullName, (string)($settings['url_email'] ?? ''), $appName);

    $mailer->send_to_both($email, $body, "Password Updated - {$appName}");
}
