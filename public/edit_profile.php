<?php

declare(strict_types=1);

define('CHANGE_INTERVAL_DAYS', 7);
define('MAX_BANNER_SIZE_MB', 6);
define('MAX_AVATAR_SIZE_MB', 4);
define('MAX_USER_LINKS', 5);

require_once __DIR__ . '/../src/core/config.php';
require_once __DIR__ . '/../src/core/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$success_message = '';

if (isset($_SESSION['success_flash'])) {
    $success_message = $_SESSION['success_flash'];
    unset($_SESSION['success_flash']);
}

/**
 * Проверяет, может ли пользователь изменить поле с временным ограничением.
 */
function can_change_field(?string $last_change_date_str): bool
{
    if ($last_change_date_str === null) {
        return true;
    }
    
    try {
        $now = new DateTime();
        $last_change = new DateTime($last_change_date_str);
        $next_change_allowed = $last_change->modify('+' . CHANGE_INTERVAL_DAYS . ' days');
        return $now >= $next_change_allowed;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Обрабатывает загрузку изображения с валидацией.
 */
function handle_image_upload(string $file_key, string $upload_dir_name, int $max_size_mb): array
{
    if (!isset($_FILES[$file_key]) || $_FILES[$file_key]['error'] !== UPLOAD_ERR_OK) {
        return [];
    }

    $file = $_FILES[$file_key];
    $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file['type'], $allowed_mime_types, true) || !in_array($file_extension, $allowed_extensions, true)) {
        return ['error' => 'Неверный формат файла. Разрешены: JPG, PNG, GIF.'];
    }

    if ($file['size'] > $max_size_mb * 1024 * 1024) {
        return ['error' => "Размер файла превышает лимит в {$max_size_mb} МБ."];
    }

    $upload_dir_path = 'uploads/' . $upload_dir_name . '/';
    $full_upload_path = __DIR__ . '/../public/' . $upload_dir_path; // Предполагается, что папка uploads лежит в public

    if (!is_dir($full_upload_path) && !mkdir($full_upload_path, 0775, true)) {
        error_log("Failed to create upload directory: " . $full_upload_path);
        return ['error' => 'Ошибка сервера при создании директории.'];
    }

    $new_filename = 'user_' . $_SESSION['user_id'] . '_' . uniqid('', true) . '.' . $file_extension;

    if (move_uploaded_file($file['tmp_name'], $full_upload_path . $new_filename)) {
        return ['path' => $upload_dir_path . $new_filename];
    }

    error_log("Failed to move uploaded file to " . $full_upload_path . $new_filename);
    return ['error' => 'Не удалось сохранить файл на сервере.'];
}

