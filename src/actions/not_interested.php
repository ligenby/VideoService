<?php

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Ошибка аутентификации.']);
    exit();
}

if (!isset($_POST['video_id']) || empty($_POST['video_id'])) {
    echo json_encode(['success' => false, 'message' => 'Некорректный ID видео.']);
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
    echo json_encode(['success' => false, 'message' => 'Видео не найдено.']);
    exit();
}
$numeric_video_id = (int)$video_data['video_id'];

$stmt = $conn->prepare("INSERT IGNORE INTO not_interested_videos (user_id, video_id) VALUES (?, ?)");
$stmt->bind_param("ii", $user_id, $numeric_video_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Видео отмечено как неинтересное.']);
} else {
    error_log("Failed to insert into not_interested_videos: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Произошла ошибка базы данных.']);
}

$stmt->close();
?>