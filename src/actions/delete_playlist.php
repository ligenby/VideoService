<?php

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен.']);
    exit;
}

$playlist_id = filter_input(INPUT_POST, 'playlist_id', FILTER_VALIDATE_INT);
$current_user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'user';

if (!$playlist_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Неверный ID плейлиста.']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT user_id FROM playlists WHERE playlist_id = ?");
    $stmt->bind_param("i", $playlist_id);
    $stmt->execute();
    $playlist = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$playlist) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Плейлист не найден.']);
        exit;
    }

    if ((int)$playlist['user_id'] !== $current_user_id && $user_role !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Нет прав для удаления этого плейлиста.']);
        exit;
    }

    $conn->begin_transaction();

    $stmt_rel = $conn->prepare("DELETE FROM playlist_videos WHERE playlist_id = ?");
    $stmt_rel->bind_param("i", $playlist_id);
    $stmt_rel->execute();
    $stmt_rel->close();

    $stmt_del = $conn->prepare("DELETE FROM playlists WHERE playlist_id = ?");
    $stmt_del->bind_param("i", $playlist_id);
    $stmt_del->execute();
    $stmt_del->close();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Плейлист успешно удален.']);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Playlist deletion error [ID: {$playlist_id}]: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера при удалении плейлиста.']);
}