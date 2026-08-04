<?php
/**
 * ISO 3166 country + account-type helpers backed by include/data/*.json.
 * Mirrors the JS-side helpers in frontend/user-portal/src/utils/countries.js.
 */

declare(strict_types=1);

function countries_map(): array
{
    static $map = null;
    if ($map !== null) return $map;

    $raw = @file_get_contents(__DIR__ . '/data/countries.json');
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $map = [];
    if (is_array($data)) {
        foreach ($data as $row) {
            $code = strtoupper((string)($row['code'] ?? ''));
            if ($code !== '') {
                $map[$code] = [
                    'code' => $code,
                    'name' => (string)($row['name'] ?? $code),
                ];
            }
        }
    }
    return $map;
}

/**
 * Accepts either a two-letter ISO code ("US") or a legacy full name
 * ("United States") — legacy signup wrote free-text country strings so
 * old rows still resolve. Returns true when either matches a known
 * country in the catalog.
 */
function country_is_known(string $value): bool
{
    if ($value === '') return false;
    $upper = strtoupper($value);
    if (isset(countries_map()[$upper])) return true;
    foreach (countries_map() as $row) {
        if (strcasecmp($row['name'], $value) === 0) return true;
    }
    return false;
}

function country_name(string $code): string
{
    $map = countries_map();
    return $map[strtoupper($code)]['name'] ?? $code;
}

/**
 * Resolve either an ISO code or a legacy full name to a canonical ISO code.
 * Returns '' when nothing matches — callers can then decide whether that
 * should fall through as "unknown" or be rejected outright. Kept separate
 * from country_is_known() because that helper is used for validation
 * (yes/no) whereas we need the code itself for blocklist comparison.
 */
function country_code_from_value(string $value): string
{
    if ($value === '') return '';
    $upper = strtoupper($value);
    if (isset(countries_map()[$upper])) {
        return $upper;
    }
    foreach (countries_map() as $code => $row) {
        if (strcasecmp($row['name'], $value) === 0) {
            return $code;
        }
    }
    return '';
}

function account_types(): array
{
    static $list = null;
    if ($list !== null) return $list;

    $raw = @file_get_contents(__DIR__ . '/data/account-types.json');
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $list = [];
    if (is_array($data)) {
        foreach ($data as $row) {
            $code = trim((string)($row['code'] ?? ''));
            if ($code !== '') {
                $list[$code] = [
                    'code' => $code,
                    'name' => (string)($row['name'] ?? $code),
                    'description' => (string)($row['description'] ?? ''),
                ];
            }
        }
    }
    return $list;
}

function account_type_is_known(string $value): bool
{
    if ($value === '') return false;
    foreach (account_types() as $row) {
        if (strcasecmp($row['code'], $value) === 0) return true;
    }
    return false;
}
