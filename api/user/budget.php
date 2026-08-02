<?php
require_once __DIR__ . '/_bootstrap.php';

/**
 * Weekly spend widget data.
 *
 * "Spent" = sum of debit transactions (trans_type != 1) posted between the
 * user's local Monday 00:00 and now. We don't have per-user timezones, so
 * this uses the server's local week boundary — good enough for the widget.
 * The limit is stored per-user in users.week_budget_limit.
 */

$startOfWeek = new DateTimeImmutable('monday this week 00:00:00');
$startIso = $startOfWeek->format('Y-m-d H:i:s');
$endIso = date('Y-m-d H:i:s');

// created_at is a text column in the transactions table; SQL date comparison
// still works because MySQL parses the strings. Filtering on trans_type = 2
// (Debit) leaves out both credits and any legacy zero-typed rows.
$stmt = $conn->prepare(
  'SELECT COALESCE(SUM(amount), 0) FROM transactions ' .
  'WHERE user_id = :uid AND trans_type = 2 AND created_at >= :start'
);
$stmt->execute(['uid' => $user['id'], 'start' => $startIso]);
$spent = (float)$stmt->fetchColumn();

$limitValue = $user['week_budget_limit'] ?? null;
$limit = $limitValue !== null ? (float)$limitValue : null;

api_json(200, [
  'ok' => true,
  'data' => [
    'spent' => round($spent, 2),
    'limit' => $limit,
    'currency' => user_currency_symbol($user),
    'week_start' => $startOfWeek->format('m/d'),
    'week_end' => (new DateTimeImmutable('sunday this week 00:00:00'))->format('m/d'),
    // Range with year for accessibility label / tooltips
    'week_start_iso' => $startOfWeek->format('Y-m-d'),
    'week_end_iso' => (new DateTimeImmutable('sunday this week 00:00:00'))->format('Y-m-d'),
  ],
]);
