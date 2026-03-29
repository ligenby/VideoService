<?php

$additional_styles = ['/css/menu.css'];
require_once __DIR__ . '/../templates/partials/header.php'; 
require_once __DIR__ . '/../templates/partials/sidebar.php'; 

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$watch_later_ids = [];
$user_playlists = [];

if ($user_id) {
    // Получение ID "Смотреть позже"
    $wl_stmt = $conn->prepare("SELECT video_id FROM watch_later WHERE user_id = ?");
    $wl_stmt->bind_param("i", $user_id);
    $wl_stmt->execute();
    $wl_result = $wl_stmt->get_result();
    while ($row_wl = $wl_result->fetch_assoc()) { 
        $watch_later_ids[] = (int)$row_wl['video_id']; 
    }
    $wl_stmt->close();

    // Пользовательские плейлисты
    $pl_stmt = $conn->prepare("SELECT playlist_id, title FROM playlists WHERE user_id = ? ORDER BY title ASC");
    $pl_stmt->bind_param("i", $user_id);
    $pl_stmt->execute();
    $user_playlists = $pl_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pl_stmt->close();
}

// Загрузка публичных плейлистов
$public_playlists = [];
$playlists_query = "
    SELECT 
        p.playlist_id, p.title, p.updated_at, u.channel_name, u.username, u.avatar_url, u.public_user_id,
        COUNT(v_count.video_id) as video_count, 
        (SELECT v.public_video_id FROM playlist_videos pv_inner JOIN videos v ON pv_inner.video_id = v.video_id WHERE pv_inner.playlist_id = p.playlist_id AND v.status = 'published' ORDER BY pv_inner.playlist_video_id ASC LIMIT 1) AS first_public_video_id,
        (SELECT v.thumbnail_url FROM playlist_videos pv_inner JOIN videos v ON pv_inner.video_id = v.video_id WHERE pv_inner.playlist_id = p.playlist_id AND v.status = 'published' ORDER BY pv_inner.playlist_video_id ASC LIMIT 1) AS thumbnail_url
    FROM playlists p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN playlist_videos pv ON p.playlist_id = pv.playlist_id
    LEFT JOIN videos v_count ON pv.video_id = v_count.video_id AND v_count.status != 'deleted' 
    WHERE p.visibility = 'public' 
    GROUP BY p.playlist_id 
    HAVING video_count > 0 
    ORDER BY p.updated_at DESC
";
$playlists_result = $conn->query($playlists_query);
if ($playlists_result) {
    $public_playlists = $playlists_result->fetch_all(MYSQLI_ASSOC);
}

// Загрузка видео-контента
$videos_result = null;

