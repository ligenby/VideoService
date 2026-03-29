<?php
// Базовые настройки страницы категории
$page_title = "Новости";
$category_name = "Новости";
$additional_styles = ['/css/category-page.css'];

require_once __DIR__ . '/../../src/core/config.php'; 
require_once __DIR__ . '/../../templates/partials/header.php'; 
require_once __DIR__ . '/../../templates/partials/sidebar.php';

$videos = [];
$category_id = null;
$user_id = $_SESSION['user_id'] ?? null;

// Определяем ID текущей категории для фильтрации контента
$stmt_cat = $conn->prepare("SELECT category_id FROM categories WHERE name = ?");
$stmt_cat->bind_param("s", $category_name);
$stmt_cat->execute();
$result_cat = $stmt_cat->get_result();

if ($cat = $result_cat->fetch_assoc()) {
    $category_id = $cat['category_id'];
}
$stmt_cat->close();

// Выгружаем все опубликованные публичные видео, относящиеся к этой категории
if ($category_id) {
    $stmt_videos = $conn->prepare("
        SELECT 
            v.video_id, v.public_video_id, v.title, v.thumbnail_url, v.views_count, 
            v.upload_date, v.duration_seconds, u.public_user_id, u.channel_name, 
            u.username, u.avatar_url
        FROM videos v
        JOIN video_categories vc ON v.video_id = vc.video_id
        JOIN users u ON v.user_id = u.user_id
        WHERE vc.category_id = ? AND v.status = 'published' AND v.visibility = 'public'
        ORDER BY v.upload_date DESC
    ");
    $stmt_videos->bind_param("i", $category_id);
    $stmt_videos->execute();
    $videos = $stmt_videos->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_videos->close();
}

// Загружаем пользовательские данные (плейлисты, отметки "Смотреть позже") 
// для корректной работы контекстного меню у каждого видео
$watch_later_ids = [];
$user_playlists = [];

if ($user_id) {
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
}
?>

<main class="main-content">
    <div class="category-page-container">
        
        <div class="yt-category-header" style="--bg-image: url('/images/news-banner.jpg');">
            <div class="yt-category-content">
                <img src="/images/news-icon.png" alt="<?= htmlspecialchars($category_name) ?> Icon" class="yt-category-icon">
                <h1 class="yt-category-title"><?= htmlspecialchars($category_name) ?></h1>
                <p class="yt-category-description">
                    Актуальные события, репортажи и аналитика в категории "<?= htmlspecialchars($category_name) ?>".
                </p>
            </div>
        </div>

        <?php if (!empty($videos)): ?>
            <div class="video-grid">
                <?php foreach ($videos as $video): ?>
                    <div class="video-card" data-video-id="<?= htmlspecialchars($video['public_video_id']) ?>">
                        
                        <a href="/watch.php?id=<?= htmlspecialchars($video['public_video_id']) ?>" class="thumbnail-link">
                            <div class="thumbnail-wrapper">
                                <img src="/<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="Превью" class="video-thumbnail">
                                <div class="video-duration"><?= format_duration($video['duration_seconds']) ?></div>
                            </div>
                        </a>
                        
                        <div class="video-info-container">
                            <div class="video-text-content">
                                <h3 class="video-title">
                                    <a href="/watch.php?id=<?= htmlspecialchars($video['public_video_id']) ?>">
                                        <?= htmlspecialchars($video['title']) ?>
                                    </a>
                                </h3>
                                <div class="channel-details">
                                    <a href="/profile.php?id=<?= htmlspecialchars($video['public_user_id']) ?>">
                                        <img src="/<?= htmlspecialchars($video['avatar_url'] ?? 'images/default_avatar.png') ?>" alt="Аватар" class="channel-avatar-small">
                                    </a>
                                    <div class="meta-stack">
                                        <a href="/profile.php?id=<?= htmlspecialchars($video['public_user_id']) ?>" class="channel-name-link">
                                            <?= htmlspecialchars($video['channel_name'] ?? $video['username']) ?>
                                        </a>
                                        <div class="video-meta">
                                            <span><?= number_format($video['views_count']) ?> просмотров</span>
                                            <span>•</span>
                                            <span><?= format_time_ago($video['upload_date']) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Выпадающее меню действий с видео -->
                            <div class="context-menu-container">
                                <button class="context-menu-button"><img src="/images/menu-history.png" alt="Меню"></button>
                                <div class="context-menu-dropdown">
                                    <?php if ($user_id): ?>
                                        <?php
                                        $is_in_watch_later = in_array($video['video_id'], $watch_later_ids);
                                        $watch_later_text = $is_in_watch_later ? "Удалить из 'Смотреть позже'" : "Смотреть позже";
                                        ?>
                                        <a href="#" data-action="toggle-watch-later">
                                            <img src="/images/watch_later.png" alt="">
                                            <span><?= $watch_later_text ?></span>
                                        </a>
                                        <a href="#" data-action="add-to-playlist">
                                            <img src="/images/playlist.png" alt="">
                                            <span>Добавить в плейлист</span>
                                        </a>
                                    <?php else: ?>
                                        <a href="#" data-action="login-required">
                                            <img src="/images/watch_later.png" alt="">
                                            <span>Смотреть позже</span>
                                        </a>
                                        <a href="#" data-action="login-required">
                                            <img src="/images/playlist.png" alt="">
                                            <span>Добавить в плейлист</span>
                                        </a>
                                    <?php endif; ?>
                                    <a href="#" data-action="share">
                                        <img src="/images/share.png" alt="">
                                        <span>Поделиться</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-page-placeholder">
                <img src="/images/empty-folder.png" alt="Пусто">
                <h2>В этой категории пока нет видео</h2>
                <p>Как только здесь появятся ролики, вы сразу их увидите.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

</div> 

<?php
// Передаем плейлисты пользователя в глобальную область видимости JS для работы модальных окон
if ($user_id) {
    echo '<script> const userPlaylists = ' . json_encode($user_playlists ?? []) . '; </script>';
}

// Подключаем общие модальные окна
require_once __DIR__ . '/../../templates/partials/global_modals.php';
?>

<script src="/js/script.js"></script>
<script src="/js/modal_manager.js"></script> 
<script src="/js/category-page.js"></script>
</body>
</html>