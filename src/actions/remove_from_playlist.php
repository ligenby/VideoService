<?php

require_once __DIR__ . '/../core/config.php';

// TODO: Вынести функцию в /core/helpers.php
if (!function_exists('send_json_response')) {
    function send_json_response(bool $success, string $message, int $http_code = 200): void {
        http_response_code($http_code);
        echo json_encode(['success' => $success, 'message' => $message]);
        exit;
    }
}

if (!isset($_SESSION['user_id'])) {
    send_json_response(false, 'Доступ запрещен.', 401);
}

$user_id = (int)$_SESSION['user_id'];
$public_video_id = $_POST['video_id'] ?? null;
$playlist_id = filter_input(INPUT_POST, 'playlist_id', FILTER_VALIDATE_INT);

if (!$public_video_id || !$playlist_id) {
    send_json_response(false, 'Неверные параметры запроса.', 400);
}

try {
    // Получаем внутренний ID видео
    $stmt_video = $conn->prepare("SELECT video_id FROM videos WHERE public_video_id = ?");
    $stmt_video->bind_param("s", $public_video_id);
    $stmt_video->execute();
    $video_data = $stmt_video->get_result()->fetch_assoc();
    $stmt_video->close();

    if (!$video_data) {
        send_json_response(false, 'Видео не найдено.', 404);
    }
    
    $video_id = (int)$video_data['video_id'];

    // Проверяем права на плейлист
    $stmt_check = $conn->prepare("SELECT user_id FROM playlists WHERE playlist_id = ?");
    $stmt_check->bind_param("i", $playlist_id);
    $stmt_check->execute();
    $playlist = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if (!$playlist || (int)$playlist['user_id'] !== $user_id) {
        send_json_response(false, 'У вас нет прав на редактирование этого плейлиста.', 403);
    }

    // Удаляем связь
    $stmt_delete = $conn->prepare("DELETE FROM playlist_videos WHERE playlist_id = ? AND video_id = ?");
    $stmt_delete->bind_param("ii", $playlist_id, $video_id);
    
    if ($stmt_delete->execute()) {
        send_json_response(true, 'Видео удалено из плейлиста.');
    } else {
        throw new Exception('DB Error: ' . $stmt_delete->error);
    }
    
    $stmt_delete->close();

} catch (Exception $e) {
    error_log("Playlist removal error: " . $e->getMessage());
    send_json_response(false, 'Внутренняя ошибка сервера.', 500);
}