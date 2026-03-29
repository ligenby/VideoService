<?php

$page_title = "Поиск";
$additional_styles = ['/css/results.css'];

require_once __DIR__ . '/../templates/partials/header.php';
require_once __DIR__ . '/../templates/partials/sidebar.php';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$is_user_logged_in = $user_id !== null;

// --- Сбор параметров фильтрации ---
$search_query   = trim($_GET['search_query'] ?? '');
$quick_filter   = $_GET['filter'] ?? 'all'; 
$order_by       = $_GET['order'] ?? 'relevance';       
$published_time = $_GET['published'] ?? 'all_time';
$duration       = $_GET['duration'] ?? 'any';          

// --- Загрузка связанных данных пользователя ---
$watch_later_ids = [];
$user_playlists = [];
$subscribed_channel_ids = [];

if ($is_user_logged_in) {
    // Список "Смотреть позже"
    $wl_stmt = $conn->prepare("SELECT video_id FROM watch_later WHERE user_id = ?");
    $wl_stmt->bind_param("i", $user_id);
    $wl_stmt->execute();
    $watch_later_ids = array_column($wl_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'video_id');
    $wl_stmt->close();

    // Плейлисты пользователя
    $pl_stmt = $conn->prepare("SELECT playlist_id, title FROM playlists WHERE user_id = ? ORDER BY title ASC");
    $pl_stmt->bind_param("i", $user_id);
    $pl_stmt->execute();
    $user_playlists = $pl_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pl_stmt->close();

    // Подписки пользователя
    $sub_stmt = $conn->prepare("SELECT channel_id FROM subscriptions WHERE subscriber_id = ?");
    $sub_stmt->bind_param("i", $user_id);
    $sub_stmt->execute();
    $subscribed_channel_ids = array_column($sub_stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'channel_id');
    $sub_stmt->close();
}

// --- Построение динамического SQL-запроса ---
$results = [];

