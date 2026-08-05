<?php
/**
 * Presentation helpers for the server-rendered admin panel.
 *
 * Loaded on every admin page via admin/layout/header.php →
 * admin/include/adminloginFunction.php.
 *
 * Two conventions worth knowing before editing this file:
 *
 *   1. Every function is wrapped in a `function_exists()` guard. Three of
 *      these names — currency(), toast_alert(), wireStatus() — are also
 *      declared in include/userFunction.php. Nothing loads both today (that
 *      file is only reached through include/loginFunction.php, which belongs
 *      to the retired pre-Vue customer pages), but a single stray require
 *      would have been an instant "Cannot redeclare" fatal on every admin
 *      page. The guards make that impossible.
 *
 *   2. Status columns are `int` in MySQL and include/config.php sets
 *      PDO::ATTR_EMULATE_PREPARES => false, so PDO hands back real PHP
 *      integers — not numeric strings. Comparisons must therefore cast.
 *      The previous `$row['wire_status'] === '0'` checks compared int to
 *      string with ===, matched nothing, and returned null: every wire,
 *      crypto and domestic status cell in the admin tables rendered blank.
 *      Anything added here must respect that.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../include/currency.php';

/**
 * Display name for the platform.
 *
 * Previously hard-coded to "Bank Pro", which silently overrode the operator's
 * configured brand — admin login alert emails went out signed "Bank Pro" no
 * matter what WEB_TITLE or settings.url_name said, because the one consumer
 * reads `defined('APP_NAME') ? APP_NAME : WEB_TITLE` and the constant always
 * won. Derived from WEB_TITLE now so the fallback chain actually works.
 */
if (!defined('APP_NAME')) {
    define('APP_NAME', defined('WEB_TITLE') ? WEB_TITLE : 'BankPro');
}

if (!function_exists('toast_alert')) {
    /**
     * Queue a Toastr notification for admin/layout/footer.php to flush.
     *
     * Values are JSON-encoded with JSON_HEX_TAG so a `</script>` sequence in
     * the message is escaped rather than closing the inline <script> block.
     * Several callers interpolate operator-supplied data straight into the
     * message — db-backup.php passes filenames, settings.php passes a POSTed
     * email address — so this is a live injection path, not a theoretical one.
     *
     * @param string       $type  success|error|warning|info
     * @param string       $msg   Body text.
     * @param string|false $title Optional heading; false renders none.
     */
    function toast_alert(string $type, string $msg, $title = false): void
    {
        $valid  = ['success', 'error', 'warning', 'info'];
        $method = in_array($type, $valid, true) ? $type : 'info';

        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;

        $msgEsc    = json_encode($msg, $flags);
        $titleEsc  = json_encode($title === false ? '' : (string)$title, $flags);
        $methodEsc = json_encode($method, $flags);

        echo "<script>(window.__toasts = window.__toasts || []).push([{$methodEsc}, {$msgEsc}, {$titleEsc}]);</script>";
    }
}

if (!function_exists('admin_profile_src')) {
    /**
     * Build a <img src> for a row's stored profile filename.
     *
     * Three things this centralises, each of which was wrong at all five call
     * sites: the filename is URL-encoded (many stored names contain spaces),
     * a missing file falls back to the bundled placeholder instead of a broken
     * image icon, and a cache-buster keyed to the file's mtime is appended —
     * uploads now overwrite a stable filename, so without it a new avatar would
     * keep rendering as the old one.
     *
     * Returns a path relative to the admin/ directory, matching how every admin
     * template references assets.
     */
    function admin_profile_src($name): string
    {
        $name = trim((string)$name);
        $dir  = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'profile' . DIRECTORY_SEPARATOR;
        if ($name === '' || strpos($name, '/') !== false || strpos($name, '\\') !== false || !is_file($dir . $name)) {
            return './dist/img/default-150x150.png';
        }
        return '../assets/profile/' . rawurlencode($name) . '?v=' . (int)filemtime($dir . $name);
    }
}

