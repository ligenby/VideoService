<?php

$additional_styles = ['/css/playlists.css'];
$body_class = 'playlists-page';

require_once __DIR__ . '/../../src/core/config.php'; 
require_once __DIR__ . '/../../templates/partials/header.php'; 
require_once __DIR__ . '/../../templates/partials/sidebar.php';

$is_user_logged_in = isset($_SESSION['user_id']);
$system_playlists = [];
$user_playlists = [];

if ($is_user_logged_in) {
    $user_id = (int)$_SESSION['user_id'];
    $video_status_condition_sub = "AND v.status != 'deleted'";
    $video_status_condition_main = "AND v_count.status != 'deleted'";

    // --- Системные плейлисты ---

    // 1. Смотреть позже
    $stmt_wl = $conn->prepare("
        SELECT COUNT(wl.watch_later_id) AS video_count, 
        (SELECT v.thumbnail_url FROM watch_later wl_inner JOIN videos v ON v.video_id = wl_inner.video_id WHERE wl_inner.user_id = ? {$video_status_condition_sub} ORDER BY wl_inner.added_at DESC LIMIT 1) AS thumbnail 
        FROM watch_later wl JOIN videos v_count ON wl.video_id = v_count.video_id 
        WHERE wl.user_id = ? {$video_status_condition_main}
    ");
    $stmt_wl->bind_param("ii", $user_id, $user_id);
    $stmt_wl->execute();
    $watch_later_data = $stmt_wl->get_result()->fetch_assoc();
    $stmt_wl->close();

    // 2. Понравившиеся
    $stmt_liked = $conn->prepare("
        SELECT COUNT(l.like_id) AS video_count, 
        (SELECT v.thumbnail_url FROM likes l_inner JOIN videos v ON v.video_id = l_inner.video_id WHERE l_inner.user_id = ? AND l_inner.like_type = 1 {$video_status_condition_sub} ORDER BY l_inner.created_at DESC LIMIT 1) AS thumbnail 
        FROM likes l JOIN videos v_count ON l.video_id = v_count.video_id 
        WHERE l.user_id = ? AND l.like_type = 1 {$video_status_condition_main}
    ");
    $stmt_liked->bind_param("ii", $user_id, $user_id);
    $stmt_liked->execute();
    $liked_data = $stmt_liked->get_result()->fetch_assoc();
    $stmt_liked->close();

    // 3. Скрытые видео
    $stmt_hidden = $conn->prepare("
        SELECT COUNT(hv.hidden_video_id) AS video_count, 
        (SELECT v.thumbnail_url FROM hidden_videos hv_inner JOIN videos v ON v.video_id = hv_inner.video_id WHERE hv_inner.user_id = ? {$video_status_condition_sub} ORDER BY hv_inner.hidden_at DESC LIMIT 1) AS thumbnail 
        FROM hidden_videos hv JOIN videos v_count ON hv.video_id = v_count.video_id 
        WHERE hv.user_id = ? {$video_status_condition_main}
    ");
    $stmt_hidden->bind_param("ii", $user_id, $user_id);
    $stmt_hidden->execute();
    $hidden_data = $stmt_hidden->get_result()->fetch_assoc();
    $stmt_hidden->close();

    $system_playlists = [
        'watch_later' => [
            'title' => 'Смотреть позже',
            'type' => 'watch_later',
            'video_count' => (int)($watch_later_data['video_count'] ?? 0),
            'thumbnail' => $watch_later_data['thumbnail'] ?? null
        ],
        'liked' => [
            'title' => 'Понравившиеся',
            'type' => 'liked',
            'video_count' => (int)($liked_data['video_count'] ?? 0),
            'thumbnail' => $liked_data['thumbnail'] ?? null
        ],
        'hidden' => [
            'title' => 'Скрытые видео',
            'type' => 'hidden',
            'video_count' => (int)($hidden_data['video_count'] ?? 0),
            'thumbnail' => $hidden_data['thumbnail'] ?? null
        ]
    ];

    // --- Пользовательские плейлисты ---
    $stmt_user = $conn->prepare("
        SELECT 
            p.playlist_id, p.title, p.visibility, p.updated_at, 
            COUNT(v_count.video_id) AS video_count, 
            (SELECT v.thumbnail_url FROM playlist_videos pv_inner JOIN videos v ON v.video_id = pv_inner.video_id WHERE pv_inner.playlist_id = p.playlist_id {$video_status_condition_sub} ORDER BY pv_inner.playlist_video_id DESC LIMIT 1) AS thumbnail
        FROM playlists p 
        LEFT JOIN playlist_videos pv ON p.playlist_id = pv.playlist_id 
        LEFT JOIN videos v_count ON pv.video_id = v_count.video_id AND v_count.status != 'deleted'
        WHERE p.user_id = ? 
        GROUP BY p.playlist_id 
        ORDER BY p.updated_at DESC
    ");
    $stmt_user->bind_param("i", $user_id);
    $stmt_user->execute();
    $user_playlists = $stmt_user->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_user->close();
}
?>

<main class="main-content">
    <?php if ($is_user_logged_in): ?>
        
        <div class="playlists-page-container">
            <div class="playlists-page-header">
                <h1 class="playlists-page-title">Плейлисты</h1>
                <div class="header-actions">
                    <button class="create-playlist-button" id="open-create-modal-btn">
                        <img src="/images/upload.png" alt="+"> 
                        <span>Создать плейлист</span>
                    </button>
                    <button class="delete-playlist-main-button" id="open-delete-modal-btn">
                        <img src="/images/musor.png" alt="-">
                        <span>Удалить плейлист</span>
                    </button>
                </div>
            </div>
            
            <div class="playlists-grid">
                
                <!-- Системные плейлисты -->
                <?php foreach ($system_playlists as $key => $playlist): 
                    if ($key === 'hidden' && $playlist['video_count'] === 0) continue;
                    
                    $link = "/pages/playlist_view.php?type={$playlist['type']}";
                    $is_empty = ($playlist['video_count'] === 0) ? 'data-empty="true"' : '';
                ?>
                    <a href="<?= htmlspecialchars($link) ?>" <?= $is_empty ?> class="playlist-card-link">
                        <article class="playlist-card">
                            <div class="playlist-thumbnail-container">
                                <?php if ($playlist['thumbnail']): ?>
                                    <img src="/<?= htmlspecialchars($playlist['thumbnail']) ?>" alt="Обложка">
                                <?php else: ?>
                                    <div class="playlist-thumbnail-placeholder"><img src="/images/play.png" alt="Нет видео"></div>
                                <?php endif; ?>
                                <div class="playlist-overlay">
                                    <img src="/images/playlist.png" alt="Иконка">
                                    <span><?= $playlist['video_count'] ?> видео</span>
                                </div>
                            </div>
                            <div class="playlist-info">
                                <h3><?= htmlspecialchars($playlist['title']) ?></h3>
                                <p class="playlist-meta">Ограниченный доступ &middot; Плейлист</p>
                                <span class="view-playlist-text">Посмотреть весь плейлист</span>
                            </div>
                        </article>
                    </a>
                <?php endforeach; ?>

                <!-- Пользовательские плейлисты -->
                <?php foreach ($user_playlists as $playlist): 
                    $visibility_text = match($playlist['visibility']) {
                        'public' => 'Открытый доступ',
                        'unlisted' => 'Доступ по ссылке',
                        default => 'Ограниченный доступ'
                    };
                    
                    $link = "/pages/playlist_view.php?id={$playlist['playlist_id']}";
                    $is_empty = ((int)$playlist['video_count'] === 0) ? 'data-empty="true"' : '';
                ?>
                    <a href="<?= htmlspecialchars($link) ?>" <?= $is_empty ?> class="playlist-card-link" data-playlist-id="<?= htmlspecialchars($playlist['playlist_id']) ?>">
                        <article class="playlist-card">
                            <div class="playlist-thumbnail-container">
                                <?php if ($playlist['thumbnail']): ?>
                                    <img src="/<?= htmlspecialchars($playlist['thumbnail']) ?>" alt="Обложка">
                                <?php else: ?>
                                    <div class="playlist-thumbnail-placeholder"><img src="/images/play.png" alt="Нет видео"></div>
                                <?php endif; ?>
                                <div class="playlist-overlay">
                                    <img src="/images/playlist.png" alt="Иконка">
                                    <span><?= $playlist['video_count'] ?> видео</span>
                                </div>
                            </div>
                            <div class="playlist-info">
                                <h3><?= htmlspecialchars($playlist['title']) ?></h3>
                                <p class="playlist-meta"><?= $visibility_text ?> &middot; Плейлист</p>
                                <span class="view-playlist-text">Посмотреть весь плейлист</span>
                            </div>
                        </article>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

    <?php else: ?>
        <div class="empty-page-placeholder">
            <img src="/images/playlist.png" alt="Плейлисты">
            <h2>Создавайте плейлисты и сохраняйте видео</h2>
            <p>Чтобы создавать и просматривать плейлисты, войдите в аккаунт.</p>
            <a href="/login.php" class="empty-page-button">Войти</a>
        </div>
    <?php endif; ?>
</main>
</div> 

<!-- Модальные окна -->
<?php if ($is_user_logged_in): ?>
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

<div class="modal-overlay" id="delete-playlist-modal">
    <div class="modal-content">
        <h2>Удалить плейлист</h2>
        <p class="modal-description">Выберите плейлист, который хотите удалить навсегда. Это действие нельзя отменить.</p>
        <div class="playlist-delete-list" id="playlist-delete-list"></div>
        <div class="modal-footer">
            <button type="button" class="modal-button cancel" id="cancel-delete-btn">Отмена</button>
            <button type="button" class="modal-button delete" id="confirm-delete-btn" disabled>Удалить</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="confirmation-delete-modal">
    <div class="modal-content confirmation">
        <h3>Подтвердите действие</h3>
        <p id="confirm-delete-text">Вы уверены, что хотите удалить этот плейлист?</p>
        <div class="modal-footer">
            <button type="button" class="modal-button confirm-cancel" id="final-delete-cancel-btn">Отмена</button>
            <button type="button" class="modal-button confirm-ok" id="final-delete-ok-btn">OK</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="/js/playlists.js"></script>
<script src="/js/script.js"></script>
</body>
</html>