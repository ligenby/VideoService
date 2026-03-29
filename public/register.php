<?php

require_once __DIR__ . '/../src/core/config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

// --- Мультиязычность ---
$curr_lang = $_COOKIE['lang'] ?? 'ru';

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en'], true)) {
    $curr_lang = $_GET['lang'];
    setcookie('lang', $curr_lang, time() + (86400 * 30), "/");
} elseif (!in_array($curr_lang, ['ru', 'en'], true)) {
    $curr_lang = 'ru';
}

$lang_file = __DIR__ . "/../src/lang/{$curr_lang}.php";
$lang = file_exists($lang_file) ? require $lang_file : [];

function t(string $key): string {
    global $lang;
    return $lang[$key] ?? $key;
}

// --- TODO: Вынести генерацию аватара в /src/core/helpers.php ---
if (!function_exists('generate_avatar')) {
    function generate_avatar(string $username): string {
        $root_path = dirname(__DIR__);
        $font_path = $root_path . '/public/fonts/Roboto-Regular.ttf';
        $output_dir = $root_path . '/public/profile/';
    
        if (!file_exists($font_path)) {
            error_log("Avatar Error: Font not found at " . $font_path);
            return 'images/default_avatar.png';
        }
    
        $image = imagecreatetruecolor(200, 200);
        $first_letter = mb_strtoupper(mb_substr($username, 0, 1, 'UTF-8'));
        
        $colors = [[255, 193, 7], [255, 87, 34], [33, 150, 243], [76, 175, 80]];
        $c = $colors[array_rand($colors)];
        
        $bg_color = imagecolorallocate($image, $c[0], $c[1], $c[2]);
        $text_color = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bg_color);
    
        $font_size = 80;
        $bbox = imagettfbbox($font_size, 0, $font_path, $first_letter);
        $x = (int)round((200 - ($bbox[2] - $bbox[0])) / 2);
        $y = (int)round((200 + ($bbox[1] - $bbox[7])) / 2);
        
        imagettftext($image, $font_size, 0, $x, $y, $text_color, $font_path, $first_letter);
    
        if (!is_dir($output_dir) && !mkdir($output_dir, 0775, true)) {
            error_log("Avatar Error: Cannot create dir " . $output_dir);
            imagedestroy($image);
            return 'images/default_avatar.png';
        }
    
        $filename = 'avatar_' . uniqid('', true) . '.png';
        $full_path = $output_dir . $filename;
        
        if (!imagepng($image, $full_path)) {
            error_log("Avatar Error: Cannot save file to " . $full_path);
            imagedestroy($image);
            return 'images/default_avatar.png';
        }
        
        imagedestroy($image);
        return 'profile/' . $filename;
    }
}
// ----------------------------------------------------------------

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors['form'] = t('err_security');
    } else {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!preg_match('/^[a-zA-Z0-9]{3,20}$/', $username)) {
            $errors['username'] = t('err_user_format');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = t('err_email_invalid');
        }
        
        $password_checks = [];
        if (strlen($password) < 8) $password_checks[] = t('err_pass_min');
        if (!preg_match('/[A-Z]/', $password)) $password_checks[] = t('err_pass_upper');
        if (!preg_match('/[a-z]/', $password)) $password_checks[] = t('err_pass_lower');
        if (!preg_match('/[0-9]/', $password)) $password_checks[] = t('err_pass_digit');
        
        if (!empty($password_checks)) {
            $errors['password'] = t('err_pass_header') . implode("\n- ", $password_checks);
        }
        
        if (!isset($errors['username'])) {
            $stmt_user = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt_user->bind_param("s", $username);
            $stmt_user->execute();
            if ($stmt_user->get_result()->num_rows > 0) {
                $errors['username'] = t('err_user_taken');
            }
            $stmt_user->close();
        }
        
        if (!isset($errors['email'])) {
            $stmt_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt_email->bind_param("s", $email);
            $stmt_email->execute();
            if ($stmt_email->get_result()->num_rows > 0) {
                $errors['email'] = t('err_email_taken');
            }
            $stmt_email->close();
        }

        if (empty($errors)) {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $avatar_url = generate_avatar($username);
            $public_user_id = generate_unique_id($conn, 'users', 'public_user_id');
            
            $stmt = $conn->prepare("
                INSERT INTO users (public_user_id, username, email, password_hash, avatar_url, registration_date) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("sssss", $public_user_id, $username, $email, $password_hash, $avatar_url);
            
            if ($stmt->execute()) {
                $redirect_url = "/login.php?registration=success&username=" . urlencode($username);
                if ($curr_lang !== 'ru') {
                    $redirect_url .= "&lang=" . $curr_lang;
                }
                header("Location: " . $redirect_url);
                exit;
            } else {
                $errors['form'] = t('err_db');
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($curr_lang) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars(t('register_title')) ?> — VideoService</title>
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body>
<main class="auth-page-container">
    <div class="auth-form">
        
        <div class="lang-switcher">
            <a href="?lang=ru" class="<?= $curr_lang === 'ru' ? 'active' : '' ?>">RU</a>
            <a href="?lang=en" class="<?= $curr_lang === 'en' ? 'active' : '' ?>">EN</a>
        </div>

        <h2><?= htmlspecialchars(t('register_title')) ?></h2>
        
        <?php if (!empty($errors['form'])): ?>
            <div class="error-message"><?= htmlspecialchars($errors['form']) ?></div>
        <?php endif; ?>
        
        <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="input-wrapper">
                <img src="/images/profile.png" alt="" class="input-icon">
                <input 
                    type="text" 
                    name="username" 
                    placeholder="<?= htmlspecialchars(t('username')) ?>" 
                    required 
                    autocomplete="username" 
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                >
                <div class="input-error-message"></div>
                <?php if (isset($errors['username'])): ?>
                    <div class="field-error-icon" data-tooltip="<?= htmlspecialchars($errors['username']) ?>">
                        <img src="/images/warning.png" alt="Ошибка">
                    </div>
                <?php endif; ?>
            </div>

            <div class="input-wrapper">
                <img src="/images/envelope.png" alt="" class="input-icon">
                <input 
                    type="email" 
                    name="email" 
                    placeholder="<?= htmlspecialchars(t('email')) ?>" 
                    required 
                    autocomplete="email" 
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                >
                <div class="input-error-message"></div>
                <?php if (isset($errors['email'])): ?>
                    <div class="field-error-icon" data-tooltip="<?= htmlspecialchars($errors['email']) ?>">
                        <img src="/images/warning.png" alt="Ошибка">
                    </div>
                <?php endif; ?>
            </div>

            <div class="input-wrapper">
                <img src="/images/private.png" alt="" class="input-icon">
                <input 
                    type="password" 
                    name="password" 
                    placeholder="<?= htmlspecialchars(t('password')) ?>" 
                    required 
                    autocomplete="new-password"
                >
                <div class="input-error-message"></div>
                <?php if (isset($errors['password'])): ?>
                    <div class="field-error-icon" data-tooltip="<?= htmlspecialchars($errors['password']) ?>">
                        <img src="/images/warning.png" alt="Ошибка">
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="submit"><?= htmlspecialchars(t('btn_register')) ?></button>
        </form>
        
        <p><?= htmlspecialchars(t('has_account')) ?> <a href="/login.php"><?= htmlspecialchars(t('link_signin')) ?></a></p>
    </div>
</main>
<script src="/js/auth_validation.js"></script>
</body>
</html>