if (!function_exists('toast_flash')) {
    /**
     * Queue a Toastr notification that survives a redirect.
     *
     * toast_alert() writes into the page's output buffer, so any handler that
     * calls it and then sends a Location: header throws the message away — the
     * operator sees the next page with no confirmation at all, which is
     * indistinguishable from the action having done nothing. Every
     * "toast then redirect" handler in the panel had that shape. Stash it in
     * the session instead; admin/layout/footer.php drains the queue on the
     * page the operator actually lands on.
     */
    function toast_flash(string $type, string $msg, $title = false): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            // Nothing to persist into — better a lost toast than a fatal.
            toast_alert($type, $msg, $title);
            return;
        }
        if (!isset($_SESSION['admin_toasts']) || !is_array($_SESSION['admin_toasts'])) {
            $_SESSION['admin_toasts'] = [];
        }
        $_SESSION['admin_toasts'][] = [$type, $msg, $title === false ? '' : (string)$title];
    }
}

if (!function_exists('admin_upload_error_reason')) {
    /**
     * Human-readable reason for a PHP upload error code.
     *
     * Every handler in this panel used to gate on `$_FILES[x]['name']` being
     * truthy and never look at `['error']`. That is exactly backwards: when
     * the upload exceeds upload_max_filesize / post_max_size, or upload_tmp_dir
     * is missing or unwritable — the normal situation on a locked-down cPanel
     * host and the normal situation NOT reproduced on a local XAMPP box — PHP
     * still populates ['name'] with the client-supplied filename while
     * ['tmp_name'] is empty and ['error'] is non-zero. The handler then
     * "succeeded" into a silent no-op.
     */
    function admin_upload_error_reason(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_OK:
                return '';
            case UPLOAD_ERR_INI_SIZE:
                return 'The file is larger than the server allows (upload_max_filesize = '
                     . (ini_get('upload_max_filesize') ?: 'unknown') . ').';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The file is larger than the form allows.';
            case UPLOAD_ERR_PARTIAL:
                return 'The upload was interrupted and only partially received. Try again.';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was selected.';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'PHP has no writable temporary upload directory (upload_tmp_dir is unset or missing on this host).';
            case UPLOAD_ERR_CANT_WRITE:
                return 'PHP could not write the upload to disk (permission denied on the temporary directory).';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension blocked the upload.';
            default:
                return 'Unknown upload error (code ' . $code . ').';
        }
    }
}

