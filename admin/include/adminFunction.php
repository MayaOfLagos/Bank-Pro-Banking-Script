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