if (!empty($search_query)) {
    $page_title = "Результаты по запросу: " . htmlspecialchars($search_query);
    
    $video_params = [];
    $video_types = '';
    $channel_params = [];
    $channel_types = '';
    
    $sql_videos_base = "SELECT v.*, u.public_user_id, u.channel_name, u.username, u.avatar_url, 'video' as type FROM videos v JOIN users u ON v.user_id = u.user_id";
    $sql_channels_base = "SELECT u.user_id, u.public_user_id, u.username, u.channel_name, u.avatar_url, u.subscriber_count, u.about_text, 'channel' as type FROM users u";

    $where_videos = ["v.status = 'published'", "v.visibility = 'public'"];
    $where_channels = ["u.channel_name IS NOT NULL"];
    
    // TODO: Для продакшена заменить LIKE на Full-Text Search (MATCH AGAINST) или внедрить Elasticsearch для повышения производительности
    $search_term = "%" . $search_query . "%";
    
    $where_videos[] = "(v.title LIKE ? OR v.description LIKE ?)";
    array_push($video_params, $search_term, $search_term);
    $video_types .= 'ss';
    
    $where_channels[] = "(u.username LIKE ? OR u.channel_name LIKE ?)";
    array_push($channel_params, $search_term, $search_term);
    $channel_types .= 'ss';

    // A. Быстрые фильтры
    if ($quick_filter === 'recent') { 
        $where_videos[] = "v.upload_date >= DATE_SUB(NOW(), INTERVAL 12 HOUR)"; 
    }
    
    if ($is_user_logged_in && $quick_filter === 'watched') { 
        $where_videos[] = "v.video_id IN (SELECT video_id FROM history WHERE user_id = ?)"; 
        $video_params[] = $user_id; 
        $video_types .= 'i'; 
    }
    
    // B. Фильтры боковой панели: Время публикации
    switch ($published_time) {
        case 'today': $where_videos[] = "v.upload_date >= CURDATE()"; break;
        case 'week':  $where_videos[] = "v.upload_date >= DATE_SUB(NOW(), INTERVAL 1 WEEK)"; break;
        case 'month': $where_videos[] = "v.upload_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)"; break;
        case 'year':  $where_videos[] = "v.upload_date >= DATE_SUB(NOW(), INTERVAL 1 YEAR)"; break;
    }

    // C. Фильтры боковой панели: Длительность
    switch ($duration) {
        case 'under5':  $where_videos[] = "v.duration_seconds < 300"; break;
        case '5-20':    $where_videos[] = "v.duration_seconds BETWEEN 300 AND 1200"; break;
        case '20-60':   $where_videos[] = "v.duration_seconds BETWEEN 1201 AND 3600"; break;
        case 'over60':  $where_videos[] = "v.duration_seconds > 3600"; break;
    }
    
    // D. Сортировка
    $order_by_videos_sql = "";
    switch ($order_by) {
        case 'date':   
            $order_by_videos_sql = " ORDER BY v.upload_date DESC"; 
            break;
        case 'views':  
        case 'rating': // Временно маппим рейтинг на просмотры
            $order_by_videos_sql = " ORDER BY v.views_count DESC"; 
            break; 
        default: 
            $order_by_videos_sql = " ORDER BY (CASE WHEN v.title LIKE ? THEN 3 ELSE 1 END) DESC, v.views_count DESC";
            $video_params[] = $search_term;
            $video_types .= 's';
            break;
    }

    $order_by_channels_sql = " ORDER BY (CASE WHEN u.channel_name LIKE ? THEN 2 ELSE 1 END) DESC, u.subscriber_count DESC";
    $channel_params[] = $search_term;
    $channel_types .= 's';

    // --- Выполнение запросов ---
    $videos = [];
    $channels = [];

    if (in_array($quick_filter, ['all', 'video', 'watched', 'recent'], true)) {
        $final_sql_videos = $sql_videos_base . " WHERE " . implode(' AND ', $where_videos) . $order_by_videos_sql;
        $stmt_videos = $conn->prepare($final_sql_videos);
        if ($stmt_videos) { 
            if (!empty($video_params)) { 
                $stmt_videos->bind_param($video_types, ...$video_params); 
            } 
            $stmt_videos->execute(); 
            $videos = $stmt_videos->get_result()->fetch_all(MYSQLI_ASSOC); 
            $stmt_videos->close(); 
        }
    }
    
    if (in_array($quick_filter, ['all', 'channel'], true)) {
        $final_sql_channels = $sql_channels_base . " WHERE " . implode(' AND ', $where_channels) . $order_by_channels_sql;
        $stmt_channels = $conn->prepare($final_sql_channels);
        if ($stmt_channels) { 
            $stmt_channels->bind_param($channel_types, ...$channel_params); 
            $stmt_channels->execute(); 
            $channels = $stmt_channels->get_result()->fetch_all(MYSQLI_ASSOC); 
            $stmt_channels->close(); 
        }
    }
    
    // Слияние результатов
    $results = array_merge($channels, $videos);
}
?>

