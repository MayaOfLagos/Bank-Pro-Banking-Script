<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

$payload = api_payload();
$code = api_field($payload, 'tax_code');
if ($code === '') api_json(422, ['ok' => false, 'message' => 'Tax code is required']);

if ($code !== (string)($user['acct_tax'] ?? '')) {
  api_json(422, ['ok' => false, 'message' => 'Invalid TAX code']);
}

$_SESSION['wire-transfer'] = $user['id'];
api_json(200, ['ok' => true, 'message' => 'Tax verified', 'data' => ['next_route' => '/transfer-imf']]);
