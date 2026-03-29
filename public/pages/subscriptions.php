<?php

$body_class = 'subscriptions-page';
$additional_styles = ['/css/subscriptions.css'];

require_once __DIR__ . '/../../src/core/config.php'; 
require_once __DIR__ . '/../../templates/partials/header.php'; 
require_once __DIR__ . '/../../templates/partials/sidebar.php';

// Сценарий для неавторизованного пользователя
if (!isset($_SESSION['user_id'])) {
?>
    <main class="main-content">
        <div class="empty-page-placeholder">
            <img src="/images/subscrieb.png" alt="Подписки">
            <h2>Не пропускайте новые видео</h2>
            <p>Здесь будут собраны ролики с каналов, на которые вы подпишетесь.</p>
            <a href="/login.php" class="empty-page-button">Войти</a>
        </div>
    </main>
    </div>
    </body>
    </html>
<?php
    exit;
}

// Сценарий для авторизованного пользователя
$user_id = (int)$_SESSION['user_id'];
$view_mode = $_GET['view'] ?? 'channels'; 
$sort_order = $_GET['sort'] ?? 'relevant';

// 1. "Смотреть позже"
$watch_later_ids = [];
$wl_stmt = $conn->prepare("SELECT video_id FROM watch_later WHERE user_id = ?");
$wl_stmt->bind_param("i", $user_id);
$wl_stmt->execute();
$wl_result = $wl_stmt->get_result();
while ($row = $wl_result->fetch_assoc()) {
    $watch_later_ids[] = (int)$row['video_id'];
}
$wl_stmt->close();

// 2. Плейлисты
$user_playlists = [];
$playlists_stmt = $conn->prepare("SELECT playlist_id, title FROM playlists WHERE user_id = ? ORDER BY title ASC");
$playlists_stmt->bind_param("i", $user_id);
$playlists_stmt->execute();
$user_playlists = $playlists_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$playlists_stmt->close();

// 3. Скрытые видео
$hidden_video_ids = [];
$hidden_stmt = $conn->prepare("SELECT video_id FROM hidden_videos WHERE user_id = ?");
$hidden_stmt->bind_param("i", $user_id);
$hidden_stmt->execute();
$hidden_result = $hidden_stmt->get_result();
while ($row = $hidden_result->fetch_assoc()) {
    $hidden_video_ids[] = (int)$row['video_id'];
}
$hidden_stmt->close();

