<?php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit();
}
if (!isset($_POST['status'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Неверный запрос']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$new_status = (int)$_POST['status'];

if ($new_status !== 0 && $new_status !== 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Некорректный статус.']);
    exit();
}

$stmt = $conn->prepare("UPDATE users SET history_enabled = ? WHERE user_id = ?");
$stmt->bind_param("ii", $new_status, $user_id);

if ($stmt->execute()) {
    $_SESSION['history_enabled'] = $new_status;
    echo json_encode(['success' => true, 'newStatus' => $new_status]);
} else {
    http_response_code(500);
    error_log("Toggle history recording failed: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Не удалось изменить статус.']);
}
$stmt->close();
?>