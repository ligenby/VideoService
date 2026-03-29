<?php
$additional_styles = [
    '/css/liked.css'
];
$body_class = 'liked-page';

require_once __DIR__ . '/../../templates/partials/header.php'; 
require_once __DIR__ . '/../../templates/partials/sidebar.php';

if (!isset($_SESSION['user_id'])):
?>
    <main class="main-content">
        <div class="empty-page-placeholder">
            <img src="/images/like.png" alt="Понравившиеся">
            <h2>Просматривайте понравившиеся видео здесь</h2>
            <p>Чтобы видеть здесь видео, которые вам понравились, <a href="/login.php" class="empty-page-button">войдите в аккаунт</a>.</p>
        </div>
    </main>
<?php
else:
    $user_id = (int)$_SESSION['user_id'];
    
    // подтягиваем лайкнутые
    $stmt = $conn->prepare("
        SELECT 
            v.video_id, v.public_video_id, v.title, v.thumbnail_url, 
            v.views_count, v.upload_date, v.duration_seconds,
            u.public_user_id, u.channel_name, u.username
        FROM likes l
        JOIN videos v ON l.video_id = v.video_id
        JOIN users u ON v.user_id = u.user_id
        WHERE l.user_id = ? AND l.like_type = 1
        AND v.status != 'deleted'
        ORDER BY l.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $liked_videos = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $first_public_video_id = !empty($liked_videos) ? $liked_videos[0]['public_video_id'] : null;
    
    $watch_later_ids = [];
    $wl_stmt = $conn->prepare("SELECT video_id FROM watch_later WHERE user_id = ?");
    $wl_stmt->bind_param("i", $user_id);
    $wl_stmt->execute();
    $wl_result = $wl_stmt->get_result();
    while ($row = $wl_result->fetch_assoc()) {
        $watch_later_ids[] = $row['video_id'];
    }
    $wl_stmt->close();

    $playlists_stmt = $conn->prepare("SELECT playlist_id, title FROM playlists WHERE user_id = ? ORDER BY title ASC");
    $playlists_stmt->bind_param("i", $user_id);
    $playlists_stmt->execute();
    $user_playlists = $playlists_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $playlists_stmt->close();
?>
    <main class="main-content">
        <div class="liked-page-container">
            <div class="liked-page-header">
                <h1 class="liked-page-title">Видео, которые вам ранее понравились</h1>
                
                <?php if (!empty($liked_videos)): ?>
                    <div class="playlist-actions">
                        <a href="<?php echo $first_public_video_id ? '/watch.php?id=' . htmlspecialchars($first_public_video_id) . '&playlist=liked' : '#'; ?>" class="action-button play-all">
                            <img src="/images/play_arrow.png" alt="Воспроизвести">
                            <span>Воспроизвести первое видео</span>
                        </a>
                        <a href="#" class="action-button shuffle" data-action="shuffle-play">
                            <img src="/images/shuffle.png" alt="Перемешать">
                            <span>Перемешать</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($liked_videos)): ?>
                <div class="liked-video-list">
                    <?php 
                    $index = 1;
                    foreach ($liked_videos as $video): 
                    ?>
                        <div class="liked-video-item" data-video-id="<?php echo htmlspecialchars($video['public_video_id']); ?>">
                            <div class="video-index"><?php echo $index; ?></div>
                            <div class="video-main-link">
                                <a href="/watch.php?id=<?php echo htmlspecialchars($video['public_video_id']); ?>&playlist=liked" class="thumbnail-wrapper-link">
                                    <div class="thumbnail-wrapper">
                                        <img class="thumbnail-image" src="/<?php echo htmlspecialchars($video['thumbnail_url']); ?>" alt="Превью видео">
                                        <div class="thumbnail-duration"><?php echo format_duration($video['duration_seconds']); ?></div>
                                    </div>
                                </a>

                                <div class="video-details">
                                    <h3 class="video-title">
                                        <a href="/watch.php?id=<?php echo htmlspecialchars($video['public_video_id']); ?>&playlist=liked">
                                            <?php echo htmlspecialchars($video['title']); ?>
                                        </a>
                                    </h3>
                                    <div class="video-meta">
                                        <a href="/profile.php?id=<?php echo htmlspecialchars($video['public_user_id']); ?>" class="video-channel-link">
                                            <?php echo htmlspecialchars($video['channel_name'] ?? $video['username']); ?>
                                        </a>
                                        <span>•</span>
                                        <span><?php echo number_format($video['views_count']); ?> <?php echo get_plural_form($video['views_count'], ['просмотр', 'просмотра', 'просмотров']); ?></span>
                                        <span>•</span>
                                        <span><?php echo format_time_ago($video['upload_date']); ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="context-menu-container">
                                <button class="context-menu-button">
                                    <img src="/images/menu-history.png" alt="Меню">
                                </button>
                                <div class="context-menu-dropdown">
                                    <?php
                                    $is_in_watch_later = in_array($video['video_id'], $watch_later_ids);
                                    $watch_later_text = $is_in_watch_later ? "Удалить из 'Смотреть позже'" : "Смотреть позже";
                                    ?>
                                    <a href="#" data-action="toggle-watch-later">
                                        <img src="/images/watch_later.png" alt="Смотреть позже">
                                        <span><?php echo $watch_later_text; ?></span>
                                    </a>
                                    <a href="#" data-action="add-to-playlist"><img src="/images/playlist.png" alt="Плейлист"><span>Добавить в плейлист</span></a>
                                    <a href="#" data-action="share-video"><img src="/images/share.png" alt="Поделиться"><span>Поделиться</span></a>
                                    <a href="#" data-action="remove-like"><img src="/images/musor.png" alt="Удалить"><span>Удалить из понравившихся</span></a>
                                </div>
                            </div>
                        </div>
                    <?php 
                    $index++;
                    endforeach; 
                    ?>
                </div>
            <?php else: ?>
                <div class="empty-page-placeholder">
                    <h2>Вам пока не понравилось ни одно видео</h2>
                    <p>Нажимайте "палец вверх" под роликами, и они появятся здесь.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
<?php
endif;
?>
</div>

<?php
if (isset($user_id)) {
    echo '<script> const userPlaylists = ' . json_encode($user_playlists) . '; </script>';
}
require_once __DIR__ . '/../../templates/partials/global_modals.php';
?>

<script src="/js/script.js"></script>
<script src="/js/modal_manager.js"></script> 
<script src="/js/liked.js"></script>
</body>
</html>