// --- Обработка POST-запроса ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = 'Ошибка безопасности. Пожалуйста, обновите страницу и попробуйте снова.';
    } else {
        $stmt_current = $conn->prepare("SELECT * FROM users WHERE user_id = ?"); 
        $stmt_current->bind_param("i", $user_id); 
        $stmt_current->execute();
        $current_data = $stmt_current->get_result()->fetch_assoc(); 
        $stmt_current->close();
        
        $channel_name = trim($_POST['channel_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $about_text = trim($_POST['about_text'] ?? '');
        $business_email = trim($_POST['business_email'] ?? '');

        // Валидация названия канала
        if (!can_change_field($current_data['channel_name_last_changed'])) { 
            $channel_name = $current_data['channel_name']; 
        } else {
            if ($channel_name === '') {
                $errors['channel_name'] = 'Название канала не может быть пустым.';
            } elseif (mb_strlen($channel_name) < 3 || mb_strlen($channel_name) > 50) {
                $errors['channel_name'] = 'Название канала должно быть от 3 до 50 символов.';
            }
        }
        
        // Валидация псевдонима (username)
        if (!can_change_field($current_data['username_last_changed'])) { 
            $username = $current_data['username']; 
        } else {
            if ($username === '') {
                $errors['username'] = 'Псевдоним не может быть пустым.';
            } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
                $errors['username'] = 'Псевдоним: латиница, цифры, "_" (3-20 симв.).';
            } elseif ($username !== $current_data['username']) {
                $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE username = ?"); 
                $stmt_check->bind_param("s", $username); 
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    $errors['username'] = 'Этот псевдоним уже занят.';
                }
                $stmt_check->close();
            }
        }
        
        if (mb_strlen($about_text) > 1000) {
            $errors['about_text'] = 'Описание не должно превышать 1000 символов.';
        }
        
        if (!empty($business_email) && !filter_var($business_email, FILTER_VALIDATE_EMAIL)) {
            $errors['business_email'] = 'Пожалуйста, введите корректный адрес электронной почты.';
        }
        
        // Валидация внешних ссылок
        if (isset($_POST['links']) && is_array($_POST['links'])) {
            foreach ($_POST['links'] as $index => $link) {
                $link_title = trim($link['title'] ?? '');
                $link_url = trim($link['url'] ?? '');

                if (($link_title === '' && $link_url !== '') || ($link_title !== '' && $link_url === '')) {
                     $errors['links'][$index]['form'] = 'Пожалуйста, заполните оба поля или оставьте оба пустыми.';
                } elseif ($link_url !== '') {
                    $url_to_validate = $link_url;
                    if (!preg_match("~^(?:f|ht)tps?://~i", $url_to_validate)) {
                        $url_to_validate = 'https://' . $url_to_validate;
                    }
                    if (!filter_var($url_to_validate, FILTER_VALIDATE_URL)) {
                        $errors['links'][$index]['url'] = 'Введите корректный URL.';
                    }
                }
            }
        }
        
        // Сохранение данных
        if (empty($errors)) {
            try {
                $conn->begin_transaction();
                
                $avatar_result = handle_image_upload('avatar', 'avatars', MAX_AVATAR_SIZE_MB); 
                $banner_result = handle_image_upload('banners', 'banners', MAX_BANNER_SIZE_MB);
                
                if (isset($avatar_result['error'])) $errors['avatar'] = 'Аватар: ' . $avatar_result['error']; 
                if (isset($banner_result['error'])) $errors['banner'] = 'Баннер: ' . $banner_result['error'];
                
                if (!empty($errors)) {
                    throw new Exception("File upload error");
                }

                $new_avatar_path = $avatar_result['path'] ?? $current_data['avatar_url']; 
                $new_banner_path = $banner_result['path'] ?? $current_data['banner_image_url'];
                
                $sql_parts = ["about_text = ?", "avatar_url = ?", "banner_image_url = ?", "business_email = ?"]; 
                $types = "ssss"; 
                $params = [$about_text, $new_avatar_path, $new_banner_path, $business_email];
                
                if ($username !== $current_data['username']) { 
                    $sql_parts[] = "username = ?"; 
                    $sql_parts[] = "username_last_changed = NOW()"; 
                    $types .= "s"; 
                    $params[] = $username; 
                }
                
                if ($channel_name !== $current_data['channel_name']) { 
                    $sql_parts[] = "channel_name = ?"; 
                    $sql_parts[] = "channel_name_last_changed = NOW()"; 
                    $types .= "s"; 
                    $params[] = $channel_name; 
                }
                
                $params[] = $user_id; 
                $types .= "i"; 
                
                $sql = "UPDATE users SET " . implode(', ', $sql_parts) . " WHERE user_id = ?";
                $stmt_update = $conn->prepare($sql); 
                $stmt_update->bind_param($types, ...$params); 
                $stmt_update->execute(); 
                $stmt_update->close();
                
                // Обновление ссылок
                $stmt_delete_links = $conn->prepare("DELETE FROM user_links WHERE user_id = ?");
                $stmt_delete_links->bind_param("i", $user_id);
                $stmt_delete_links->execute();
                $stmt_delete_links->close();
                
                if (!empty($_POST['links']) && is_array($_POST['links'])) {
                    $stmt_insert_link = $conn->prepare("INSERT INTO user_links (user_id, link_title, link_url) VALUES (?, ?, ?)");
                    $links_to_add = array_slice($_POST['links'], 0, MAX_USER_LINKS);
                    
                    foreach ($links_to_add as $link) {
                        $link_title = trim($link['title']);
                        $link_url = trim($link['url']);
                        if ($link_title !== '' && $link_url !== '') {
                            $stmt_insert_link->bind_param("iss", $user_id, $link_title, $link_url);
                            $stmt_insert_link->execute();
                        }
                    }
                    $stmt_insert_link->close();
                }

                // Обновление рекомендаций (каналов)
                $stmt_get_sections = $conn->prepare("SELECT section_id FROM recommendation_sections WHERE user_id = ?");
                $stmt_get_sections->bind_param("i", $user_id);
                $stmt_get_sections->execute();
                $sections_res = $stmt_get_sections->get_result();
                $section_ids_to_delete = [];
                
                while ($row = $sections_res->fetch_assoc()) { 
                    $section_ids_to_delete[] = $row['section_id']; 
                }
                $stmt_get_sections->close();
                
                if (!empty($section_ids_to_delete)) {
                    $ids_placeholder = implode(',', array_fill(0, count($section_ids_to_delete), '?'));
                    $types_str = str_repeat('i', count($section_ids_to_delete));
                    
                    $stmt_del_channels = $conn->prepare("DELETE FROM recommended_channels WHERE section_id IN ($ids_placeholder)");
                    $stmt_del_channels->bind_param($types_str, ...$section_ids_to_delete);
                    $stmt_del_channels->execute();
                    $stmt_del_channels->close();
                    
                    $stmt_del_sections = $conn->prepare("DELETE FROM recommendation_sections WHERE section_id IN ($ids_placeholder)");
                    $stmt_del_sections->bind_param($types_str, ...$section_ids_to_delete);
                    $stmt_del_sections->execute();
                    $stmt_del_sections->close();
                }
                
                if (!empty($_POST['recommendations']) && is_array($_POST['recommendations'])) {
                    $stmt_insert_section = $conn->prepare("INSERT INTO recommendation_sections (user_id, title) VALUES (?, ?)");
                    $stmt_insert_channel = $conn->prepare("INSERT INTO recommended_channels (section_id, recommended_channel_id) VALUES (?, ?)");
                    $stmt_find_user = $conn->prepare("SELECT user_id FROM users WHERE public_user_id = ? LIMIT 1");
                    
                    foreach (array_slice($_POST['recommendations'], 0, 3) as $section_data) {
                        $title = trim($section_data['title'] ?? 'Рекомендуемые каналы') ?: 'Рекомендуемые каналы';
                        
                        $stmt_insert_section->bind_param("is", $user_id, $title);
                        $stmt_insert_section->execute();
                        $new_section_id = $conn->insert_id;
                        
                        if ($new_section_id && !empty($section_data['channels']) && is_array($section_data['channels'])) {
                            foreach (array_slice($section_data['channels'], 0, 10) as $channel_public_id) {
                                $stmt_find_user->bind_param("s", $channel_public_id);
                                $stmt_find_user->execute();
                                $result = $stmt_find_user->get_result();
                                
                                if ($result->num_rows > 0) {
                                    $channel_numeric_id = (int)$result->fetch_assoc()['user_id'];
                                    $stmt_insert_channel->bind_param("ii", $new_section_id, $channel_numeric_id);
                                    $stmt_insert_channel->execute();
                                }
                            }
                        }
                    }
                    $stmt_insert_section->close();
                    $stmt_insert_channel->close();
                    $stmt_find_user->close();
                }
                
                $conn->commit(); 
                
                $_SESSION['success_flash'] = "Изменения успешно сохранены!";
                $redirect_url = "/edit_profile.php";
                if (isset($_POST['from']) && $_POST['from'] === 'settings') {
                    $redirect_url .= "?from=settings";
                }
                header("Location: " . $redirect_url);
                exit;

            } catch (Exception $e) { 
                $conn->rollback(); 
                error_log("Profile update failed for user_id {$user_id}: " . $e->getMessage()); 
                $errors['form'] = "Произошла внутренняя ошибка сервера. Попробуйте позже."; 
            }
        }
    }
}

