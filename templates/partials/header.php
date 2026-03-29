<?php
// ===================================================================
//  /templates/partials/header.php - ФИНАЛЬНАЯ ВЕРСИЯ С АДМИН-ПАНЕЛЬЮ
// ===================================================================

require_once __DIR__ . '/../../src/core/config.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if (isset($_SESSION['user_id'])) {
    $stmt_check = $conn->prepare("SELECT status FROM users WHERE user_id = ?");
    $stmt_check->bind_param("i", $_SESSION['user_id']);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result();
    
    if ($user_check = $res_check->fetch_assoc()) {
        if ($user_check['status'] === 'banned') {
            
            session_unset();
            session_destroy();
            
            if (isset($_COOKIE['remember_token'])) {
                setcookie('remember_token', '', time() - 3600, '/');
            }

            header("Location: /login.php?reason=banned");
            exit();
        }
    }
    $stmt_check->close();
}
// --- КОНЕЦ ПРОВЕРКИ ---

$currentPage = basename($_SERVER['SCRIPT_NAME']);
$body_classes = []; 

if ($currentPage !== 'index.php') {
    $hide_category_filters = true;
}

if (!empty($hide_category_filters)) {
    $body_classes[] = 'no-category-filters';
}

$current_search_query = htmlspecialchars($_GET['search_query'] ?? '', ENT_QUOTES, 'UTF-8');

$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LensEra</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/main.css">
    <link rel="stylesheet" href="/css/common_modals.css">
    <link rel="icon" type="image/png" href="/images/my-icon.png">

    <?php 
    if (isset($additional_styles) && is_array($additional_styles)): 
        foreach ($additional_styles as $style): 
    ?>
            <link rel="stylesheet" href="<?php echo htmlspecialchars($style); ?>">
    <?php 
        endforeach; 
    endif; 
    ?>

    <script>
        const isUserLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
        const userHasChannel = <?php echo (isset($_SESSION['channel_name']) && !empty($_SESSION['channel_name'])) ? 'true' : 'false'; ?>;
        const currentUserAvatarUrl = <?php echo isset($_SESSION['avatar_url']) ? json_encode('/' . $_SESSION['avatar_url']) : 'null'; ?>;
        const csrfToken = '<?php echo $csrf_token; ?>';
    </script>
</head>
<body class="<?php echo implode(' ', $body_classes); ?>">

