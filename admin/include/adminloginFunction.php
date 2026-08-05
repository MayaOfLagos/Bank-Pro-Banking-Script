<?php
/**
 * Admin authentication.
 *
 * This file is required by admin/layout/header.php, admin/login.php,
 * admin/index.php and admin/db-backup.php, so it runs on every admin request.
 * Two things it exports at file scope are depended on by those callers and
 * must not be removed:
 *
 *   * `$conn`  — admin/layout/header.php:17 and :31 use it directly.
 *   * the side effect of starting a session.
 *
 * Everything else now lives inside admin_handle_login(). Previously the whole
 * credential flow sat at file scope, which meant (a) the `return` used by the
 * IP-denied branch returned out of the *include* rather than stopping the
 * request, and (b) its locals ($sql, $stmt, $row, $ip, ...) leaked into the
 * global scope of every admin page that included this file.
 */

declare(strict_types=1);

/**
 * Session bootstrap.
 *
 * The hardening flags are applied HERE, not only in admin/include/session.php.
 * admin/layout/header.php requires this file (line 3) before session.php
 * (line 5), and session.php's hardening block is gated on
 * `session_status() === PHP_SESSION_NONE`. The bare `session_start()` that
 * used to be on line 2 of this file therefore made that gate false on every
 * admin request, so cookie_httponly, cookie_samesite and use_strict_mode were
 * silently never applied to any admin session: the cookie was readable by
 * JavaScript, sent cross-site, and PHP would adopt an attacker-supplied
 * session ID.
 *
 * Setting the flags before the start call here fixes it without depending on
 * include order. session.php's own block still runs first when it happens to
 * load first (admin/db-backup.php), and no-ops harmlessly otherwise.
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

require_once("../include/config.php");
require_once __DIR__ . "/../../include/auth_flow.php";
require_once __DIR__ . "/../../include/audit.php";
require_once "adminFunction.php";
require_once __DIR__ . "/adminClass.php";

$conn = dbConnect();

/**
 * Failed logins after which the account is locked, and for how long.
 * Mirrors SECURITY_VERIFY_MAX_ATTEMPTS / SECURITY_VERIFY_LOCK_MINUTES in
 * api/_security.php so both sides of the app enforce the same policy.
 * Backed by admin.verify_attempts / verify_locked_until — see
 * SQL File/migrations/2026_08_05_01_admin_login_rate_limit.sql.
 */
const ADMIN_LOGIN_MAX_ATTEMPTS = 5;
const ADMIN_LOGIN_LOCK_MINUTES = 15;

if (!function_exists('admin_login_audit')) {
    /**
     * Write an admin_audit_log row for a login event.
     *
     * include/audit.php's audit_log() deliberately no-ops when there is no
     * admin session, which is exactly the case for a FAILED login — the one
     * event most worth recording. This writes directly instead, resolving the
     * admin id when the email matches a real row and falling back to 0 when
     * it does not (an unknown-email attempt is still worth a row).
     *
     * Best-effort, like audit_log(): a logging failure must never block or
     * break authentication.
     */
    function admin_login_audit(PDO $conn, string $action, string $email, array $details = []): void
    {
        try {
            $adminId   = 0;
            $adminName = $email;

            $lookup = $conn->prepare("SELECT id, firstname, lastname FROM admin WHERE admin_email = :email LIMIT 1");
            $lookup->execute(['email' => $email]);
            if ($found = $lookup->fetch(PDO::FETCH_ASSOC)) {
                $adminId = (int)$found['id'];
                $name    = trim(($found['firstname'] ?? '') . ' ' . ($found['lastname'] ?? ''));
                $adminName = $name !== '' ? $name : $email;
            }

            $stmt = $conn->prepare(
                "INSERT INTO admin_audit_log
                    (admin_id, admin_name, action, target_type, target_id, details, ip_address)
                 VALUES
                    (:admin_id, :admin_name, :action, 'admin', :target_id, :details, :ip_address)"
            );
            $stmt->execute([
                'admin_id'   => $adminId,
                'admin_name' => substr($adminName, 0, 200),
                'action'     => substr($action, 0, 100),
                'target_id'  => substr($email, 0, 50),
                'details'    => $details === [] ? null : json_encode($details, JSON_UNESCAPED_SLASHES),
                'ip_address' => substr(auth_flow_client_ip(), 0, 45),
            ]);
        } catch (\Throwable $e) {
            error_log('[admin_login_audit] ' . $action . ' failed: ' . $e->getMessage());
        }
    }
}

