<?php

// Конфиг, сессия и проверка CSRF ожидаются из точки входа

$response = ['success' => false, 'message' => 'Произошла неизвестная ошибка.'];

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    $response['message'] = 'Доступ запрещен. Пожалуйста, войдите в аккаунт.';
    echo json_encode($response);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$channel_name = trim($_POST['channel_name'] ?? '');

if (mb_strlen($channel_name) < 3 || mb_strlen($channel_name) > 30) {
    http_response_code(400);
    $response['message'] = 'Название должно содержать от 3 до 30 символов.';
    echo json_encode($response);
    exit;
}

if (!preg_match('/^[a-zA-Zа-яА-Я0-9 _-]+$/u', $channel_name)) {
    http_response_code(400);
    $response['message'] = 'Название содержит недопустимые символы.';
    echo json_encode($response);
    exit;
}

try {
    $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE channel_name = ?");
    $stmt_check->bind_param("s", $channel_name);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows > 0) {
        http_response_code(409);
        $response['message'] = 'Это название канала уже занято.';
        echo json_encode($response);
        exit;
    }
    $stmt_check->close();

    $avatar_db_path = $_SESSION['avatar_url'] ?? null;

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        if (!defined('ROOT_PATH')) {
            define('ROOT_PATH', dirname(__DIR__, 2));
        }
        
        $uploadDir = ROOT_PATH . "/public/uploads/avatars/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExt = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExt, $allowedTypes, true)) {
            $newFileName = "avatar_" . $user_id . "_" . time() . "." . $fileExt;
            if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $uploadDir . $newFileName)) {
                $avatar_db_path = "uploads/avatars/" . $newFileName;
            }
        }
    }

    $stmt_update = $conn->prepare("UPDATE users SET channel_name = ?, avatar_url = ? WHERE user_id = ?");
    $stmt_update->bind_param("ssi", $channel_name, $avatar_db_path, $user_id);

    if ($stmt_update->execute()) {
        $_SESSION['channel_name'] = $channel_name;
        $_SESSION['avatar_url'] = $avatar_db_path;

        $response = [
            'success' => true,
            'message' => 'Канал успешно создан!',
            'data' => ['avatarUrl' => '/' . $avatar_db_path]
        ];
    } else {
        throw new Exception("Failed to update user record.");
    }
    
    $stmt_update->close();

} catch (Exception $e) {
    error_log("Channel creation failed [User ID: {$user_id}]: " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Критическая ошибка сервера.';
}

echo json_encode($response);