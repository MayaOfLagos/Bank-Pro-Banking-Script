<?php
require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/_ledger.php';

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 200;
if ($limit < 1) $limit = 1;
if ($limit > 500) $limit = 500;

$ledger = user_ledger($conn, (int)$user['id'], user_currency_symbol($user));

api_json(200, ['ok' => true, 'data' => array_slice($ledger, 0, $limit)]);
