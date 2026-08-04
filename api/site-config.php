<?php
/**
 * Public site configuration endpoint — no auth required, no CSRF (GET only).
 * The login page needs the brand name, logo, favicon, and support contact
 * before the user has a session, so this stays outside the auth bootstrap.
 */

declare(strict_types=1);

require_once __DIR__ . '/../session.php';
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/branding.php';
require_once __DIR__ . '/_security.php';

header('Content-Type: application/json; charset=utf-8');
// No client-side caching — the SPA relies on this response for admin
// toggles (registration_enabled, brand overrides). A stale cached copy
// would leave the SPA out of step for up to the cache window every time
// an admin flips a setting.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!in_array(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), ['GET', 'HEAD'], true)) {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$conn = dbConnect();
$stmt = $conn->prepare("SELECT image, favicon, about_us, url_name, url_tel, url_email, registration_enabled FROM settings WHERE id='1' LIMIT 1");
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$logoUrl = resolve_logo_url($settings, __DIR__ . '/..');
$faviconUrl = resolve_favicon($settings, __DIR__ . '/..')['href'];

/**
 * Best-effort IP → ISO country hint for the register wizard's auto-fill.
 * Priority:
 *   1. Cloudflare's CF-IPCountry header (free, no external call if the
 *      site sits behind CF).
 *   2. Bare-metal PHP: nothing reliable without a paid geo DB, so return
 *      "" and let the client fall back to browser Intl detection.
 * Never blocks the response — this is a cosmetic UX hint, not a policy
 * gate.
 */
$detectedCountry = '';
$cfCountry = trim((string)($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
if ($cfCountry !== '' && preg_match('/^[A-Z]{2}$/', $cfCountry)) {
    $detectedCountry = $cfCountry;
}

echo json_encode([
    'ok' => true,
    'data' => [
        'brand_name' => (string)($settings['url_name'] ?? WEB_TITLE),
        'tagline' => (string)($settings['about_us'] ?? ''),
        'logo_url' => $logoUrl,
        'favicon_url' => $faviconUrl,
        'support_email' => (string)($settings['url_email'] ?? WEB_EMAIL),
        'support_phone' => (string)($settings['url_tel'] ?? ''),
        // Admin toggle — when false, the SPA hides the register link
        // and the /register route guards to /login. The register API
        // enforces the same flag server-side (defence in depth).
        'registration_enabled' => (int)($settings['registration_enabled'] ?? 1) === 1,
        // UX hint for the register wizard's country dropdown. Empty when
        // we can't resolve it — client will fall back to browser Intl.
        'detected_country_code' => $detectedCountry,
        // Seed a CSRF token so the very first non-GET request from an
        // unauthenticated visitor (e.g. login POST) has one to send. The
        // server will regenerate it after successful auth.
        'csrf_token' => api_csrf_token(),
    ],
]);
