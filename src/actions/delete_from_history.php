<?php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit();
}

if (!isset($_POST['video_id']) || empty($_POST['video_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Не указан или некорректен ID видео']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$public_video_id = $_POST['video_id'];

$stmt_get_id = $conn->prepare("SELECT video_id FROM videos WHERE public_video_id = ?");
$stmt_get_id->bind_param("s", $public_video_id);
$stmt_get_id->execute();
$video_data = $stmt_get_id->get_result()->fetch_assoc();
$stmt_get_id->close();

if (!$video_data) {
    echo json_encode(['success' => true, 'message' => 'Видео уже удалено или не найдено']);
    exit();
}
$numeric_video_id = (int)$video_data['video_id'];

$stmt = $conn->prepare("DELETE FROM history WHERE user_id = ? AND video_id = ?");
$stmt->bind_param("ii", $user_id, $numeric_video_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Видео удалено из истории']);
} else {
    http_response_code(500);
    error_log("History deletion failed: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Ошибка при удалении из базы данных']);
}
$stmt->close();
?>