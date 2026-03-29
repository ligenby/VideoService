<?php

require_once __DIR__ . '/../core/config.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST" || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Необходима авторизация.']);
    exit;
}

if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Ошибка валидации CSRF-токена.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$comment_id = (int)$_POST['comment_id'];
$type = ($_POST['type'] === 'like') ? 1 : -1;

try {
    // Проверка существующей реакции пользователя
    $stmt = $conn->prepare("SELECT type FROM comment_likes WHERE user_id = ? AND comment_id = ?");
    $stmt->bind_param("ii", $user_id, $comment_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $new_user_action = 0; // 0 = сброс, 1 = лайк, -1 = дизлайк

    if ($existing) {
        if ($existing['type'] == $type) {
            // Снятие реакции
            $stmt_del = $conn->prepare("DELETE FROM comment_likes WHERE user_id = ? AND comment_id = ?");
            $stmt_del->bind_param("ii", $user_id, $comment_id);
            $stmt_del->execute();
            $new_user_action = 0;
        } else {
            // Изменение реакции (с лайка на дизлайк или наоборот)
            $stmt_upd = $conn->prepare("UPDATE comment_likes SET type = ? WHERE user_id = ? AND comment_id = ?");
            $stmt_upd->bind_param("iii", $type, $user_id, $comment_id);
            $stmt_upd->execute();
            $new_user_action = $type;
        }
    } else {
        // Новая реакция
        $stmt_ins = $conn->prepare("INSERT INTO comment_likes (user_id, comment_id, type) VALUES (?, ?, ?)");
        $stmt_ins->bind_param("iii", $user_id, $comment_id, $type);
        $stmt_ins->execute();
        $new_user_action = $type;
    }

    // Актуализация счетчиков в таблице comments для оптимизации чтения
    $stmt_count = $conn->prepare("
        SELECT 
            SUM(CASE WHEN type = 1 THEN 1 ELSE 0 END) as likes,
            SUM(CASE WHEN type = -1 THEN 1 ELSE 0 END) as dislikes
        FROM comment_likes 
        WHERE comment_id = ?
    ");
    $stmt_count->bind_param("i", $comment_id);
    $stmt_count->execute();
    $counts = $stmt_count->get_result()->fetch_assoc();
    $stmt_count->close();

    $likes = (int)$counts['likes'];
    $dislikes = (int)$counts['dislikes'];

    $upd_comments = $conn->prepare("UPDATE comments SET like_count = ?, dislike_count = ? WHERE comment_id = ?");
    $upd_comments->bind_param("iii", $likes, $dislikes, $comment_id);
    $upd_comments->execute();

    echo json_encode([
        'success' => true,
        'likes' => $likes,
        'dislikes' => $dislikes,
        'user_action' => $new_user_action
    ]);

} catch (Exception $e) {
    error_log("Toggle Comment Like Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ошибка базы данных.']);
}