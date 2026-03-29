<?php

require_once __DIR__ . '/../src/core/config.php';

$public_video_id = trim($_GET['id'] ?? '');

if (empty($public_video_id)) {
    header("Location: /");
    exit;
}

$current_user_id = $_SESSION['user_id'] ?? null;

// Получение основной информации о видео и авторе
$stmt = $conn->prepare("
    SELECT 
        v.video_id, v.public_video_id, v.user_id, v.title, v.description, v.file_path, v.thumbnail_url, 
        v.visibility, v.status, v.allow_comments, v.duration_seconds, v.views_count, v.upload_date,
        u.public_user_id, u.username, u.channel_name, u.avatar_url, u.subscriber_count 
    FROM videos v 
    JOIN users u ON v.user_id = u.user_id 
    WHERE v.public_video_id = ?
");
$stmt->bind_param("s", $public_video_id);
$stmt->execute();
$video = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$video) {
    header("Location: /404.php?error=file");
    exit; 
}

if (in_array($video['status'], ['deleted', 'blocked'], true)) {
    header("Location: /404.php?error=video");
    exit;
}

if ($video['status'] !== 'published' && $current_user_id !== (int)$video['user_id']) {
    header("Location: /404.php?error=video");
    exit;
}

$video_id = (int)$video['video_id'];
$channel_id = (int)$video['user_id'];

