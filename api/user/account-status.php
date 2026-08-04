<?php
require_once __DIR__ . '/_bootstrap.php';

// `hold` covers any non-active status, not just the literal 'hold'. The
// enforcement elsewhere refuses suspended and blocked accounts too, so a
// narrower flag would tell the customer everything was fine while every
// action kept failing.
$blockMessage = auth_account_block_message($user);

api_json(200, [
  'ok' => true,
  'data' => [
    'acct_status' => auth_account_status($user),
    'hold' => $blockMessage !== null,
    // Deliberately not exposing users.acct_status_reason — the admin form
    // that writes it treats it as an internal review note.
    'message' => $blockMessage !== null ? $blockMessage : '',
    'support_email' => $settings['url_email'] ?? WEB_EMAIL,
    'support_phone' => $settings['url_tel'] ?? ''
  ]
]);
