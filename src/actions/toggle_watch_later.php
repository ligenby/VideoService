<?php

require_once __DIR__ . '/../core/config.php';

function send_json_response(array $data, int $statusCode = 200): void 
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    send_json_response(["success" => false, "message" => "Необходимо войти в аккаунт"], 401);
}

$public_video_id = $_POST['video_id'] ?? null;

if (empty($public_video_id)) {
    send_json_response(["success" => false, "message" => "Неверный запрос"], 400);
}

$user_id = (int)$_SESSION['user_id'];

try {
    // Конвертация публичного строкового ID во внутренний числовой
    $stmt_get_id = $conn->prepare("SELECT video_id FROM videos WHERE public_video_id = ?");
    $stmt_get_id->bind_param("s", $public_video_id);
    $stmt_get_id->execute();
    $video_data = $stmt_get_id->get_result()->fetch_assoc();
    $stmt_get_id->close();

    if (!$video_data) {
        send_json_response(["success" => false, "message" => "Видео не найдено"], 404);
    }
    
    $numeric_video_id = (int)$video_data['video_id'];

    $stmt_check = $conn->prepare("SELECT watch_later_id FROM watch_later WHERE user_id = ? AND video_id = ?");
    $stmt_check->bind_param("ii", $user_id, $numeric_video_id);
    $stmt_check->execute();
    $is_added = $stmt_check->get_result()->num_rows > 0;
    $stmt_check->close();
    
    if ($is_added) {
        $stmt_action = $conn->prepare("DELETE FROM watch_later WHERE user_id = ? AND video_id = ?");
    } else {
        $stmt_action = $conn->prepare("INSERT INTO watch_later (user_id, video_id) VALUES (?, ?)");
    }
    
    $stmt_action->bind_param("ii", $user_id, $numeric_video_id);
    
    if (!$stmt_action->execute()) {
        throw new Exception($stmt_action->error);
    }
    $stmt_action->close();

    send_json_response(["success" => true, "is_added" => !$is_added]);

} catch (Exception $e) {
    error_log("Toggle Watch Later Failed: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => 'Ошибка на стороне сервера.'], 500);
}