<?php

require_once __DIR__ . '/../core/config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'notifications' => [], 'unread_count' => 0]);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

$sql = "
    SELECT n.*, 
           u.username as actor_name, 
           u.public_user_id as actor_public_id,
           u.avatar_url as actor_avatar,
           
           v_like.title as video_title_like,
           v_like.public_video_id as video_public_id_like,

           c.text as comment_text,
           v_comment.title as video_title_comment,
           v_comment.public_video_id as video_public_id_comment,

           v_fallback.title as video_title_fallback,
           v_fallback.public_video_id as video_public_id_fallback
    FROM notifications n
    LEFT JOIN users u ON n.actor_user_id = u.user_id
    
    LEFT JOIN videos v_like ON n.target_id = v_like.video_id AND n.type = 'like'
    
    LEFT JOIN comments c ON n.target_id = c.comment_id AND n.type IN ('comment', 'reply')
    LEFT JOIN videos v_comment ON c.video_id = v_comment.video_id

    LEFT JOIN videos v_fallback ON n.target_id = v_fallback.video_id AND n.type IN ('comment', 'reply')
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC 
    LIMIT 20
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$raw_result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$notifications = [];

foreach ($raw_result as $row) {
    $video_title = $row['video_title_comment'] ?? $row['video_title_like'] ?? $row['video_title_fallback'] ?? 'видео';
    $public_video_id = $row['video_public_id_comment'] ?? $row['video_public_id_like'] ?? $row['video_public_id_fallback'] ?? '';
    
    $link_url = '#';

    // Формирование ссылки в зависимости от типа уведомления
    switch ($row['type']) {
        case 'subscription':
            $link_url = '/profile.php?id=' . $row['actor_public_id'];
            break;
        case 'report_update':
            $link_url = '/other/complaints.php';
            break;
        case 'comment':
        case 'reply':
        case 'like':
            if ($public_video_id) {
                $link_url = '/watch.php?id=' . $public_video_id;
                if (in_array($row['type'], ['comment', 'reply']) && $row['comment_text']) {
                    $link_url .= '&lc=' . $row['target_id'];
                }
            }
            break;
    }

    $notifications[] = [
        'notification_id' => $row['notification_id'],
        'type'            => $row['type'],
        'is_read'         => (bool)$row['is_read'],
        'time_ago'        => format_time_ago($row['created_at']),
        'actor_name'      => $row['actor_name'] ?? 'Пользователь',
        'actor_avatar'    => $row['actor_avatar'],
        'actor_public_id' => $row['actor_public_id'], 
        'comment_text'    => $row['comment_text'],
        'video_title'     => $video_title,
        'link_url'        => $link_url
    ];
}

$count_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$unread_count = (int)$count_stmt->get_result()->fetch_assoc()['unread'];
$count_stmt->close();

echo json_encode([
    'success' => true, 
    'notifications' => $notifications, 
    'unread_count' => $unread_count
]);