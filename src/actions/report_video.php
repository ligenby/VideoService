<?php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация.']);
    exit;
}

if (!isset($_POST['video_id']) || empty($_POST['video_id']) || !isset($_POST['reason']) || empty(trim($_POST['reason']))) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Не все обязательные поля были заполнены.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$public_video_id = $_POST['video_id'];
$reason = trim($_POST['reason']);
$details = isset($_POST['details']) ? trim($_POST['details']) : null;
$target_type = 'video';

$stmt_get_id = $conn->prepare("SELECT video_id FROM videos WHERE public_video_id = ?");
$stmt_get_id->bind_param("s", $public_video_id);
$stmt_get_id->execute();
$video_data = $stmt_get_id->get_result()->fetch_assoc();
$stmt_get_id->close();

if (!$video_data) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Видео не найдено.']);
    exit;
}
$numeric_video_id = (int)$video_data['video_id'];


if ($reason === 'Другое' && !empty($details)) {
    $reason = $details;
}

$stmt = $conn->prepare(
    "INSERT IGNORE INTO reports (user_id, target_type, target_id, reason) VALUES (?, ?, ?, ?)"
);
$stmt->bind_param("isis", $user_id, $target_type, $numeric_video_id, $reason);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Жалоба успешно отправлена.']);
} else {
    http_response_code(500);
    error_log("Report submission failed: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Произошла внутренняя ошибка сервера.']);
}

$stmt->close();
?>