<?php

function send_json_response($success, $message, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    send_json_response(false, 'Для отправки отзыва необходимо войти в аккаунт.', 401);
}

$user_id = $_SESSION['user_id'];
$subject = trim($_POST['subject'] ?? '');
$content = trim($_POST['content'] ?? '');
$rating = filter_var($_POST['rating'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
$allow_publication = isset($_POST['consent']) ? 1 : 0; 
$status = 'в обработке';

if (empty($subject) || empty($content) || $rating === false) {
    send_json_response(false, 'Пожалуйста, заполните все поля и выберите оценку.', 400);
}

try {
    $stmt = $conn->prepare("
        INSERT INTO reviews (user_id, rating, subject, content, status, allow_publication) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("iisssi", $user_id, $rating, $subject, $content, $status, $allow_publication);
    
    if ($stmt->execute()) {
        send_json_response(true, 'Спасибо! Ваш отзыв отправлен на модерацию.');
    } else {
        throw new Exception($stmt->error);
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Review submission failed: " . $e->getMessage());
    send_json_response(false, 'Произошла ошибка на сервере. Не удалось сохранить отзыв.', 500);
}
?>