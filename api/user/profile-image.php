<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
  api_json(422, ['ok' => false, 'message' => 'Image file is required']);
}

$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || (int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
  api_json(422, ['ok' => false, 'message' => 'Image must be smaller than 5 MB']);
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
  api_json(422, ['ok' => false, 'message' => 'Invalid image format']);
}

$filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
$target = __DIR__ . '/../../assets/profile/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $target)) {
  api_json(500, ['ok' => false, 'message' => 'Failed to upload image']);
}

$stmt = $conn->prepare('UPDATE users SET image=:image WHERE id=:id');
$stmt->execute(['image' => $filename, 'id' => $user['id']]);

api_json(200, ['ok' => true, 'message' => 'Profile image updated', 'data' => ['image' => $filename]]);
