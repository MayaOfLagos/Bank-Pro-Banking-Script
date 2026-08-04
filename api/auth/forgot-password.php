<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auth_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = auth_payload();
auth_require($payload, ['email']);

$email = filter_var(auth_field($payload, 'email'), FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    auth_json(422, ['ok' => false, 'message' => 'Invalid email address.']);
}

$genericResponse = [
    'ok' => true,
    'message' => 'If an account matches that email, a password reset link will be sent.',
];

// Log every attempt (registered or not) so rate limiting cannot be used to
// probe account existence.
$countStmt = $conn->prepare('SELECT COUNT(*) FROM password_reset_attempts WHERE email = :email AND attempted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)');
$countStmt->execute(['email' => $email]);
if ((int)$countStmt->fetchColumn() >= 3) {
    auth_json(200, $genericResponse);
}

$log = $conn->prepare('INSERT INTO password_reset_attempts (email) VALUES (:email)');
$log->execute(['email' => $email]);

$stmt = $conn->prepare('SELECT * FROM users WHERE acct_email = :email LIMIT 1');
$stmt->execute(['email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    auth_json(200, $genericResponse);
}

$token = bin2hex(random_bytes(16));

// Compute expiry in MySQL time (not PHP time) so the timezone matches
// what NOW() will report during token validation. Previously PHP's date()
// used its own timezone which drifted 1h from the MySQL server clock and
// made reset tokens expire on issue.
$update = $conn->prepare('UPDATE users SET resettoken = :token, resettokenexp = DATE_ADD(NOW(), INTERVAL 30 MINUTE) WHERE acct_email = :email');
$update->execute([
    'token' => $token,
    'email' => $email,
]);

auth_send_reset_email($user, $token, $appName, $appUrl, $mailer);

auth_json(200, $genericResponse);
