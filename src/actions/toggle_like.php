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
$type = $_POST['type'] ?? null;

if (!$public_video_id || !in_array($type, ['like', 'dislike'], true)) {
    send_json_response(["success" => false, "message" => "Неверные параметры запроса"], 400);
}

$user_id = (int)$_SESSION['user_id'];
$like_type_to_set = ($type === 'like') ? 1 : -1;

try {
    $stmt_get_id = $conn->prepare("SELECT video_id, user_id FROM videos WHERE public_video_id = ?");
    $stmt_get_id->bind_param("s", $public_video_id);
    $stmt_get_id->execute();
    $video_data = $stmt_get_id->get_result()->fetch_assoc();
    $stmt_get_id->close();

    if (!$video_data) {
        send_json_response(["success" => false, "message" => "Видео не найдено"], 404);
    }

    $video_id = (int)$video_data['video_id'];
    $video_owner_id = (int)$video_data['user_id'];

    $stmt_check = $conn->prepare("SELECT like_type FROM likes WHERE user_id = ? AND video_id = ?");
    $stmt_check->bind_param("ii", $user_id, $video_id);
    $stmt_check->execute();
    $current_like = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();
    
    if ($current_like) {
        if ((int)$current_like['like_type'] === $like_type_to_set) {
            // Снятие лайка/дизлайка при повторном клике
            $stmt_action = $conn->prepare("DELETE FROM likes WHERE user_id = ? AND video_id = ?");
            $stmt_action->bind_param("ii", $user_id, $video_id);
            $stmt_action->execute();
        } else {
            // Смена реакции (с лайка на дизлайк и наоборот)
            $stmt_action = $conn->prepare("UPDATE likes SET like_type = ? WHERE user_id = ? AND video_id = ?");
            $stmt_action->bind_param("iii", $like_type_to_set, $user_id, $video_id);
            $stmt_action->execute();
            
            if ($like_type_to_set === 1 && function_exists('createNotification')) {
                 createNotification($conn, $video_owner_id, $user_id, 'like', $video_id);
            }
        }
    } else {
        // Новая реакция
        $stmt_action = $conn->prepare("INSERT INTO likes (user_id, video_id, like_type) VALUES (?, ?, ?)");
        $stmt_action->bind_param("iii", $user_id, $video_id, $like_type_to_set);
        $stmt_action->execute();
        
        if ($like_type_to_set === 1 && function_exists('createNotification')) {
             createNotification($conn, $video_owner_id, $user_id, 'like', $video_id);
        }
    }
    
    if (isset($stmt_action)) {
        $stmt_action->close();
    }

    // Получение актуальной статистики для UI
    $stmt_stats = $conn->prepare("
        SELECT 
            SUM(like_type = 1) AS likes, 
            SUM(like_type = -1) AS dislikes,
            (SELECT like_type FROM likes WHERE user_id = ? AND video_id = ?) AS user_like
        FROM likes 
        WHERE video_id = ?
    ");
    
    $stmt_stats->bind_param("iii", $user_id, $video_id, $video_id);
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
    $stmt_stats->close();

    send_json_response([
        "success" => true,
        "likes" => (int)($stats['likes'] ?? 0),
        "dislikes" => (int)($stats['dislikes'] ?? 0),
        "user_like" => (int)($stats['user_like'] ?? 0)
    ]);

} catch (Exception $e) {
    error_log("Toggle Like Error: " . $e->getMessage());
    send_json_response(["success" => false, "message" => "Внутренняя ошибка сервера."], 500);
}