?>
    <main class="main-content">
        <div class="subscriptions-page-container">
            
            <div class="subscriptions-header">
                <h2 class="subscriptions-page-title">
                    <?= $view_mode === 'videos' ? 'Последние видео с подписок' : 'Каналы, на которые вы подписаны' ?>
                </h2>
                
                <div class="header-actions">
                    <a href="?view=<?= $view_mode === 'channels' ? 'videos' : 'channels' ?>" class="view-toggle-button styled">
                        <img src="/images/<?= $view_mode === 'channels' ? 'video_library' : 'people' ?>.png" alt="Вид">
                        <span><?= $view_mode === 'channels' ? 'Видео' : 'Каналы' ?></span>
                    </a>
                    
                    <div class="sort-menu-container">
                        <?php if ($view_mode === 'channels'): 
                            $sort_labels = [
                                'relevant' => 'Самые релевантные', 
                                'recent' => 'Недавнее действие', 
                                'popularity' => 'По популярности', 
                                'name_asc' => 'А – Я', 
                                'name_desc' => 'Я – А'
                            ];
                        ?>
                            <button class="sort-menu-button" id="sort-menu-button">
                                <span><?= $sort_labels[$sort_order] ?? 'Самые релевантные' ?></span>
                                <img src="/images/arrow-down.png" alt="сортировка" class="sort-arrow-icon">
                            </button>
                            <div class="sort-menu-dropdown" id="sort-menu-dropdown">
                                <a href="?view=channels&sort=relevant" class="sort-option">Самые релевантные</a>
                                <a href="?view=channels&sort=recent" class="sort-option">Недавнее действие</a>
                                <a href="?view=channels&sort=popularity" class="sort-option">По популярности</a>
                                <a href="?view=channels&sort=name_asc" class="sort-option">А – Я</a>
                                <a href="?view=channels&sort=name_desc" class="sort-option">Я – А</a>
                            </div>
                        <?php else: 
                            $sort_labels = ['relevant' => 'Сначала новые', 'popularity' => 'Самые популярные'];
                        ?>
                            <button class="sort-menu-button" id="sort-menu-button">
                                <span><?= $sort_labels[$sort_order] ?? 'Сначала новые' ?></span>
                                <img src="/images/arrow-down.png" alt="сортировка" class="sort-arrow-icon">
                            </button>
                            <div class="sort-menu-dropdown" id="sort-menu-dropdown">
                                <a href="?view=videos&sort=relevant" class="sort-option">Сначала новые</a>
                                <a href="?view=videos&sort=popularity" class="sort-option">Самые популярные</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($view_mode === 'channels'): 
                $order_by_clause = match($sort_order) {
                    'recent' => "ORDER BY s.created_at DESC",
                    'popularity' => "ORDER BY u.subscriber_count DESC",
                    'name_asc' => "ORDER BY u.channel_name ASC",
                    'name_desc' => "ORDER BY u.channel_name DESC",
                    default => "ORDER BY s.created_at DESC"
                };
                
                $stmt = $conn->prepare("
                    SELECT u.user_id as channel_id_internal, u.public_user_id, u.username, u.channel_name, u.avatar_url, u.subscriber_count 
                    FROM subscriptions s 
                    JOIN users u ON s.channel_id = u.user_id 
                    WHERE s.subscriber_id = ? 
                    {$order_by_clause}
                ");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result && $result->num_rows > 0): ?>
                    <div class="channel-list">
                        <?php while ($channel = $result->fetch_assoc()): ?>
                            <div class="channel-card" data-channel-id="<?= htmlspecialchars($channel['public_user_id']) ?>">
                                <a href="/profile.php?id=<?= htmlspecialchars($channel['public_user_id']) ?>" class="channel-main-link">
                                    <img class="channel-avatar" src="/<?= htmlspecialchars($channel['avatar_url']) ?>" alt="Аватар">
                                    <div class="channel-info">
                                        <h3 class="channel-name"><?= htmlspecialchars($channel['channel_name'] ?? $channel['username']) ?></h3>
                                        <p class="subscribers-count"><?= number_format($channel['subscriber_count'], 0, '.', ' ') ?> <?= get_plural_form($channel['subscriber_count'], ['подписчик', 'подписчика', 'подписчиков']) ?></p>
                                    </div>
                                </a>
                                <div class="channel-item-menu-container">
                                    <button class="channel-item-menu-button"><img src="/images/menu-history.png" alt="Меню"></button>
                                    <div class="channel-item-menu">
                                        <a href="#" data-action="confirm-unsubscribe"><img src="/images/unsubscribe1.png" alt="Отписаться"><span>Отписаться</span></a>
                                        <a href="#" data-action="share-channel"><img src="/images/share.png" alt="Поделиться"><span>Поделиться</span></a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-page-placeholder">
                        <h2>Здесь будут ваши подписки</h2>
                        <p>Подпишитесь на каналы, чтобы их видео появлялись в вашей ленте.</p>
                    </div>
                <?php endif; ?>

            <?php else: // $view_mode === 'videos'
                $order_by_clause = ($sort_order === 'popularity') ? "ORDER BY v.views_count DESC" : "ORDER BY v.upload_date DESC";
                
                $hidden_videos_placeholder = '';
                if (!empty($hidden_video_ids)) {
                    $placeholders = implode(',', array_fill(0, count($hidden_video_ids), '?'));
                    $hidden_videos_placeholder = " AND v.video_id NOT IN ($placeholders)";
                }
                
                $query = "
                    SELECT 
                        v.video_id, v.public_video_id, v.user_id, v.title, v.thumbnail_url, v.views_count, v.upload_date, v.duration_seconds, v.description, 
                        u.public_user_id, u.channel_name, u.username, u.avatar_url 
                    FROM videos v 
                    JOIN users u ON v.user_id = u.user_id 
                    WHERE v.status = 'published' 
                      AND v.user_id IN (SELECT channel_id FROM subscriptions WHERE subscriber_id = ?) 
                      {$hidden_videos_placeholder} 
                    {$order_by_clause} 
                    LIMIT 100
                ";
                
                $stmt = $conn->prepare($query);

                $types = 'i';
                $params = [$user_id];
                if (!empty($hidden_video_ids)) {
                    $types .= str_repeat('i', count($hidden_video_ids));
                    $params = array_merge($params, $hidden_video_ids);
                }
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $videos_by_channel = [];
                while ($video = $result->fetch_assoc()) {
                    $videos_by_channel[$video['user_id']]['info'] = [
                        'name' => $video['channel_name'] ?? $video['username'], 
                        'avatar' => $video['avatar_url'], 
                        'public_id' => $video['public_user_id']
                    ];
                    $videos_by_channel[$video['user_id']]['videos'][] = $video;
                }

                if (!empty($videos_by_channel)): ?>
                    <div class="video-feed">
                        <?php foreach ($videos_by_channel as $channel_id => $data): ?>
                            <div class="video-group">
                                <hr class="video-group-divider">
                                <div class="video-group-header">
                                    <a href="/profile.php?id=<?= htmlspecialchars($data['info']['public_id']) ?>" class="channel-link">
                                        <img src="/<?= htmlspecialchars($data['info']['avatar']) ?>" alt="" class="channel-avatar-small">
                                        <h3 class="channel-name-large"><?= htmlspecialchars($data['info']['name']) ?></h3>
                                    </a>
                                </div>
                                <div class="video-list">
                                    <?php foreach ($data['videos'] as $video): ?>
                                        <div class="video-list-item" data-video-id="<?= htmlspecialchars($video['public_video_id']) ?>">
                                            <a href="/watch.php?id=<?= htmlspecialchars($video['public_video_id']) ?>" class="video-main-link">
                                                <div class="video-thumbnail-wrapper">
                                                    <img class="video-thumbnail" src="/<?= htmlspecialchars($video['thumbnail_url']) ?>" alt="Превью">
                                                    <div class="video-duration"><?= format_duration($video['duration_seconds']) ?></div>
                                                </div>
                                                <div class="video-details">
                                                    <h3 class="video-title"><?= htmlspecialchars($video['title']) ?></h3>
                                                    <div class="video-meta">
                                                        <span><?= number_format($video['views_count'], 0, '.', ' ') ?> <?= get_plural_form($video['views_count'], ['просмотр', 'просмотра', 'просмотров']) ?></span>
                                                        <span>•</span>
                                                        <span><?= format_time_ago($video['upload_date']) ?></span>
                                                    </div>
                                                    <p class="video-description">
                                                        <?= htmlspecialchars(mb_substr($video['description'], 0, 100)) . (mb_strlen($video['description']) > 100 ? '...' : '') ?>
                                                    </p>
                                                </div>
                                            </a>
                                            <div class="video-item-menu-container">
                                                <button class="video-item-menu-button"><img src="/images/menu-history.png" alt="Меню"></button>
                                                <div class="video-item-menu">
                                                    <?php
                                                    $is_in_watch_later = in_array((int)$video['video_id'], $watch_later_ids, true);
                                                    $watch_later_text = $is_in_watch_later ? "Удалить из 'Смотреть позже'" : "Смотреть позже";
                                                    ?>
                                                    <a href="#" data-action="toggle-watch-later"><img src="/images/watch_later.png" alt="Смотреть позже"><span><?= $watch_later_text ?></span></a>
                                                    <a href="#" data-action="add-to-playlist"><img src="/images/playlist.png" alt="Плейлист"><span>Добавить в плейлист</span></a>
                                                    <a href="#" data-action="share-video"><img src="/images/share.png" alt="Поделиться"><span>Поделиться</span></a>
                                                    <a href="#" data-action="toggle-hide"><img src="/images/hide.png" alt="Скрыть"><span>Скрыть</span></a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-page-placeholder">
                        <h2>Нет новых видео</h2>
                        <p>Как только каналы, на которые вы подписаны, загрузят новые видео, они появятся здесь.</p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
</div> 

<script>
    const userPlaylists = <?= json_encode($user_playlists ?? []) ?>;
</script>

<?php require_once __DIR__ . '/../../templates/partials/global_modals.php'; ?>

<script src="/js/script.js"></script>
<script src="/js/modal_manager.js"></script> 
<script src="/js/subscriptions.js"></script>
</body>
</html>