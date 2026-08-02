<?php

declare(strict_types=1);

function api_csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function api_csrf_regenerate(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}

function api_request_requires_csrf(): bool
{
    return !in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD', 'OPTIONS'], true);
}

function api_has_valid_csrf_token(): bool
{
    $provided = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return $provided !== '' && hash_equals(api_csrf_token(), $provided);
}

function api_enforce_csrf(callable $respond): void
{
    if (api_request_requires_csrf() && !api_has_valid_csrf_token()) {
        $respond(419, [
            'ok' => false,
            'message' => 'Your security token expired. Refresh the page and try again.',
        ]);
    }
}

const SECURITY_VERIFY_MAX_ATTEMPTS = 5;
const SECURITY_VERIFY_LOCK_MINUTES = 15;

function security_enforce_verify_lock(PDO $conn, int $userId, callable $respond): void
{
    $stmt = $conn->prepare('SELECT verify_locked_until FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $userId]);
    $lockedUntil = $stmt->fetchColumn();
    if ($lockedUntil && strtotime((string)$lockedUntil) > time()) {
        $respond(429, [
            'ok' => false,
            'message' => 'Too many failed attempts. Try again in ' . SECURITY_VERIFY_LOCK_MINUTES . ' minutes.',
        ]);
    }
}

function security_record_verify_failure(PDO $conn, int $userId): void
{
    $lockClause = 'CASE WHEN verify_attempts + 1 >= :max THEN DATE_ADD(NOW(), INTERVAL :mins MINUTE) ELSE verify_locked_until END';
    $stmt = $conn->prepare(
        'UPDATE users SET verify_attempts = verify_attempts + 1, ' .
        'verify_locked_until = ' . $lockClause . ' WHERE id = :id'
    );
    $stmt->execute([
        'id' => $userId,
        'max' => SECURITY_VERIFY_MAX_ATTEMPTS,
        'mins' => SECURITY_VERIFY_LOCK_MINUTES,
    ]);
}

function security_reset_verify_attempts(PDO $conn, int $userId): void
{
    $stmt = $conn->prepare('UPDATE users SET verify_attempts = 0, verify_locked_until = NULL WHERE id = :id');
    $stmt->execute(['id' => $userId]);
}
