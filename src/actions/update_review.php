<?php

require_once __DIR__ . '/../core/config.php';

function send_json_response(bool $success, string $message, array $data = [], int $http_code = 200): void 
{
    http_response_code($http_code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    send_json_response(false, 'Неверный запрос или пользователь не авторизован.', [], 400);
}

$user_id = (int)$_SESSION['user_id'];
$review_id = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
$subject = trim($_POST['subject'] ?? '');
$content = trim($_POST['content'] ?? '');

if (!$review_id || empty($subject) || empty($content)) {
    send_json_response(false, 'Все поля должны быть заполнены.', [], 400);
}
 
$stmt = $conn->prepare("SELECT status FROM reviews WHERE review_id = ? AND user_id = ?");
$stmt->bind_param("ii", $review_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    send_json_response(false, 'Отзыв не найден или нет прав на редактирование.', [], 403);
}

$review = $result->fetch_assoc();
$stmt->close();

// Редактирование разрешено только для отзывов, ожидающих модерацию
if ($review['status'] !== 'в обработке') {
    send_json_response(false, 'Рассмотренные отзывы не подлежат редактированию.', [], 403);
}

$stmt_update = $conn->prepare("UPDATE reviews SET subject = ?, content = ? WHERE review_id = ?");
$stmt_update->bind_param("ssi", $subject, $content, $review_id);

if ($stmt_update->execute()) {
    send_json_response(true, 'Отзыв успешно обновлен.', ['subject' => $subject, 'content' => $content]);
} else {
    send_json_response(false, 'Ошибка при сохранении отзыва.', [], 500);
}

$stmt_update->close();