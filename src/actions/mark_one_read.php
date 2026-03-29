<?php
require_once __DIR__ . '/../core/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['id'])) exit;

$notif_id = (int)$_POST['id'];
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
$stmt->bind_param("ii", $notif_id, $user_id);
$stmt->execute();

echo json_encode(['success' => true]);
?>