// --- Подготовка данных для HTML ---
$stmt_get_user = $conn->prepare("SELECT * FROM users WHERE user_id = ?"); 
$stmt_get_user->bind_param("i", $user_id); 
$stmt_get_user->execute();
$user_data = $stmt_get_user->get_result()->fetch_assoc(); 
$stmt_get_user->close(); 

$is_username_locked = !can_change_field($user_data['username_last_changed']);
$is_channel_name_locked = !can_change_field($user_data['channel_name_last_changed']);
$username_tooltip = ''; 
$channel_name_tooltip = '';
$now = new DateTime();

if ($is_username_locked) {
    $next_change_date = (new DateTime($user_data['username_last_changed']))->modify('+' . CHANGE_INTERVAL_DAYS . ' days');
    $days_left = ($now->diff($next_change_date))->days + 1;
    $username_tooltip = "Изменить можно будет через " . $days_left . ' ' . get_plural_form($days_left, ['день', 'дня', 'дней']);
}

if ($is_channel_name_locked) {
    $next_change_date = (new DateTime($user_data['channel_name_last_changed']))->modify('+' . CHANGE_INTERVAL_DAYS . ' days');
    $days_left = ($now->diff($next_change_date))->days + 1;
    $channel_name_tooltip = "Изменить можно будет через " . $days_left . ' ' . get_plural_form($days_left, ['день', 'дня', 'дней']);
}

