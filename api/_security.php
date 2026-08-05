<?php

declare(strict_types=1);

require_once __DIR__ . '/../include/admin_alerts.php';

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
            'data' => [
                'locked_until' => (string)$lockedUntil,
                'attempts_remaining' => 0,
            ],
        ]);
    }
}

function security_record_verify_failure(PDO $conn, int $userId): array
{
    // Read the lock state (and who this is) before the write, so the operator
    // alert below can fire on the transition into a lockout and not on every
    // failed attempt.
    $before = $conn->prepare('SELECT firstname, lastname, acct_no, verify_locked_until FROM users WHERE id = :id LIMIT 1');
    $before->execute(['id' => $userId]);
    $prior = $before->fetch(PDO::FETCH_ASSOC) ?: [];
    $priorLock = (string)($prior['verify_locked_until'] ?? '');
    $wasLocked = $priorLock !== '' && strtotime($priorLock) > time();

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

    $read = $conn->prepare('SELECT verify_attempts, verify_locked_until FROM users WHERE id = :id LIMIT 1');
    $read->execute(['id' => $userId]);
    $row = $read->fetch(PDO::FETCH_ASSOC) ?: [];
    $attempts = (int)($row['verify_attempts'] ?? 0);

    // Tell the operators the moment an account trips the lockout — one is a
    // forgotten PIN, several across accounts in a short window is stuffing.
    $newLock = (string)($row['verify_locked_until'] ?? '');
    if (!$wasLocked && $newLock !== '' && strtotime($newLock) > time()) {
        $customer = trim((string)($prior['firstname'] ?? '') . ' ' . (string)($prior['lastname'] ?? ''));
        if ($customer === '') {
            $customer = (string)($prior['acct_no'] ?? '') !== '' ? (string)$prior['acct_no'] : (string)$userId;
        }
        admin_notify(
            (new AdminAlert)->adminCustomerLockoutMsg($customer, $attempts, $newLock, (string)($_SERVER['REMOTE_ADDR'] ?? '')),
            'Customer account locked after failed attempts'
        );
    }

    return [
        'attempts' => $attempts,
        'attempts_remaining' => max(0, SECURITY_VERIFY_MAX_ATTEMPTS - $attempts),
        'locked_until' => $row['verify_locked_until'] ?? null,
    ];
}

function security_reset_verify_attempts(PDO $conn, int $userId): void
{
    $stmt = $conn->prepare('UPDATE users SET verify_attempts = 0, verify_locked_until = NULL WHERE id = :id');
    $stmt->execute(['id' => $userId]);
}
