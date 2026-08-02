<?php
require_once __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  api_json(405, ['ok' => false, 'message' => 'Method not allowed']);
}

if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
  api_json(422, ['ok' => false, 'message' => 'Image file is required']);
}

$ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
  api_json(422, ['ok' => false, 'message' => 'Invalid image format']);
}

$filename = ($user['firstname'] ?? 'user') . $_FILES['image']['name'];
$target = __DIR__ . '/../../assets/profile/' . $filename;
if (!move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
  api_json(500, ['ok' => false, 'message' => 'Failed to upload image']);
}

$stmt = $conn->prepare('UPDATE users SET image=:image WHERE id=:id');
$stmt->execute(['image' => $filename, 'id' => $user['id']]);

api_json(200, ['ok' => true, 'message' => 'Profile image updated', 'data' => ['image' => $filename]]);