if (!function_exists('admin_login_locked_until')) {
    /**
     * Returns the lock expiry as a timestamp when the account is currently
     * locked out, or null when it is free to attempt.
     *
     * Tolerates the columns not existing yet: if the rate-limit migration has
     * not been applied, this returns null and login behaves as it did before,
     * rather than fataling the admin panel.
     */
    function admin_login_locked_until(array $admin): ?int
    {
        $until = $admin['verify_locked_until'] ?? null;
        if ($until === null || $until === '') {
            return null;
        }
        $ts = strtotime((string)$until);

        return ($ts !== false && $ts > time()) ? $ts : null;
    }
}

if (!function_exists('admin_login_record_failure')) {
    /**
     * Increment the failure counter and lock the account once it hits the cap.
     *
     * @return array{attempts:int,locked_until:?string} State AFTER the update,
     *         so the caller can tell whether this attempt was the one that
     *         tripped the lock. Returns attempts=0 when the columns are not
     *         present (migration not yet applied).
     */
    function admin_login_record_failure(PDO $conn, int $adminId): array
    {
        try {
            $stmt = $conn->prepare(
                'UPDATE admin
                    SET verify_attempts = verify_attempts + 1,
                        verify_locked_until = CASE
                            WHEN verify_attempts + 1 >= :max THEN DATE_ADD(NOW(), INTERVAL :mins MINUTE)
                            ELSE verify_locked_until
                        END
                  WHERE id = :id'
            );
            $stmt->execute([
                'id'   => $adminId,
                'max'  => ADMIN_LOGIN_MAX_ATTEMPTS,
                'mins' => ADMIN_LOGIN_LOCK_MINUTES,
            ]);

            $read = $conn->prepare('SELECT verify_attempts, verify_locked_until FROM admin WHERE id = :id LIMIT 1');
            $read->execute(['id' => $adminId]);
            $row = $read->fetch(PDO::FETCH_ASSOC) ?: [];

            return [
                'attempts'     => (int)($row['verify_attempts'] ?? 0),
                'locked_until' => $row['verify_locked_until'] ?? null,
            ];
        } catch (\Throwable $e) {
            // Columns missing (migration not applied) or DB blip — never let
            // bookkeeping stop the auth decision that already happened.
            error_log('[admin_login] could not record failure: ' . $e->getMessage());

            return ['attempts' => 0, 'locked_until' => null];
        }
    }
}

if (!function_exists('admin_login_reset_failures')) {
    /** Clear the counter and any lock after a successful password check. */
    function admin_login_reset_failures(PDO $conn, int $adminId): void
    {
        try {
            $stmt = $conn->prepare('UPDATE admin SET verify_attempts = 0, verify_locked_until = NULL WHERE id = :id');
            $stmt->execute(['id' => $adminId]);
        } catch (\Throwable $e) {
            error_log('[admin_login] could not reset failures: ' . $e->getMessage());
        }
    }
}