<main class="main-content">
    <div class="results-page-container">
        
        <div class="results-header">
            <div class="quick-filters-container">
                <a href="#" data-filter="all" class="filter-tab <?= $quick_filter === 'all' ? 'active' : '' ?>">Все</a>
                <a href="#" data-filter="video" class="filter-tab <?= $quick_filter === 'video' ? 'active' : '' ?>">Видео</a>
                <a href="#" data-filter="channel" class="filter-tab <?= $quick_filter === 'channel' ? 'active' : '' ?>">Каналы</a>
                <?php if ($is_user_logged_in): ?>
                    <a href="#" data-filter="watched" class="filter-tab <?= $quick_filter === 'watched' ? 'active' : '' ?>">Просмотренные</a>
                <?php endif; ?>
                <a href="#" data-filter="recent" class="filter-tab <?= $quick_filter === 'recent' ? 'active' : '' ?>">Недавно опубликованные</a>
            </div>
            
            <button class="filter-button" id="open-filters-btn">
                <img src="/images/filter.png" alt="Фильтры">
                <span>Фильтры</span>
            </button>
        </div>

        <?php if (!empty($results)): ?>
            <div class="results-list-container">
                <?php foreach ($results as $item): ?>
                    <?php if ($item['type'] === 'channel'): ?>
                        <article class="channel-list-item">
                            <a href="/profile.php?id=<?= htmlspecialchars($item['public_user_id']) ?>">
                                <img src="/<?= htmlspecialchars($item['avatar_url']) ?>" alt="Аватар" class="channel-avatar-large">
                            </a>
                            <div class="channel-details">
                                <a href="/profile.php?id=<?= htmlspecialchars($item['public_user_id']) ?>" class="channel-list-title">
                                    <?= htmlspecialchars($item['channel_name'] ?? $item['username']) ?>
                                </a>
                                <div class="channel-meta">
                                    <span>@<?= htmlspecialchars($item['username']) ?></span>
                                    <span class="meta-separator">•</span>
                                    <span data-subscriber-count="<?= htmlspecialchars($item['public_user_id']) ?>">
                                        <?= number_format($item['subscriber_count']) ?> подписчиков
                                    </span>
                                </div>
                                <p class="channel-description">
                                    <?= htmlspecialchars(mb_substr($item['about_text'] ?? 'Нет описания.', 0, 120)) . (mb_strlen($item['about_text'] ?? '') > 120 ? '...' : '') ?>
                                </p>
                            </div>
                            
                            <?php if ($is_user_logged_in && $item['user_id'] === $user_id): ?>
                                <a href="/pages/you.php" class="subscribe-button own-channel-button">Вы</a>
                            <?php else: 
                                $is_subscribed = in_array($item['user_id'], $subscribed_channel_ids, true);
                            ?>
                                <button class="subscribe-button <?= $is_subscribed ? 'subscribed' : '' ?>" data-channel-id="<?= htmlspecialchars($item['public_user_id']) ?>">
                                    <?= $is_subscribed ? 'Вы подписаны' : 'Подписаться' ?>
                                </button>
                            <?php endif; ?>
                        </article>
                        
                    <?php else: // 'video' ?>
                        <article class="video-list-item" data-video-id="<?= htmlspecialchars($item['public_video_id']) ?>">
                           <a href="/watch.php?id=<?= htmlspecialchars($item['public_video_id']) ?>" class="video-main-clickable-area" title="<?= htmlspecialchars($item['title']) ?>"></a>
                           <div class="video-list-thumbnail-wrapper">
                                <img src="/<?= htmlspecialchars($item['thumbnail_url']) ?>" alt="Превью" class="video-list-thumbnail">
                                <span class="video-duration"><?= format_duration($item['duration_seconds']) ?></span>
                            </div>
                            <div class="video-list-details">
                                <h3 class="video-list-title">
                                    <a href="/watch.php?id=<?= htmlspecialchars($item['public_video_id']) ?>"><?= htmlspecialchars($item['title']) ?></a>
                                </h3>
                                <div class="video-list-meta">
                                    <span><?= number_format($item['views_count']) ?> просмотров</span>
                                    <span class="meta-separator">•</span>
                                    <span><?= format_time_ago($item['upload_date']) ?></span>
                                </div>
                                <a href="/profile.php?id=<?= htmlspecialchars($item['public_user_id']) ?>" class="channel-info">
                                    <img src="/<?= htmlspecialchars($item['avatar_url']) ?>" alt="Аватар" class="channel-avatar">
                                    <span class="channel-name"><?= htmlspecialchars($item['channel_name'] ?? $item['username']) ?></span>
                                </a>
                                <p class="video-list-description">
                                    <?= htmlspecialchars(mb_substr($item['description'], 0, 150)) . (mb_strlen($item['description']) > 150 ? '...' : '') ?>
                                </p>
                            </div>
                            
                            <div class="context-menu-container">
                                <button class="context-menu-button"><img src="/images/menu-history.png" alt="Меню"></button>
                                <div class="context-menu-dropdown">
                                    <?php if ($is_user_logged_in):
                                        $is_in_watch_later = in_array($item['video_id'], $watch_later_ids, true);
                                    ?>
                                        <a href="#" data-action="toggle-watch-later">
                                            <img src="/images/watch_later.png" alt="">
                                            <span><?= $is_in_watch_later ? "Удалить из 'Смотреть позже'" : "Смотреть позже" ?></span>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="#" data-action="add-to-playlist"><img src="/images/playlist.png" alt=""><span>Добавить в плейлист</span></a>
                                    <a href="#" data-action="share-video"><img src="/images/share.png" alt=""><span>Поделиться</span></a>
                                    
                                    <?php if ($is_user_logged_in): ?>
                                        <a href="#" data-action="report"><img src="/images/complaint.png" alt=""><span>Пожаловаться</span></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            
        <?php elseif (!empty($search_query)): ?>
            <div class="empty-placeholder">
                <img src="/images/empty-folder.png" alt="Ничего не найдено">
                <h2>Ничего не найдено по вашему запросу "<?= htmlspecialchars($search_query) ?>"</h2>
                <p>Попробуйте изменить поисковый запрос или воспользуйтесь фильтрами.</p>
            </div>
        <?php endif; ?>
    </div>
