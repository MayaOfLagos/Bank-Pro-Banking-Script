<?php
/**
 * auth_flow.php — shared helpers for the three admin-controlled
 * auth-flow policies:
 *
 *   1. Idle-session timeout (session.php calls
 *      auth_flow_apply_idle_timeout()).
 *   2. Login-time IP allow/block list (api/auth/login.php and
 *      admin/login.php call auth_flow_ip_allowed()).
 *   3. Admin-forced logout (api/user/_bootstrap.php calls
 *      auth_flow_session_invalidated_for()).
 *
 * All settings are read from the singleton settings row (id=1). The
 * caller passes an existing PDO handle so we don't open a second
 * connection just for a policy check on every request.
 *
 * PHP 7.4 compatible — no str_starts_with, no match, no readonly.
 */

declare(strict_types=1);

/**
 * settings.session_idle_minutes bounds. Values outside this range are
 * clamped in the admin form and rejected on read as a defence in depth.
 */
const AUTH_FLOW_SESSION_IDLE_MIN = 5;
const AUTH_FLOW_SESSION_IDLE_MAX = 1440;
const AUTH_FLOW_SESSION_IDLE_DEFAULT = 30;

/**
 * Load the singleton settings row. Returns [] when the row is missing
 * (fresh install pre-seed) so every caller can chain ?? defaults.
 */
function auth_flow_load_settings(PDO $conn): array
{
    try {
        $stmt = $conn->prepare("SELECT session_idle_minutes, login_ip_allowlist, login_ip_blocklist FROM settings WHERE id = '1' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    } catch (Throwable $e) {
        // Migration hasn't run yet — behave as if defaults are set so we
        // never lock the whole site out over a missing column.
        return [];
    }
}

/**
 * Clamp an admin-supplied idle-minutes value into the whitelisted range.
 * Non-numeric or out-of-range input falls back to the default.
 */
function auth_flow_normalise_idle_minutes($raw): int
{
    if (!is_numeric($raw)) {
        return AUTH_FLOW_SESSION_IDLE_DEFAULT;
    }
    $n = (int)$raw;
    if ($n < AUTH_FLOW_SESSION_IDLE_MIN) return AUTH_FLOW_SESSION_IDLE_MIN;
    if ($n > AUTH_FLOW_SESSION_IDLE_MAX) return AUTH_FLOW_SESSION_IDLE_MAX;
    return $n;
}

/**
 * Read the effective idle timeout in seconds. Falls back to the default
 * when the settings row is missing or the column value is out of range.
 */
function auth_flow_idle_seconds(PDO $conn): int
{
    $settings = auth_flow_load_settings($conn);
    $minutes = isset($settings['session_idle_minutes'])
        ? auth_flow_normalise_idle_minutes($settings['session_idle_minutes'])
        : AUTH_FLOW_SESSION_IDLE_DEFAULT;
    return $minutes * 60;
}

/**
 * Enforce the idle-timeout policy on the current session. Call after
 * session_start(). When the session has been idle for too long:
 *
 *   - session_unset() + session_destroy() clears the pending state
 *   - session_start() opens a fresh, empty session for the next request
 *   - returns true so callers (session.php) can perform their existing
 *     redirect-to-login behaviour for non-XHR requests.
 *
 * $_SESSION['last_activity'] is stamped on every call so idle time is
 * measured from the last request, not from session creation.
 */
function auth_flow_apply_idle_timeout(PDO $conn): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    $idleSeconds = auth_flow_idle_seconds($conn);
    $timedOut = false;
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity'] > $idleSeconds)) {
        session_unset();
        session_destroy();
        // Re-open so the next line stamping last_activity has somewhere
        // to write. use_strict_mode is already set by the caller.
        session_start();
        $timedOut = true;
    }
    $_SESSION['last_activity'] = time();
    return $timedOut;
}

/**
 * Split a stored IP list ("1.2.3.4, ::1 , 10.0.0.0") into individual
 * validated addresses. Bad entries are dropped, not preserved — that
 * way a typo can never accidentally match anyone.
 */
function auth_flow_parse_ip_list($raw): array
{
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $out = [];
    // Support commas, newlines and whitespace as separators so an admin
    // can paste one-per-line or CSV without either format silently
    // producing "0 valid entries".
    $parts = preg_split('/[\s,]+/', $raw) ?: [];
    foreach ($parts as $part) {
        $ip = trim($part);
        if ($ip === '') continue;
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) continue;
        // Normalise so "::1" and "0:0:0:0:0:0:0:1" compare equal.
        $packed = @inet_pton($ip);
        if ($packed === false) continue;
        $out[bin2hex($packed)] = $ip;
    }
    return array_values($out);
}

