<?php

$additional_styles = ['/css/you.css'];
$body_class = 'you-page';

require_once __DIR__ . '/../../src/core/config.php';
require_once __DIR__ . '/../../templates/partials/header.php';
require_once __DIR__ . '/../../templates/partials/sidebar.php';

// --- Сценарий для неавторизованных пользователей ---
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

// --- Сценарий для авторизованных пользователей ---
$user_id = (int)$_SESSION['user_id'];
$video_status_condition = " AND v.status != 'deleted'";

// Логика получения связанных (мульти) аккаунтов
$all_accounts = [];
$stmt_group = $conn->prepare("SELECT link_group_id FROM linked_accounts WHERE user_id = ?");
$stmt_group->bind_param("i", $user_id);
$stmt_group->execute();
$group_result = $stmt_group->get_result()->fetch_assoc();
$stmt_group->close();

if ($group_result) {
    $link_group_id = $group_result['link_group_id'];
    $stmt_accounts = $conn->prepare("
        SELECT u.user_id, u.username, u.email, u.channel_name, u.avatar_url 
        FROM users u 
        JOIN linked_accounts la ON u.user_id = la.user_id 
        WHERE la.link_group_id = ?
    ");
    $stmt_accounts->bind_param("s", $link_group_id);
    $stmt_accounts->execute();
    $all_accounts = $stmt_accounts->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_accounts->close();
} else {
    $stmt_single = $conn->prepare("SELECT user_id, username, email, channel_name, avatar_url FROM users WHERE user_id = ?");
    $stmt_single->bind_param("i", $user_id);
    $stmt_single->execute();
    $all_accounts = $stmt_single->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_single->close();
}

// Навигация по вкладкам
$tabs = ['history', 'subscriptions', 'playlists', 'watch_later', 'liked'];
$active_tab = (isset($_GET['tab']) && in_array($_GET['tab'], $tabs, true)) ? $_GET['tab'] : 'history';

$tab_names = [
    'history'       => 'История', 
    'subscriptions' => 'Подписки', 
    'playlists'     => 'Плейлисты', 
    'watch_later'   => 'Смотреть позже', 
    'liked'         => 'Понравилось'
];

$tab_icons = [
    'history'       => '/images/history.png', 
    'subscriptions' => '/images/subscrieb.png', 
    'playlists'     => '/images/playlist.png', 
    'watch_later'   => '/images/watch_later.png', 
    'liked'         => '/images/like.png'
];

$content_data = [];
$is_playlist_tab = ($active_tab === 'playlists');

// Загрузка данных контента
if ($active_tab !== 'playlists') {
    // SQL запросы вынесены и отформатированы для удобства чтения
    $sql_query = match($active_tab) {
        'history' => "
            SELECT v.video_id, v.public_video_id, v.title, v.thumbnail_url, v.duration_seconds, v.views_count, v.upload_date, u.public_user_id, u.channel_name, u.username, u.avatar_url 
            FROM history h 
            JOIN videos v ON h.video_id = v.video_id 
            JOIN users u ON v.user_id = u.user_id 
            WHERE h.user_id = ? {$video_status_condition} 
            GROUP BY v.video_id 
            ORDER BY MAX(h.viewed_at) DESC LIMIT 40",
            
        'subscriptions' => "
            SELECT v.video_id, v.public_video_id, v.title, v.thumbnail_url, v.duration_seconds, v.views_count, v.upload_date, u.public_user_id, u.channel_name, u.username, u.avatar_url 
            FROM videos v 
            JOIN users u ON v.user_id = u.user_id 
            WHERE v.user_id IN (SELECT channel_id FROM subscriptions WHERE subscriber_id = ?) 
              AND v.status = 'published' 
            ORDER BY v.upload_date DESC LIMIT 40",
            
        'watch_later' => "
            SELECT v.video_id, v.public_video_id, v.title, v.thumbnail_url, v.duration_seconds, v.views_count, v.upload_date, u.public_user_id, u.channel_name, u.username, u.avatar_url 
            FROM watch_later wl 
            JOIN videos v ON wl.video_id = v.video_id 
            JOIN users u ON v.user_id = u.user_id 
            WHERE wl.user_id = ? {$video_status_condition} 
            ORDER BY wl.display_order ASC, wl.added_at DESC LIMIT 40",
            
        'liked' => "
            SELECT v.video_id, v.public_video_id, v.title, v.thumbnail_url, v.duration_seconds, v.views_count, v.upload_date, u.public_user_id, u.channel_name, u.username, u.avatar_url 
            FROM likes l 
            JOIN videos v ON l.video_id = v.video_id 
            JOIN users u ON v.user_id = u.user_id 
            WHERE l.user_id = ? AND l.like_type = 1 {$video_status_condition} 
            ORDER BY l.created_at DESC LIMIT 40",
    };

    $stmt = $conn->prepare($sql_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $content_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} else { // Если вкладка 'playlists'
    $system_playlists = [];
    $video_status_condition_sub = "AND v.status != 'deleted'";
    
    // Смотреть позже (Сборный запрос)
    $stmt_wl = $conn->prepare("
        SELECT 
            COUNT(v_count.video_id) as count, 
            (SELECT v.thumbnail_url FROM watch_later wl_inner JOIN videos v ON v.video_id = wl_inner.video_id WHERE wl_inner.user_id = ? {$video_status_condition_sub} ORDER BY wl_inner.added_at DESC LIMIT 1) as thumbnail 
        FROM watch_later wl 
        LEFT JOIN videos v_count ON wl.video_id = v_count.video_id AND v_count.status != 'deleted' 
        WHERE wl.user_id = ?
    ");
    $stmt_wl->bind_param("ii", $user_id, $user_id); 
    $stmt_wl->execute(); 
    $wl_data = $stmt_wl->get_result()->fetch_assoc();
    if ((int)$wl_data['count'] > 0) { 
        $system_playlists[] = ['type' => 'watch_later', 'title' => 'Смотреть позже', 'video_count' => (int)$wl_data['count'], 'latest_thumbnail' => $wl_data['thumbnail']]; 
    }
    $stmt_wl->close();
    
    // Понравившиеся
    $stmt_liked = $conn->prepare("
        SELECT 
            COUNT(v_count.video_id) as count, 
            (SELECT v.thumbnail_url FROM likes l_inner JOIN videos v ON v.video_id = l_inner.video_id WHERE l_inner.user_id = ? AND l_inner.like_type = 1 {$video_status_condition_sub} ORDER BY l_inner.created_at DESC LIMIT 1) as thumbnail 
        FROM likes l 
        LEFT JOIN videos v_count ON l.video_id = v_count.video_id AND v_count.status != 'deleted' 
        WHERE l.user_id = ? AND l.like_type = 1
    ");
    $stmt_liked->bind_param("ii", $user_id, $user_id); 
    $stmt_liked->execute(); 
    $liked_data = $stmt_liked->get_result()->fetch_assoc();
    if ((int)$liked_data['count'] > 0) { 
        $system_playlists[] = ['type' => 'liked', 'title' => 'Понравившиеся', 'video_count' => (int)$liked_data['count'], 'latest_thumbnail' => $liked_data['thumbnail']]; 
    }
    $stmt_liked->close();
    
    // Кастомные плейлисты пользователя
    $stmt_playlists = $conn->prepare("
        SELECT 
            p.playlist_id, p.title, p.visibility, p.updated_at, 
            COUNT(v_count.video_id) AS video_count, 
            (SELECT v.thumbnail_url FROM playlist_videos pv_inner JOIN videos v ON v.video_id = pv_inner.video_id WHERE pv_inner.playlist_id = p.playlist_id {$video_status_condition_sub} ORDER BY pv_inner.playlist_video_id DESC LIMIT 1) AS latest_thumbnail 
        FROM playlists p 
        LEFT JOIN playlist_videos pv ON p.playlist_id = pv.playlist_id 
        LEFT JOIN videos v_count ON pv.video_id = v_count.video_id AND v_count.status != 'deleted' 
        WHERE p.user_id = ? 
        GROUP BY p.playlist_id 
        ORDER BY p.updated_at DESC
    ");
    $stmt_playlists->bind_param("i", $user_id); 
    $stmt_playlists->execute();
    $user_playlists_result = $stmt_playlists->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_playlists->close();
    
    $content_data = array_merge($system_playlists, $user_playlists_result);
}

// Получение данных для JavaScript (модальные окна "Добавить в плейлист" и т.д.)
$watch_later_ids = [];
if (!$is_playlist_tab) {
    $wl_stmt = $conn->prepare("SELECT video_id FROM watch_later WHERE user_id = ?");
    $wl_stmt->bind_param("i", $user_id); 
    $wl_stmt->execute(); 
    $wl_result = $wl_stmt->get_result();
    while ($row = $wl_result->fetch_assoc()) { 
        $watch_later_ids[] = (int)$row['video_id']; 
    }
    $wl_stmt->close();
}

$playlists_stmt = $conn->prepare("SELECT playlist_id, title FROM playlists WHERE user_id = ? ORDER BY title ASC");
$playlists_stmt->bind_param("i", $user_id); 
$playlists_stmt->execute();
$user_playlists_for_js = $playlists_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$playlists_stmt->close();
?>

<main class="main-content">
    <div class="you-page-wrapper">

        <!-- Шапка страницы -->
        <div class="you-page-header-section">
            <header class="profile-header-block">
                <img src="/<?= htmlspecialchars($_SESSION['avatar_url'] ?? 'images/default_avatar.png') ?>" alt="Аватар" class="avatar-large">
                <div class="user-details">
                    <h1><?= htmlspecialchars($_SESSION['channel_name'] ?? $_SESSION['username']) ?></h1>
                    <p>@<?= htmlspecialchars($_SESSION['username']) ?></p>
                    
                    <div class="profile-actions">
                        <?php if (!empty($_SESSION['channel_name'])): ?>
                            <a href="/profile.php?id=<?= htmlspecialchars($_SESSION['public_user_id'] ?? '') ?>" class="profile-button">
                                <img src="/images/profile.png" alt="">
                                <span>Мой канал</span>
                            </a>
                        <?php else: ?>
                            <a href="#" class="profile-button requires-channel">
                                <img src="/images/add_account.png" alt="">
                                <span>Создать канал</span>
                            </a>
                        <?php endif; ?>
                        
                        <div class="account-switcher-container">
                            <button id="account-switcher-trigger" class="profile-button">
                                <img src="/images/switch_account.png" alt="">
                                <span>Сменить аккаунт</span>
                            </button>
                            
                            <!-- Выпадающее меню смены аккаунтов -->
                            <div id="account-switcher-panel" class="account-switcher-dropdown">
                                <div class="account-panel-header">
                                    <img src="/<?= htmlspecialchars($_SESSION['avatar_url'] ?? 'images/default_avatar.png') ?>" alt="Аватар" class="avatar-small">
                                    <div class="account-item-details">
                                        <span class="channel-name"><?= htmlspecialchars($_SESSION['channel_name'] ?? $_SESSION['username']) ?></span>
                                        <span class="account-email"><?= htmlspecialchars($_SESSION['email'] ?? 'Нет email') ?></span>
                                    </div>
                                </div>
                                <div class="account-list">
                                    <?php foreach($all_accounts as $account): ?>
                                        <div class="account-list-item">
                                            <form action="/api/switch_account" method="POST" class="account-item-form">
                                                <input type="hidden" name="user_id" value="<?= $account['user_id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                                <button type="submit" class="account-item">
                                                    <img src="/<?= htmlspecialchars($account['avatar_url'] ?? 'images/default_avatar.png') ?>" alt="Аватар" class="avatar-small">
                                                    <div class="account-item-details">
                                                        <span class="channel-name"><?= htmlspecialchars($account['channel_name'] ?? $account['username']) ?></span>
                                                        <span class="account-email"><?= htmlspecialchars($account['email'] ?? 'Нет email') ?></span>
                                                    </div>
                                                    <?php if ((int)$account['user_id'] === $user_id): ?>
                                                        <img src="/images/check.png" alt="Выбран" class="checkmark">
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                            
                                            <?php if ((int)$account['user_id'] !== $user_id): ?>
                                                <form action="/api/remove_linked_account" method="POST" class="remove-account-form">
                                                    <input type="hidden" name="user_id_to_remove" value="<?= $account['user_id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                                    <button type="submit" class="remove-account-button" title="Удалить из списка"><img src="/images/close.png" alt="Удалить"></button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="account-actions">
                                    <button id="manage-accounts-btn" class="action-link" type="button"><img src="/images/edit.png" alt=""><span>Управлять аккаунтами</span></button>
                                    <a href="/login.php?action=link" class="action-link"><img src="/images/add_account.png" alt=""><span>Добавить аккаунт</span></a>
                                    <form action="/logout.php" method="POST" class="logout-form-menu">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                                        <button type="submit" class="action-link"><img src="/images/logout.png" alt=""><span>Выйти</span></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <nav class="tab-navigation">
                <?php foreach ($tabs as $tab): ?>
                    <a href="?tab=<?= $tab ?>" class="tab-link <?= $tab === $active_tab ? 'active' : '' ?>">
                        <img src="<?= $tab_icons[$tab] ?>" alt="">
                        <span><?= $tab_names[$tab] ?></span>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
        
        <!-- Контент вкладок -->
        <div class="tab-content-area">
            <?php if (!empty($content_data)): ?>
                
                <?php if ($is_playlist_tab): ?>
                    <div class="playlist-grid">
                        <?php foreach($content_data as $playlist): 
                            if ((int)$playlist['video_count'] === 0) continue;
                            
                            $is_system = isset($playlist['type']);
                            $link = $is_system ? "/pages/playlist_view.php?type={$playlist['type']}" : "/pages/playlist_view.php?id={$playlist['playlist_id']}";
                            
                            if ($is_system) {
                                $visibility_text = 'Ограниченный доступ · Плейлист'; 
                                $updated_text = 'Обновлено недавно';
                            } else {
                                $visibility_map = ['public' => 'Открытый доступ', 'unlisted' => 'Доступ по ссылке', 'private' => 'Ограниченный доступ'];
                                $visibility_text = ($visibility_map[$playlist['visibility']] ?? 'Ограниченный доступ') . ' · Плейлист';
                                $updated_text = 'Обновлено ' . format_time_ago($playlist['updated_at']);
                            }
                        ?>
                            <article class="playlist-card">
                                <a href="<?= $link ?>" class="playlist-card-link" data-playlist-id="<?= htmlspecialchars($playlist['playlist_id'] ?? $playlist['type']) ?>">
                                    <div class="playlist-thumbnail-container">
                                        <img src="/<?= htmlspecialchars($playlist['latest_thumbnail'] ?? 'images/playlist_placeholder.png') ?>" alt="Обложка">
                                        <div class="playlist-overlay">
                                            <img src="/images/playlist.png" alt="">
                                            <span><?= $playlist['video_count'] ?> видео</span>
                                        </div>
                                    </div>
                                    <div class="playlist-info">
                                        <h3><?= htmlspecialchars($playlist['title']) ?></h3>
                                        <p class="playlist-meta"><?= $visibility_text ?></p>
                                        <p class="playlist-meta"><?= $updated_text ?></p>
                                        <p class="view-playlist-link">Посмотреть весь плейлист</p>
                                    </div>
                                </a>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    
                <?php else: // Видео (История, Подписки и т.д.) ?>
                    <div class="video-grid">
                        <?php foreach($content_data as $video): ?>
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
                                            <a href="/watch.php?id=<?= htmlspecialchars($video['public_video_id']) ?>"><?= htmlspecialchars($video['title']) ?></a>
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
                                                    <span><?= number_format($video['views_count'], 0, '.', ' ') ?> просмотров • <?= format_time_ago($video['upload_date']) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="context-menu-container">
                                        <button class="context-menu-button"><img src="/images/menu-history.png" alt="Меню"></button>
                                        <div class="context-menu-dropdown">
                                            <?php 
                                            $is_in_watch_later = in_array((int)$video['video_id'], $watch_later_ids, true); 
                                            ?>
                                            <a href="#" data-action="toggle-watch-later">
                                                <img src="/images/watch_later.png" alt="">
                                                <span><?= $is_in_watch_later ? "Удалить из 'Смотреть позже'" : "Смотреть позже" ?></span>
                                            </a>
                                            <a href="#" data-action="add-to-playlist"><img src="/images/playlist.png" alt=""><span>Добавить в плейлист</span></a>
                                            <a href="#" data-action="share"><img src="/images/share.png" alt=""><span>Поделиться</span></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="empty-page-placeholder">
                    <img src="/images/empty-folder.png" alt="Пусто">
                    <h2>Здесь пока ничего нет</h2>
                    <p>Контент этой вкладки будет отображаться здесь, как только он появится.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

</div>

<script> 
    const userPlaylists = <?= json_encode($user_playlists_for_js ?? []) ?>; 
</script>

<?php require_once __DIR__ . '/../../templates/partials/global_modals.php'; ?>

<script src="/js/script.js"></script>
<script src="/js/modal_manager.js"></script> 
<script src="/js/you.js"></script>
</body>
</html>