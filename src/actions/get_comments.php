<?php

require_once __DIR__ . '/../core/config.php';
header('Content-Type: application/json');

$public_video_id = $_GET['video_id'] ?? null;

if (!$public_video_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing video_id parameter.']);
    exit;
}

$sort_param = $_GET['sort'] ?? 'newest';
$current_user_id = $_SESSION['user_id'] ?? 0;

// Строгий маппинг сортировки для предотвращения SQL-инъекций в ORDER BY
$order_clause = match ($sort_param) {
    'popular' => 'c.like_count DESC, c.created_at DESC',
    'oldest'  => 'c.created_at ASC',
    default   => 'c.created_at DESC',
};

try {
    $stmt_get_id = $conn->prepare("SELECT video_id FROM videos WHERE public_video_id = ?");
    $stmt_get_id->bind_param("s", $public_video_id);
    $stmt_get_id->execute();
    $video_data = $stmt_get_id->get_result()->fetch_assoc();
    $stmt_get_id->close();
    
    if (!$video_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Video not found.']);
        exit;
    }
    
    $video_id = (int)$video_data['video_id'];

    $sql = "
        SELECT 
            c.comment_id, 
            c.text, 
            c.created_at, 
            c.like_count, 
            c.dislike_count, 
            c.parent_comment_id,
            u.public_user_id, 
            u.username, 
            u.avatar_url,
            (SELECT type FROM comment_likes cl WHERE cl.comment_id = c.comment_id AND cl.user_id = ?) as user_action
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.video_id = ?
        ORDER BY {$order_clause}
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $current_user_id, $video_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $comments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'comments' => $comments]);

} catch (Exception $e) {
    error_log("Get comments error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}