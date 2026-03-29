<?php

require_once __DIR__ . '/../core/config.php'; 

// TODO: Вынести send_json_response в helpers.php
function send_json_response(array $data, int $statusCode = 200): void 
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    send_json_response(["success" => false, "message" => "Необходимо войти в аккаунт"], 401);
}

$public_channel_id = $_POST['channel_id'] ?? null;

if (empty($public_channel_id)) {
    send_json_response(["success" => false, "message" => "Неверный запрос: не указан ID канала"], 400);
}

$subscriber_id = (int)$_SESSION['user_id'];
 
try {
    // Получаем внутренний ID канала по публичному хешу
    $stmt_get_id = $conn->prepare("SELECT user_id FROM users WHERE public_user_id = ?");
    $stmt_get_id->bind_param("s", $public_channel_id);
    $stmt_get_id->execute();
    $channel_data = $stmt_get_id->get_result()->fetch_assoc();
    $stmt_get_id->close();

    if (!$channel_data) {
        send_json_response(["success" => false, "message" => "Канал не найден"], 404);
    }
    
    $channel_id = (int)$channel_data['user_id'];

    if ($subscriber_id === $channel_id) {
        send_json_response(["success" => false, "message" => "Вы не можете подписаться на себя"]);
    }

    $stmt_check = $conn->prepare("SELECT subscription_id FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
    $stmt_check->bind_param("ii", $subscriber_id, $channel_id);
    $stmt_check->execute();
    $is_subscribed = $stmt_check->get_result()->num_rows > 0;
    $stmt_check->close();

    // Переключаем статус подписки
    if ($is_subscribed) {
        $stmt_action = $conn->prepare("DELETE FROM subscriptions WHERE subscriber_id = ? AND channel_id = ?");
    } else {
        $stmt_action = $conn->prepare("INSERT INTO subscriptions (subscriber_id, channel_id) VALUES (?, ?)");
    }
    
    $stmt_action->bind_param("ii", $subscriber_id, $channel_id);
    
    if (!$stmt_action->execute()) {
        throw new Exception("Ошибка изменения статуса подписки: " . $stmt_action->error);
    }
    $stmt_action->close();

    // Создаем уведомление только при новой подписке
    if (!$is_subscribed && function_exists('createNotification')) {
        createNotification($conn, $channel_id, $subscriber_id, 'subscription', null);
    }
    
    // Актуализируем счетчик подписчиков (денормализация для скорости выборки)
    $count_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM subscriptions WHERE channel_id = ?");
    $count_stmt->bind_param("i", $channel_id);
    $count_stmt->execute();
    $new_count = (int)$count_stmt->get_result()->fetch_assoc()['cnt'];
    $count_stmt->close();

    $update_stmt = $conn->prepare("UPDATE users SET subscriber_count = ? WHERE user_id = ?");
    $update_stmt->bind_param("ii", $new_count, $channel_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    send_json_response([
        "success" => true,
        "is_subscribed" => !$is_subscribed,
        "subscriber_count" => $new_count
    ]);

} catch (Exception $e) {
    error_log("Toggle Subscription Error: " . $e->getMessage());
    send_json_response(['success' => false, 'message' => 'Внутренняя ошибка сервера.'], 500);
}