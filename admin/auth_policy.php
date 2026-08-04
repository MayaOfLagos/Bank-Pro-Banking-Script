<?php
/**
 * Admin panel — auth-flow policy controls. Kept in its own page (rather
 * than folded into settings.php) because a parallel workstream is
 * modifying settings.php in a different branch; the two changes need to
 * merge cleanly on production. If the ops team wants to consolidate
 * later they can copy the two cards below into their preferred
 * settings-page location and delete this file — the underlying columns
 * are independent of the page they live on.
 *
 * Covers:
 *   * settings.session_idle_minutes   — idle auto sign-out (5..1440 min)
 *   * settings.login_ip_allowlist     — comma / newline IP allow list
 *   * settings.login_ip_blocklist     — comma / newline IP block list
 */

include_once("./layout/header.php");
require_once __DIR__ . "/../include/auth_flow.php";

/**
 * Sanitise + validate a raw IP list textarea submission. Preserves valid
 * entries in their user-facing form and drops garbage silently so admin
 * copy/paste noise (trailing commas, extra newlines) never accidentally
 * turns a well-formed list into "0 entries -> policy disabled".
 * Returns a canonical newline-separated string ready for storage.
 */
function auth_policy_clean_ip_list_input(string $raw): string
{
    $valid = auth_flow_parse_ip_list($raw);
    return implode("\n", $valid);
}

$errors = [];

if (isset($_POST['save_session_policy'])) {
    $minutes = auth_flow_normalise_idle_minutes($_POST['session_idle_minutes'] ?? '');
    $stmt = $conn->prepare("UPDATE settings SET session_idle_minutes = :m WHERE id = '1'");
    $stmt->execute(['m' => $minutes]);
    toast_alert(
        'success',
        'Sessions will now sign out after ' . $minutes . ' minute(s) of inactivity.',
        'Session policy saved'
    );
}

if (isset($_POST['save_ip_lists'])) {
    $rawAllow = (string)($_POST['login_ip_allowlist'] ?? '');
    $rawBlock = (string)($_POST['login_ip_blocklist'] ?? '');
    $cleanAllow = auth_policy_clean_ip_list_input($rawAllow);
    $cleanBlock = auth_policy_clean_ip_list_input($rawBlock);

    // Warn but don't block if the admin pasted garbage — we saved the
    // valid subset. Empty submission is fine; it means "no policy".
    $allowInputCount = count(preg_split('/[\s,]+/', trim($rawAllow)) ?: []);
    $allowValidCount = $cleanAllow === '' ? 0 : count(explode("\n", $cleanAllow));
    $blockInputCount = count(preg_split('/[\s,]+/', trim($rawBlock)) ?: []);
    $blockValidCount = $cleanBlock === '' ? 0 : count(explode("\n", $cleanBlock));

    if (trim($rawAllow) !== '' && $allowValidCount < $allowInputCount) {
        $errors[] = 'Allowlist: dropped ' . ($allowInputCount - $allowValidCount) . ' invalid IP entrie(s).';
    }
    if (trim($rawBlock) !== '' && $blockValidCount < $blockInputCount) {
        $errors[] = 'Blocklist: dropped ' . ($blockInputCount - $blockValidCount) . ' invalid IP entrie(s).';
    }

    $stmt = $conn->prepare("UPDATE settings SET login_ip_allowlist = :a, login_ip_blocklist = :b WHERE id = '1'");
    $stmt->execute([
        'a' => $cleanAllow === '' ? null : $cleanAllow,
        'b' => $cleanBlock === '' ? null : $cleanBlock,
    ]);
    toast_alert(
        'success',
        'Sign-in IP policy saved. Allowlist has ' . $allowValidCount . ' entrie(s); blocklist has ' . $blockValidCount . '.',
        'IP policy saved'
    );
}

$settingsStmt = $conn->prepare("SELECT session_idle_minutes, login_ip_allowlist, login_ip_blocklist FROM settings WHERE id = '1' LIMIT 1");
$settingsStmt->execute();
$policy = $settingsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$currentIdle = auth_flow_normalise_idle_minutes($policy['session_idle_minutes'] ?? AUTH_FLOW_SESSION_IDLE_DEFAULT);
$currentAllow = (string)($policy['login_ip_allowlist'] ?? '');
$currentBlock = (string)($policy['login_ip_blocklist'] ?? '');
?>

<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6"><h1>Auth-flow policy</h1></div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="./dashboard.php">Home</a></li>
                    <li class="breadcrumb-item active">Auth-flow policy</li>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-warning">
                <?php foreach ($errors as $err): ?>
                    <div><?= htmlspecialchars($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-6">
                <div class="card card-primary card-outline">
                    <div class="card-header"><h3 class="card-title">Session policy</h3></div>
                    <form method="post">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="session_idle_minutes">Auto sign out after X minutes of inactivity</label>
                                <input
                                    type="number"
                                    id="session_idle_minutes"
                                    name="session_idle_minutes"
                                    class="form-control"
                                    min="<?= (int)AUTH_FLOW_SESSION_IDLE_MIN ?>"
                                    max="<?= (int)AUTH_FLOW_SESSION_IDLE_MAX ?>"
                                    step="1"
                                    value="<?= htmlspecialchars((string)$currentIdle) ?>"
                                    required
                                >
                                <small class="form-text text-muted">
                                    Applies to both the customer portal and this admin panel. Allowed range:
                                    <?= (int)AUTH_FLOW_SESSION_IDLE_MIN ?>&ndash;<?= (int)AUTH_FLOW_SESSION_IDLE_MAX ?> minutes.
                                    Values outside this range are clamped to the nearest bound.
                                </small>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="save_session_policy" class="btn btn-primary">Save session policy</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card card-primary card-outline">
                    <div class="card-header"><h3 class="card-title">Sign-in IP policy</h3></div>
                    <form method="post">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="login_ip_allowlist">Allowlist</label>
                                <textarea
                                    id="login_ip_allowlist"
                                    name="login_ip_allowlist"
                                    class="form-control"
                                    rows="5"
                                    placeholder="One IP per line or comma-separated. Example:&#10;127.0.0.1&#10;203.0.113.10&#10;2001:db8::1"
                                ><?= htmlspecialchars($currentAllow) ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="login_ip_blocklist">Blocklist</label>
                                <textarea
                                    id="login_ip_blocklist"
                                    name="login_ip_blocklist"
                                    class="form-control"
                                    rows="5"
                                    placeholder="One IP per line or comma-separated."
                                ><?= htmlspecialchars($currentBlock) ?></textarea>
                            </div>

                            <div class="alert alert-info small mb-0">
                                <strong>Precedence:</strong> if the allowlist is non-empty, only allowlisted
                                IPs may sign in and the blocklist is ignored. If the allowlist is empty,
                                any IP that appears on the blocklist is denied. Applies to both the customer
                                sign-in endpoint and this admin panel's sign-in page. Invalid entries (bad
                                IP format) are silently dropped on save so a typo can never accidentally
                                match a real client.
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" name="save_ip_lists" class="btn btn-primary">Save IP policy</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include_once("./layout/footer.php"); ?>
