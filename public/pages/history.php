<?php
$additional_styles = ['/css/history.css'];
require_once __DIR__ . '/../../templates/partials/header.php';
require_once __DIR__ . '/../../templates/partials/sidebar.php';


// Неавторизованные пользователи
if (!isset($_SESSION['user_id'])):

?>
    <main class="main-content">
        <div class="empty-page-placeholder">
            <img src="/images/history.png" alt="История">
            <h2>Следите за историей просмотра</h2>
            <p>Здесь будет отображаться история просмотренных вами видео.</p>
            <a href="/login.php" class="empty-page-button">Войти</a>
        </div>
    </main>
<?php
else:
    $user_id = (int)$_SESSION['user_id'];
    
    // Настройки пользователя

    $user_stmt = $conn->prepare("SELECT email, history_enabled FROM users WHERE user_id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_data = $user_stmt->get_result()->fetch_assoc();
    $history_enabled = $user_data['history_enabled'];
    $user_email = $user_data['email'];
    $_SESSION['history_enabled'] = $history_enabled;
    $user_stmt->close();

    // История просмотров
    $stmt = $conn->prepare("

        SELECT 
            v.video_id, v.public_video_id, v.title, v.thumbnail_url, v.duration_seconds, v.views_count, v.upload_date, v.description,
            u.public_user_id, u.username, u.channel_name, u.avatar_url, u.subscriber_count, 
            MAX(h.viewed_at) as last_viewed
        FROM history h
        JOIN videos v ON h.video_id = v.video_id
        JOIN users u ON v.user_id = u.user_id
        WHERE h.user_id = ?
        AND v.status != 'deleted'
        GROUP BY v.video_id
        ORDER BY last_viewed DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Получаем ID видео из плейлиста "Смотреть позже"
    $watch_later_ids = [];
    $wl_stmt = $conn->prepare("SELECT video_id FROM watch_later WHERE user_id = ?");
    $wl_stmt->bind_param("i", $user_id);
    $wl_stmt->execute();
    $wl_result = $wl_stmt->get_result();
    while ($row = $wl_result->fetch_assoc()) {
        $watch_later_ids[] = $row['video_id'];
    }
    $wl_stmt->close();

    // Получаем плейлисты пользователя для модального окна
    $playlists_stmt = $conn->prepare("SELECT playlist_id, title FROM playlists WHERE user_id = ? ORDER BY title ASC");
    $playlists_stmt->bind_param("i", $user_id);
    $playlists_stmt->execute();
    $user_playlists = $playlists_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $playlists_stmt->close();
?>
    <main class="main-content">
        <div class="history-page-container" id="history-container">
            <div class="history-page-header">
                <h1 class="history-page-title">История просмотра</h1>
                <div class="history-global-controls">
                    <a href="#" class="history-global-control-item" data-action="open-modal" data-modal-id="clear-history-modal">
                        <img src="/images/musor.png" alt="Очистить">
                        <span>Очистить историю просмотра</span>
                    </a>
                    <a href="#" class="history-global-control-item" data-action="open-modal" data-modal-id="toggle-history-modal">
                        <img src="/images/<?php echo $history_enabled ? 'stop.png' : 'play.png'; ?>" alt="Пауза/Старт" id="toggle-history-icon">
                        <span id="toggle-history-text"><?php echo $history_enabled ? 'Приостановить запись истории' : 'Возобновить запись истории'; ?></span>
                    </a>
                </div>
            </div>
            
            <div id="history-content">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php
                    $current_date_header = null;
                    while ($video = $result->fetch_assoc()):
                        $video_date = format_history_date($video['last_viewed']);
                        if ($video_date !== $current_date_header) {
                            if ($current_date_header !== null) echo '</div>'; 
                            echo '<h2 class="history-date-header">' . $video_date . '</h2>';
                            echo '<div class="history-video-list">';
                            $current_date_header = $video_date;
                        }
                    ?>
                        <div class="history-video-item" data-video-id="<?php echo htmlspecialchars($video['public_video_id']); ?>">
                            <div class="history-thumbnail-wrapper">
                                <a href="/watch.php?id=<?php echo htmlspecialchars($video['public_video_id']); ?>" class="history-thumbnail-link">
                                    <img class="history-thumbnail" src="/<?php echo htmlspecialchars($video['thumbnail_url'] ?? 'images/default-thumbnail.png'); ?>" alt="Превью">
                                </a>
                                <?php if (!empty($video['duration_seconds'])): ?>
                                    <div class="history-thumbnail-duration"><?php echo format_duration($video['duration_seconds']); ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="history-video-details">
                                <a href="/watch.php?id=<?php echo htmlspecialchars($video['public_video_id']); ?>" style="text-decoration: none;">
                                    <h3 class="history-video-title"><?php echo htmlspecialchars($video['title']); ?></h3>
                                </a>
                                <div class="history-channel-info">
                                    <!-- Ссылка на профиль -->
                                    <a href="/profile.php?id=<?php echo htmlspecialchars($video['public_user_id']); ?>" class="history-channel-link">
                                        <img src="/<?php echo htmlspecialchars($video['avatar_url']); ?>" alt="" class="history-channel-avatar">
                                        <span class="history-channel-name"><?php echo htmlspecialchars($video['channel_name'] ?? $video['username']); ?></span>
                                    </a>
                                    
                                    <!-- Остальная информация (подписчики) -->
                                    <span class="history-video-meta"> • </span> 
                                    <span class="history-video-meta"><?php echo number_format($video['subscriber_count']); ?> подписчиков</span>
                                </div>
                                <p class="history-video-meta">
                                    <?php echo number_format($video['views_count'], 0, '', ' '); ?> просмотров • <?php echo format_time_ago($video['upload_date']); ?>
                                </p>
                                <p class="history-video-description"><?php echo htmlspecialchars($video['description']); ?></p>
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
                                    <a href="#" data-action="history-toggle-watch-later">
                                        <img src="/images/watch_later.png" alt="Смотреть позже">
                                        <span><?php echo $watch_later_text; ?></span>
                                    </a>
                                    <a href="#" data-action="history-add-to-playlist">
                                        <img src="/images/playlist.png" alt="Плейлист">
                                        <span>Добавить в плейлист</span>
                                    </a>
                                    <a href="#" data-action="history-share">
                                        <img src="/images/share.png" alt="Поделиться">
                                        <span>Поделиться</span>
                                    </a>
                                    <a href="#" data-action="history-delete">
                                        <img src="/images/delete.png" alt="Удалить">
                                        <span>Удалить из истории просмотра</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                    <?php if ($result->num_rows > 0) echo '</div>'; ?>
                <?php else: ?>
                    <div class="empty-page-placeholder">
                        <h2>В списке пока ничего нет</h2>
                        <p>Просмотренные вами видео будут появляться здесь.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
<?php endif; ?>

<!-- Модальные окна, специфичные для страницы истории -->
<div class="confirmation-modal-overlay" id="clear-history-modal">
    <div class="confirmation-modal-content">
        <h3 class="confirmation-modal-title">Очистить историю просмотра?</h3>
        <p class="confirmation-modal-user"><?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($user_email); ?>)</p>
        <div class="confirmation-modal-body"><p>Ваша история просмотра будет удалена со всех устройств. Это действие нельзя будет отменить.</p></div>
        <div class="confirmation-modal-footer">
            <button class="confirmation-modal-button cancel" data-action="close-modal">Отмена</button>
            <button class="confirmation-modal-button confirm" data-action="confirm-clear">Очистить историю просмотра</button>
        </div>
    </div>
</div>
<div class="confirmation-modal-overlay" id="toggle-history-modal">
    <div class="confirmation-modal-content">
        <h3 class="confirmation-modal-title" id="toggle-modal-title"></h3>
        <p class="confirmation-modal-user"><?php echo htmlspecialchars($_SESSION['username']); ?> (<?php echo htmlspecialchars($user_email ?? ''); ?>)</p>
        <div class="confirmation-modal-body" id="toggle-modal-body"><p></p></div>
        <div class="confirmation-modal-footer">
            <button class="confirmation-modal-button cancel" data-action="close-modal">Отмена</button>
            <button class="confirmation-modal-button confirm" data-action="confirm-toggle" id="toggle-modal-confirm-btn"></button>
        </div>
    </div>
</div>


</div>

<?php
if (isset($user_id)) {
    echo '<script> const userPlaylists = ' . json_encode($user_playlists ?? []) . '; </script>';
}
require_once __DIR__ . '/../../templates/partials/global_modals.php';
?>

<script src="/js/modal_manager.js"></script> 
<script src="/js/history.js"></script>
<script src="/js/script.js"></script>
</body>
</html>