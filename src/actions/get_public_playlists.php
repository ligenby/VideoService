<?php

header('Content-Type: application/json');

// Основной SQL-запрос для получения информации о плейлистах
$playlists_query = "
    SELECT 
        p.playlist_id,
        p.title,
        p.updated_at,
        u.channel_name,
        u.username,
        u.avatar_url,
        u.public_user_id, 
        u.subscriber_count,
        COUNT(pv.video_id) as video_count
    FROM playlists p
    JOIN users u ON p.user_id = u.user_id
    JOIN playlist_videos pv ON p.playlist_id = pv.playlist_id
    WHERE p.visibility = 'public'
    GROUP BY p.playlist_id
    HAVING video_count > 0
    ORDER BY p.updated_at DESC
";

$result = $conn->query($playlists_query);
$playlists_data = [];

if ($result) {
    while ($playlist = $result->fetch_assoc()) {
        $playlist_id = $playlist['playlist_id'];
        
        $videos_stmt = $conn->prepare("
            SELECT v.thumbnail_url, v.public_video_id 
            FROM playlist_videos pv
            JOIN videos v ON pv.video_id = v.video_id
            WHERE pv.playlist_id = ?
            ORDER BY pv.playlist_video_id DESC
            LIMIT 3
        ");
        $videos_stmt->bind_param("i", $playlist_id);
        $videos_stmt->execute();
        $videos_result = $videos_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $videos_stmt->close();
        
        $playlist['videos'] = $videos_result;
        
        if (function_exists('format_time_ago')) {
            $playlist['updated_ago'] = format_time_ago($playlist['updated_at']);
        }

        $playlists_data[] = $playlist;
    }
}

echo json_encode($playlists_data);
?>