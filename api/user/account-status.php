<?php
require_once __DIR__ . '/_bootstrap.php';

api_json(200, [
  'ok' => true,
  'data' => [
    'acct_status' => $user['acct_status'] ?? '',
    'hold' => (string)($user['acct_status'] ?? '') === 'hold',
    'support_email' => $settings['url_email'] ?? WEB_EMAIL,
    'support_phone' => $settings['url_tel'] ?? ''
  ]
]);