if (!function_exists('admin_store_upload')) {
    /**
     * Validate and store one uploaded file, honestly.
     *
     * Returns ['ok' => bool, 'name' => string, 'error' => string]. `ok` is only
     * true when the bytes are verifiably on disk at the destination — callers
     * must not report success on anything weaker than that.
     *
     * Hardening applied to every caller in the panel:
     *   - inspects $_FILES[...]['error'] before anything else;
     *   - is_uploaded_file() so a local path can never be moved;
     *   - extension allowlist, matched case-insensitively;
     *   - getimagesize() content sniff for raster formats, so a renamed
     *     script cannot masquerade as a .png;
     *   - a server-chosen destination basename. The raw client filename is
     *     NEVER used in the path — that was an unrestricted file upload: an
     *     admin-session attacker could POST `evil.php` and land executable
     *     code under the document root.
     *   - $targetDir must be absolute (resolve it with __DIR__, never a
     *     relative "../assets/..." — that is resolved against the process
     *     CWD, which is the script dir under mod_php but not under
     *     PHP-FPM/CGI, and is the classic works-on-localhost-only bug).
     *
     * @param array       $file        One $_FILES entry.
     * @param string      $targetDir   Absolute destination directory.
     * @param string[]    $allowedExt  Lowercase extensions without the dot.
     * @param int         $maxBytes    Hard size ceiling.
     * @param string|null $baseName    Destination basename without extension.
     *                                 Null derives a random one.
     * @return array{ok:bool,name:string,error:string}
     */
    function admin_store_upload(array $file, string $targetDir, array $allowedExt, int $maxBytes, ?string $baseName = null): array
    {
        $fail = static function (string $why): array {
            return ['ok' => false, 'name' => '', 'error' => $why];
        };

        $code = isset($file['error']) ? (int)$file['error'] : UPLOAD_ERR_NO_FILE;
        if ($code !== UPLOAD_ERR_OK) {
            return $fail(admin_upload_error_reason($code));
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return $fail('The upload did not arrive as a real HTTP upload. Check upload_tmp_dir on this host.');
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0) {
            return $fail('The uploaded file is empty.');
        }
        if ($size > $maxBytes) {
            return $fail('The file is ' . number_format($size / 1024, 0) . ' KB; the limit is '
                       . number_format($maxBytes / 1024, 0) . ' KB.');
        }

        $ext = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($ext === 'jpe') {
            $ext = 'jpg';
        }
        if ($ext === '' || !in_array($ext, $allowedExt, true)) {
            return $fail('Only ' . implode(', ', array_map(static function ($e) { return '.' . $e; }, $allowedExt))
                       . ' files are accepted.');
        }

        // Content sniff for the raster formats getimagesize() understands.
        // .ico and .svg are deliberately exempt (getimagesize cannot read
        // either) — the forced extension keeps them non-executable anyway.
        $rasterTypes = [
            'png'  => IMAGETYPE_PNG,
            'jpg'  => IMAGETYPE_JPEG,
            'jpeg' => IMAGETYPE_JPEG,
            'gif'  => IMAGETYPE_GIF,
            'webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18,
        ];
        if (isset($rasterTypes[$ext])) {
            $probe = @getimagesize($tmp);
            if ($probe === false || (int)($probe[2] ?? 0) !== $rasterTypes[$ext]) {
                return $fail('That file is not a valid ' . strtoupper($ext) . ' image.');
            }
        }

        if (!is_dir($targetDir) && !@mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            return $fail('The destination folder ' . basename($targetDir) . '/ does not exist and could not be created. Create it and give PHP write access.');
        }
        if (!is_writable($targetDir)) {
            return $fail('The destination folder ' . basename($targetDir) . '/ is not writable by PHP. Fix its permissions (0755 with the right owner) in cPanel File Manager.');
        }

        if ($baseName === null || $baseName === '') {
            $baseName = bin2hex(random_bytes(8));
        }
        // Strip anything that could alter the path. Belt and braces: the
        // caller already controls this value.
        $baseName = preg_replace('/[^A-Za-z0-9._-]/', '', $baseName);
        $baseName = ltrim((string)$baseName, '.');
        if ($baseName === '') {
            $baseName = bin2hex(random_bytes(8));
        }

        $name        = $baseName . '.' . $ext;
        $destination = rtrim($targetDir, "/\\") . DIRECTORY_SEPARATOR . $name;

        if (!@move_uploaded_file($tmp, $destination)) {
            return $fail('PHP could not write to ' . basename($targetDir) . '/' . $name . '. This is almost always a filesystem permission problem on the destination folder.');
        }
        // move_uploaded_file() can return true on some hosts where the file is
        // subsequently unreadable, so verify rather than trust.
        if (!is_file($destination) || (int)@filesize($destination) <= 0) {
            return $fail('The file did not survive the write to ' . basename($targetDir) . '/. Check disk quota and folder permissions.');
        }
        @chmod($destination, 0644);

        return ['ok' => true, 'name' => $name, 'error' => ''];
    }
}