</main>
</div> <!-- Закрытие обертки страницы (если она открывается в header.php) -->

<!-- Боковая панель фильтров -->
<div class="filter-panel-overlay" id="filter-panel">
    <div class="filter-panel-content">
        <div class="filter-panel-header">
            <h3>Фильтры поиска</h3>
            <button class="close-filter-panel" id="close-filters-btn">&times;</button>
        </div>

        <div class="filter-section">
            <h4>Упорядочить</h4>
            <a href="#" data-filter-type="order" data-filter-value="relevance" class="filter-option <?= $order_by === 'relevance' ? 'active' : '' ?>">По релевантности</a>
            <a href="#" data-filter-type="order" data-filter-value="date" class="filter-option <?= $order_by === 'date' ? 'active' : '' ?>">По дате загрузки</a>
            <a href="#" data-filter-type="order" data-filter-value="views" class="filter-option <?= $order_by === 'views' ? 'active' : '' ?>">По числу просмотров</a>
            <a href="#" data-filter-type="order" data-filter-value="rating" class="filter-option <?= $order_by === 'rating' ? 'active' : '' ?>">По рейтингу</a>
        </div>

        <div class="filter-section">
            <h4>Время публикации</h4>
            <a href="#" data-filter-type="published" data-filter-value="all_time" class="filter-option <?= $published_time === 'all_time' ? 'active' : '' ?>">За все время</a>
            <a href="#" data-filter-type="published" data-filter-value="today" class="filter-option <?= $published_time === 'today' ? 'active' : '' ?>">За сегодня</a>
            <a href="#" data-filter-type="published" data-filter-value="week" class="filter-option <?= $published_time === 'week' ? 'active' : '' ?>">За неделю</a>
            <a href="#" data-filter-type="published" data-filter-value="month" class="filter-option <?= $published_time === 'month' ? 'active' : '' ?>">За месяц</a>
            <a href="#" data-filter-type="published" data-filter-value="year" class="filter-option <?= $published_time === 'year' ? 'active' : '' ?>">За год</a>
        </div>

        <div class="filter-section">
            <h4>Длительность</h4>
            <a href="#" data-filter-type="duration" data-filter-value="any" class="filter-option <?= $duration === 'any' ? 'active' : '' ?>">Любая</a>
            <a href="#" data-filter-type="duration" data-filter-value="under5" class="filter-option <?= $duration === 'under5' ? 'active' : '' ?>">До 5 минут</a>
            <a href="#" data-filter-type="duration" data-filter-value="5-20" class="filter-option <?= $duration === '5-20' ? 'active' : '' ?>">5-20 минут</a>
            <a href="#" data-filter-type="duration" data-filter-value="20-60" class="filter-option <?= $duration === '20-60' ? 'active' : '' ?>">20-60 минут</a>
            <a href="#" data-filter-type="duration" data-filter-value="over60" class="filter-option <?= $duration === 'over60' ? 'active' : '' ?>">Более 60 минут</a>
        </div>
    </div>
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
            <button class="add-to-playlist-modal-close" data-action="close-modal">&times;</button>
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
                <button type="button" class="modal-button-small cancel" data-action="close-modal">Отмена</button>
                <button type="submit" class="modal-button-small create">Создать</button>
            </div>
        </form>
    </div>
