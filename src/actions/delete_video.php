<?php

// Предполагается, что скрипт вызывается через роутер/api, где $conn и $_SESSION уже инициализированы

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация.']);
    exit;
}

$public_video_id = trim($_POST['public_video_id'] ?? '');

if (empty($public_video_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Не указан идентификатор видео.']);
    exit;
}

$current_user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

try {
    $stmt = $conn->prepare("SELECT user_id FROM videos WHERE public_video_id = ? AND status != 'deleted'");
    $stmt->bind_param("s", $public_video_id);
    $stmt->execute();
    $video = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$video) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Видео не найдено или уже удалено.']);
        exit;
    }

    if ((int)$video['user_id'] !== $current_user_id && $user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'У вас нет прав для удаления этого видео.']);
        exit;
    }

    // Мягкое удаление (Soft Delete)
    $update_stmt = $conn->prepare("
        UPDATE videos 
        SET status = 'deleted', visibility = 'private', deleted_at = NOW() 
        WHERE public_video_id = ?
    ");
    $update_stmt->bind_param("s", $public_video_id);
    $update_stmt->execute();
    
    if ($update_stmt->affected_rows === 0) {
        throw new Exception('Не удалось обновить статус видео.');
    }

    $update_stmt->close();
    
    echo json_encode(['success' => true, 'message' => 'Видео успешно удалено.']);

} catch (Exception $e) {
    error_log("Video deletion error [Video: {$public_video_id}, User: {$current_user_id}]: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Внутренняя ошибка сервера.']);
}