<?php
require_once __DIR__ . '/../../session.php';
require_once __DIR__ . '/../_security.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function logout_json(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
  logout_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

api_enforce_csrf('logout_json');

$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
}
session_destroy();

logout_json(200, ['ok' => true, 'message' => 'Logged out']);
