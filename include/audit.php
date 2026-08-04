<?php
/**
 * Admin audit trail helper.
 *
 * audit_log($action, $targetType, $targetId, $details)
 *   Records a single row in `admin_audit_log`. Reads the current admin
 *   from $_SESSION['admin'] (which stores the admin's email — the login
 *   flow sets it in admin/include/adminloginFunction.php). We resolve the
 *   email back to admin.id + display name at write time so the log stays
 *   useful even if an admin row is later renamed.
 *
 * Design notes:
 *   * Silently no-ops when there is no admin session. This keeps the helper
 *     safe to sprinkle into shared code paths that may run outside the
 *     admin panel (e.g. cron).
 *   * Wrapped in try/catch so a missing table, a schema drift, or a
 *     transient DB blip can NEVER fatal the calling admin action. The
 *     whole point of this helper is passive observation.
 *   * Uses its OWN PDO connection via dbConnect() rather than borrowing
 *     $conn from the caller, so the helper is drop-in for any file that
 *     already has include/config.php loaded — no globals required.
 */

if (!function_exists('audit_log')) {
    /**
     * @param string      $action     e.g. 'user.status_changed', 'settings.updated'
     * @param string|null $targetType e.g. 'user', 'card', 'settings'
     * @param string|null $targetId   e.g. the user id (as string)
     * @param array|null  $details    associative array — will be JSON-encoded
     */
    function audit_log($action, $targetType = null, $targetId = null, $details = null)
    {
        // Defensive: no admin session means either an unauthenticated
        // request slipped through or we're running outside the admin panel.
        // Either way, silently bail — never throw.
        if (session_status() === PHP_SESSION_NONE) {
            // Do not start a session on the caller's behalf; if there's no
            // active session there is no admin identity to log.
            return;
        }
        if (empty($_SESSION['admin'])) {
            return;
        }
        if (!function_exists('dbConnect')) {
            return;
        }

        try {
            $conn = dbConnect();

            // Resolve admin email -> id + display name. Kept in a fresh
            // lookup on every call so a renamed admin gets the fresh name
            // snapshotted. Cheap: one indexed row read.
            $adminEmail = (string)$_SESSION['admin'];
            $lookup = $conn->prepare("SELECT id, firstname, lastname FROM admin WHERE admin_email = :email LIMIT 1");
            $lookup->execute(['email' => $adminEmail]);
            $admin = $lookup->fetch(PDO::FETCH_ASSOC);

            if (!$admin) {
                // Unknown admin (session stale?). Still log with id=0 so
                // the row exists — better than silent loss.
                $adminId   = 0;
                $adminName = $adminEmail;
            } else {
                $adminId   = (int)$admin['id'];
                $adminName = trim(($admin['firstname'] ?? '') . ' ' . ($admin['lastname'] ?? ''));
                if ($adminName === '') {
                    $adminName = $adminEmail;
                }
            }

            $detailsJson = null;
            if (is_array($details) && !empty($details)) {
                $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                // Cap size defensively — audit rows should stay small.
                if ($detailsJson !== false && strlen($detailsJson) > 20000) {
                    $detailsJson = substr($detailsJson, 0, 20000);
                }
            }

            $ip = isset($_SERVER['REMOTE_ADDR']) ? substr((string)$_SERVER['REMOTE_ADDR'], 0, 45) : null;

            $sql = "INSERT INTO admin_audit_log
                        (admin_id, admin_name, action, target_type, target_id, details, ip_address)
                    VALUES
                        (:admin_id, :admin_name, :action, :target_type, :target_id, :details, :ip_address)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                'admin_id'    => $adminId,
                'admin_name'  => $adminName !== '' ? substr($adminName, 0, 200) : null,
                'action'      => substr((string)$action, 0, 100),
                'target_type' => $targetType !== null ? substr((string)$targetType, 0, 50) : null,
                'target_id'   => $targetId !== null ? substr((string)$targetId, 0, 50) : null,
                'details'     => $detailsJson,
                'ip_address'  => $ip,
            ]);
        } catch (\Throwable $e) {
            // Swallow every failure — audit is best-effort. Log to PHP
            // error_log so operators can still spot systemic issues.
            error_log('[audit_log] failed for action "' . $action . '": ' . $e->getMessage());
            return;
        }
    }
}
