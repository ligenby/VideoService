<?php
/**
 * upload.php
 * Страница загрузки и предварительного редактирования видео.
 */

// Заголовки безопасности (необходимы для работы SharedArrayBuffer и FFmpeg WASM)
header("Cross-Origin-Opener-Policy: same-origin");
header("Cross-Origin-Embedder-Policy: require-corp");

require_once __DIR__ . '/../src/core/config.php';

// Если запрос POST, передаем управление обработчику
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../src/actions/handle_upload.php';
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php?redirect=upload');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$csrf_token = $_SESSION['csrf_token'] ?? '';

// --- Логика навигации "Назад" ---
$back_link_url = '/';
$back_link_text = 'Вернуться на главную';

if (isset($_GET['from']) && $_GET['from'] === 'profile' && !empty($_GET['id'])) {
    $profile_id = htmlspecialchars($_GET['id']);
    $back_link_url = '/profile.php?id=' . $profile_id;
    $back_link_text = 'Вернуться в профиль';
}

// --- Получение и сортировка категорий ---
$final_categories = [];
$result_cat = $conn->query("SELECT category_id, name FROM categories ORDER BY name ASC");

if ($result_cat) {
    $db_categories = $result_cat->fetch_all(MYSQLI_ASSOC);
    
    // Ищем категорию "Общее", чтобы поставить её первой
    $general_cat_index = array_search('Общее', array_column($db_categories, 'name'), true);
    
    if ($general_cat_index !== false) {
        $final_categories[] = [
            'category_id' => $db_categories[$general_cat_index]['category_id'],
            'name'        => 'Не выбрано / Общее',
            'is_default'  => true
        ];
        unset($db_categories[$general_cat_index]);
    }
    
    $final_categories = array_merge($final_categories, array_values($db_categories));
}

// Иконки категорий (маппинг)
$category_icons = [
    'Музыка'           => '/images/music.png',
    'Фильмы и сериалы' => '/images/movies.png',
    'Видеоигры'        => '/images/games.png',
    'Новости'          => '/images/news.png',
    'Спорт'            => '/images/sport.png',
    'Курсы'            => '/images/courses.png',
    'Мода и красота'   => '/images/fashion.png',
];
$default_icon = '/images/category-default.png';

// --- Получение плейлистов пользователя ---
$user_playlists = [];
$stmt_pl = $conn->prepare("SELECT playlist_id, title, visibility FROM playlists WHERE user_id = ? ORDER BY title ASC");
$stmt_pl->bind_param("i", $user_id);
$stmt_pl->execute();
$result_pl = $stmt_pl->get_result();

if ($result_pl) {
    $user_playlists = $result_pl->fetch_all(MYSQLI_ASSOC);
}
$stmt_pl->close();

// --- Обработка ошибок ---
$error_messages = [
    'fields'        => 'Пожалуйста, заполните название и выберите файл.',
    'type'          => 'Недопустимый формат видео.',
    'thumb_type'    => 'Недопустимый формат изображения.',
    'upload_failed' => 'Ошибка загрузки на сервер.',
    'db_error'      => 'Ошибка базы данных.',
];
$current_error = isset($_GET['error']) ? ($error_messages[$_GET['error']] ?? 'Произошла неизвестная ошибка.') : null;

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Загрузка видео — VideoService</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/upload.css">
    
    <script>
        const csrfToken = '<?= htmlspecialchars($csrf_token) ?>';
    </script>