if (!function_exists('admin_login_geo_lookup')) {
    /**
     * Best-effort city/country for an IP, for the login-alert email.
     *
     * Called AFTER the session is established, never before. The previous
     * version ran a plain-HTTP, no-timeout file_get_contents() between the
     * password check and the session write: if ip-api.com was slow or
     * rate-limiting (its free tier caps at 45 req/min), PHP's
     * max_execution_time killed the script with the credentials already
     * verified and no session created — a correct password that looked like a
     * failed login, with the `@` suppressor ensuring nothing was logged.
     *
     * Now: https, a 2-second cap on both connect and read, and any failure
     * simply yields 'Unknown'.
     */
    function admin_login_geo_lookup(string $ip): string
    {
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return 'Localhost';
        }
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'Unknown';
        }

        $context = stream_context_create([
            'http'  => ['timeout' => 2, 'ignore_errors' => true, 'method' => 'GET'],
            'https' => ['timeout' => 2, 'ignore_errors' => true, 'method' => 'GET'],
        ]);

        $raw = @file_get_contents(
            'https://ip-api.com/json/' . urlencode($ip) . '?fields=city,regionName,country',
            false,
            $context
        );
        if ($raw === false) {
            return 'Unknown';
        }

        $geo = json_decode($raw, true);
        if (!is_array($geo) || empty($geo['city'])) {
            return 'Unknown';
        }

        return trim(implode(', ', array_filter([
            (string)($geo['city'] ?? ''),
            (string)($geo['regionName'] ?? ''),
            (string)($geo['country'] ?? ''),
        ])));
    }
}