if ($user_id) {
    // TODO: Для production заменить скоринг внутри SQL на рекомендательный движок (Machine Learning/Redis)
    $stmt = $conn->prepare("
        SELECT 
            v.*, u.public_user_id, u.username, u.avatar_url, u.channel_name,
            GROUP_CONCAT(c.name SEPARATOR ',') AS categories,
            (
                (v.views_count / 5000) 
                + (CASE WHEN v.upload_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 2 ELSE 0 END)
                + (CASE WHEN EXISTS (SELECT 1 FROM history h JOIN videos v_hist ON h.video_id = v_hist.video_id WHERE h.user_id = ? AND v_hist.user_id = v.user_id) THEN 15 ELSE 0 END)
                + (SELECT IFNULL(COUNT(DISTINCT vc_hist.category_id) * 10, 0) FROM history h JOIN video_categories vc_hist ON h.video_id = vc_hist.video_id WHERE h.user_id = ? AND vc_hist.category_id IN (SELECT vc_current.category_id FROM video_categories vc_current WHERE vc_current.video_id = v.video_id))
                - (CASE WHEN EXISTS (SELECT 1 FROM not_interested_videos ni JOIN videos v_ni ON ni.video_id = v_ni.video_id WHERE ni.user_id = ? AND v_ni.user_id = v.user_id) THEN 20 ELSE 0 END)
            ) AS recommendation_score
        FROM videos v 
        JOIN users u ON v.user_id = u.user_id
        LEFT JOIN video_categories vc ON v.video_id = vc.video_id
        LEFT JOIN categories c ON vc.category_id = c.category_id
        WHERE 
            v.status = 'published' AND v.visibility = 'public'
            AND v.video_id NOT IN (SELECT ni.video_id FROM not_interested_videos ni WHERE ni.user_id = ?)
            AND v.video_id NOT IN (SELECT h.video_id FROM history h WHERE h.user_id = ?)
        GROUP BY v.video_id
        ORDER BY recommendation_score DESC, v.upload_date DESC
        LIMIT 50
    ");
    $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $videos_result = $stmt->get_result();
    $stmt->close();
} else {
    $videos_result = $conn->query("
        SELECT v.*, u.public_user_id, u.username, u.avatar_url, u.channel_name,
            GROUP_CONCAT(c.name SEPARATOR ',') AS categories
        FROM videos v 
        JOIN users u ON v.user_id = u.user_id
        LEFT JOIN video_categories vc ON v.video_id = vc.video_id
        LEFT JOIN categories c ON vc.category_id = c.category_id
        WHERE v.status = 'published' AND v.visibility = 'public'
        GROUP BY v.video_id 
        ORDER BY v.views_count DESC, v.upload_date DESC
        LIMIT 50
    ");
}
?>

<main class="main-content">
    <div class="video-grid">
        
        <?php foreach ($public_playlists as $playlist): 
            $watch_link = !empty($playlist['first_public_video_id']) ? "/watch.php?id=" . htmlspecialchars($playlist['first_public_video_id']) . "&playlist=" . htmlspecialchars($playlist['playlist_id']) : "#"; 
        ?>
            <article class="content-item playlist-item">
                <a href="<?= $watch_link ?>" class="playlist-card-link">
                    <div class="playlist-card">
                        <div class="playlist-thumbnail-container">
                            <img src="/<?= htmlspecialchars($playlist['thumbnail_url'] ?? 'images/playlist_placeholder.png') ?>" alt="Обложка">
                            <div class="playlist-overlay-hover"><img src="/images/play_arrow.png" alt="Воспроизвести"><span>ВОСПРОИЗВЕСТИ</span></div>
                            <div class="playlist-overlay-bottom"><img src="/images/playlist.png" alt="Иконка плейлиста"><span><?= $playlist['video_count'] ?> видео</span></div>
                        </div>
                        <div class="playlist-details-main">
                            <img class="playlist-channel-avatar" src="/<?= htmlspecialchars($playlist['avatar_url'] ?? 'profile/profile.png') ?>" alt="Avatar">
                            <div class="playlist-text-info">
                                <h3 class="playlist-title-main"><?= htmlspecialchars($playlist['title']) ?></h3>
                                <p class="playlist-meta-main"><?= htmlspecialchars($playlist['channel_name'] ?? $playlist['username']) ?></p>
                            </div>
                        </div>
                    </div>
                </a>
            </article>
        <?php endforeach; ?>
        
        <?php if ($videos_result && $videos_result->num_rows > 0): ?>
            <?php while ($row = $videos_result->fetch_assoc()): 
                $extra_classes = '';
                if (!empty($row['categories'])) {
                    $categories = explode(',', $row['categories']);
                    $class_names = array_map(function($category) {
                        return match ($category) {
                            'Музыка' => 'music-item',
                            'Фильмы и сериалы' => 'movies-item',
                            'Видеоигры' => 'games-item',
                            'Новости' => 'news-item',
                            'Спорт' => 'sport-item',
                            'Курсы' => 'courses-item',
                            'Мода и красота' => 'fashion-item',
                            default => null
                        };
                    }, $categories);
                    $extra_classes = ' ' . implode(' ', array_filter($class_names));
                }
            ?>
                <article class="video-card content-item video-item<?= htmlspecialchars($extra_classes) ?>" data-video-id="<?= htmlspecialchars($row['public_video_id']) ?>" data-upload-date="<?= htmlspecialchars($row['upload_date']) ?>">
                    <a href="/watch.php?id=<?= htmlspecialchars($row['public_video_id']) ?>" class="video-card-link">
                        <div class="thumbnail-wrapper">
                            <img class="thumbnail" src="/<?= htmlspecialchars($row['thumbnail_url'] ?? 'images/default-thumbnail.png') ?>" alt="Thumbnail">
                            <?php if (!empty($row['duration_seconds'])): ?>
                                <span class="thumbnail-duration"><?= format_duration($row['duration_seconds']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="video-details-bottom">
                            <div class="channel-avatar-wrapper">
                                <img class="channel-avatar" src="/<?= htmlspecialchars($row['avatar_url'] ?? 'profile/profile.png') ?>" alt="Avatar">
                            </div>
                            <div class="video-text">
                                <h3 class="video-title"><?= htmlspecialchars($row['title']) ?></h3>
                                <a href="/profile.php?id=<?= htmlspecialchars($row['public_user_id']) ?>" class="channel-name-link"><?= htmlspecialchars($row['channel_name'] ?? $row['username']) ?></a>
                                <p class="video-meta">
                                    <span><?= number_format((int)$row['views_count'], 0, '.', ' ') ?> просмотров</span><span>•</span><span><?= format_time_ago($row['upload_date']) ?></span>
                                </p>
                            </div>
                        </div>
                    </a>
                    
                    <div class="context-menu-container">
                        <button class="context-menu-button"><img src="/images/menu-history.png" alt="Меню"></button>
                        <div class="context-menu-dropdown">
                            <?php if ($user_id): 
                                $is_in_watch_later = in_array((int)$row['video_id'], $watch_later_ids, true);
                            ?>
                                <a href="#" data-action="toggle-watch-later"><img src="/images/watch_later.png" alt=""><span><?= $is_in_watch_later ? "Удалить из 'Смотреть позже'" : "Смотреть позже" ?></span></a>
                                <a href="#" data-action="add-to-playlist"><img src="/images/playlist.png" alt=""><span>Добавить в плейлист</span></a>
                                <a href="#" data-action="share-video"><img src="/images/share.png" alt=""><span>Поделиться</span></a>
                                <a href="#" data-action="not-interested"><img src="/images/hide.png" alt=""><span>Не интересно</span></a>
                                <a href="#" data-action="report"><img src="/images/complaint.png" alt=""><span>Пожаловаться</span></a>
                            <?php else: ?>
                                <a href="#" data-action="add-to-playlist"><img src="/images/playlist.png" alt=""><span>Добавить в плейлист</span></a>
                                <a href="#" data-action="share-video"><img src="/images/share.png" alt=""><span>Поделиться</span></a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>

    <div class="empty-page-placeholder" id="main-page-placeholder" style="display: none;">
        <img src="/images/empty-folder.png" alt="Пусто">
        <h2 id="placeholder-title"></h2>
        <p id="placeholder-text"></p>
    </div>
</main>
</div>

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
        <div class="report-modal-header">
            <h2 class="report-modal-title">Пожаловаться на видео</h2>
        </div>
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

<script>
    const userPlaylists = <?= json_encode($user_playlists ?? []) ?>;
</script>
<script src="/js/script.js"></script>
<script src="/js/menu.js"></script>
</body>
</html>