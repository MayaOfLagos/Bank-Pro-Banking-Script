<?php
require_once __DIR__ . '/_bootstrap.php';

$email = filter_var(trim((string)($_GET['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$token = trim((string)($_GET['reset_token'] ?? ''));

if (!$email || !preg_match('/^[a-zA-Z0-9_\-]{8,}$/', $token)) {
    auth_json(422, ['ok' => false, 'message' => 'Reset link is invalid or has expired.']);
}

$stmt = $conn->prepare('SELECT id FROM users WHERE acct_email = :email AND resettoken = :token AND resettokenexp >= NOW() LIMIT 1');
$stmt->execute([
    'email' => $email,
    'token' => $token,
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    auth_json(422, ['ok' => false, 'message' => 'Reset link is invalid or has expired.']);
}

auth_json(200, [
    'ok' => true,
    'message' => 'Reset link validated',
]);
