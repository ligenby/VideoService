<?php
// Удаляем связанный аккаунт из списка

// Конфиг
require_once __DIR__ . '/../core/config.php';

// Редирект обратно
function redirect_back($error_message = null) {
    header("Location: /pages/you.php");
    exit();
}

// Базовые проверки безопасности
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_back();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    redirect_back();
}
if (!isset($_POST['user_id_to_remove']) || !is_numeric($_POST['user_id_to_remove'])) {
    redirect_back();
}


$active_user_id = (int)$_SESSION['user_id'];
$user_id_to_remove = (int)$_POST['user_id_to_remove'];

// Не даём удалить текущий аккаунт
if ($active_user_id === $user_id_to_remove) {
    redirect_back("Нельзя удалить активный аккаунт этим способом.");
}

// Проверяем что аккаунты связаны одной группой
$can_remove = false;
$stmt_group = $conn->prepare("SELECT link_group_id FROM linked_accounts WHERE user_id IN (?, ?)");
$stmt_group->bind_param("ii", $active_user_id, $user_id_to_remove);
$stmt_group->execute();
$result = $stmt_group->get_result();

if ($result->num_rows === 2) {
    $rows = $result->fetch_all(MYSQLI_ASSOC);
    if ($rows[0]['link_group_id'] === $rows[1]['link_group_id']) {
        $can_remove = true;
    }
}
$stmt_group->close();

// Удаляем если всё ок
if ($can_remove) {
    $stmt_delete = $conn->prepare("DELETE FROM linked_accounts WHERE user_id = ?");
    $stmt_delete->bind_param("i", $user_id_to_remove);
    $stmt_delete->execute();
    $stmt_delete->close();
} else {
    
}

redirect_back();
?>