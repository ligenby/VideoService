<?php
$additional_styles = [
    '/css/playlist_view.css?v=1.4'
];
$body_class = 'playlist-view-page';

require_once __DIR__ . '/../../src/core/config.php';
require_once __DIR__ . '/../../templates/partials/header.php'; 
require_once __DIR__ . '/../../templates/partials/sidebar.php';

$playlist_id = $_GET['id'] ?? null;
$playlist_type = $_GET['type'] ?? null;
$current_user_id = $_SESSION['user_id'] ?? null;

$videos = [];
$playlist_info = null;
$playlist_owner_info = null;
$error_message = null;
// если есть айдишник и нет типа - значит это кастомный плейлист
$is_user_playlist = !is_null($playlist_id) && is_null($playlist_type); 
$watch_later_ids = [];

if (!$current_user_id) {
    $error_message = "Для просмотра плейлистов необходимо войти в аккаунт.";
} else {
    $wl_stmt = $conn->prepare("SELECT video_id FROM watch_later WHERE user_id = ?");
    $wl_stmt->bind_param("i", $current_user_id);
    $wl_stmt->execute();
    $wl_result = $wl_stmt->get_result();
    while ($row = $wl_result->fetch_assoc()) {
        $watch_later_ids[] = $row['video_id'];
    }
    $wl_stmt->close();

    if ($playlist_id) {
        $stmt_playlist = $conn->prepare("SELECT p.title, p.description, p.visibility, p.user_id, u.public_user_id, u.username, u.channel_name, u.avatar_url FROM playlists p JOIN users u ON p.user_id = u.user_id WHERE p.playlist_id = ?");
        $stmt_playlist->bind_param("i", $playlist_id);
        $stmt_playlist->execute();
        $playlist_data = $stmt_playlist->get_result()->fetch_assoc();
        $stmt_playlist->close();

        if ($playlist_data) {
            $can_view = ($playlist_data['visibility'] !== 'private' || ($current_user_id == $playlist_data['user_id']));
            
            if ($can_view) {
                $playlist_info = $playlist_data;
                $playlist_owner_info = ['username' => $playlist_data['channel_name'] ?? $playlist_data['username'], 'avatar_url' => $playlist_data['avatar_url'], 'public_id' => $playlist_data['public_user_id']];
                
                $stmt_videos = $conn->prepare("
                    SELECT v.*, u.public_user_id, u.channel_name, u.username 
                    FROM playlist_videos pv 
                    JOIN videos v ON pv.video_id = v.video_id 
                    JOIN users u ON v.user_id = u.user_id 
                    WHERE pv.playlist_id = ? AND v.status != 'deleted' 
                    ORDER BY pv.playlist_video_id ASC
                ");
                $stmt_videos->bind_param("i", $playlist_id);
                $stmt_videos->execute();
                $videos = $stmt_videos->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_videos->close();
            } else {
                $error_message = "У вас нет доступа к этому плейлисту.";
            }
        } else {
            $error_message = "Плейлист не найден.";
        }
    } elseif ($playlist_type) {
        $stmt_owner = $conn->prepare("SELECT public_user_id, username, channel_name, avatar_url FROM users WHERE user_id = ?");
        $stmt_owner->bind_param("i", $current_user_id);
        $stmt_owner->execute();
        $owner_data = $stmt_owner->get_result()->fetch_assoc();
        $playlist_owner_info = ['username' => $owner_data['channel_name'] ?? $owner_data['username'], 'avatar_url' => $owner_data['avatar_url'], 'public_id' => $owner_data['public_user_id']];
        $stmt_owner->close();
        
        $sql = "";
        switch ($playlist_type) {
            case 'liked':
                $playlist_info = ['title' => "Понравившиеся", 'description' => 'Видео, которые вы отметили как понравившиеся.', 'visibility' => 'private'];
                $sql = "SELECT v.*, u.public_user_id, u.channel_name, u.username FROM likes l JOIN videos v ON l.video_id = v.video_id JOIN users u ON v.user_id = u.user_id WHERE l.user_id = ? AND l.like_type = 1 AND v.status != 'deleted' ORDER BY l.created_at DESC";
                break;
            case 'watch_later':
                $playlist_info = ['title' => "Смотреть позже", 'description' => 'Видео, которые вы сохранили на потом.', 'visibility' => 'private'];
                $sql = "SELECT v.*, u.public_user_id, u.channel_name, u.username FROM watch_later wl JOIN videos v ON wl.video_id = v.video_id JOIN users u ON v.user_id = u.user_id WHERE wl.user_id = ? AND v.status != 'deleted' ORDER BY wl.display_order ASC, wl.added_at DESC";
                break;
            case 'hidden':
                $playlist_info = ['title' => "Скрытые видео", 'description' => 'Видео, которые вы скрыли из рекомендаций и подписок.', 'visibility' => 'private'];
                $sql = "SELECT v.*, u.public_user_id, u.channel_name, u.username FROM hidden_videos hv JOIN videos v ON hv.video_id = v.video_id JOIN users u ON v.user_id = u.user_id WHERE hv.user_id = ? AND v.status != 'deleted' ORDER BY hv.hidden_at DESC";
                break;
            default: 
                $error_message = "Неизвестный тип плейлиста."; 
                break;
        }

        if ($sql) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $current_user_id);
            $stmt->execute();
            $videos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
    } else {
        $error_message = "Не указан ID или тип плейлиста.";
    }
}

