<?php

$additional_styles = ['/css/profile.css'];

require_once __DIR__ . '/../src/core/config.php';
require_once __DIR__ . '/../src/core/helpers.php';
require_once __DIR__ . '/../templates/partials/header.php';
require_once __DIR__ . '/../templates/partials/sidebar.php';

$public_profile_id = trim($_GET['id'] ?? '');

if (empty($public_profile_id)) {
    http_response_code(404);
    exit('Пользователь не найден.');
}

$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

// Загрузка основной информации профиля
$stmt = $conn->prepare("
    SELECT 
        user_id, public_user_id, username, channel_name, about_text, 
        avatar_url, banner_image_url, subscriber_count, business_email 
    FROM users 
    WHERE public_user_id = ? AND status = 'active'
");
$stmt->bind_param("s", $public_profile_id);
$stmt->execute();
$profile_user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile_user) {
    http_response_code(404);
    exit('Пользователь не найден или заблокирован.');
}

$profile_id = (int)$profile_user['user_id'];
$is_owner = ($current_user_id === $profile_id);

// Проверка подписки
$is_subscribed = false;
if ($current_user_id && !$is_owner) {
    $sub_stmt = $conn->prepare("SELECT 1 FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
    $sub_stmt->bind_param("ii", $current_user_id, $profile_id);
    $sub_stmt->execute();
    $is_subscribed = $sub_stmt->get_result()->num_rows > 0;
    $sub_stmt->close();
}

// Количество опубликованных видео
$video_count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM videos WHERE user_id = ? AND status = 'published' AND visibility = 'public'");
$video_count_stmt->bind_param("i", $profile_id);
$video_count_stmt->execute();
$video_count = (int)$video_count_stmt->get_result()->fetch_assoc()['count'];
$video_count_stmt->close();

$tabs = ['home', 'videos', 'playlists', 'about'];
$active_tab = (isset($_GET['tab']) && in_array($_GET['tab'], $tabs, true)) ? $_GET['tab'] : 'home';

$videos_data = [];
$playlists_data = [];
$recommendation_data = [];
$user_links = [];

// Обработка логики вкладок
switch ($active_tab) {
    case 'home':
        // Последние видео
        $stmt_videos = $conn->prepare("
            SELECT public_video_id, title, thumbnail_url, duration_seconds, views_count, upload_date 
            FROM videos 
            WHERE user_id = ? AND status = 'published' AND visibility = 'public' 
            ORDER BY upload_date DESC LIMIT 8
        ");
        $stmt_videos->bind_param("i", $profile_id);
        $stmt_videos->execute();
        $videos_data = $stmt_videos->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_videos->close();

        // Плейлисты (только публичные, если это не владелец)
        $stmt_playlists = $conn->prepare("
            SELECT 
                p.playlist_id, 
                p.title, 
                (SELECT v.thumbnail_url FROM playlist_videos pv JOIN videos v ON pv.video_id = v.video_id WHERE pv.playlist_id = p.playlist_id AND v.status = 'published' ORDER BY pv.playlist_video_id DESC LIMIT 1) as thumbnail, 
                (SELECT COUNT(v_count.video_id) FROM playlist_videos pv_count JOIN videos v_count ON pv_count.video_id = v_count.video_id WHERE pv_count.playlist_id = p.playlist_id AND v_count.status != 'deleted') as video_count,
                (SELECT v2.public_video_id FROM playlist_videos pv2 JOIN videos v2 ON pv2.video_id = v2.video_id WHERE pv2.playlist_id = p.playlist_id AND v2.status = 'published' ORDER BY pv2.playlist_video_id ASC LIMIT 1) as first_video_id 
            FROM playlists p 
            WHERE p.user_id = ? AND p.visibility = 'public' 
            ORDER BY p.updated_at DESC LIMIT 4
        ");
        $stmt_playlists->bind_param("i", $profile_id);
        $stmt_playlists->execute();
        $playlists_data = $stmt_playlists->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_playlists->close();

        // Рекомендованные каналы (секции)
        $stmt_sections = $conn->prepare("SELECT section_id, title FROM recommendation_sections WHERE user_id = ? ORDER BY section_id ASC");
        $stmt_sections->bind_param("i", $profile_id);
        $stmt_sections->execute();
        $sections_result = $stmt_sections->get_result();
        
        while ($section = $sections_result->fetch_assoc()) {
            $stmt_channels = $conn->prepare("
                SELECT u.public_user_id, u.username, u.channel_name, u.avatar_url 
                FROM recommended_channels rc 
                JOIN users u ON rc.recommended_channel_id = u.user_id 
                WHERE rc.section_id = ? LIMIT 10
            ");
            $stmt_channels->bind_param("i", $section['section_id']);
            $stmt_channels->execute();
            $channels = $stmt_channels->get_result()->fetch_all(MYSQLI_ASSOC);
            
            if (!empty($channels)) {
                $recommendation_data[] = ['title' => $section['title'], 'channels' => $channels];
            }
            $stmt_channels->close();
        }
        $stmt_sections->close();
        break;

    case 'videos':
        $stmt = $conn->prepare("
            SELECT public_video_id, title, thumbnail_url, duration_seconds, views_count, upload_date 
            FROM videos 
            WHERE user_id = ? AND status = 'published' AND visibility = 'public' 
            ORDER BY upload_date DESC
        ");
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        $videos_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        break;

    case 'playlists':
        $visibility_condition = $is_owner ? "" : " AND p.visibility = 'public' ";
        $sql_playlists = "
            SELECT 
                p.playlist_id, p.title, p.visibility,
                COUNT(v_count.video_id) AS video_count,
                (SELECT v.thumbnail_url FROM playlist_videos pv_inner JOIN videos v ON v.video_id = pv_inner.video_id WHERE pv_inner.playlist_id = p.playlist_id AND v.status = 'published' ORDER BY pv_inner.playlist_video_id DESC LIMIT 1) AS thumbnail,
                (SELECT v2.public_video_id FROM playlist_videos pv2 JOIN videos v2 ON pv2.video_id = v2.video_id WHERE pv2.playlist_id = p.playlist_id AND v2.status = 'published' ORDER BY pv2.playlist_video_id ASC LIMIT 1) as first_video_id
            FROM playlists p
            LEFT JOIN playlist_videos pv ON p.playlist_id = pv.playlist_id
            LEFT JOIN videos v_count ON pv.video_id = v_count.video_id AND v_count.status != 'deleted' 
            WHERE p.user_id = ? {$visibility_condition}
            GROUP BY p.playlist_id 
            ORDER BY p.updated_at DESC
        ";
        $stmt_playlists = $conn->prepare($sql_playlists);
        $stmt_playlists->bind_param("i", $profile_id);
        $stmt_playlists->execute();
        $playlists_data = $stmt_playlists->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_playlists->close();
        break;

    case 'about':
        $stmt_links = $conn->prepare("SELECT link_title, link_url FROM user_links WHERE user_id = ? ORDER BY link_id ASC LIMIT 5");
        $stmt_links->bind_param("i", $profile_id);
        $stmt_links->execute();
        $raw_links = $stmt_links->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_links->close();
        
        $user_links = array_map(function($link) {
            $link['icon_path'] = '/images/' . get_social_icon_filename($link['link_title'], $link['link_url']);
            return $link;
        }, $raw_links);
        break;
}

// Загрузка статистики канала
// TODO: При высоких нагрузках вынести подсчет агрегатов (SUM/COUNT) в кэш или cron-задачу
$stats_stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(v.views_count), 0) as total_views, 
        (
            SELECT COUNT(l.like_id) 
            FROM likes l 
            JOIN videos v_likes ON v_likes.video_id = l.video_id 
            WHERE v_likes.user_id = u.user_id AND l.like_type = 1
        ) as total_likes,
        u.registration_date as join_date_sql 
    FROM users u
    LEFT JOIN videos v ON u.user_id = v.user_id AND v.status = 'published' AND v.visibility = 'public'
    WHERE u.user_id = ?
    GROUP BY u.user_id, u.registration_date
");
$stats_stmt->bind_param("i", $profile_id);
$stats_stmt->execute();
$aggregate_stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$total_views = (int)($aggregate_stats['total_views'] ?? 0);
$total_likes = (int)($aggregate_stats['total_likes'] ?? 0);
$join_date = !empty($aggregate_stats['join_date_sql']) ? format_date_russian($aggregate_stats['join_date_sql']) : 'Неизвестно';
$subscriber_count = (int)($profile_user['subscriber_count'] ?? 0);
?>

<main class="main-content">
    <div class="profile-page-container">
        <div class="profile-header-section">
            <div class="channel-banner" style="background-image: url('/<?= htmlspecialchars($profile_user['banner_image_url'] ?? 'images/default_banner.jpg') ?>');"></div>
            
            <div class="profile-header">
                <div class="profile-info">
                    <img src="/<?= htmlspecialchars($profile_user['avatar_url'] ?? 'images/default_avatar.png') ?>" alt="Аватар" class="profile-avatar">
                    <div class="profile-text">
                        <h1><?= htmlspecialchars($profile_user['channel_name'] ?? $profile_user['username']) ?></h1>
                        <div class="profile-meta">
                            <span>@<?= htmlspecialchars($profile_user['username']) ?></span>
                            <span id="subscriber-count-display"><?= number_format($subscriber_count, 0, '.', ' ') . ' ' . get_plural_form($subscriber_count, ['подписчик', 'подписчика', 'подписчиков']) ?></span>
                            <span><?= $video_count . ' ' . get_plural_form($video_count, ['видео', 'видео', 'видео']) ?></span>
                        </div>
                        <?php if (!empty($profile_user['about_text'])):
                            $description = $profile_user['about_text'];
                            $char_limit = 100;
                        ?>
                            <p class="profile-description">
                                <span><?= mb_strlen($description) > $char_limit ? htmlspecialchars(mb_substr($description, 0, $char_limit)) . '...' : htmlspecialchars($description) ?></span>
                                <a href="?id=<?= htmlspecialchars($public_profile_id) ?>&tab=about">ещё</a>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="profile-actions">
                    <?php if (!$is_owner): ?>
                        <?php if ($current_user_id): ?>
                            <button class="profile-button subscribe-button <?= $is_subscribed ? 'subscribed' : '' ?>" id="subscribe-btn" data-channel-id="<?= htmlspecialchars($profile_user['public_user_id']) ?>">
                                <?= $is_subscribed ? 'Вы подписаны' : 'Подписаться' ?>
                            </button>
                        <?php else: ?>
                            <a href="/login.php" class="profile-button subscribe-button">Подписаться</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="tabs-and-actions-container">
                <nav class="profile-tabs">
                    <a href="?id=<?= htmlspecialchars($public_profile_id) ?>&tab=home" class="<?= $active_tab === 'home' ? 'active' : '' ?>">Главная</a>
                    <a href="?id=<?= htmlspecialchars($public_profile_id) ?>&tab=videos" class="<?= $active_tab === 'videos' ? 'active' : '' ?>">Видео</a>
                    <a href="?id=<?= htmlspecialchars($public_profile_id) ?>&tab=playlists" class="<?= $active_tab === 'playlists' ? 'active' : '' ?>">Плейлисты</a>
                    <a href="?id=<?= htmlspecialchars($public_profile_id) ?>&tab=about" class="<?= $active_tab === 'about' ? 'active' : '' ?>">О канале</a>
                </nav>
                
                <?php if ($is_owner): ?>
                    <div class="tab-actions">
                        <a href="/edit_profile.php" class="profile-button secondary">Настроить вид канала</a>
                        <a href="/upload.php?from=profile&id=<?= htmlspecialchars($public_profile_id) ?>" id="create-video-btn" class="profile-button" style="display: none;">Создать видео</a>
                        <button id="delete-video-open-btn" class="profile-button secondary" style="display: none;">Удалить видео</button>
                        <button id="create-playlist-btn" class="profile-button" style="display: none;">Создать плейлист</button>
                        <button id="delete-playlist-open-btn" class="profile-button secondary" style="display: none;">Удалить плейлист</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-content">
            <?php if ($active_tab === 'home'): ?>
                <?php if (empty($videos_data) && empty($playlists_data) && empty($recommendation_data)): ?>
                    <p class="empty-message">На этом канале пока нет контента.</p>
                <?php else: ?>
                    
                    <?php if (!empty($videos_data)): ?>
                        <div class="content-shelf">
                            <h2 class="shelf-title">Последние видео</h2>
                            <div class="video-grid">
                                <?php foreach ($videos_data as $video): ?>
                                    <div class="video-card">
                                        <a href="/watch.php?id=<?= htmlspecialchars($video['public_video_id']) ?>">
                                            <div class="thumbnail-wrapper">
                                                <img src="/<?= htmlspecialchars($video['thumbnail_url']) ?>" class="thumbnail" alt="thumbnail">
                                                <div class="video-duration"><?= format_duration($video['duration_seconds']) ?></div>
                                            </div>
                                            <h3 class="video-title"><?= htmlspecialchars($video['title']) ?></h3>
                                            <p class="video-meta"><?= number_format($video['views_count'], 0, '.', ' ') ?> просмотров • <?= format_time_ago($video['upload_date']) ?></p>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($playlists_data)): ?>
                        <div class="content-shelf">
                            <h2 class="shelf-title">Плейлисты</h2>
                            <div class="playlist-grid">
                                <?php foreach ($playlists_data as $playlist): 
                                    if ((int)$playlist['video_count'] === 0) continue;
                                ?>
                                    <a href="/watch.php?id=<?= htmlspecialchars($playlist['first_video_id']) ?>&list=PL<?= htmlspecialchars($playlist['playlist_id']) ?>" 
                                       class="playlist-card-link"
                                       data-video-count="<?= $playlist['video_count'] ?>"
                                       data-first-video-id="<?= htmlspecialchars($playlist['first_video_id'] ?? '') ?>">
                                        <article class="playlist-card">
                                            <div class="playlist-thumbnail-container">
                                                <img src="/<?= htmlspecialchars($playlist['thumbnail'] ?? 'images/default.png') ?>" class="thumbnail" alt="playlist cover">
                                                <div class="playlist-overlay"><img src="/images/playlist.png" alt=""><span><?= $playlist['video_count'] ?> видео</span></div>
                                            </div>
                                            <div class="playlist-info"><h3><?= htmlspecialchars($playlist['title']) ?></h3></div>
                                        </article>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($recommendation_data)): ?>
                        <div class="content-shelf">
                            <?php foreach ($recommendation_data as $section): ?>
                                <h2 class="shelf-title"><?= htmlspecialchars($section['title']) ?></h2>
                                <div class="channel-grid">
                                    <?php foreach ($section['channels'] as $channel): ?>
                                        <a href="/profile.php?id=<?= htmlspecialchars($channel['public_user_id']) ?>" class="recommended-channel-card">
                                            <img src="/<?= htmlspecialchars($channel['avatar_url']) ?>" alt="Аватар">
                                            <h3><?= htmlspecialchars($channel['channel_name'] ?? $channel['username']) ?></h3>
                                            <p>@<?= htmlspecialchars($channel['username']) ?></p>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php elseif ($active_tab === 'videos'): ?>
                <?php if (empty($videos_data)): ?>
                    <p class="empty-message">Здесь пока нет видео.</p>
                <?php else: ?>
                    <div class="video-grid">
                        <?php foreach ($videos_data as $video): ?>
                             <div class="video-card" data-public-video-id="<?= htmlspecialchars($video['public_video_id']) ?>">
                                <a href="/watch.php?id=<?= htmlspecialchars($video['public_video_id']) ?>">
                                    <div class="thumbnail-wrapper">
                                        <img src="/<?= htmlspecialchars($video['thumbnail_url']) ?>" class="thumbnail" alt="thumbnail">
                                        <div class="video-duration"><?= format_duration($video['duration_seconds']) ?></div>
                                    </div>
                                    <h3 class="video-title"><?= htmlspecialchars($video['title']) ?></h3>
                                    <p class="video-meta"><?= number_format($video['views_count'], 0, '.', ' ') ?> просмотров • <?= format_time_ago($video['upload_date']) ?></p>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($active_tab === 'playlists'): ?>
                <?php if (empty($playlists_data)): ?>
                    <p class="empty-message">Здесь пока нет плейлистов.</p>
                <?php else: ?>
                    <div class="playlist-grid-profile">
                        <?php foreach ($playlists_data as $playlist): 
                             if ((int)$playlist['video_count'] === 0 && !$is_owner) continue;
                        ?>
                            <a href="/watch.php?id=<?= htmlspecialchars($playlist['first_video_id']) ?>&list=PL<?= htmlspecialchars($playlist['playlist_id']) ?>" 
                               class="playlist-card-link"
                               data-playlist-id="<?= htmlspecialchars($playlist['playlist_id']) ?>"
                               data-video-count="<?= $playlist['video_count'] ?>"
                               data-first-video-id="<?= htmlspecialchars($playlist['first_video_id'] ?? '') ?>">
                                <article class="playlist-card">
                                    <div class="playlist-thumbnail-container">
                                        <img src="/<?= htmlspecialchars($playlist['thumbnail'] ?? 'images/default.png') ?>" class="thumbnail" alt="playlist cover">
                                        <div class="playlist-overlay"><img src="/images/playlist.png" alt=""><span><?= $playlist['video_count'] ?> видео</span></div>
                                    </div>
                                    <div class="playlist-info">
                                        <h3><?= htmlspecialchars($playlist['title']) ?></h3>
                                        <?php if ($is_owner): ?>
                                            <p class="playlist-meta-profile">
                                                <?php 
                                                    echo match($playlist['visibility']) {
                                                        'public' => 'Открытый доступ',
                                                        'unlisted' => 'Доступ по ссылке',
                                                        default => 'Ограниченный доступ'
                                                    };
                                                ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($active_tab === 'about'): ?>
                <div class="about-tab-content">
                    <div class="about-grid">
                        <div class="about-main-info">
                            <h3>Общая информация</h3>
                            <?php if (!empty($profile_user['about_text'])): ?>
                                <p class="about-text"><?= nl2br(htmlspecialchars($profile_user['about_text'])) ?></p>
                            <?php else: ?>
                                <p class="empty-message about-text-placeholder">Владелец канала еще не добавил описание.</p>
                            <?php endif; ?>
                            
                            <?php if (!empty($user_links)): ?>
                                <div class="links-section">
                                    <h3>Ссылки</h3>
                                    <div class="links-list">
                                        <?php foreach ($user_links as $link): ?>
                                            <a href="<?= htmlspecialchars($link['link_url']) ?>" class="profile-link-item" target="_blank" rel="noopener noreferrer">
                                                <img src="<?= htmlspecialchars($link['icon_path']) ?>" alt="<?= htmlspecialchars($link['link_title']) ?>" class="link-icon">
                                                <span class="link-title"><?= htmlspecialchars($link['link_title']) ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($profile_user['business_email'])): ?>
                                <div class="contact-info-section">
                                    <h3>Для коммерческих запросов</h3>
                                    <div class="profile-link-item email-item">
                                        <img src="/images/link_email.png" alt="Email" class="link-icon"> 
                                        <p class="contact-info-email"><?= htmlspecialchars($profile_user['business_email']) ?></p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="about-stats-sidebar">
                            <div class="stats-card">
                                <h3 class="stats-card-title">
                                    <img src="/images/stats.png" alt="Stats" class="stats-icon-img">
                                    Мини-статистика канала
                                </h3>
                                <div class="stats-list">
                                    <div class="stats-item">
                                        <span class="stats-icon">👁️</span> 
                                        <div>
                                            <div class="stats-value"><?= number_format($total_views, 0, '.', ' ') ?></div>
                                            <div class="stats-label">Всего просмотров</div>
                                        </div>
                                    </div>
                                    <div class="stats-item">
                                        <span class="stats-icon">👍</span> 
                                        <div>
                                            <div class="stats-value"><?= number_format($total_likes, 0, '.', ' ') ?></div>
                                            <div class="stats-label">Всего лайков</div>
                                        </div>
                                    </div>
                                    <div class="stats-item">
                                        <span class="stats-icon">👤</span> 
                                        <div>
                                            <div class="stats-value"><?= number_format($subscriber_count, 0, '.', ' ') ?></div>
                                            <div class="stats-label">Подписчиков (текущий)</div>
                                        </div>
                                    </div>
                                    <div class="stats-item">
                                        <span class="stats-icon">📅</span> 
                                        <div>
                                            <div class="stats-value"><?= htmlspecialchars($join_date) ?></div>
                                            <div class="stats-label">Дата регистрации</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</div>

<?php if ($is_owner): ?>
<!-- Модальные окна управления (только для владельца) -->
<div class="modal-overlay" id="create-playlist-modal">
    <div class="modal-content">
        <h2>Новый плейлист</h2>
        <form id="create-playlist-form">
            <div class="form-group input-wrapper">
                <input type="text" name="title" id="playlist-title" placeholder="Укажите название" maxlength="100"> 
                <div class="input-error-message"></div>
            </div>
            <div class="form-group">
                <label>Доступ</label>
                <div class="visibility-options">
                    <label><input type="radio" name="visibility" value="private" checked><img src="/images/private.png" alt="" class="visibility-icon"><span>Ограниченный доступ</span></label>
                    <label><input type="radio" name="visibility" value="public"><img src="/images/public.png" alt="" class="visibility-icon"><span>Открытый доступ</span></label>
                    <label><input type="radio" name="visibility" value="unlisted"><img src="/images/unlisted.png" alt="" class="visibility-icon"><span>Доступ по ссылке</span></label>
                </div>
            </div>
            <div class="form-group">
                <label for="playlist-description">Описание</label>
                <textarea name="description" id="playlist-description" placeholder="Введите описание (необязательно)" maxlength="300" rows="1"></textarea>
                <div id="char-counter-container"><span id="char-counter">0 / 300</span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-button cancel" id="cancel-playlist-creation">Отмена</button>
                <button type="submit" class="modal-button create">Создать</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="delete-video-modal">
    <div class="modal-content">
        <h2>Удалить видео</h2>
        <p class="modal-description">Выберите видео, которое хотите удалить навсегда. Это действие нельзя отменить.</p>
        <div class="video-delete-list" id="video-delete-list"></div>
        <div class="modal-footer">
            <button type="button" class="modal-button cancel" id="cancel-video-delete-btn">Отмена</button>
            <button type="button" class="modal-button delete" id="confirm-video-delete-btn" disabled>Удалить</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="confirmation-video-delete-modal">
    <div class="modal-content confirmation">
        <h3>Подтвердите удаление</h3>
        <p id="confirm-video-delete-text">Вы уверены, что хотите удалить это видео? Оно будет скрыто со всех страниц.</p>
        <div class="modal-footer">
            <button type="button" class="modal-button confirm-cancel" id="final-video-delete-cancel-btn">Отмена</button>
            <button type="button" class="modal-button create confirm-ok" id="final-video-delete-ok-btn">ОК</button> 
        </div>
    </div>
</div>

<div class="modal-overlay" id="delete-playlist-modal">
    <div class="modal-content">
        <h2>Удалить плейлист</h2>
        <p class="modal-description">Выберите плейлист, который хотите удалить навсегда. Это действие нельзя отменить.</p>
        <div class="playlist-delete-list" id="playlist-delete-list"></div>
        <div class="modal-footer">
            <button type="button" class="modal-button cancel" id="cancel-playlist-delete-btn">Отмена</button>
            <button type="button" class="modal-button delete" id="confirm-playlist-delete-btn" disabled>Удалить</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="confirmation-playlist-delete-modal">
    <div class="modal-content confirmation">
        <h3>Подтвердите действие</h3>
        <p id="confirm-playlist-delete-text">Вы уверены, что хотите удалить этот плейлист? Это действие нельзя отменить.</p>
        <div class="modal-footer">
            <button type="button" class="modal-button confirm-cancel" id="final-playlist-delete-cancel-btn">Отмена</button>
            <button type="button" class="modal-button create confirm-ok" id="final-playlist-delete-ok-btn">ОК</button> 
        </div>
    </div>
</div>
<?php endif; ?>

<script src="/js/profile.js"></script>
<script src="/js/script.js"></script>
</body>
</html>