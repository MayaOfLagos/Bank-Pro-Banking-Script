<?php
/**
 * Shared currency helper — backed by the ISO-style symbol list in
 * include/data/currency-symbols.json (mirror of the same file the Vue
 * portal uses at frontend/user-portal/src/data/currency-symbols.json).
 *
 * Never load the JSON in a request-critical path more than once; the
 * private loader caches statically. All functions accept a nullable code
 * so callers don't have to null-check before display.
 */

declare(strict_types=1);

/**
 * @return array<string,array{currency:string,abbreviation:string,symbol:string}>
 *         Map keyed by uppercase currency code.
 */
function currency_symbols_map(): array
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }

    $path = __DIR__ . '/data/currency-symbols.json';
    $raw = @file_get_contents($path);
    $data = is_string($raw) ? json_decode($raw, true) : null;

    $map = [];
    if (is_array($data)) {
        foreach ($data as $row) {
            $code = isset($row['abbreviation']) ? strtoupper((string)$row['abbreviation']) : '';
            if ($code !== '') {
                $map[$code] = [
                    'currency'     => (string)($row['currency'] ?? $code),
                    'abbreviation' => $code,
                    'symbol'       => (string)($row['symbol'] ?? $code),
                ];
            }
        }
    }
    return $map;
}

/**
 * Return the display symbol for a currency code (e.g. "USD" → "$",
 * "NGN" → "₦"). Falls back to the code itself for unknown codes, or
 * to $fallback when the code is empty/null.
 */
function currency_symbol(?string $code, string $fallback = '$'): string
{
    if ($code === null || $code === '') {
        return $fallback;
    }
    $upper = strtoupper($code);
    $map = currency_symbols_map();
    return $map[$upper]['symbol'] ?? $upper;
}

/**
 * Symbol for a user row's account currency.
 *
 * Lives here rather than in api/user/_bootstrap.php so shared includes such as
 * include/transfer_otp.php can format amounts without depending on which
 * entrypoint happens to have loaded that bootstrap.
 *
 * Fixes the CAD → "¢" bug the old switch had (CAD should be "$"), and
 * translates the two legacy free-form values ("Euro", "Yuan") to canonical
 * ISO codes before lookup.
 */
function user_currency_symbol(array $user): string
{
    $raw = trim((string)($user['acct_currency'] ?? 'USD'));
    if ($raw === '') return '$';
    $alias = array('Euro' => 'EUR', 'Yuan' => 'CNY');
    $code = isset($alias[$raw]) ? $alias[$raw] : $raw;
    return currency_symbol($code, '$');
}

/** Human-readable currency name for a code. */
function currency_name(?string $code): string
{
    if ($code === null || $code === '') return '';
    $upper = strtoupper($code);
    $map = currency_symbols_map();
    return $map[$upper]['currency'] ?? $upper;
}

/**
 * Format a numeric amount with the appropriate symbol prefix.
 * Uses number_format for locale-neutral thousands separators + fixed decimals.
 */
function currency_format(float $amount, ?string $code, int $decimals = 2): string
{
    return currency_symbol($code) . number_format($amount, $decimals, '.', ',');
}