/**
 * Return true if the given IP is contained in the parsed list. Uses the
 * normalised binary form so IPv6 shorthand doesn't defeat matching.
 */
function auth_flow_ip_in_list(string $ip, array $list): bool
{
    if ($list === []) return false;
    $packed = @inet_pton($ip);
    if ($packed === false) return false;
    $needle = bin2hex($packed);
    foreach ($list as $entry) {
        $entryPacked = @inet_pton($entry);
        if ($entryPacked === false) continue;
        if (bin2hex($entryPacked) === $needle) return true;
    }
    return false;
}

/**
 * Decide whether a login attempt from $ip should be permitted.
 *
 * Rules:
 *   - allowlist set + IP not in allowlist  → block (allowlist wins)
 *   - allowlist unset                      → block only if IP is on blocklist
 *   - both unset                           → allow (open by default)
 *
 * Returns an assoc array:
 *   ['allowed' => bool, 'reason' => string]
 * $reason is 'allowlist' when the allowlist rejected the IP, 'blocklist'
 * when the blocklist matched, or '' when allowed.
 */
function auth_flow_ip_allowed(PDO $conn, string $ip): array
{
    $settings = auth_flow_load_settings($conn);
    $allow = auth_flow_parse_ip_list($settings['login_ip_allowlist'] ?? null);
    $block = auth_flow_parse_ip_list($settings['login_ip_blocklist'] ?? null);

    if ($allow !== []) {
        if (!auth_flow_ip_in_list($ip, $allow)) {
            return ['allowed' => false, 'reason' => 'allowlist'];
        }
        // Allowlist explicitly matched — allowlist wins, blocklist is ignored.
        return ['allowed' => true, 'reason' => ''];
    }

    if ($block !== [] && auth_flow_ip_in_list($ip, $block)) {
        return ['allowed' => false, 'reason' => 'blocklist'];
    }

    return ['allowed' => true, 'reason' => ''];
}

/**
 * Public standard message we surface for either allowlist or blocklist
 * rejections. Intentionally identical to prevent an attacker from
 * enumerating the difference between "you're on the blocklist" and
 * "your IP just isn't on the allowlist".
 */
function auth_flow_ip_denied_message(): string
{
    return 'Sign-in is not allowed from your location.';
}

/**
 * Pull the current request's client IP. Wrapped so callers don't scatter
 * $_SERVER access — makes it easy to swap in proxy-aware logic later
 * without editing every login endpoint.
 */
function auth_flow_client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

/**
 * Check whether an admin has forced-logout the given user AFTER the
 * given session_started_at timestamp. Returns true when the session
 * should be torn down. Missing column / missing user → false (fail
 * open, since the alternative is locking out everyone during a rollback).
 */
function auth_flow_session_invalidated_for(PDO $conn, int $userId, int $sessionStartedAt): bool
{
    try {
        $stmt = $conn->prepare('SELECT sessions_invalidated_at FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $ts = $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
    if (!$ts) return false;
    $cutoff = (int)strtotime((string)$ts);
    if ($cutoff <= 0) return false;
    // A session started at-or-after the invalidation cutoff survives.
    return $sessionStartedAt < $cutoff;
}

/**
 * Login is only allowed when acct_status is 'active'. Every other value —
 * 'hold' (pending admin review, set on signup), 'suspended', 'blocked',
 * 'inactive' — gets a status-specific message so the user knows what to
 * do. Returns null when the account is cleared to sign in.
 *
 * Kept case-insensitive because legacy admin panels sometimes write
 * capitalised values ("Hold" vs "hold").
 *
 * Lives here rather than in api/auth/_bootstrap.php so the mid-session
 * gate in api/user/_bootstrap.php refuses accounts on exactly the same
 * terms the login gate does, with identical wording.
 */
function auth_account_block_message(array $user): ?string {
    $status = auth_account_status($user);
    if ($status === 'active') {
        return null;
    }
    if ($status === 'hold' || $status === 'pending') {
        return 'Your account is pending review. You\'ll receive an email once it\'s approved.';
    }
    if ($status === 'suspended') {
        return 'Your account has been suspended. Contact support to restore access.';
    }
    if ($status === 'blocked' || $status === 'banned') {
        return 'Your account has been blocked. Contact support if you believe this is a mistake.';
    }
    if ($status === 'inactive' || $status === 'closed' || $status === 'deactivated') {
        return 'This account is not active. Contact support to reactivate.';
    }
    return 'Your account can\'t sign in right now. Contact support.';
}

/**
 * Normalised acct_status. A missing or NULL column reads as 'active' —
 * the column is nullable with an 'active' default, so treating absence as
 * a block would lock out any legacy row that predates it.
 */
function auth_account_status(array $user): string {
    return strtolower(trim((string)($user['acct_status'] ?? 'active')));
}
