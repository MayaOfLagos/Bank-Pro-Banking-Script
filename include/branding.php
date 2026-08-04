<?php
/**
 * Shared branding helpers. Any PHP shell that renders <head> reaches for
 * these so all surfaces (public marketing, admin panel, customer portal)
 * resolve the same admin-configured logo + favicon with the same fallback
 * chain.
 */

declare(strict_types=1);

/**
 * Resolve the browser favicon.
 *
 * Fallback chain: settings.favicon (admin upload) → legacy favicon.png in
 * assets/settings → generic /assets/img/favicon.ico.
 *
 * @param array  $settings    Row from `settings` (may be empty).
 * @param string $repoRoot    Absolute filesystem path to the repo root, used
 *                            to test file existence. Callers usually pass
 *                            __DIR__ . '/..' from `include/` or __DIR__
 *                            from the repo root.
 * @return array{href:string,type:string} URL path (absolute-from-domain)
 *                            and the matching MIME type attribute.
 */
function resolve_favicon(array $settings, string $repoRoot): array
{
    $repoRoot = rtrim($repoRoot, "/\\");
    $default = ['href' => '/assets/img/favicon.ico', 'type' => 'image/x-icon'];

    $field = trim((string)($settings['favicon'] ?? ''));
    if ($field !== '' && is_file($repoRoot . '/assets/settings/' . $field)) {
        $ext = strtolower(pathinfo($field, PATHINFO_EXTENSION));
        $type = $ext === 'ico' ? 'image/x-icon'
              : ($ext === 'svg' ? 'image/svg+xml'
              : ($ext === 'jpg' ? 'image/jpeg' : 'image/' . $ext));
        return [
            'href' => '/assets/settings/' . rawurlencode($field),
            'type' => $type,
        ];
    }

    if (is_file($repoRoot . '/assets/settings/favicon.png')) {
        return ['href' => '/assets/settings/favicon.png', 'type' => 'image/png'];
    }

    return $default;
}

/**
 * Resolve the admin-configured brand logo. Returns null when the file
 * referenced by settings.image doesn't exist (so callers can show a
 * fallback wordmark or icon).
 */
function resolve_logo_url(array $settings, string $repoRoot): ?string
{
    $repoRoot = rtrim($repoRoot, "/\\");
    $field = trim((string)($settings['image'] ?? ''));
    if ($field !== '' && is_file($repoRoot . '/assets/settings/' . $field)) {
        return '/assets/settings/' . rawurlencode($field);
    }
    return null;
}
