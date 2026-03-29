<?php
require_once __DIR__ . '/../core/config.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса.']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Вы должны войти в систему.']);
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Неверный токен безопасности.']);
    exit;
}

if (!isset($_POST['comment_id']) || !isset($_POST['reason']) || empty(trim($_POST['reason']))) {
    echo json_encode(['success' => false, 'message' => 'Не указана причина жалобы.']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$comment_id = (int)$_POST['comment_id'];
$reason = trim($_POST['reason']);
$target_type = 'comment';
try {
    $check_stmt = $conn->prepare("SELECT user_id FROM comments WHERE comment_id = ?");
    $check_stmt->bind_param("i", $comment_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Комментарий не найден.']);
        exit;
    }
    $check_stmt->close();

    $stmt = $conn->prepare("INSERT IGNORE INTO reports (user_id, target_type, target_id, reason, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("isis", $user_id, $target_type, $comment_id, $reason);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Спасибо! Мы проверим этот комментарий.']);
    } else {
        throw new Exception("Ошибка БД: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    error_log("Report comment error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ошибка сервера.']);
}
?>