if (!function_exists('admin_csrf_token')) {
    /**
     * Session-bound CSRF token for the server-rendered admin panel.
     *
     * The customer API has had one since api/_security.php; the admin panel
     * had nothing but SameSite=Strict on the session cookie. SameSite is a
     * good first line but it is a browser-side control: it does not cover
     * older browsers, and it does not cover a same-site subdomain or an
     * injected page on the same registrable domain. Every state-changing
     * admin POST now carries this token.
     */
    function admin_csrf_token(): string
    {
        if (empty($_SESSION['admin_csrf']) || !is_string($_SESSION['admin_csrf'])) {
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['admin_csrf'];
    }
}

if (!function_exists('admin_csrf_valid')) {
    /**
     * True when the current POST carries a matching token. GET is never
     * checked — no admin page mutates state on GET.
     */
    function admin_csrf_valid(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return true;
        }
        $provided = (string)($_POST['admin_csrf'] ?? '');

        return $provided !== '' && hash_equals(admin_csrf_token(), $provided);
    }
}

if (!function_exists('admin_csrf_inject_forms')) {
    /**
     * Output-buffer callback that stamps the CSRF token into every
     * `method="post"` form the panel renders.
     *
     * Doing it here rather than in ~20 templates means a new page cannot
     * forget it — the protection is on by construction. Registered from
     * admin/layout/header.php's ob_start().
     */
    function admin_csrf_inject_forms(string $html): string
    {
        $field = '<input type="hidden" name="admin_csrf" value="'
               . htmlspecialchars(admin_csrf_token(), ENT_QUOTES) . '">';

        $out = preg_replace_callback(
            '/<form\b[^>]*>/i',
            static function (array $m) use ($field): string {
                // Only POST forms; the audit-log filter form is a GET and must
                // keep its query string clean.
                if (!preg_match('/\bmethod\s*=\s*["\']?post\b/i', $m[0])) {
                    return $m[0];
                }
                return $m[0] . $field;
            },
            $html
        );

        // preg_replace_callback returns null if it blows the backtrack limit on
        // a very large page. Never swallow the page in that case.
        return $out === null ? $html : $out;
    }
}

/**
 * Transaction status codes, shared by wire / crypto / domestic transfers.
 * Mirrors the column comments in the schema: 0=In Progress, 1=Completed,
 * 2=Hold, 3=Cancelled.
 */
const ADMIN_TRANSACTION_STATUS_MAP = [
    0 => ['secondary', 'IN PROGRESS'],
    1 => ['success',   'COMPLETED'],
    2 => ['warning',   'HOLD'],
    3 => ['danger',    'CANCELLED'],
];

if (!function_exists('admin_column')) {
    /**
     * Read one column out of a row that may not be an array.
     *
     * These helpers are called straight from table loops in the page
     * templates, and several call sites pass the result of a fetch() that
     * can legitimately be `false` when no row matched. Typed `array`
     * parameters would turn that into a TypeError and take the whole admin
     * page down, so every helper below normalises through here instead: a
     * missing or malformed row degrades to an "UNKNOWN" badge, never a 500.
     *
     * @param mixed $row
     * @return mixed
     */
    function admin_column($row, string $key)
    {
        return is_array($row) ? ($row[$key] ?? null) : null;
    }
}

if (!function_exists('admin_status_badge')) {
    /**
     * Shared renderer behind the status helpers below.
     *
     * @param mixed $status Raw column value (int or numeric string).
     * @param array<int,array{0:string,1:string}> $map code => [bootstrap suffix, label]
     */
    function admin_status_badge($status, array $map): string
    {
        $code = is_numeric($status) ? (int)$status : -1;
        [$variant, $label] = $map[$code] ?? ['light', 'UNKNOWN'];

        return '<span class="badge badge-' . $variant . '">' . htmlspecialchars($label, ENT_QUOTES) . '</span>';
    }
}

if (!function_exists('wireStatus')) {
    /**
     * Badge for `wire_transfer.wire_status`. Used by admin/wire-trans.php.
     * @param mixed $result
     */
    function wireStatus($result): string
    {
        return admin_status_badge(admin_column($result, 'wire_status'), ADMIN_TRANSACTION_STATUS_MAP);
    }
}

if (!function_exists('cryptoTransaction')) {
    /**
     * Badge for `crypto.crypto_status`. Used by admin/crypto-transaction.php.
     * @param mixed $result
     */
    function cryptoTransaction($result): string
    {
        return admin_status_badge(admin_column($result, 'crypto_status'), ADMIN_TRANSACTION_STATUS_MAP);
    }
}

