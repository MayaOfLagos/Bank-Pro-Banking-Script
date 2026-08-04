<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

// Idempotent — succeeds whether or not a pending change actually exists.
// Lets the client close a modal without checking state first.
$stmt = $conn->prepare(
  'UPDATE users SET pending_email = NULL,
                    pending_email_otp = NULL,
                    pending_email_expires = NULL,
                    pending_email_attempts = 0
   WHERE id = :id'
);
$stmt->execute(['id' => $user['id']]);

api_json(200, ['ok' => true, 'message' => 'Pending email change cancelled']);
