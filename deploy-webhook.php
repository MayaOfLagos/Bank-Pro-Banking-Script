<?php
/**
 * GitHub webhook receiver that triggers the cPanel VersionControlDeployment
 * UAPI, which pulls the latest commit AND runs .cpanel.yml deploy tasks.
 *
 * How to wire this up:
 *   1. In cPanel > Manage API Tokens, create a token (any expiry) and copy
 *      the value. Also note your cPanel username.
 *   2. Copy include/webhook-config.example.php to include/webhook-config.php
 *      and fill in the values. That file is gitignored.
 *   3. On GitHub, go to Settings > Webhooks > Add webhook:
 *        Payload URL:   https://your-domain.com/deploy-webhook.php
 *        Content type:  application/json
 *        Secret:        (same as GITHUB_WEBHOOK_SECRET in webhook-config.php)
 *        Events:        Just the push event
 *        Active:        yes
 *   4. Push to `production`. GitHub calls this URL. This file verifies the
 *      HMAC, calls cPanel, and cPanel pulls + runs .cpanel.yml.
 *
 * Manual fallback: if the webhook fails or you disable it, you can still
 * click "Update from Remote and Deploy HEAD" in cPanel > Git Version Control.
 */

declare(strict_types=1);

// Same-origin lock: only accept POST from GitHub.
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['ok' => false, 'error' => 'POST only']);
    exit;
}

$configPath = __DIR__ . '/include/webhook-config.php';
if (!is_file($configPath)) {
    error_log('deploy-webhook: include/webhook-config.php missing');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Server misconfigured']);
    exit;
}
/** @var array{
 *   github_secret: string,
 *   cpanel_user: string,
 *   cpanel_token: string,
 *   cpanel_host: string,
 *   repo_root: string,
 *   allowed_branch: string
 * } $cfg
 */
$cfg = require $configPath;

// Reject if any required key is missing or still the placeholder.
$required = ['github_secret', 'cpanel_user', 'cpanel_token', 'cpanel_host', 'repo_root', 'allowed_branch'];
foreach ($required as $key) {
    if (empty($cfg[$key]) || str_starts_with((string)$cfg[$key], 'CHANGE_ME')) {
        error_log("deploy-webhook: webhook-config.php missing/placeholder value for {$key}");
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Server misconfigured']);
        exit;
    }
}

// Verify GitHub HMAC signature.
$body = (string)file_get_contents('php://input');
$signature = (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
$expected = 'sha256=' . hash_hmac('sha256', $body, $cfg['github_secret']);
if (!hash_equals($expected, $signature)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Bad signature']);
    exit;
}

// Only deploy for pushes to the configured branch.
$event = (string)($_SERVER['HTTP_X_GITHUB_EVENT'] ?? '');
if ($event === 'ping') {
    echo json_encode(['ok' => true, 'pong' => true]);
    exit;
}
if ($event !== 'push') {
    http_response_code(204);
    echo json_encode(['ok' => true, 'ignored' => "event={$event}"]);
    exit;
}

$payload = json_decode($body, true);
$ref = (string)($payload['ref'] ?? '');
$expectedRef = 'refs/heads/' . $cfg['allowed_branch'];
if ($ref !== $expectedRef) {
    http_response_code(204);
    echo json_encode(['ok' => true, 'ignored' => "ref={$ref}"]);
    exit;
}

// Trigger cPanel deploy. VersionControlDeployment/create both pulls the
// remote branch and runs .cpanel.yml tasks in one step.
$url = sprintf(
    'https://%s:2083/execute/VersionControlDeployment/create?repository_root=%s',
    rawurlencode($cfg['cpanel_host']),
    rawurlencode($cfg['repo_root'])
);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: cpanel ' . $cfg['cpanel_user'] . ':' . $cfg['cpanel_token'],
    ],
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_TIMEOUT => 120,
]);
$response = curl_exec($ch);
$httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false || $httpCode >= 400) {
    error_log("deploy-webhook: cPanel UAPI failed http={$httpCode} err={$curlErr} body=" . substr((string)$response, 0, 500));
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'Upstream deploy failed', 'status' => $httpCode]);
    exit;
}

$decoded = json_decode((string)$response, true);
$success = is_array($decoded) && !empty($decoded['status']);

http_response_code($success ? 200 : 502);
echo json_encode([
    'ok' => $success,
    'cpanel_status' => $decoded['status'] ?? null,
    'cpanel_messages' => $decoded['messages'] ?? null,
    'cpanel_errors' => $decoded['errors'] ?? null,
]);
