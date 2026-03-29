<?php

require_once __DIR__ . '/../core/config.php';

$query = trim($_GET['q'] ?? '');

if (empty($query)) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$search_term = "%" . $query . "%";
$user_id = $_SESSION['user_id'] ?? null;

$suggestions = [];
$processed_titles = [];

// Подсчет точных совпадений для решения о показе миниатюр (предотвращение визуального шума)
$stmt_channel_count = $conn->prepare("SELECT COUNT(DISTINCT user_id) as count FROM users WHERE username LIKE ? OR channel_name LIKE ?");
$stmt_channel_count->bind_param("ss", $search_term, $search_term);
$stmt_channel_count->execute();
$channel_match_count = (int)$stmt_channel_count->get_result()->fetch_assoc()['count'];
$stmt_channel_count->close();

$stmt_video_count = $conn->prepare("SELECT COUNT(DISTINCT video_id) as count FROM videos WHERE title LIKE ? AND status = 'published' AND visibility = 'public'");
$stmt_video_count->bind_param("s", $search_term);
$stmt_video_count->execute();
$video_match_count = (int)$stmt_video_count->get_result()->fetch_assoc()['count'];
$stmt_video_count->close();

// 1. Поиск по персональной истории (наивысший приоритет)
if ($user_id) {
    $stmt_history = $conn->prepare("SELECT DISTINCT query FROM search_history WHERE user_id = ? AND query LIKE ? ORDER BY searched_at DESC LIMIT 5");
    $stmt_history->bind_param("is", $user_id, $search_term);
    $stmt_history->execute();
    $history_result = $stmt_history->get_result();
    
    while ($row = $history_result->fetch_assoc()) {
        $title = $row['query'];
        $lower_title = mb_strtolower($title);
        
        if (!in_array($lower_title, $processed_titles, true)) {
            $suggestions[] = ['type' => 'history', 'title' => $title];
            $processed_titles[] = $lower_title;
        }
    }
    $stmt_history->close();
}

// 2. Поиск по каналам
$stmt_channels = $conn->prepare("SELECT public_user_id, username, channel_name, avatar_url FROM users WHERE username LIKE ? OR channel_name LIKE ? LIMIT 3");
$stmt_channels->bind_param("ss", $search_term, $search_term);
$stmt_channels->execute();
$channels_result = $stmt_channels->get_result();

while ($row = $channels_result->fetch_assoc()) {
    $title = $row['channel_name'] ?: $row['username'];
    $lower_title = mb_strtolower($title);
    
    if (!in_array($lower_title, $processed_titles, true)) {
        $item = [
            'type'  => 'channel',
            'id'    => $row['public_user_id'],
            'title' => $title,
        ];
        
        if ($channel_match_count <= 1) {
            $item['thumbnail'] = $row['avatar_url'];
        }
        
        $suggestions[] = $item;
        $processed_titles[] = $lower_title;
    }
}
$stmt_channels->close();

// 3. Поиск по названиям видео (дополняем список до 10 элементов)
if (count($suggestions) < 10) {
    $limit = 10 - count($suggestions);
    $stmt_videos = $conn->prepare("SELECT public_video_id, title, thumbnail_url FROM videos WHERE title LIKE ? AND status = 'published' AND visibility = 'public' LIMIT ?");
    $stmt_videos->bind_param("si", $search_term, $limit);
    $stmt_videos->execute();
    $videos_result = $stmt_videos->get_result();
    
    while ($row = $videos_result->fetch_assoc()) {
        $title = $row['title'];
        $lower_title = mb_strtolower($title);
        
        if (!in_array($lower_title, $processed_titles, true)) {
            $item = [
                'type'  => 'video',
                'id'    => $row['public_video_id'],
                'title' => $title,
            ];
            
            if ($video_match_count <= 1) {
                $item['thumbnail'] = $row['thumbnail_url'];
            }
            
            $suggestions[] = $item;
            $processed_titles[] = $lower_title;
        }
    }
    $stmt_videos->close();
}

header('Content-Type: application/json');
echo json_encode(array_slice($suggestions, 0, 10));