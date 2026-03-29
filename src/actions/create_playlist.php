<?php

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен. Пожалуйста, войдите в аккаунт.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$title = trim($_POST['title'] ?? '');
$visibility = in_array($_POST['visibility'] ?? '', ['private', 'public', 'unlisted'], true) ? $_POST['visibility'] : 'private';
$description = trim($_POST['description'] ?? '');
$public_video_id = $_POST['video_id_to_add'] ?? null;

if (empty($title) || mb_strlen($title) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Некорректное название плейлиста (от 1 до 100 символов).']);
    exit;
}

try {
    $conn->begin_transaction();

    $stmt_create = $conn->prepare("
        INSERT INTO playlists (user_id, title, visibility, description, created_at, updated_at) 
        VALUES (?, ?, ?, ?, NOW(), NOW())
    ");
    $stmt_create->bind_param("isss", $user_id, $title, $visibility, $description);
    
    if (!$stmt_create->execute()) {
        throw new Exception("Playlist creation failed: " . $stmt_create->error);
    }
    
    $new_playlist_id = $conn->insert_id;
    $stmt_create->close();
    
    $message = 'Плейлист успешно создан!';

    if ($public_video_id && $new_playlist_id) {
        $stmt_add = $conn->prepare("
            INSERT INTO playlist_videos (playlist_id, video_id) 
            SELECT ?, video_id FROM videos WHERE public_video_id = ?
        ");
        $stmt_add->bind_param("is", $new_playlist_id, $public_video_id);
        $stmt_add->execute();
        
        if ($stmt_add->affected_rows > 0) {
            $message = 'Плейлист создан и видео добавлено!';
        }
        $stmt_add->close();
    }

    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'playlist' => [
            'playlist_id' => $new_playlist_id, 
            'title' => $title
        ]
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Playlist creation error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Произошла ошибка на сервере. Попробуйте снова.']);
}