$user_playlists = [];
if($current_user_id) {
    $playlists_stmt = $conn->prepare("SELECT playlist_id, title FROM playlists WHERE user_id = ? ORDER BY title ASC");
    $playlists_stmt->bind_param("i", $current_user_id);
    $playlists_stmt->execute();
    $user_playlists = $playlists_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $playlists_stmt->close();
}
?>

<main class="main-content">
    <?php if ($error_message): ?>
        <div class="empty-page-placeholder">
            <h2>Ошибка</h2>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php else: ?>
        <div class="playlist-view-container">
            
            <aside class="playlist-info-sidebar">
                <div class="playlist-thumbnail-container">
                    <?php if (!empty($videos)): ?>
                        <img src="/<?php echo htmlspecialchars($videos[0]['thumbnail_url']); ?>" alt="Обложка плейлиста" class="playlist-thumbnail">
                        <a href="/watch.php?id=<?php echo htmlspecialchars($videos[0]['public_video_id']); ?>&playlist=<?php echo $playlist_id ?? $playlist_type; ?>" class="play-all-overlay">
                            <img src="/images/play_arrow.png" alt="Воспроизвести">
                            <span>ВОСПРОИЗВЕСТИ ВСЕ</span>
                        </a>
                    <?php else: ?>
                         <div class="playlist-thumbnail-placeholder"></div>
                    <?php endif; ?>
                </div>

                <div class="playlist-info-details">
                    <h1 class="playlist-title"><?php echo htmlspecialchars($playlist_info['title']); ?></h1>
                    <?php if ($playlist_owner_info): ?>
                        <div class="playlist-owner">
                            <img src="/<?php echo htmlspecialchars($playlist_owner_info['avatar_url']); ?>" alt="Аватар" class="owner-avatar">
                            <span><?php echo htmlspecialchars($playlist_owner_info['username']); ?></span>
                        </div>
                    <?php endif; ?>
                    <?php
                        $visibility_text = 'Ограниченный доступ';
                        if (isset($playlist_info['visibility'])) {
                            if ($playlist_info['visibility'] === 'public') $visibility_text = 'Открытый доступ';
                            if ($playlist_info['visibility'] === 'unlisted') $visibility_text = 'Доступ по ссылке';
                        }
                    ?>
                    <div class="playlist-meta-info">
                        <span><?php echo $visibility_text; ?></span>
                        <span id="video-count-display">&bull; <?php echo count($videos); ?> видео</span>
                    </div>
                    <?php if (!empty($playlist_info['description'])): ?>
                        <p class="playlist-description"><?php echo nl2br(htmlspecialchars($playlist_info['description'])); ?></p>
                    <?php endif; ?>
                    <div class="playlist-actions">
                         <a href="#" class="action-button" data-action="shuffle-play"><img src="/images/shuffle.png" alt="Перемешать"><span>Перемешать</span></a>
                         <a href="#" class="action-button" data-action="share-playlist"><img src="/images/share.png" alt="Поделиться"><span>Поделиться</span></a>
                    </div>
                </div>
            </aside>

            <div class="video-list-container">
                <?php if (!empty($videos)): ?>
                    <?php foreach ($videos as $index => $video): ?>
                        <div class="playlist-video-item" 
                             data-video-id="<?php echo htmlspecialchars($video['public_video_id']); ?>"
                             data-internal-id="<?php echo htmlspecialchars($video['video_id']); ?>">
                            <span class="video-index"><?php echo $index + 1; ?></span>
                            
                            <div class="video-main-link">
                                <a href="/watch.php?id=<?php echo htmlspecialchars($video['public_video_id']); ?>&playlist=<?php echo $playlist_id ?? $playlist_type; ?>" class="thumbnail-wrapper-link">
                                    <div class="thumbnail-wrapper">
                                        <img class="thumbnail-image" src="/<?php echo htmlspecialchars($video['thumbnail_url']); ?>" alt="Превью">
                                        <div class="thumbnail-duration"><?php echo format_duration($video['duration_seconds']); ?></div>
                                    </div>
                                </a>

                                <div class="video-details">
                                    <h3 class="video-title">
                                        <a href="/watch.php?id=<?php echo htmlspecialchars($video['public_video_id']); ?>&playlist=<?php echo $playlist_id ?? $playlist_type; ?>">
                                            <?php echo htmlspecialchars($video['title']); ?>
                                        </a>
                                    </h3>
                                    
                                    <a href="/profile.php?id=<?php echo htmlspecialchars($video['public_user_id']); ?>" class="video-channel-link">
                                        <?php echo htmlspecialchars($video['channel_name'] ?? $video['username']); ?>
                                    </a>
                                </div>
                            </div>

                            <div class="context-menu-container">
                                <button class="context-menu-button"><img src="/images/menu-history.png" alt="Меню"></button>
                                <div class="context-menu-dropdown">
                                    <?php if ($playlist_type !== 'watch_later'): ?>
                                        <?php
                                        $is_in_watch_later = in_array($video['video_id'], $watch_later_ids);
                                        $watch_later_text = $is_in_watch_later ? "Удалить из 'Смотреть позже'" : "Добавить в 'Смотреть позже'";
                                        ?>
                                        <a href="#" data-action="toggle-watch-later"><img src="/images/watch_later.png" alt=""><span><?php echo $watch_later_text; ?></span></a>
                                    <?php endif; ?>
                                    <a href="#" data-action="add-to-playlist"><img src="/images/playlist.png" alt=""><span>Добавить в плейлист</span></a>
                                    <a href="#" data-action="share-video"><img src="/images/share.png" alt=""><span>Поделиться</span></a>
                                    <?php
                                    switch($playlist_type) {
                                        case 'watch_later': echo '<a href="#" data-action="remove-from-watch-later"><img src="/images/delete.png" alt=""><span>Удалить из "Смотреть позже"</span></a>'; break;
                                        case 'liked': echo '<a href="#" data-action="remove-from-liked"><img src="/images/delete.png" alt=""><span>Удалить из понравившихся</span></a>'; break;
                                        case 'hidden': echo '<a href="#" data-action="unhide-video"><img src="/images/delete.png" alt=""><span>Вернуть из скрытых</span></a>'; break;
                                    }
                                    if ($is_user_playlist) {
                                        echo '<a href="#" data-action="remove-from-playlist" data-playlist-id="' . htmlspecialchars($playlist_id) . '"><img src="/images/delete.png" alt=""><span>Удалить из этого плейлиста</span></a>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="empty-list-message">В этом плейлисте пока нет видео.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</main>
</div>

<?php
if (isset($current_user_id)) {
    echo '<script> const userPlaylists = ' . json_encode($user_playlists) . '; </script>';
}

$context_id = $playlist_id ?? null;
$context_type = $playlist_type ?? null;
$context = [
    'id' => $context_id, 
    'type' => $context_type
];

echo '<script> window.playlistContext = ' . json_encode($context) . '; </script>'; 

$user_id = $current_user_id;
require_once __DIR__ . '/../../templates/partials/global_modals.php';
?>

<script src="/js/script.js"></script>
<script src="/js/modal_manager.js"></script> 
<script src="/js/playlist_view.js"></script>

</body>
</html>