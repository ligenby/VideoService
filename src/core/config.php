<?php

// Показываем все ошибки в деве - никаких сюрпризов!
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Настройка безопасных сессионных куки
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 30,
    'path'     => '/',
    'domain'   => '',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Подключаем хелперы и базу
require_once __DIR__ . '/helpers.php'; 
require_once __DIR__ . '/db.php';

// Создаём CSRF токен если нет
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Проверяем куки "запомни меня" для автологина
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $parts = explode(':', $_COOKIE['remember_token'], 2);
    if (count($parts) === 2) {
        list($selector, $validator) = $parts;

        $stmt = $conn->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW()");
        $stmt->bind_param("s", $selector);
        $stmt->execute();
        $token_data = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($token_data && hash_equals($token_data['hashed_validator'], hash('sha256', $validator))) {

            $stmt_user = $conn->prepare("
                SELECT user_id, public_user_id, username, avatar_url, channel_name, email, role 
                FROM users 
                WHERE user_id = ?
            ");
            $stmt_user->bind_param("i", $token_data['user_id']);
            $stmt_user->execute();
            $user = $stmt_user->get_result()->fetch_assoc();
            $stmt_user->close();

            if ($user) {
                session_regenerate_id(true);
                
                $_SESSION['user_id'] = $user['user_id']; 
                $_SESSION['public_user_id'] = $user['public_user_id']; 

                $_SESSION['username'] = $user['username'];
                $_SESSION['avatar_url'] = $user['avatar_url'];
                $_SESSION['channel_name'] = $user['channel_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
            }
        }
    }
}

function generate_unique_id($conn, $table, $column, $length = 11) {
    do {
        $random_bytes = random_bytes(ceil($length * 3 / 4));
        $unique_id = rtrim(strtr(base64_encode($random_bytes), '+/', '-_'), '=');
        $unique_id = substr($unique_id, 0, $length);
        
        $stmt = $conn->prepare("SELECT 1 FROM `{$table}` WHERE `{$column}` = ?");
        $stmt->bind_param("s", $unique_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
        
    } while ($result->num_rows > 0); 

    return $unique_id;
}

// Изредка чистим старые токены (шанс 1%)
if (rand(1, 100) === 1) {
    $conn->query("DELETE FROM auth_tokens WHERE expires < NOW()");
}