$user_recommendations = [];
$stmt_sections = $conn->prepare("SELECT section_id, title FROM recommendation_sections WHERE user_id = ? ORDER BY section_id ASC");
$stmt_sections->bind_param("i", $user_id);
$stmt_sections->execute();
$sections_result = $stmt_sections->get_result();

while ($section = $sections_result->fetch_assoc()) {
    $stmt_channels = $conn->prepare("
        SELECT u.user_id, u.public_user_id, u.username, u.channel_name, u.avatar_url 
        FROM recommended_channels rc 
        JOIN users u ON rc.recommended_channel_id = u.user_id 
        WHERE rc.section_id = ? LIMIT 10
    ");
    $stmt_channels->bind_param("i", $section['section_id']);
    $stmt_channels->execute();
    $section['channels'] = $stmt_channels->get_result()->fetch_all(MYSQLI_ASSOC);
    $user_recommendations[] = $section;
    $stmt_channels->close();
}
$stmt_sections->close();

$stmt_links = $conn->prepare("SELECT link_title, link_url FROM user_links WHERE user_id = ? ORDER BY link_id ASC");
$stmt_links->bind_param("i", $user_id);
$stmt_links->execute();
$user_links = $stmt_links->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_links->close();

$is_from_settings = (isset($_GET['from']) && $_GET['from'] === 'settings') || (isset($_POST['from']) && $_POST['from'] === 'settings');

if ($is_from_settings) {
    $back_link_url = '/other/settings.php?tab=channel';
    $back_link_text = 'Вернуться в настройки';
} else {
    $back_link_url = '/profile.php?id=' . htmlspecialchars($user_data['public_user_id']);
    $back_link_text = 'Вернуться в профиль';
}

$hide_header = true;
$additional_styles = ['/css/edit_profile.css'];
require_once __DIR__ . '/../templates/partials/header.php';
?>

<!-- Уведомления (передача в JS) -->
<?php if (!empty($errors) || !empty($success_message)): ?>
    <script id="php-notifications" type="application/json">
        <?= json_encode(array_merge(
            !empty($success_message) ? [['message' => $success_message, 'type' => 'success']] : [],
            array_map(function ($field, $message) {
                return ['message' => $message, 'type' => 'error', 'field' => $field];
            }, array_keys($errors), $errors)
        )); ?>
    </script>
<?php endif; ?>

<a href="<?= htmlspecialchars($back_link_url) ?>" class="back-to-site-link">
    <img src="/images/back.png" alt="<">
    <span><?= htmlspecialchars($back_link_text) ?></span>
</a>

<main class="main-content-full-width">
    <div class="profile-edit-container">
        <h1>Настройки канала</h1>
        <form action="/edit_profile.php" method="POST" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? ''); ?>">
            <?php if ($is_from_settings): ?>
                <input type="hidden" name="from" value="settings">
            <?php endif; ?>
            
            <section class="form-section">
                <h2>Оформление</h2>
                
                <div class="form-group image-upload-section">
                    <div class="image-preview-area banner-preview">
                        <?php
                            $banner_url = $user_data['banner_image_url'] ?? 'images/default_banner.jpg';
                            $is_default_banner = str_contains($banner_url, 'default_banner.jpg');
                        ?>
                        <div class="image-preview <?= $is_default_banner ? 'has-placeholder' : '' ?>" 
                             id="banner-preview" 
                             data-original-image="url('/<?= htmlspecialchars($banner_url); ?>?v=<?= time() ?>')" 
                             style="background-image: url('/<?= htmlspecialchars($banner_url); ?>?v=<?= time() ?>');">
                        </div>
                    </div>
                    <div class="upload-info">
                        <h3>Баннер</h3>
                        <p class="image-upload-description">Рекомендуем загрузить изображение размером не менее <strong>1920 x 480</strong> пикселей. Размер файла – не более <strong><?= MAX_BANNER_SIZE_MB; ?> МБ</strong>.</p>
                        <div class="upload-actions">
                            <input type="file" name="banner" id="banner-input" accept="image/jpeg,image/png,image/gif" style="display: none;">
                            <label for="banner-input" class="upload-button">Загрузить</label>
                            <button type="button" class="preview-toggle-button">Предпросмотр</button>
                        </div>
                    </div>
                </div>
                
                <div class="form-group image-upload-section">
                    <div class="image-preview-area avatar-preview">
                         <?php
                            $avatar_url = $user_data['avatar_url'] ?? 'images/default_avatar.png';
                            $is_default_avatar = str_contains($avatar_url, 'default_avatar.png');
                        ?>
                        <div class="image-preview <?= $is_default_avatar ? 'has-placeholder' : '' ?>" 
                             id="avatar-preview" 
                             data-original-image="url('/<?= htmlspecialchars($avatar_url); ?>?v=<?= time() ?>')" 
                             style="background-image: url('/<?= htmlspecialchars($avatar_url); ?>?v=<?= time() ?>');">
                            <div class="avatar-placeholder"><img src="/images/play_arrow.png" alt="Загрузить"></div>
                        </div>
                    </div>
                    <div class="upload-info">
                        <h3>Фото профиля</h3>
                        <p class="image-upload-description">Рекомендуем использовать изображение размером не менее <strong>98 x 98</strong> пикселей. Размер файла – не более <strong><?= MAX_AVATAR_SIZE_MB; ?> МБ</strong>. Если вы хотите узнать, идет ли вам аватар, можете нажать на <button type="button" class="scroll-to-preview-link">предпросмотр</button>.</p>
                         <div class="upload-actions">
                            <input type="file" name="avatar" id="avatar-input" accept="image/jpeg,image/png,image/gif" style="display: none;">
                            <label for="avatar-input" class="upload-button">Загрузить</label>
                        </div>
                    </div>
                </div>
                
                <div class="live-preview-container" id="live-preview-container">
                    <div class="live-preview-wrapper">
                        <div class="channel-banner-preview" id="live-banner-preview" style="background-image: url('/<?= htmlspecialchars($user_data['banner_image_url'] ?? 'images/default_banner.png'); ?>?v=<?= time() ?>');"></div>
                        <div class="profile-header-preview">
                            <div class="profile-info-preview">
                                <div class="profile-avatar-preview" id="live-avatar-preview" style="background-image: url('/<?= htmlspecialchars($user_data['avatar_url'] ?? 'images/default_avatar.png'); ?>?v=<?= time() ?>');"></div>
                                <div class="profile-text-preview">
                                    <h1 id="live-channel-name-preview"><?= htmlspecialchars($user_data['channel_name'] ?? $user_data['username']); ?></h1>
                                    <div class="profile-meta-preview">
                                        <span id="live-username-preview">@<?= htmlspecialchars($user_data['username']); ?></span>
                                        <span>100 тыс. подписчиков</span>
                                        <span>152 видео</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="form-section">
                <h2>Основные сведения</h2>
                <div class="form-group <?= $is_channel_name_locked ? 'is-locked' : ''; ?>">
                    <label for="channel_name">Название канала</label>
                    <p class="input-description">Название можно менять раз в <?= CHANGE_INTERVAL_DAYS; ?> дней. Если вы хотите узнать, подходит ли название, можете нажать на <button type="button" class="scroll-to-preview-link">предпросмотр</button>.</p>
                    <div class="input-with-icon">
                        <input type="text" id="channel_name" name="channel_name" placeholder="Введите название канала..." value="<?= htmlspecialchars($user_data['channel_name'] ?? ''); ?>" data-original-value="<?= htmlspecialchars($user_data['channel_name'] ?? ''); ?>" maxlength="50" required <?= $is_channel_name_locked ? 'disabled' : ''; ?>>
                        <?php if ($is_channel_name_locked): ?>
                            <div class="info-icon" data-tooltip="<?= htmlspecialchars($channel_name_tooltip); ?>"><img src="/images/info.png" alt="i"></div>
                        <?php endif; ?>
                    </div>
                    <div class="char-counter"><span>0</span> / 50</div>
                </div>
                
                <div class="form-group <?= $is_username_locked ? 'is-locked' : ''; ?>">
                    <label for="username">Псевдоним (@логин)</label>
                    <p class="input-description">Псевдоним можно менять раз в <?= CHANGE_INTERVAL_DAYS; ?> дней. Если вы хотите узнать, подходит ли псевдоним, можете нажать на <button type="button" class="scroll-to-preview-link">предпросмотр</button>.</p>
                    <div class="input-with-icon">
                        <input type="text" id="username" name="username" placeholder="Введите псевдоним..." value="<?= htmlspecialchars($user_data['username'] ?? ''); ?>" data-original-value="<?= htmlspecialchars($user_data['username'] ?? ''); ?>" maxlength="20" required <?= $is_username_locked ? 'disabled' : ''; ?>>
                        <?php if ($is_username_locked): ?>
                            <div class="info-icon" data-tooltip="<?= htmlspecialchars($username_tooltip); ?>"><img src="/images/info.png" alt="i"></div>
                        <?php endif; ?>
                    </div>
                    <div class="char-counter"><span>0</span> / 20</div>
                </div>
                
                <div class="form-group">
                    <label for="about_text">Описание канала</label>
                    <textarea id="about_text" name="about_text" data-original-value="<?= htmlspecialchars($user_data['about_text'] ?? ''); ?>" maxlength="1000" placeholder="Расскажите аудитории о своем канале..."><?= htmlspecialchars($user_data['about_text'] ?? ''); ?></textarea>
                    <div class="char-counter"><span>0</span> / 1000</div>
                </div>
            </section>
            
            <section class="form-section">
                <h2>URL канала</h2>
                <p class="input-description">Это постоянный веб-адрес вашего канала. Вы можете поделиться им с другими.</p>
                <div class="channel-url-container">
                    <?php $channel_url_display = 'http://' . $_SERVER['HTTP_HOST'] . '/profile.php?id=' . htmlspecialchars($user_data['public_user_id']); ?>
                    <span class="channel-url-text"><?= $channel_url_display; ?></span>
                    <button type="button" class="copy-url-button" data-clipboard-text="<?= $channel_url_display; ?>" title="Копировать ссылку"><img src="/images/copy.png" alt="Копировать"></button>
                </div>
            </section>

            <section class="form-section">
                <h2>Контактная информация</h2>
                <p class="input-description">Укажите, как связаться с вами по вопросам сотрудничества. Зрители могут увидеть адрес электронной почты на вкладке "О канале".</p>
                <div class="form-group-material">
                    <input type="email" id="business_email" name="business_email" placeholder=" " value="<?= htmlspecialchars($user_data['business_email'] ?? ''); ?>" maxlength="255">
                    <label for="business_email">Адрес электронной почты</label>
                </div>
                <?php if (isset($errors['business_email'])): ?>
                    <div class="input-error-bubble visible" style="position: static; margin-top: 8px;"><?= htmlspecialchars($errors['business_email']); ?></div>
                <?php endif; ?>
            </section>

            <section class="form-section">
                <h2>Ссылки</h2>
                <p class="input-description">Поделитесь внешними ссылками с аудиторией. Они будут видны в профиле канала и на вкладке "О канале".</p>
                <div id="links-container"></div>
                <button type="button" id="add-link-btn" class="add-link-button">
                    <img src="/images/add_link.png" alt="+">
                    <span>Добавить ссылку</span>
                </button>
            </section>

            <section class="form-section">
                <h2>Рекомендуемые каналы</h2>
                <p class="input-description">Создайте до 3-х секций с рекомендованными каналами, которые будут отображаться на странице вашего профиля. В каждую секцию можно добавить до 10 каналов.</p>
                <div id="recommendations-container"></div>
                <button type="button" id="add-recommendation-section-btn" class="add-section-button">
                    <img src="/images/add_account.png" alt="+">
                    <span>Добавить секцию</span>
                </button>
            </section>

            <div class="form-actions-static">
                <button type="button" id="cancel-changes-btn" class="cancel-button">Отменить</button>
                <button type="submit" class="submit-button">Сохранить</button>
            </div>
        </form>
    </div>
</main>

<script>
    const existingRecommendations = <?= json_encode($user_recommendations ?? []) ?>;
    const existingLinks = <?= json_encode($user_links ?? []) ?>;
    const MAX_LINKS = <?= MAX_USER_LINKS ?>;
</script>
<script src="/js/lib/Sortable.min.js"></script>
<script src="/js/edit_profile.js?v=<?= time() ?>"></script>
</body>
</html>