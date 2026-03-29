<?php

require_once __DIR__ . '/../core/config.php';

/**
 * @todo В будущем перенести вывод ошибок в систему flash-сообщений (Session Flash Messages).
 */
function redirect_with_error(string $location, string $message): void {
    // Временное решение для отображения ошибки
    exit(htmlspecialchars($message));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    redirect_with_error('/pages/you.php', 'Ошибка валидации CSRF-токена.');
}

if (empty($_POST['user_id'])) {
    header("Location: /pages/you.php");
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$target_user_id = (int)$_POST['user_id'];

// Предотвращаем редирект на самого себя
if ($current_user_id === $target_user_id) {
    header("Location: /pages/you.php");
    exit();
}

// Проверяем, принадлежат ли аккаунты к одной группе (linked_accounts)
$can_switch = false;
$stmt_group = $conn->prepare("SELECT link_group_id FROM linked_accounts WHERE user_id = ?");
$stmt_group->bind_param("i", $current_user_id);
$stmt_group->execute();
$group_result = $stmt_group->get_result()->fetch_assoc();
$stmt_group->close();

if ($group_result) {
    $link_group_id = $group_result['link_group_id'];
    $stmt_check = $conn->prepare("SELECT link_id FROM linked_accounts WHERE user_id = ? AND link_group_id = ?");
    $stmt_check->bind_param("is", $target_user_id, $link_group_id);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows > 0) {
        $can_switch = true;
    }
    $stmt_check->close();
}

if (!$can_switch) {
    redirect_with_error('/pages/you.php', 'Доступ запрещен. Аккаунт не привязан к текущему профилю.');
}

// Инициализация новой сессии для целевого аккаунта
$stmt_user = $conn->prepare("SELECT user_id, public_user_id, username, email, avatar_url, channel_name, role FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $target_user_id);
$stmt_user->execute();
$result = $stmt_user->get_result();

if ($user_data = $result->fetch_assoc()) {
    // Регенерация ID сессии для предотвращения Session Fixation
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user_data['user_id'];
    $_SESSION['public_user_id'] = $user_data['public_user_id'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['email'] = $user_data['email'];
    $_SESSION['avatar_url'] = $user_data['avatar_url'];
    $_SESSION['channel_name'] = $user_data['channel_name'];
    $_SESSION['role'] = $user_data['role'];

    // Фиксация времени входа
    $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $update_stmt->bind_param("i", $target_user_id);
    $update_stmt->execute();
    $update_stmt->close();
}

$stmt_user->close();

header("Location: /pages/you.php");
exit();