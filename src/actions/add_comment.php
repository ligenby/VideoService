<?php

require_once __DIR__ . '/../core/config.php';

header('Content-Type: application/json');

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_SESSION['user_id']) || 
    !isset($_POST['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid or unauthorized request.']);
    exit;
}

$public_video_id = $_POST['video_id'] ?? null;
$text = trim($_POST['text'] ?? '');

if (!$public_video_id || empty($text)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required data.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$parent_id = !empty($_POST['parent_comment_id']) ? (int)$_POST['parent_comment_id'] : null;

try {
    $stmt_get = $conn->prepare("SELECT video_id, user_id FROM videos WHERE public_video_id = ?");
    $stmt_get->bind_param("s", $public_video_id);
    $stmt_get->execute();
    $video_data = $stmt_get->get_result()->fetch_assoc();
    $stmt_get->close();

    if (!$video_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Video not found.']);
        exit;
    }

    $video_id = (int)$video_data['video_id'];
    $video_owner_id = (int)$video_data['user_id'];
    
    $stmt = $conn->prepare("INSERT INTO comments (video_id, user_id, text, parent_comment_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisi", $video_id, $user_id, $text, $parent_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to insert comment: " . $stmt->error);
    }
    
    $new_comment_id = $stmt->insert_id;
    $stmt->close();
        
    if ($parent_id && function_exists('createNotification')) {
        $p_stmt = $conn->prepare("SELECT user_id FROM comments WHERE comment_id = ?");
        $p_stmt->bind_param("i", $parent_id);
        $p_stmt->execute();
        $p_res = $p_stmt->get_result()->fetch_assoc();
        $p_stmt->close();

        if (!empty($p_res['user_id'])) {
            createNotification($conn, (int)$p_res['user_id'], $user_id, 'reply', $new_comment_id);
        }
    } elseif (function_exists('createNotification')) {
        createNotification($conn, $video_owner_id, $user_id, 'comment', $new_comment_id);
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log("Add comment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error.']);
}