if (!function_exists('domesticTransaction')) {
    /**
     * Badge for `domestic.dom_status`. Used by admin/domestic-trans.php.
     * @param mixed $result
     */
    function domesticTransaction($result): string
    {
        return admin_status_badge(admin_column($result, 'dom_status'), ADMIN_TRANSACTION_STATUS_MAP);
    }
}

if (!function_exists('getCardStatus')) {
    /**
     * Badge for `card.card_status` (1=Active, 2=Process, 3=Hold, 4=Pause).
     * Used by admin/cards.php.
     * @param mixed $status
     */
    function getCardStatus($status): string
    {
        return admin_status_badge(admin_column($status, 'card_status'), [
            1 => ['success', 'ACTIVE'],
            2 => ['info',    'PROCESSING'],
            3 => ['warning', 'HOLD'],
            4 => ['danger',  'PAUSE'],
        ]);
    }
}

if (!function_exists('getCardType')) {
    /**
     * Identify a card scheme from its number. Used by admin/cards.php.
     *
     * The previous implementation split on a space, took the first group, and
     * compared its first two characters against a list of exactly eight
     * two-digit prefixes. That mis-identified most real cards: every Visa
     * except those beginning "40", and every Mastercard except "52", fell
     * through to "INVALID". It also assumed the number was stored
     * space-delimited, which is only true when whoever typed it into
     * admin/cards.php happened to add spaces.
     *
     * Now: strip everything that is not a digit, then test documented IIN
     * ranges longest-prefix-first.
     *
     * @param mixed $card Row containing `card_number`.
     */
    function getCardType($card): string
    {
        $digits = preg_replace('/\D+/', '', (string)(admin_column($card, 'card_number') ?? ''));
        if ($digits === null || $digits === '') {
            return 'INVALID';
        }

        $p = static fn(int $len): int => (int)substr($digits, 0, $len);

        // Order matters: ranges that share a leading digit must be tested
        // from most specific to least (JCB 3528-3589 before Diners 36/38/39).
        if ($p(1) === 4) {
            return 'VISA';
        }
        if (($p(2) >= 51 && $p(2) <= 55) || ($p(4) >= 2221 && $p(4) <= 2720)) {
            return 'MASTERCARD';
        }
        if ($p(2) === 34 || $p(2) === 37) {
            return 'AMERICAN EXPRESS';
        }
        if ($p(4) >= 3528 && $p(4) <= 3589) {
            return 'JCB';
        }
        if (($p(3) >= 300 && $p(3) <= 305) || $p(2) === 36 || $p(2) === 38 || $p(2) === 39) {
            return 'DINERS';
        }
        if ($p(4) === 6011 || $p(2) === 65 || ($p(3) >= 644 && $p(3) <= 649)) {
            return 'DISCOVER';
        }
        if ($p(2) === 62 || $p(2) === 81) {
            return 'UNIONPAY';
        }
        if ($p(2) === 50 || ($p(2) >= 56 && $p(2) <= 58) || $p(4) === 6304
            || $p(4) === 6759 || ($p(4) >= 6761 && $p(4) <= 6763)) {
            return 'MAESTRO';
        }

        return 'INVALID';
    }
}

if (!function_exists('currency')) {
    /**
     * Currency symbol for an account row.
     *
     * Delegates to the shared ISO symbol table in include/currency.php — the
     * same source the customer API uses via user_currency_symbol(). The old
     * hard-coded USD/EUR pair returned null for every other currency, so a
     * GBP or NGN account rendered its balance with no symbol at all
     * (`currency($row) . number_format(...)` → "1,250.00"). The shared helper
     * falls back to the uppercase code rather than to nothing.
     *
     * @param mixed $row Row containing `acct_currency`.
     */
    function currency($row): string
    {
        $code = admin_column($row, 'acct_currency');

        return currency_symbol(is_string($code) ? $code : null);
    }
}