<?php if (empty($hide_header)): ?>
    <header class="main-header">
        <div class="header-top">
            <div class="header-left">
                <button class="icon-button menu-button" title="Меню">
                    <img class="icon-img" src="/images/menu.png" alt="Меню">
                </button>
                <a href="/" class="logo">
                    <img class="logo-icon" src="/images/LE.png" alt="Logo">
                    <span class="logo-text">LensEra</span>
                </a>
            </div>
            
            <div class="header-center">
                <form class="search-container" id="search-form">
                    <div class="search-input-wrapper">
                        <input type="text" id="search-input" placeholder="Введите запрос" autocomplete="off" value="<?php echo $current_search_query; ?>">
                        <button type="button" class="clear-search-btn" id="clear-search-btn" title="Очистить" style="display: none;">&times;</button>
                    </div>
                    <button type="submit" class="search-button" title="Поиск">
                        <img class="icon-img" src="/images/search.png" alt="Search">
                    </button>
                    <div id="search-suggestions-container"></div>
                </form>
                <button type="button" class="voice-search-button" title="Голосовой поиск">
                    <img class="icon-img" src="/images/micro.png" alt="Voice Search">
                </button>
            </div>

            <div class="header-right">
            <!-- УВЕДОМЛЕНИЯ -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="notification-wrapper">
                    <button class="icon-button" id="notification-btn" title="Уведомления">
                        <!-- Замените src на путь к вашей иконке колокольчика -->
                        <img class="icon-img" src="/images/bell.png" alt="Уведомления">
                        
                        <!-- Счетчик (скрыт по умолчанию) -->
                        <span class="notification-badge" id="notification-badge" style="display: none;">0</span>
                    </button>
                    
                    <!-- Выпадающее меню -->
                    <div class="notification-dropdown" id="notification-dropdown">
                        <div class="notification-header">
                            <h3>Уведомления</h3>
                        </div>
                        <div class="notification-list" id="notification-list">
                            <div class="notification-loading">Загрузка...</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <!-- МЕНЮ ДЛЯ АВТОРИЗОВАННОГО ПОЛЬЗОВАТЕЛЯ -->
                    <div class="user-menu-container">
                        <button id="user-menu-trigger" class="user-menu-trigger-btn menu-trigger-btn">
                            <img class="user-avatar" src="/<?php echo htmlspecialchars($_SESSION['avatar_url']); ?>" alt="Аватар">
                        </button>
            
                        <div class="user-menu-dropdown dropdown-menu">
                            <div class="user-menu-main-panel">
                                <div class="user-menu-header">
                                    <img class="user-avatar-large" src="/<?php echo htmlspecialchars($_SESSION['avatar_url']); ?>" alt="Аватар">
                                    <div class="user-info-text">
                                        <div class="user-name"><?php echo htmlspecialchars($_SESSION['channel_name'] ?? $_SESSION['username']); ?></div>
                                        <div class="user-handle">@<?php echo htmlspecialchars($_SESSION['username']); ?></div>
                                    </div>
                                </div>
                                <a href="/profile.php?id=<?php echo htmlspecialchars($_SESSION['public_user_id'] ?? ''); ?>" class="user-menu-channel-link">Посмотреть канал</a>
                                
                                <hr class="user-menu-divider">
                                
                                <!-- ССЫЛКА НА АДМИН ПАНЕЛЬ (ВИДНА ТОЛЬКО АДМИНУ) -->
                                <?php if ($is_admin): ?>
                                    <a href="/admin/users.php" class="user-menu-item" style="color: #3ea6ff;">
                                        <img src="/images/settings.png" alt="" class="user-menu-icon" style="filter: invert(53%) sepia(98%) saturate(1478%) hue-rotate(190deg) brightness(101%) contrast(101%);">
                                        <span>Панель админа</span>
                                    </a>
                                    <hr class="user-menu-divider">
                                <?php endif; ?>
                                
                                <a href="/pages/you.php" class="user-menu-item"><img src="/images/switch_account.png" alt="" class="user-menu-icon"><span>Сменить аккаунт</span></a>
                                <form action="/logout.php" method="POST" class="user-menu-item-form">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <button type="submit" class="user-menu-item as-button"><img src="/images/logout.png" alt="" class="user-menu-icon"><span>Выйти</span></button>
                                </form>
                                <hr class="user-menu-divider">
                                <a href="/upload.php?from=home" class="user-menu-item requires-channel"><img src="/images/upload.png" alt="" class="user-menu-icon"><span>Создать видео</span></a>
                                <hr class="user-menu-divider">
                                <div class="user-menu-item theme-menu-trigger"><img src="/images/theme.png" alt="" class="user-menu-icon"><span>Тема: как на устройстве</span><span class="arrow-right">&gt;</span></div>
                                <div class="user-menu-item language-menu-trigger"><img src="/images/language.png" alt="" class="user-menu-icon"><span>Язык: Русский</span><span class="arrow-right">&gt;</span></div>
                                <hr class="user-menu-divider">
                                <a href="/other/settings.php" class="user-menu-item"><img src="/images/settings.png" alt="" class="user-menu-icon"><span>Настройки</span></a>
                                <hr class="user-menu-divider">
                                <a href="/other/help.php" class="user-menu-item"><img src="/images/quest.png" alt="" class="user-menu-icon"><span>Справка</span></a>
                                <a href="/other/reviews.php" class="user-menu-item"><img src="/images/alert.png" alt="" class="user-menu-icon"><span>Отправить отзыв</span></a>
                            </div>
                            <!-- Подменю -->
                            <div class="user-submenu" data-submenu="theme">
                                <div class="user-submenu-header">
                                    <button class="user-submenu-back-btn"><img src="/images/back.png" alt="Назад"></button>
                                    <h3>Тема</h3>
                                </div>
                                <div class="user-menu-item" data-theme="system"><img src="/images/check1.png" class="checkmark"><span>Как на устройстве</span></div>
                                <div class="user-menu-item" data-theme="dark"><img src="/images/check1.png" class="checkmark"><span>Темная</span></div>
                                <div class="user-menu-item" data-theme="light"><img src="/images/check1.png" class="checkmark"><span>Светлая</span></div>
                            </div>
                            <div class="user-submenu" data-submenu="language">
                                 <div class="user-submenu-header">
                                    <button class="user-submenu-back-btn"><img src="/images/back.png" alt="Назад"></button>
                                    <h3>Язык</h3>
                                </div>
                                <div class="user-menu-item" data-lang="ru"><img src="/images/check1.png" class="checkmark"><span>Русский</span></div>
                                <div class="user-menu-item" data-lang="en"><img src="/images/check1.png" class="checkmark"><span>English</span></div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- МЕНЮ ДЛЯ ГОСТЯ (НЕАВТОРИЗОВАННОГО ПОЛЬЗОВАТЕЛЯ) -->
                    <div class="guest-menu-container">
                        <button class="icon-button guest-menu-trigger menu-trigger-btn" title="Настройки">
                            <img class="icon-img" src="/images/menu-history.png" alt="Меню">
                        </button>
                        <div class="user-menu-dropdown dropdown-menu">
                            <div class="user-menu-main-panel">
                                <a href="/login.php" class="user-menu-item"><img src="/images/upload.png" alt="" class="user-menu-icon"><span>Создать видео</span></a>
                                <hr class="user-menu-divider">
                                <div class="user-menu-item theme-menu-trigger"><img src="/images/theme.png" alt="" class="user-menu-icon"><span>Тема: как на устройстве</span><span class="arrow-right">&gt;</span></div>
                                <div class="user-menu-item language-menu-trigger"><img src="/images/language.png" alt="" class="user-menu-icon"><span>Язык: Русский</span><span class="arrow-right">&gt;</span></div>
                                <hr class="user-menu-divider">
                                <a href="/other/settings.php" class="user-menu-item"><img src="/images/settings.png" alt="" class="user-menu-icon"><span>Настройки</span></a>
                                <hr class="user-menu-divider">
                                <a href="/other/help.php" class="user-menu-item"><img src="/images/quest.png" alt="" class="user-menu-icon"><span>Справка</span></a>
                                <a href="/other/reviews.php" class="user-menu-item"><img src="/images/alert.png" alt="" class="user-menu-icon"><span>Отправить отзыв</span></a>
                            </div>
                            <!-- Подменю -->
                            <div class="user-submenu" data-submenu="theme">
                                <div class="user-submenu-header">
                                    <button class="user-submenu-back-btn"><img src="/images/back.png" alt="Назад"></button>
                                    <h3>Тема</h3>
                                </div>
                                <div class="user-menu-item" data-theme="system"><img src="/images/check1.png" class="checkmark"><span>Как на устройстве</span></div>
                                <div class="user-menu-item" data-theme="dark"><img src="/images/check1.png" class="checkmark"><span>Темная</span></div>
                                <div class="user-menu-item" data-theme="light"><img src="/images/check1.png" class="checkmark"><span>Светлая</span></div>
                            </div>
                            <div class="user-submenu" data-submenu="language">
                                 <div class="user-submenu-header">
                                    <button class="user-submenu-back-btn"><img src="/images/back.png" alt="Назад"></button>
                                    <h3>Язык</h3>
                                </div>
                                <div class="user-menu-item" data-lang="ru"><img src="/images/check1.png" class="checkmark"><span>Русский</span></div>
                                <div class="user-menu-item" data-lang="en"><img src="/images/check1.png" class="checkmark"><span>English</span></div>
                            </div>
                        </div>
                    </div>
                    <a href="/login.php" title="Войти" class="login-button-header">
                        <img class="user-avatar guest-icon" src="/images/profile.png" alt="Аватар">
                        <span>Войти</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if (empty($hide_category_filters)): ?>
            <nav class="category-filters">
                <a href="#" class="filter-item active" data-filter="video">Все</a>
                <a href="#" class="filter-item" data-filter="music">Музыка</a>
                <a href="#" class="filter-item" data-filter="movies">Фильмы и сериалы</a>
                <a href="#" class="filter-item" data-filter="playlist">Плейлисты</a>
                <a href="#" class="filter-item" data-filter="games">Видеоигры</a>
                <a href="#" class="filter-item" data-filter="news">Новости</a>
                <a href="#" class="filter-item" data-filter="sport">Спорт</a>
                <a href="#" class="filter-item" data-filter="courses">Курсы</a>
                <a href="#" class="filter-item" data-filter="fashion">Мода и красота</a>
                <a href="#" class="filter-item" data-filter="recent">Недавно опубликованные</a>
            </nav>
        <?php endif; ?>
    </header>
