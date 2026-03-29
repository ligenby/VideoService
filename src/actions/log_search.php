<?php

if (!isset($_SESSION['user_id']) || empty(trim($_POST['query'] ?? ''))) {
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$query = trim($_POST['query']);

$stmt = $conn->prepare("INSERT INTO search_history (user_id, query) VALUES (?, ?)");
$stmt->bind_param("is", $user_id, $query);
$stmt->execute();
$stmt->close();

echo json_encode(['success' => true]);