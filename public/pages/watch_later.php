<?php

$additional_styles = ['/css/watch_later.css'];
$body_class = 'watch-later-page';

require_once __DIR__ . '/../../src/core/config.php';
require_once __DIR__ . '/../../templates/partials/header.php'; 
require_once __DIR__ . '/../../templates/partials/sidebar.php';

// Логика для неавторизованных пользователей
if (!isset($_SESSION['user_id'])) {
?>
    <main class="main-content">
        <div class="empty-page-placeholder">
            <img src="/images/watch_later.png" alt="Смотреть позже">
            <h2>Здесь будут видео, которые вы сохранили на потом</h2>
            <p>Нажмите на значок "Смотреть позже" под видео, чтобы добавить его сюда.</p>
            <a href="/login.php" class="empty-page-button">Войти</a>
        </div>
    </main>
    </div> <!-- Закрытие обертки из sidebar.php -->
    </body>
    </html>
<?php
    exit;
}

// Логика для авторизованных пользователей
$user_id = (int)$_SESSION['user_id'];
$sort_order = $_GET['sort'] ?? 'manual'; 

// Формирование SQL сортировки с использованием match (PHP 8)
$order_by_clause = match ($sort_order) {
    'date_added_new'     => "ORDER BY wl.added_at DESC",
    'date_added_old'     => "ORDER BY wl.added_at ASC",
    'popularity'         => "ORDER BY v.views_count DESC",
    'date_published_new' => "ORDER BY v.upload_date DESC",
    'date_published_old' => "ORDER BY v.upload_date ASC",
    default              => "ORDER BY wl.display_order ASC, wl.added_at DESC", // manual
};

// Загрузка списка видео
$stmt = $conn->prepare("
    SELECT 
        v.video_id, v.public_video_id, v.title, v.description, v.thumbnail_url, 
        v.duration_seconds, v.views_count, v.upload_date, 
        u.public_user_id, u.channel_name, u.username
    FROM watch_later wl
    JOIN videos v ON wl.video_id = v.video_id
    JOIN users u ON v.user_id = u.user_id
    WHERE wl.user_id = ? 
      AND v.status != 'deleted' 
    {$order_by_clause}
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$videos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$first_public_video_id = !empty($videos) ? $videos[0]['public_video_id'] : null;

// Загрузка плейлистов пользователя (для модального окна "Добавить в плейлист")
$playlists_stmt = $conn->prepare("SELECT playlist_id, title FROM playlists WHERE user_id = ? ORDER BY title ASC");
$playlists_stmt->bind_param("i", $user_id);
$playlists_stmt->execute();
$user_playlists = $playlists_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$playlists_stmt->close();
?>

<main class="main-content">
    <div class="watch-later-container">
        
        <div class="page-header">
            <h1 class="page-title">Смотреть позже</h1>
            
            <?php if (!empty($videos)): ?>
                <div class="header-actions">
                    <a href="<?= $first_public_video_id ? '/watch.php?id=' . htmlspecialchars($first_public_video_id) . '&playlist=watch_later&sort=' . htmlspecialchars($sort_order) : '#' ?>" class="action-button play-all">
                        <img src="/images/play_arrow.png" alt="Воспроизвести">
                        <span>Воспроизвести</span>
                    </a>
                    
                    <a href="#" class="action-button shuffle" data-action="shuffle-play">
                        <img src="/images/shuffle.png" alt="Перемешать">
                        <span>Перемешать</span>
                    </a>
                    
                    <div class="sort-menu-container">
                        <button class="sort-menu-button" id="sort-menu-button">
                            <span>Упорядочить</span>
                            <img src="/images/arrow-down.png" alt="v" class="sort-arrow-icon">
                        </button>
                        <div class="sort-menu-dropdown">
                            <a href="?sort=manual" class="sort-option <?= $sort_order === 'manual' ? 'active' : '' ?>">Моя сортировка</a>
                            <a href="?sort=date_added_new" class="sort-option <?= $sort_order === 'date_added_new' ? 'active' : '' ?>">Дата добавления: сначала новые</a>
                            <a href="?sort=date_added_old" class="sort-option <?= $sort_order === 'date_added_old' ? 'active' : '' ?>">Дата добавления: сначала старые</a>
                            <a href="?sort=popularity" class="sort-option <?= $sort_order === 'popularity' ? 'active' : '' ?>">По популярности</a>
                            <a href="?sort=date_published_new" class="sort-option <?= $sort_order === 'date_published_new' ? 'active' : '' ?>">Дата публикации: сначала новые</a>
                            <a href="?sort=date_published_old" class="sort-option <?= $sort_order === 'date_published_old' ? 'active' : '' ?>">Дата публикации: сначала старые</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($videos)): ?>
            <div class="video-list" id="sortable-video-list">
                <?php foreach ($videos as $video): ?>
                    <div class="video-item" data-video-id="<?= htmlspecialchars($video['public_video_id']) ?>">
                        
                        <div class="drag-handle-container">
                            <img src="/images/drag_handle.png" alt="Перетащить" class="drag-handle-icon" title="Удерживайте, чтобы изменить порядок">
                        </div>

                        <a href="/watch.php?id=<?= htmlspecialchars($video['public_video_id']) ?>&playlist=watch_later&sort=<?= htmlspecialchars($sort_order) ?>" class="video-main-link">
                            <div class="thumbnail-wrapper">
                                <img class="thumbnail-image" src="/<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="Превью видео">
                                <div class="thumbnail-duration"><?= format_duration($video['duration_seconds']) ?></div>
                            </div>
                            
                            <div class="video-details">
                                <h3 class="video-title"><?= htmlspecialchars($video['title']) ?></h3>
                                <div class="video-meta">
                                    <span><?= htmlspecialchars($video['channel_name'] ?? $video['username']) ?></span> •
                                    <span><?= number_format($video['views_count'], 0, '.', ' ') ?> просмотров</span> •
                                    <span><?= format_time_ago($video['upload_date']) ?></span>
                                </div>
                            </div>
                        </a>

                        <div class="context-menu-container">
                            <button class="context-menu-button">
                                <img src="/images/menu-history.png" alt="Меню">
                            </button>
                            <div class="context-menu-dropdown">
                                <a href="#" data-action="add-to-playlist">
                                    <img src="/images/playlist.png" alt="">
                                    <span>Добавить в плейлист</span>
                                </a>
                                <a href="#" data-action="remove-watch-later">
                                    <img src="/images/delete.png" alt="">
                                    <span>Удалить из "Смотреть позже"</span>
                                </a>
                                <a href="#" data-action="share-video">
                                    <img src="/images/share.png" alt="">
                                    <span>Поделиться</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-page-placeholder vertical">
                <h2>Здесь будут видео, которые вы сохранили</h2>
                <p>Нажимайте на значок "Смотреть позже" под видео, чтобы добавить его сюда.</p>
            </div>
        <?php endif; ?>
        
    </div>
</main>
</div> 

<script>
    const userPlaylists = <?= json_encode($user_playlists ?? []) ?>;
</script>

<?php require_once __DIR__ . '/../../templates/partials/global_modals.php'; ?>

<script src="/js/lib/Sortable.min.js"></script>
<script src="/js/script.js"></script>
<script src="/js/modal_manager.js"></script> 
<script src="/js/watch_later.js"></script>

</body>
</html>