<?php endif; ?>

<!-- МОДАЛЬНОЕ ОКНО СОЗДАНИЯ КАНАЛА -->
<div id="create-channel-modal" class="modal-overlay">
    <div class="modal-content improved-modal">
        <button class="modal-close-btn">&times;</button>
        <h2>Создание канала</h2>
        <p>Придумайте уникальное название и загрузите аватар, чтобы персонализировать ваш канал.</p>
        
        <form id="create-channel-form" enctype="multipart/form-data">
            <div class="avatar-upload-container">
                <label for="channel_avatar" class="avatar-preview" id="avatar-preview" title="Нажмите, чтобы загрузить аватар">
                    <img src="/images/camera-icon.png" class="avatar-edit-icon" id="avatar-icon">
                </label>
                <input type="file" id="channel_avatar" name="avatar" accept="image/*" style="display: none;">
            </div>
            <div class="form-group">
                <label for="channel_name">Название канала</label>
                <input type="text" id="channel_name" name="channel_name" required placeholder="Например, «Игровые приключения»">
            </div>
            <div id="modal-error" class="error-message"></div>
            <button type="submit" class="modal-submit-btn">Создать канал</button>
        </form>
    </div>
</div>

<!-- МОДАЛЬНОЕ ОКНО ГОЛОСОВОГО ПОИСКА -->
<div id="voice-search-modal" class="modal-overlay">
    <div class="voice-modal-content">
        <button class="modal-close-btn" id="close-voice-modal">&times;</button>
        <div id="voice-mic-icon-container">
            <img src="/images/micro.png" alt="Микрофон" class="icon-img">
        </div>
        <h2 id="voice-modal-title">Говорите...</h2>
        <p id="voice-interim-results"></p>
        <p id="voice-modal-status">Ожидание речи</p>
    </div>
</div> 