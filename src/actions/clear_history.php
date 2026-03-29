<?php

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit();
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("DELETE FROM history WHERE user_id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'История успешно очищена.']);
} else {
    http_response_code(500);
    error_log("Clear history failed: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Ошибка при очистке истории.']);
}
$stmt->close();
?>