</div>

<div class="report-modal-overlay" id="report-modal">
    <div class="report-modal-content">
        <div class="report-modal-header">
            <h2 class="report-modal-title">Пожаловаться на видео</h2>
            <button class="modal-close-button" data-action="close-modal">&times;</button>
        </div>
        <form id="report-form">
            <div class="report-modal-body">
                <input type="hidden" name="video_id" id="report-video-id">
                <div class="report-option">
                    <input type="radio" id="reason-sex" name="reason" value="Содержание сексуального характера" required>
                    <label for="reason-sex">Содержание сексуального характера</label>
                </div>
                <div class="report-option">
                    <input type="radio" id="reason-violent" name="reason" value="Жестокое или отталкивающее содержание">
                    <label for="reason-violent">Жестокое или отталкивающее содержание</label>
                </div>
                <div class="report-option">
                    <input type="radio" id="reason-hate" name="reason" value="Дискриминационные высказывания и оскорбления">
                    <label for="reason-hate">Дискриминационные высказывания и оскорбления</label>
                </div>
                <div class="report-option">
                    <input type="radio" id="reason-harmful" name="reason" value="Вредные или опасные действия">
                    <label for="reason-harmful">Вредные или опасные действия</label>
                </div>
                <div class="report-option">
                    <input type="radio" id="reason-spam" name="reason" value="Спам">
                    <label for="reason-spam">Спам</label>
                </div>
                <div class="report-option">
                    <input type="radio" id="reason-child" name="reason" value="Жестокое обращение с детьми">
                    <label for="reason-child">Жестокое обращение с детьми</label>
                </div>
                <div class="report-option">
                    <input type="radio" id="reason-other-radio" name="reason" value="Другое">
                    <label for="reason-other-radio">Другое</label>
                </div>
                <div class="other-reason-container" id="other-reason-box">
                    <textarea id="other-reason-details" name="details" maxlength="300" placeholder="Опишите нарушение (необязательно)"></textarea>
                    <div class="char-counter"><span id="char-count">0</span> / 300</div>
                </div>
            </div>
            <div class="report-modal-footer">
                <button type="button" class="report-button cancel" data-action="close-modal">Отмена</button>
                <button type="submit" class="report-button submit" disabled>Пожаловаться</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay-small" id="login-required-modal">
    <div class="modal-content-small login-modal-content">
        <button class="modal-close-button" data-action="close-modal">&times;</button>
        <h3>Требуется вход в аккаунт</h3>
        <p class="login-modal-text">Чтобы воспользоваться этой функцией, войдите в свой аккаунт.</p>
        <div class="login-modal-actions">
            <a href="/register.php" class="modal-button-small secondary">Регистрация</a>
            <a href="/login.php" class="modal-button-small create">Войти</a>
        </div>
    </div>
</div>

<!-- Передача конфигурации в JavaScript -->
<script>
    const userPlaylists = <?= json_encode($user_playlists ?? []) ?>;
</script>
<script src="/js/script.js"></script>
<script src="/js/results.js"></script> 

</body>
</html>