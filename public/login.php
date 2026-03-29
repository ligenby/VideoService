<?php

require_once __DIR__ . '/../src/core/config.php';

// --- Логика мультиязычности ---
$curr_lang = $_COOKIE['lang'] ?? 'ru';

if (isset($_GET['lang']) && in_array($_GET['lang'], ['ru', 'en'], true)) {
    $curr_lang = $_GET['lang'];
    setcookie('lang', $curr_lang, time() + (86400 * 30), "/");
} elseif (!in_array($curr_lang, ['ru', 'en'], true)) {
    $curr_lang = 'ru';
}

$lang_file = __DIR__ . "/../src/lang/{$curr_lang}.php";
$lang = file_exists($lang_file) ? require $lang_file : [];

if (!function_exists('t')) {
    function t($key) {
        global $lang;
        return $lang[$key] ?? $key;
    }
}

$csrf_token = $_SESSION['csrf_token'];

// --- Логика связывания аккаунтов ---
if (isset($_GET['action']) && $_GET['action'] === 'link' && isset($_SESSION['user_id'])) {
    $link_initiator_id = (int)$_SESSION['user_id'];
    
    $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $update_stmt->bind_param("i", $link_initiator_id);
    $update_stmt->execute();
    $update_stmt->close();

    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    session_unset();
    session_destroy();
    
    session_start();
    $_SESSION['link_initiated_by'] = $link_initiator_id;
    
    $redirect_url = '/login.php' . ($curr_lang !== 'ru' ? '?lang=' . $curr_lang : '');
    header("Location: $redirect_url");
    exit;
}

if (isset($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

$error = null;

if (isset($_GET['reason']) && $_GET['reason'] === 'banned') {
    $error = t('account_banned');
}

// --- Обработка формы авторизации ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = t('error_session');
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        $stmt = $conn->prepare("
             SELECT user_id, public_user_id, username, password_hash, avatar_url, channel_name, email, role, status 
             FROM users 
             WHERE username = ?
        ");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            
            if ($user['status'] === 'banned') {
                $error = t('account_banned');
            } elseif ($user['status'] === 'active') {
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['public_user_id'] = $user['public_user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['avatar_url'] = $user['avatar_url'];
                $_SESSION['channel_name'] = $user['channel_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                // Продолжение логики связывания аккаунтов (если была инициирована)
                if (isset($_SESSION['link_initiated_by'])) {
                    $user_id_A = (int)$_SESSION['link_initiated_by'];
                    $user_id_B = (int)$user['user_id'];
                    unset($_SESSION['link_initiated_by']);

                    if ($user_id_A !== $user_id_B) {
                        $stmt_group = $conn->prepare("SELECT link_group_id FROM linked_accounts WHERE user_id = ? OR user_id = ? LIMIT 1");
                        $stmt_group->bind_param("ii", $user_id_A, $user_id_B);
                        $stmt_group->execute();
                        $existing_group = $stmt_group->get_result()->fetch_assoc();
                        $stmt_group->close();
                        
                        $link_group_id = $existing_group ? $existing_group['link_group_id'] : uniqid('link_', true);

                        $stmt_link = $conn->prepare("INSERT IGNORE INTO linked_accounts (link_group_id, user_id) VALUES (?, ?)");
                        $stmt_link->bind_param("si", $link_group_id, $user_id_A);
                        $stmt_link->execute();
                        $stmt_link->bind_param("si", $link_group_id, $user_id_B);
                        $stmt_link->execute();
                        $stmt_link->close();
                    }
                }
                
                // Функция "Запомнить меня"
                if (!empty($_POST['remember_me'])) {
                    $selector = bin2hex(random_bytes(16));
                    $validator = bin2hex(random_bytes(32));
                    $hashed_validator = hash('sha256', $validator);
                    
                    $expires = new DateTime('+30 days');
                    $expires_str = $expires->format('Y-m-d H:i:s');

                    $stmt_token = $conn->prepare("INSERT INTO auth_tokens (user_id, selector, hashed_validator, expires) VALUES (?, ?, ?, ?)");
                    $stmt_token->bind_param("isss", $user['user_id'], $selector, $hashed_validator, $expires_str);
                    $stmt_token->execute();

                    setcookie('remember_token', $selector . ':' . $validator, $expires->getTimestamp(), '/', '', isset($_SERVER['HTTPS']), true);
                }

                header("Location: /pages/you.php");
                exit;
            } else {
                $error = t('status_error') . htmlspecialchars($user['status']);
            }
        } else {
            if (!$error) {
                $error = t('error_login_fail');
            }
        }
    }
}

$prefilled_username = htmlspecialchars($_GET['username'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($curr_lang) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= t('login_title') ?> — VideoService</title>
    <link rel="stylesheet" href="/css/auth.css">
</head>
<body>
    <main class="auth-page-container">
        <div class="auth-form">
            
            <div class="lang-switcher">
                <a href="?lang=ru" class="<?= $curr_lang === 'ru' ? 'active' : '' ?>">RU</a>
                <a href="?lang=en" class="<?= $curr_lang === 'en' ? 'active' : '' ?>">EN</a>
            </div>

            <h2><?= t('login_title') ?></h2>
            
            <?php if ($error): ?>
                <p class="error-message"><?= $error ?></p>
            <?php endif; ?>
            
            <?php if (isset($_GET['registration']) && $_GET['registration'] === 'success'): ?>
                <p class="success-message"><?= t('reg_success') ?></p>
            <?php endif; ?>
            
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="input-wrapper">
                    <img src="/images/profile.png" alt="" class="input-icon">
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="<?= t('username') ?>" 
                        required 
                        autocomplete="username" 
                        value="<?= $prefilled_username ?>"
                    >
                    <div class="input-error-message"></div>
                </div>
                
                <div class="input-wrapper">
                    <img src="/images/private.png" alt="" class="input-icon">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="<?= t('password') ?>" 
                        required 
                        autocomplete="current-password"
                    >
                    <div class="input-error-message"></div>
                </div>
                
                <div class="remember-me-wrapper">
                    <label class="toggle-switch">
                        <input type="checkbox" id="remember_me" name="remember_me" value="1">
                        <span class="slider round"></span>
                    </label>
                    <label for="remember_me" class="toggle-label"><?= t('remember_me') ?></label>
                </div>
                
                <button type="submit"><?= t('btn_login') ?></button>
            </form>
            
            <p><?= t('no_account') ?> <a href="/register.php"><?= t('link_create') ?></a></p>
        </div>
    </main>
    <script src="/js/auth_validation.js"></script>
</body>
</html>