</head>
<body class="upload-page">

    <a href="<?= $back_link_url ?>" class="back-to-site-link">
        <img src="/images/back.png" alt="<">
        <span><?= $back_link_text ?></span>
    </a>

    <div class="upload-container">
        <div class="upload-form-wrapper">
            
            <form action="/upload.php" method="post" enctype="multipart/form-data" id="main-upload-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <h2>Загрузка нового видео</h2>
                
                <?php if ($current_error): ?>
                    <div class="form-message error"><?= htmlspecialchars($current_error) ?></div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="title">Название (обязательно)</label>
                    <input type="text" id="title" name="title" required placeholder="Введите название видео">
                    <div class="input-error-message"></div>
                </div>

                <div class="form-group">
                    <label for="description">Описание</label>
                    <textarea id="description" name="description" placeholder="Расскажите, о чем ваше видео..."></textarea>
                </div>

                <div class="form-group">
                    <label>Файл видео (обязательно)</label>
                    <div class="file-input-wrapper">
                        <label for="video-input" class="file-input-label">Выберите файл</label>
                        <span class="file-name-display" id="video-filename">Файл не выбран</span>
                        <button type="button" class="edit-button" id="edit-video-btn" style="display: none;">✂️ Редактор</button>
                    </div>
                    <input type="file" id="video-input" name="video" accept="video/*" required style="display:none;">
                    <div class="input-error-message"></div>
                </div>

                <div class="form-group">
                    <label>Заставка видео</label>
                    <div class="file-input-wrapper">
                        <label for="thumbnail-input" class="file-input-label">Выбрать</label>
                        <span class="file-name-display" id="thumbnail-filename">Файл не выбран</span>
                        <button type="button" class="edit-button" id="edit-thumbnail-btn" style="display: none;">🖌 Редактировать</button>
                    </div>
                    <input type="file" id="thumbnail-input" name="thumbnail" accept="image/*" style="display:none;">
                </div>

                <div class="form-group">
                    <label>Категория</label>
                    <div class="custom-select-container" id="category-select-container">
                        <input type="hidden" name="main_category_id" value="<?= htmlspecialchars($final_categories[0]['category_id'] ?? '') ?>"> 
                        <button type="button" class="custom-select-button">
                            <span class="custom-select-value">Не выбрано / Общее</span>
                            <img src="/images/arrow-down.png" alt="v" class="custom-select-arrow">
                        </button>
                        <div class="custom-select-dropdown">
                            <?php foreach ($final_categories as $cat): 
                                $icon = !empty($cat['is_default']) ? $default_icon : ($category_icons[$cat['name']] ?? $default_icon);
                            ?>
                                <div class="custom-select-option" data-value="<?= htmlspecialchars($cat['category_id']) ?>">
                                    <img src="<?= htmlspecialchars($icon) ?>" alt="">
                                    <span><?= htmlspecialchars($cat['name']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group radio-group">
                    <p class="form-group-label">Параметры доступа</p>
                    <label><input type="radio" name="visibility" value="public" checked><span class="custom-radio"></span>Открытый доступ</label>
                    <label><input type="radio" name="visibility" value="unlisted"><span class="custom-radio"></span>Доступ по ссылке</label>
                    <label><input type="radio" name="visibility" value="private"><span class="custom-radio"></span>Ограниченный доступ</label>
                </div>

                <div class="form-group radio-group">
                    <p class="form-group-label">Добавить в плейлист</p>
                    <label><input type="radio" name="playlist_option" value="none" checked><span class="custom-radio"></span>Не добавлять</label>

                    <?php if (empty($user_playlists)): ?>
                        <label class="disabled"><input type="radio" disabled><span class="custom-radio"></span>Выбрать (нет плейлистов)</label>
                    <?php else: ?>
                        <label><input type="radio" name="playlist_option" value="existing"><span class="custom-radio"></span>Выбрать существующий</label>
                        
                        <div id="existing-playlist-container" class="playlist-input-container" style="display: none;">
                            <div class="custom-select-container" id="playlist-select-container">
                                <input type="hidden" name="playlist_id" value="">
                                <button type="button" class="custom-select-button">
                                    <span class="custom-select-value">Выберите плейлист</span>
                                    <img src="/images/arrow-down.png" alt="v" class="custom-select-arrow">
                                </button>
                                <div class="custom-select-dropdown">
                                    <?php foreach ($user_playlists as $pl): ?>
                                        <div class="custom-select-option" 
                                             data-value="<?= htmlspecialchars($pl['playlist_id']) ?>" 
                                             data-visibility="<?= htmlspecialchars($pl['visibility']) ?>">
                                            <span><?= htmlspecialchars($pl['title']) ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group checkbox-group">
                     <label>
                        <input type="checkbox" name="allow_comments" value="1" checked>
                        <span class="custom-checkbox"></span>
                        Разрешить комментарии
                    </label>
                </div>

                <div class="submit-button-wrapper">
                    <button type="submit">Загрузить видео</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно: Редактор изображений (Cropper) -->
    <div class="modal-overlay" id="crop-image-modal">
        <div class="modal-content">
            <h3>Редактирование значка</h3>
            
            <div class="editor-toolbar">
                <span class="toolbar-label">Формат:</span>
                <button type="button" class="ratio-btn active" data-ratio="1.7777">16:9</button>
                <button type="button" class="ratio-btn" data-ratio="1.3333">4:3</button>
                <button type="button" class="ratio-btn" data-ratio="1">1:1</button>
                <button type="button" class="ratio-btn" data-ratio="NaN">Свободно</button>
            </div>

            <div class="image-crop-container">
                <img id="image-to-crop" src="" alt="Crop preview" style="display: block; max-width: 100%;">
            </div>
            
            <div class="modal-actions">
                <button type="button" class="modal-btn-secondary" id="cancel-crop-btn">Отмена</button>
                <button type="button" class="modal-btn-primary" id="save-crop-btn">Сохранить</button>
            </div>
        </div>
    </div>

    <!-- Модальное окно: Редактор видео (FFmpeg) -->
    <div class="modal-overlay" id="advanced-video-editor-modal">
        <div class="modal-content advanced-editor">
            <h3>Редактор видео</h3>
            
            <div class="editor-main-area">
                <div class="video-preview-wrapper">
                    <video id="video-preview-player" playsinline></video>
                </div>
            </div>

            <div class="timeline-area">
                <div class="timeline-controls">
                    <button id="play-pause-btn" class="timeline-play-btn" title="Воспроизвести / Пауза"></button>
                    <span id="current-time-display">00:00 / 00:00</span>
                </div>
                
                <div class="timeline-wrapper">
                    <div class="track-bg"></div>
                    <div id="trim-selection" class="trim-selection"></div>
                    <div id="trim-handle-start" class="trim-handle" title="Начало обрезки"></div>
                    <div id="trim-handle-end" class="trim-handle" title="Конец обрезки"></div>
                    <div id="timeline-playhead" class="timeline-playhead"></div>
                </div>
                
                <p id="editor-status" class="editor-status"></p>
            </div>

            <div class="modal-actions">
                <button type="button" class="modal-btn-secondary" id="cancel-editor-btn">Отмена</button>
                <button type="button" class="modal-btn-primary" id="apply-editor-btn">Обрезать и сохранить</button>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@ffmpeg/ffmpeg@0.11.6/dist/ffmpeg.min.js" crossorigin="anonymous"></script>
    <script src="/js/upload_helpers.js"></script>

</body>
</html>