if (!function_exists('admin_handle_login')) {
    /**
     * Handle an `admin_login` POST. Returns true when a session was created
     * (the caller has already been redirected and the script exited), false
     * when the attempt was rejected and the login form should re-render.
     */
    function admin_handle_login(PDO $conn): bool
    {
        // IP allow/block-list is enforced BEFORE credentials are checked.
        // Same policy as the customer login endpoint — a banned IP shouldn't
        // even find out whether an admin account matches the address it
        // supplied.
        $adminIpCheck = auth_flow_ip_allowed($conn, auth_flow_client_ip());
        if (!$adminIpCheck['allowed']) {
            toast_alert('error', auth_flow_ip_denied_message());
            return false;
        }

        // Deliberately NOT passed through inputValidation(): that helper is
        // htmlspecialchars(htmlentities(trim($v))), an output-encoding step,
        // and running it over a password silently rewrites any password
        // containing & < > " ' before it reaches password_verify(). The
        // address is validated as an address instead, and the password is
        // taken raw — see the legacy fallback below for how existing hashes
        // written through the old mangling path keep working.
        $admin_email    = trim((string)($_POST['admin_email'] ?? ''));
        $admin_password = (string)($_POST['admin_password'] ?? '');

        $genericError = 'Incorrect password or email address.';

        if ($admin_email === '' || !filter_var($admin_email, FILTER_VALIDATE_EMAIL)) {
            toast_alert('error', $genericError);
            return false;
        }

        $stmt = $conn->prepare("SELECT * FROM admin WHERE admin_email = :admin_email LIMIT 1");
        $stmt->execute(['admin_email' => $admin_email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // `$row === false` rather than rowCount(): PDO leaves rowCount()
        // unspecified for SELECT statements.
        if ($row === false) {
            admin_login_audit($conn, 'admin.login_failed', $admin_email, ['reason' => 'unknown_email']);
            toast_alert('error', $genericError);
            return false;
        }

        $adminId = (int)$row['id'];

        // Lockout is checked before the password so a locked account cannot be
        // probed further, matching api/auth/login.php's ordering.
        $lockedUntil = admin_login_locked_until($row);
        if ($lockedUntil !== null) {
            admin_login_audit($conn, 'admin.login_blocked', $admin_email, [
                'reason'       => 'locked_out',
                'locked_until' => date('c', $lockedUntil),
            ]);
            toast_alert('error', 'Too many failed attempts. Try again in ' . ADMIN_LOGIN_LOCK_MINUTES . ' minutes.');
            return false;
        }

        $storedHash = (string)$row['admin_password'];
        $validPassword = password_verify($admin_password, $storedHash);

        // Legacy fallback: admin/profile.php historically ran the new password
        // through inputValidation() before password_hash(), so hashes written
        // that way only match the mangled form. Accept it once, then silently
        // re-hash from the raw password so the account converges on the
        // correct value. Costs one extra bcrypt only on an otherwise-failed
        // attempt.
        $needsLegacyRehash = false;
        if (!$validPassword && function_exists('inputValidation')) {
            $legacy = inputValidation($admin_password);
            if ($legacy !== $admin_password && password_verify($legacy, $storedHash)) {
                $validPassword = true;
                $needsLegacyRehash = true;
            }
        }

        if (!$validPassword) {
            $state = admin_login_record_failure($conn, $adminId);
            admin_login_audit($conn, 'admin.login_failed', $admin_email, [
                'reason'   => 'bad_password',
                'attempts' => $state['attempts'],
            ]);

            // Alert on the lockout, not on every miss: one typo should not
            // generate mail, but the fifth consecutive failure is a
            // brute-force signal and is exactly what nothing reported before.
            if ($state['locked_until'] !== null && $state['attempts'] >= ADMIN_LOGIN_MAX_ATTEMPTS) {
                try {
                    admin_notify(
                        (new emailMessage())->adminFailedLoginAlertMsg(
                            $admin_email,
                            auth_flow_client_ip(),
                            substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 300),
                            $state['attempts'],
                            (string)$state['locked_until'],
                            date('D, d M Y H:i:s T')
                        ),
                        'Admin account locked after failed sign-in attempts'
                    );
                } catch (\Throwable $e) {
                    error_log('[admin_login] lockout alert failed: ' . $e->getMessage());
                }
            }

            toast_alert('error', $genericError);
            return false;
        }

        admin_login_reset_failures($conn, $adminId);

        // Transparent hash upgrade: legacy-mangled hashes, and any hash whose
        // cost factor has fallen behind the current default.
        if ($needsLegacyRehash || password_needs_rehash($storedHash, PASSWORD_BCRYPT)) {
            try {
                $rehash = $conn->prepare('UPDATE admin SET admin_password = :hash WHERE id = :id');
                $rehash->execute([
                    'hash' => password_hash($admin_password, PASSWORD_BCRYPT),
                    'id'   => $adminId,
                ]);
            } catch (\Throwable $e) {
                error_log('[admin_login] password rehash failed: ' . $e->getMessage());
            }
        }

        // Session fixation defence: issue a fresh session ID now that the
        // identity has changed. Matches api/auth/login.php:98.
        session_regenerate_id(true);

        $adminName = trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''));
        if ($adminName === '') {
            $adminName = (string)$row['admin_email'];
        }

        $_SESSION['admin']              = (string)$row['admin_email'];
        // Stored alongside the email because the email is mutable — an admin
        // changing their own address via admin/profile.php otherwise leaves a
        // session whose only identity token no longer resolves, and
        // include/audit.php then records every later action as admin_id=0.
        $_SESSION['admin_id']           = $adminId;
        $_SESSION['admin_welcome_name'] = $adminName;
        $_SESSION['session_started_at'] = time();
        $_SESSION['last_activity']      = time();

        // Everything below is best-effort notification. It runs AFTER the
        // session is written so a slow geo provider or SMTP host can delay the
        // response but can never cost the admin their login.
        $ip        = auth_flow_client_ip();
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 300);
        $loginTime = date('D, d M Y H:i:s T');

        audit_log('admin.login_success', 'admin', (string)$adminId, [
            'email'      => $row['admin_email'],
            'ip'         => $ip,
            'user_agent' => $userAgent,
        ]);

        try {
            $location = admin_login_geo_lookup($ip);

            $emailTpl = new emailMessage();
            $mailer   = new message();
            $mailer->send_mail(
                (string)$row['admin_email'],
                $emailTpl->adminLoginAlertMsg($adminName, $ip, $location, $userAgent, $loginTime),
                'Admin login alert - ' . emailMessage::brandName()
            );
        } catch (\Throwable $e) {
            error_log('[admin_login] alert email failed: ' . $e->getMessage());
        }

        header('Location: ./dashboard.php');
        exit;
    }
}

if (isset($_POST['admin_login'])) {
    admin_handle_login($conn);
}
