<?php
require_once __DIR__ . '/../core/config.php';

/**
 * Отправляет стандартизированный JSON-ответ.
 * @param array $data
 * @param int $statusCode
 */
function send_json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    send_json_response(['success' => false, 'message' => 'Необходима авторизация.'], 401);
}

if (empty($_POST['video_id'])) {
    send_json_response(['success' => false, 'message' => 'Не указан ID видео.'], 400);
}

$user_id = (int)$_SESSION['user_id'];
$public_video_id = (string)$_POST['video_id'];

try {
    // Получаем внутренний ID видео по публичному хешу
    $stmt_get_id = $conn->prepare("SELECT video_id FROM videos WHERE public_video_id = ?");
    $stmt_get_id->bind_param("s", $public_video_id);
    $stmt_get_id->execute();
    $video_data = $stmt_get_id->get_result()->fetch_assoc();
    $stmt_get_id->close();

    if (!$video_data) {
        send_json_response(['success' => false, 'message' => 'Видео не найдено.'], 404);
    }
    
    $numeric_video_id = (int)$video_data['video_id'];

    // Проверяем текущий статус видимости
    $stmt_check = $conn->prepare("SELECT hidden_video_id FROM hidden_videos WHERE user_id = ? AND video_id = ?");
    $stmt_check->bind_param("ii", $user_id, $numeric_video_id);
    $stmt_check->execute();
    $is_hidden = $stmt_check->get_result()->num_rows > 0;
    $stmt_check->close();

    if ($is_hidden) {
        $stmt_action = $conn->prepare("DELETE FROM hidden_videos WHERE user_id = ? AND video_id = ?");
        $stmt_action->bind_param("ii", $user_id, $numeric_video_id);
        
        if (!$stmt_action->execute()) {
            throw new Exception($stmt_action->error);
        }
        
        send_json_response(['success' => true, 'message' => 'Видео снова отображается.', 'is_hidden' => false]);
    } else {
        $stmt_action = $conn->prepare("INSERT INTO hidden_videos (user_id, video_id) VALUES (?, ?)");
        $stmt_action->bind_param("ii", $user_id, $numeric_video_id);
        
        if (!$stmt_action->execute()) {
            throw new Exception($stmt_action->error);
        }
        
        send_json_response(['success' => true, 'message' => 'Видео скрыто.', 'is_hidden' => true]);
    }

} catch (Exception $e) {
    error_log("Toggle Hidden Video Error [User ID: {$user_id}]: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => 'Внутренняя ошибка сервера.'], 500);
}