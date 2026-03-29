<?php

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация.']);
    exit;
}

$public_video_id = trim($_POST['video_id'] ?? '');
$playlist_id = filter_input(INPUT_POST, 'playlist_id', FILTER_VALIDATE_INT);
$user_id = (int)$_SESSION['user_id'];

if (empty($public_video_id) || !$playlist_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Некорректные данные запроса.']);
    exit;
}

try {
    $sql = "
        INSERT INTO playlist_videos (playlist_id, video_id)
        SELECT p.playlist_id, v.video_id
        FROM playlists p
        JOIN videos v ON v.public_video_id = ?
        WHERE p.playlist_id = ? 
          AND p.user_id = ?
          AND NOT EXISTS (
              SELECT 1 
              FROM playlist_videos pv 
              WHERE pv.playlist_id = p.playlist_id 
                AND pv.video_id = v.video_id
          )
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sii", $public_video_id, $playlist_id, $user_id);
    $stmt->execute();
    
    $affected_rows = $stmt->affected_rows;
    $stmt->close();

    if ($affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Видео успешно добавлено в плейлист!']);
    } else {
        // Если строк не затронуто, выясняем причину для корректного ответа пользователю
        $check_stmt = $conn->prepare("
            SELECT 
                (SELECT COUNT(*) FROM playlists WHERE playlist_id = ? AND user_id = ?) as owns_playlist,
                (SELECT COUNT(*) FROM videos WHERE public_video_id = ?) as video_exists
        ");
        $check_stmt->bind_param("iis", $playlist_id, $user_id, $public_video_id);
        $check_stmt->execute();
        $check = $check_stmt->get_result()->fetch_assoc();
        $check_stmt->close();

        if (empty($check['video_exists'])) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Видео не найдено.']);
        } elseif (empty($check['owns_playlist'])) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'У вас нет прав на редактирование этого плейлиста.']);
        } else {
            // Если плейлист и видео существуют, значит сработал блок NOT EXISTS (дубликат)
            echo json_encode(['success' => true, 'message' => 'Видео уже есть в этом плейлисте.']);
        }
    }

} catch (Exception $e) {
    error_log("Add video to playlist error: " . $e->getMessage()); 
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Внутренняя ошибка сервера.']);
}