// Загрузка рекомендаций
// TODO: Для production-среды заменить ORDER BY RAND() на более производительное решение (например, кэширование или Elasticsearch)
$stmt_rec = $conn->prepare("
    SELECT v.public_video_id, v.title, v.thumbnail_url, v.views_count, v.upload_date, v.duration_seconds, u.channel_name, u.username, u.public_user_id
    FROM videos v 
    JOIN users u ON v.user_id = u.user_id 
    WHERE v.video_id != ? AND v.visibility = 'public' AND v.status = 'published'
    ORDER BY RAND() 
    LIMIT 12
");
$stmt_rec->bind_param("i", $video_id); 
$stmt_rec->execute();
$recommended_videos = $stmt_rec->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_rec->close();

$next_video = $recommended_videos[0] ?? null;

// Учет просмотров
// TODO: При высоких нагрузках вынести счетчик просмотров в Redis/Memcached с отложенной синхронизацией в БД
$update_stmt = $conn->prepare("UPDATE videos SET views_count = views_count + 1 WHERE video_id = ?");
$update_stmt->bind_param("i", $video_id);
$update_stmt->execute();
$update_stmt->close();

// Запись в историю просмотра
if ($current_user_id && ((int)($_SESSION['history_enabled'] ?? 1) === 1)) {
    $history_stmt = $conn->prepare("REPLACE INTO history (user_id, video_id) VALUES (?, ?)");
    $history_stmt->bind_param("ii", $current_user_id, $video_id);
    $history_stmt->execute();
    $history_stmt->close();
}

// Проверка статуса подписки
$is_subscribed = false;
if ($current_user_id) {
    $sub_stmt = $conn->prepare("SELECT 1 FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
    $sub_stmt->bind_param("ii", $current_user_id, $channel_id);
    $sub_stmt->execute();
    $is_subscribed = $sub_stmt->get_result()->num_rows > 0;
    $sub_stmt->close();
}

// Подсчет комментариев
$comments_stmt = $conn->prepare("SELECT COUNT(*) FROM comments WHERE video_id = ?");
$comments_stmt->bind_param("i", $video_id);
$comments_stmt->execute();
$comments_count = (int)($comments_stmt->get_result()->fetch_row()[0] ?? 0);
$comments_stmt->close();

// Статистика лайков
$likes_data = ['likes' => 0, 'dislikes' => 0, 'user_like_type' => 0];
$likes_stmt = $conn->prepare("
    SELECT 
        SUM(like_type = 1) AS likes, 
        SUM(like_type = -1) AS dislikes,
        COALESCE(MAX(CASE WHEN user_id = ? THEN like_type ELSE 0 END), 0) AS user_like_type
    FROM likes 
    WHERE video_id = ?
");
$likes_stmt->bind_param("ii", $current_user_id, $video_id);
$likes_stmt->execute();
if ($likes_result = $likes_stmt->get_result()->fetch_assoc()) {
    $likes_data = $likes_result;
}
$likes_stmt->close();

// Пользовательские плейлисты
$user_playlists = [];
if ($current_user_id) {
    $pl_stmt = $conn->prepare("SELECT playlist_id, title FROM playlists WHERE user_id = ? ORDER BY title ASC");
    $pl_stmt->bind_param("i", $current_user_id);
    $pl_stmt->execute();
    $user_playlists = $pl_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pl_stmt->close();
}

// Логика отображения плейлиста в боковой панели
$playlist_videos = [];
$playlist_title = '';
$playlist_param = $_GET['list'] ?? $_GET['playlist'] ?? null; 

if ($playlist_param) { 
    $sql = '';
    $param_to_bind = null;
    $playlist_id_raw = str_starts_with($playlist_param, 'PL') ? substr($playlist_param, 2) : $playlist_param;
    $playlist_id = filter_var($playlist_id_raw, FILTER_VALIDATE_INT);
    $is_system_playlist = in_array($playlist_id_raw, ['liked', 'watch_later'], true);

    if ($playlist_id !== false && !$is_system_playlist) {
        $stmt_info = $conn->prepare("SELECT title, visibility, user_id FROM playlists WHERE playlist_id = ?");
        $stmt_info->bind_param("i", $playlist_id);
        $stmt_info->execute();
        $info = $stmt_info->get_result()->fetch_assoc();
        $stmt_info->close();
        
        if ($info && ($info['visibility'] === 'public' || $info['visibility'] === 'unlisted' || $current_user_id === (int)$info['user_id'])) {
            $playlist_title = $info['title'];
            $sql = "SELECT v.public_video_id, v.title, v.thumbnail_url, v.duration_seconds, u.channel_name, u.username 
                    FROM playlist_videos pv 
                    JOIN videos v ON pv.video_id = v.video_id 
                    JOIN users u ON v.user_id = u.user_id 
                    WHERE pv.playlist_id = ? 
                    AND v.status != 'deleted' 
                    ORDER BY pv.playlist_video_id ASC";
            $param_to_bind = $playlist_id;
        }

    } elseif ($is_system_playlist && $current_user_id) {
        if ($playlist_param === 'liked') {
            $playlist_title = 'Понравившиеся';
            $sql = "SELECT v.public_video_id, v.title, v.thumbnail_url, v.duration_seconds, u.channel_name, u.username 
                    FROM likes l 
                    JOIN videos v ON l.video_id = v.video_id 
                    JOIN users u ON v.user_id = u.user_id 
                    WHERE l.user_id = ? AND l.like_type = 1 AND v.status != 'deleted' 
                    ORDER BY l.created_at DESC";
            $param_to_bind = $current_user_id;
        } elseif ($playlist_param === 'watch_later') {
            $playlist_title = 'Смотреть позже';
            $sort_order = $_GET['sort'] ?? 'manual';
            $order_by_clause = ($sort_order === 'manual') ? "ORDER BY wl.watch_later_id ASC" : "ORDER BY wl.added_at DESC";
            $sql = "SELECT v.public_video_id, v.title, v.thumbnail_url, v.duration_seconds, u.channel_name, u.username 
                    FROM watch_later wl 
                    JOIN videos v ON wl.video_id = v.video_id 
                    JOIN users u ON v.user_id = u.user_id 
                    WHERE wl.user_id = ? AND v.status != 'deleted' 
                    {$order_by_clause}";
            $param_to_bind = $current_user_id;
        }
    }
    
    if (!empty($sql) && $param_to_bind !== null) {
        $playlist_stmt = $conn->prepare($sql);
        $playlist_stmt->bind_param('i', $param_to_bind);
        $playlist_stmt->execute();
        $playlist_videos = $playlist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $playlist_stmt->close();
    }
}

$current_user_avatar = $_SESSION['avatar_url'] ?? 'images/default_avatar.png';
$hide_category_filters = true; 
$additional_styles = ['/css/watch.css?v=1.5'];

require_once __DIR__ . '/../templates/partials/header.php';
require_once __DIR__ . '/../templates/partials/sidebar.php';
?>

<main class="main-content">
    <div class="watch-page-container">

        <!-- Основной контент видео -->
        <div class="video-main-content">
            
            <!-- Плеер -->
            <div class="video-container" data-volume-level="high">
                <video class="main-video" src="/<?= htmlspecialchars($video['file_path']) ?>" autoplay></video>
                
                <!-- Экран окончания (End screen) -->
                <div class="end-screen" style="display: none;">
                    <?php if ($next_video): ?>
                        <div class="end-screen-content">
                            <div class="up-next-section">
                                <span class="up-next-label">Следующее видео</span>
                                <h3 class="up-next-title"><?= htmlspecialchars($next_video['title']) ?></h3>
                                <p class="up-next-channel"><?= htmlspecialchars($next_video['channel_name'] ?? $next_video['username']) ?></p>
                                
                                <div class="autoplay-circle-container">
                                    <svg class="progress-ring" width="60" height="60">
                                        <circle class="progress-ring__circle" stroke="white" stroke-width="4" fill="transparent" r="26" cx="30" cy="30"/>
                                    </svg>
                                    <button class="cancel-autoplay-btn" id="cancel-autoplay">Отмена</button>
                                </div>
                                <a href="/watch.php?id=<?= $next_video['public_video_id'] ?>" class="play-next-btn" id="play-next-link">Запустить сейчас</a>
                            </div>
                            
                            <div class="end-screen-grid">
                                <?php 
                                $grid_videos = array_slice($recommended_videos, 1, 4);
                                foreach ($grid_videos as $gv): ?>
                                    <a href="/watch.php?id=<?= $gv['public_video_id'] ?>" class="end-card">
                                        <div class="end-card-thumb">
                                            <img src="/<?= htmlspecialchars($gv['thumbnail_url']) ?>" alt="">
                                            <span class="end-card-duration"><?= format_duration($gv['duration_seconds']) ?></span>
                                        </div>
                                        <div class="end-card-info">
                                            <div class="end-card-title"><?= htmlspecialchars($gv['title']) ?></div>
                                            <div class="end-card-author"><?= htmlspecialchars($gv['channel_name'] ?? $gv['username']) ?></div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Настройки плеера -->
                <div class="settings-menu-container">
                    <div class="settings-menu main-menu">
                        <div class="menu-item" data-opens="subtitles"><img src="/images/tr1.png" alt=""><span class="label">Субтитры</span><span class="value" id="subtitles-value">Выкл. &gt;</span></div>
                        <div class="menu-item" data-opens="speed"><img src="/images/fast.png" alt=""><span class="label">Скорость</span><span class="value" id="speed-value">Обычная &gt;</span></div>
                        <div class="menu-item" data-opens="quality"><img src="/images/filter.png" alt=""><span class="label">Качество</span><span class="value" id="quality-value">Авто &gt;</span></div>
                        <div class="menu-item" id="pip-menu-item"><img src="/images/pip.png" alt=""><span class="label">Картинка в картинке</span></div>
                    </div>
                    <div class="settings-menu submenu" id="subtitles-menu"><div class="submenu-header"><button class="back-btn">&lt;</button><span>Субтитры</span></div><div class="menu-option active" data-value="off">✓ Выкл.</div></div>
                    <div class="settings-menu submenu" id="speed-menu"><div class="submenu-header"><button class="back-btn">&lt;</button><span>Скорость</span></div><div class="menu-option" data-value="0.5">0.5</div><div class="menu-option" data-value="0.75">0.75</div><div class="menu-option active" data-value="1">✓ Обычная</div><div class="menu-option" data-value="1.25">1.25</div><div class="menu-option" data-value="1.5">1.5</div><div class="menu-option" data-value="2">2</div></div>
                    <div class="settings-menu submenu" id="quality-menu"><div class="submenu-header"><button class="back-btn">&lt;</button><span>Качество</span></div><div class="menu-option active" data-value="auto">✓ Авто</div><div class="menu-option" data-value="1080">1080p</div><div class="menu-option" data-value="720">720p</div><div class="menu-option" data-value="480">480p</div></div>
                </div>

                <div class="video-controls-container">
                    <div class="timeline-container">
                        <div class="timeline">
                            <div class="progress-bar"></div>
                            <div class="thumb-indicator"></div>
                        </div>
                    </div>
                    <div class="controls">
                        <div class="controls-left">
                            <button class="play-pause-btn"><img src="/images/play1.png" alt="Play/Pause"></button>
                            <button class="next-btn"><img src="/images/skipvideo.png" alt="Next"></button>
                            <div class="volume-container">
                                <button class="mute-btn"><img src="/images/soundvideo.png" alt="Volume"></button>
                                <input class="volume-slider" type="range" min="0" max="1" step="any" value="1">
                            </div>
                            <div class="time-container"><span class="current-time">0:00</span> / <span class="total-time">0:00</span></div>
                        </div>
                        <div class="controls-right">
                            <button class="settings-btn"><img src="/images/set1.png" alt="Settings"></button>
                            <button class="fullscreen-btn"><img src="/images/ewindow.png" alt="Fullscreen"></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Информация о видео -->
            <h1 class="video-title-watch"><?= htmlspecialchars($video['title']) ?></h1>
            <div class="video-actions-bar">
                <div class="channel-info">
                    <a href="/profile.php?id=<?= htmlspecialchars($video['public_user_id']) ?>">
                        <img src="/<?= htmlspecialchars($video['avatar_url'] ?? 'images/default_avatar.png') ?>" alt="Аватар канала" class="channel-avatar-watch">
                    </a>
                    <div class="channel-name-watch">
                        <a href="/profile.php?id=<?= htmlspecialchars($video['public_user_id']) ?>">
                            <span><?= htmlspecialchars($video['channel_name'] ?? $video['username']) ?></span>
                        </a>
                        <span class="subscribers-count" id="sub-count"><?= htmlspecialchars($video['subscriber_count']) ?> подписчиков</span>
                    </div>
                </div>
                <div class="action-buttons">
                    <?php if ($current_user_id && $current_user_id !== $channel_id): ?>
                        <button 
                            class="subscribe-button <?= $is_subscribed ? 'subscribed' : '' ?>" 
                            id="subscribe-btn" 
                            data-action="toggle-subscription"
                            data-channel-id="<?= htmlspecialchars($video['public_user_id']) ?>">
                            <?= $is_subscribed ? 'Вы подписаны' : 'Подписаться' ?>
                        </button>
                    <?php elseif (!$current_user_id): ?>
                        <a href="/login.php" class="subscribe-button">Подписаться</a>
                    <?php endif; ?>

                    <div class="like-dislike-buttons">
                        <button class="like-button <?= ($likes_data['user_like_type'] == 1) ? 'active' : '' ?>" data-action="toggle-like" data-type="like" data-video="<?= htmlspecialchars($video['public_video_id']) ?>">
                            👍 <span id="like-count"><?= (int)$likes_data['likes'] ?></span>
                        </button>
                        <button class="dislike-button <?= ($likes_data['user_like_type'] == -1) ? 'active' : '' ?>" data-action="toggle-like" data-type="dislike" data-video="<?= htmlspecialchars($video['public_video_id']) ?>">
                            👎 <span id="dislike-count"><?= (int)$likes_data['dislikes'] ?></span>
                        </button>
                    </div>

                    <button class="action-btn" data-action="share-video">
                        <img src="/images/share.png" alt="Поделиться">
                        <span>Поделиться</span>
                    </button>

                    <div class="more-actions-container">
                        <button class="action-btn more-actions-btn">
                            <img src="/images/menu-history.png" alt="Еще">
                        </button>
                        <div class="more-actions-dropdown">
                            <a href="#" data-action="add-to-playlist">
                                <img src="/images/playlist.png" alt="Плейлист">
                                <span>Добавить в плейлист</span>
                            </a>
                            <a href="#" data-action="report">
                                <img src="/images/complaint.png" alt="Жалоба">
                                <span>Пожаловаться</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="video-description-box">
                <div class="video-meta-info">
                    <span><?= number_format($video['views_count']) ?> просмотров</span>
                    <span>•</span>
                    <span><?= format_time_ago($video['upload_date']) ?></span>
                </div>
                
                <div class="description-content collapsed" id="video-description">
                    <?php if (!empty($video['description'])): ?>
                        <p class="video-text-description"><?= nl2br(htmlspecialchars($video['description'])) ?></p>
                    <?php endif; ?>
                </div>
                
                <button id="toggle-description-btn" class="toggle-desc-btn">...ещё</button>
            </div>

            <!-- Секция комментариев -->
            <div class="comments-section" id="comments-section" data-video-id="<?= htmlspecialchars($video['public_video_id']) ?>">
                <?php if ((int)$video['allow_comments'] === 1): ?>
                    
                    <?php if ($current_user_id): ?>
                        <div class="comments-header">
                            <h3 class="comments-header-title"><span id="comments-count"><?= $comments_count ?></span> комментариев</h3>
                            <div class="sort-controls">
                                <button class="sort-btn active" data-sort="popular">Сначала популярные</button>
                                <button class="sort-btn" data-sort="newest">Сначала новые</button>
                            </div>
                        </div>
                        
                        <div class="comment-form-container">
                            <img src="/<?= htmlspecialchars($current_user_avatar) ?>" alt="Ваш аватар" class="comment-form-avatar">
                            <form id="comment-form" class="comment-form">
                                <div class="comment-input-wrapper">
                                    <textarea name="text" id="comment-text-input" placeholder="Оставьте комментарий..." required></textarea>
                                </div>
                                <div class="comment-form-actions">
                                    <button type="button" class="comment-action-btn cancel" id="cancel-comment-btn">Отмена</button>
                                    <button type="submit" class="comment-action-btn submit" id="submit-comment-btn" disabled>Отправить</button>
                                </div>
                            </form>
                        </div>

                        <div class="comments-list" id="comments-list"></div>

                    <?php else: ?>
                        <!-- Заглушка для неавторизованных пользователей -->
                        <div class="comments-guest-stub">
                            <div class="guest-stub-icon">
                                <svg viewBox="0 0 24 24"><path d="M12 17c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm6-9h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zM8.9 6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2H8.9V6zM18 20H6V10h12v10z"/></svg>
                            </div>
                            <h3 class="guest-stub-title">Хотите почитать комментарии?</h3>
                            <p class="guest-stub-text">Войдите в аккаунт, чтобы просматривать комментарии, оставлять свои и участвовать в обсуждениях.</p>
                            <a href="/login.php?redirect=watch&id=<?= htmlspecialchars($video['public_video_id']) ?>" class="guest-stub-button">Войти</a>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="comments-disabled-message"><p>Комментарии к этому видео отключены автором.</p></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Правая колонка: Рекомендации и Плейлисты -->
        <aside class="recommendations-sidebar">

            <?php if (!empty($playlist_videos)): ?>
                <div class="playlist-panel">
                    <div class="playlist-panel-header">
                        <h3><?= htmlspecialchars($playlist_title) ?></h3>
                        <div class="playlist-meta">
                            <span>
                                <?php 
                                $current_index = array_search($public_video_id, array_column($playlist_videos, 'public_video_id'));
                                echo ($current_index !== false) ? ($current_index + 1) . ' / ' . count($playlist_videos) : ''; 
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="playlist-controls">
                        <button class="playlist-control-btn playlist-shuffle-btn" title="Перемешать"><img src="/images/shuffle.png" alt="Перемешать"></button>
                        <button class="playlist-control-btn playlist-loop-btn" title="Повтор"><img src="/images/repeat.png" alt="Повтор"></button>
                    </div>
                    <div class="playlist-panel-videos">
                        <?php foreach ($playlist_videos as $index => $p_video): 
                            $is_current = ($p_video['public_video_id'] === $public_video_id);
                            $list_param_value = htmlspecialchars($playlist_param);
                            $sort_param = isset($_GET['sort']) ? '&sort=' . htmlspecialchars($_GET['sort']) : ''; 
                        ?>
                            <a href="/watch.php?id=<?= $p_video['public_video_id'] ?>&list=<?= $list_param_value ?><?= $sort_param ?>" 
                               class="playlist-video-item <?= $is_current ? 'active' : '' ?>" 
                               data-video-id="<?= $p_video['public_video_id'] ?>">
                                
                                <span class="playlist-video-index"><?= $index + 1 ?></span>
                                <div class="thumbnail-wrapper">
                                    <img src="/<?= htmlspecialchars($p_video['thumbnail_url']) ?>" alt="thumbnail" class="playlist-video-thumbnail">
                                    <span class="thumbnail-duration"><?= format_duration($p_video['duration_seconds']) ?></span>
                                </div>

                                <div class="playlist-video-details">
                                    <h4 class="playlist-video-title"><?= htmlspecialchars($p_video['title']) ?></h4>
                                    <p class="playlist-video-channel"><?= htmlspecialchars($p_video['channel_name'] ?? $p_video['username']) ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php foreach ($recommended_videos as $rec): ?>
                <div class="recommendation-card video-card" data-video-id="<?= htmlspecialchars($rec['public_video_id']) ?>">
                    <a class="recommendation-card-link" href="/watch.php?id=<?= htmlspecialchars($rec['public_video_id']) ?>">
                        <div class="thumbnail-wrapper">
                            <img class="recommendation-thumbnail" src="/<?= htmlspecialchars($rec['thumbnail_url']) ?>" alt="Thumbnail">
                            <span class="thumbnail-duration"><?= format_duration($rec['duration_seconds']) ?></span>
                        </div>
                        
                        <div class="recommendation-details">
                            <h4 class="recommendation-title"><?= htmlspecialchars($rec['title']) ?></h4>
                            <div class="recommendation-meta">
                                <p class="recommendation-channel"><?= htmlspecialchars($rec['channel_name'] ?? $rec['username']) ?></p>
                                <div class="recommendation-stats">
                                    <span><?= number_format($rec['views_count']) ?> просмотров</span>
                                    <span>•</span>
                                    <span><?= format_time_ago($rec['upload_date']) ?></span>
                                </div>
                            </div>
                        </div>
                    </a>
                    <div class="context-menu-container">
                        <button class="context-menu-button">
                            <img src="/images/menu-history.png" alt="Меню">
                        </button>
                        <div class="context-menu-dropdown">
                            <a href="#" data-action="toggle-watch-later">
                                <img src="/images/watch_later.png" alt="">
                                <span>Смотреть позже</span>
                            </a>
                            <a href="#" data-action="add-to-playlist">
                                <img src="/images/playlist.png" alt="">
                                <span>Добавить в плейлист</span>
                            </a>
                            <a href="#" data-action="share-video">
                                <img src="/images/share.png" alt="">
                                <span>Поделиться</span>
                            </a>
                            <a href="#" data-action="report">
                                <img src="/images/complaint.png" alt="">
                                <span>Пожаловаться</span>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </aside>

    </div>
</main>

<!-- Модальные окна -->
<div class="share-modal-overlay" id="share-modal">
    <div class="share-modal-content">
        <div class="share-modal-header">
            <h3 class="share-modal-title">Поделиться</h3>
            <button class="share-modal-close" data-action="close-modal">&times;</button>
        </div>
        <div class="share-social-links">
            <a href="#" class="social-link-item" data-action="share-social" data-network="vk"><img src="/images/vk.png" alt="ВКонтакте" class="social-icon"><span class="social-name">ВКонтакте</span></a>
            <a href="#" class="social-link-item" data-action="share-social" data-network="telegram"><img src="/images/telegram.png" alt="Telegram" class="social-icon"><span class="social-name">Telegram</span></a>
            <a href="#" class="social-link-item" data-action="share-social" data-network="twitter"><img src="/images/twitter.png" alt="Twitter" class="social-icon"><span class="social-name">Twitter</span></a>
        </div>
        <div class="share-link-container">
            <input type="text" class="share-link-input" readonly>
            <button class="share-copy-button" data-action="copy-share-link">Копировать</button>
        </div>
    </div>
</div>

<div class="add-to-playlist-modal-overlay" id="add-to-playlist-modal">
    <div class="add-to-playlist-modal-content">
        <div class="add-to-playlist-modal-header">
            <h3 class="add-to-playlist-modal-title">Добавить в плейлист</h3>
            <button class="add-to-playlist-modal-close" data-action="close-playlist-modal">&times;</button>
        </div>
        <div class="playlist-selection-list"></div>
        <div class="create-new-playlist-section">
            <button class="create-new-playlist-link" data-action="open-create-playlist-modal"><img src="/images/upload.png" alt="+"><span>Создать плейлист</span></button>
        </div>
    </div>
</div>

<div class="modal-overlay-small" id="create-playlist-fly-modal">
    <div class="modal-content-small">
        <h3>Новый плейлист</h3>
        <form id="create-playlist-fly-form" novalidate>
            <div class="form-group-small input-wrapper-small">
                <input type="text" name="title" placeholder="Укажите название" required maxlength="100">
                <div class="input-error-message-small"></div>
            </div>
            <div class="form-group-small">
                <label>Доступ</label>
                <div class="custom-select-container" id="visibility-select-container">
                    <input type="hidden" name="visibility" value="private">
                    <button type="button" class="custom-select-button">
                        <span class="custom-select-value">Ограниченный доступ</span>
                        <img src="/images/arrow-down.png" alt="v" class="custom-select-arrow">
                    </button>
                    <div class="custom-select-dropdown">
                        <div class="custom-select-option" data-value="private">Ограниченный доступ</div>
                        <div class="custom-select-option" data-value="public">Открытый доступ</div>
                        <div class="custom-select-option" data-value="unlisted">Доступ по ссылке</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer-small">
                <button type="button" class="modal-button-small cancel" data-action="close-create-fly-modal">Отмена</button>
                <button type="submit" class="modal-button-small create">Создать</button>
            </div>
        </form>
    </div>
</div>

<div class="report-modal-overlay" id="report-modal">
    <div class="report-modal-content">
        <div class="report-modal-header"><h2 class="report-modal-title">Пожаловаться на видео</h2></div>
        <form id="report-form">
            <div class="report-modal-body">
                <input type="hidden" name="video_id" id="report-video-id">
                <div class="report-option"><input type="radio" id="reason-sex" name="reason" value="Содержание сексуального характера" required><label for="reason-sex">Содержание сексуального характера</label></div>
                <div class="report-option"><input type="radio" id="reason-violent" name="reason" value="Жестокое или отталкивающее содержание"><label for="reason-violent">Жестокое или отталкивающее содержание</label></div>
                <div class="report-option"><input type="radio" id="reason-hate" name="reason" value="Дискриминационные высказывания и оскорбления"><label for="reason-hate">Дискриминационные высказывания и оскорбления</label></div>
                <div class="report-option"><input type="radio" id="reason-harmful" name="reason" value="Вредные или опасные действия"><label for="reason-harmful">Вредные или опасные действия</label></div>
                <div class="report-option"><input type="radio" id="reason-spam" name="reason" value="Спам"><label for="reason-spam">Спам</label></div>
                <div class="report-option"><input type="radio" id="reason-child" name="reason" value="Жестокое обращение с детьми"><label for="reason-child">Жестокое обращение с детьми</label></div>
                <div class="report-option"><input type="radio" id="reason-other-radio" name="reason" value="Другое"><label for="reason-other-radio">Другое</label></div>
                <div class="other-reason-container" id="other-reason-box">
                    <textarea id="other-reason-details" name="details" maxlength="300" placeholder="Опишите нарушение (необязательно)"></textarea>
                    <div class="char-counter"><span id="char-count">0</span> / 300</div>
                </div>
            </div>
            <div class="report-modal-footer">
                <button type="button" class="report-button cancel" data-action="close-report-modal">Отмена</button>
                <button type="submit" class="report-button submit" disabled>Пожаловаться</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay-small" id="login-required-modal">
    <div class="modal-content-small login-modal-content">
        <button class="modal-close-button" data-action="close-modal">&times;</button>
        <h3>Требуется вход в аккаунт</h3>
        <p class="login-modal-text">Чтобы воспользоваться этой функцией, пожалуйста, войдите в свой аккаунт или зарегистрируйтесь.</p>
        <div class="login-modal-actions">
            <a href="/register.php" class="modal-button-small secondary">Регистрация</a>
            <a href="/login.php" class="modal-button-small create">Войти</a>
        </div>
    </div>
</div>

<div class="report-modal-overlay" id="report-comment-modal">
    <div class="report-modal-content">
        <div class="report-modal-header">
            <h2 class="report-modal-title">Пожаловаться на комментарий</h2>
        </div>
        <form id="report-comment-form">
            <div class="report-modal-body">
                <input type="hidden" name="comment_id" id="report-comment-id">
                <div class="report-option">
                    <input type="radio" id="rc-spam" name="reason" value="Спам или реклама" required>
                    <label for="rc-spam">Спам или реклама</label>
                </div>
                <div class="report-option">
                    <input type="radio" id="rc-hate" name="reason" value="Оскорбления или угрозы">
                    <label for="rc-hate">Оскорбления или угрозы</label>
                </div>
                <div class="report-option">
                    <input type="radio" id="rc-sex" name="reason" value="Материалы сексуального характера">
                    <label for="rc-sex">Материалы сексуального характера</label>
                </div>
                <div class="report-option">
                    <input type="radio" id="rc-personal" name="reason" value="Личные данные">
                    <label for="rc-personal">Личные данные</label>
                </div>
                <div class="report-option">
                    <input type="radio" id="rc-other" name="reason" value="Другое">
                    <label for="rc-other">Другое</label>
                </div>
            </div>
            <div class="report-modal-footer">
                <button type="button" class="report-button cancel" data-action="close-report-comment-modal">Отмена</button>
                <button type="submit" class="report-button submit" disabled>Пожаловаться</button>
            </div>
        </form>
    </div>
</div>

<script>
    const userPlaylists = <?= json_encode($user_playlists ?? []) ?>;
</script>
<script src="/js/watch.js?v=1.5"></script> 
<script src="/js/script.js?v=1.3"></script> 

</body>
</html>