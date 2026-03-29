<?php

require_once __DIR__ . '/../core/config.php';

if (!defined('CHANGE_INTERVAL_DAYS')) {
    define('CHANGE_INTERVAL_DAYS', 7);
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен.']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';

$response = ['success' => false, 'message' => 'Произошла неизвестная ошибка.'];

try {
    switch ($action) {
        case 'account':
            $email = trim($_POST['email'] ?? '');
            
            $stmt_curr = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
            $stmt_curr->bind_param("i", $user_id);
            $stmt_curr->execute();
            $curr_user = $stmt_curr->get_result()->fetch_assoc();
            $stmt_curr->close();

            if ($email === $curr_user['email']) {
                echo json_encode(['success' => true, 'message' => 'Изменений не было.']);
                exit();
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $response['message'] = 'Неверный формат email.';
                break;
            } 
            
            $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
            $stmt_check->bind_param("si", $email, $user_id);
            $stmt_check->execute();
            
            if ($stmt_check->get_result()->num_rows > 0) {
                $response['message'] = 'Этот адрес электронной почты уже используется другим аккаунтом.';
            } else {
                $stmt = $conn->prepare("UPDATE users SET email = ? WHERE user_id = ?");
                $stmt->bind_param("si", $email, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['email'] = $email;
                    $response = ['success' => true, 'message' => 'Email успешно обновлен.'];
                } else {
                    $response['message'] = 'Не удалось обновить email.';
                }
                $stmt->close();
            }
            $stmt_check->close();
            break;

        case 'notifications':
            $notify_on_comment = isset($_POST['notify_on_comment']) ? 1 : 0;
            $notify_on_subscriber = isset($_POST['notify_on_new_subscriber']) ? 1 : 0;
            $notify_on_like = isset($_POST['notify_on_like']) ? 1 : 0;
            $notify_on_reply = isset($_POST['notify_on_reply']) ? 1 : 0;

            $stmt = $conn->prepare("UPDATE users SET notify_on_comment = ?, notify_on_new_subscriber = ?, notify_on_like = ?, notify_on_reply = ? WHERE user_id = ?");
            $stmt->bind_param("iiiii", $notify_on_comment, $notify_on_subscriber, $notify_on_like, $notify_on_reply, $user_id);
            
            if ($stmt->execute()) {
                $response = ['success' => true, 'message' => 'Настройки уведомлений сохранены.'];
            }
            $stmt->close();
            break;

        case 'password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            
            if (mb_strlen($new_password) < 8) {
                $response['message'] = 'Новый пароль слишком короткий.'; 
                break;
            }
            if (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[0-9]/', $new_password)) {
                 $response['message'] = 'Пароль должен содержать цифры и заглавные буквы.'; 
                 break;
            }

            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($current_password, $user['password_hash'])) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt_update->bind_param("si", $new_hash, $user_id);
                
                if ($stmt_update->execute()) {
                    // Инвалидация всех сессий пользователя в целях безопасности
                    $stmt_tokens = $conn->prepare("DELETE FROM auth_tokens WHERE user_id = ?");
                    $stmt_tokens->bind_param("i", $user_id);
                    $stmt_tokens->execute();
                    $stmt_tokens->close();

                    $stmt_links = $conn->prepare("DELETE FROM linked_accounts WHERE user_id = ?");
                    $stmt_links->bind_param("i", $user_id);
                    $stmt_links->execute();
                    $stmt_links->close();
 
                    if (isset($_COOKIE['remember_token'])) {
                        setcookie('remember_token', '', time() - 3600, '/');
                    }

                    session_unset();
                    session_destroy();

                    $response = [
                        'success' => true, 
                        'message' => 'Пароль изменен. Выполняется выход...', 
                        'redirect' => '/login.php'
                    ];
                } else {
                    $response['message'] = 'Ошибка при обновлении пароля.';
                }
                $stmt_update->close();
            } else {
                $response['message'] = 'Текущий пароль введен неверно.';
            }
            break;
        
        case 'privacy':
            $history_enabled = isset($_POST['history_enabled']) ? 1 : 0;
            $stmt = $conn->prepare("UPDATE users SET history_enabled = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $history_enabled, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['history_enabled'] = $history_enabled;
                $response = ['success' => true, 'message' => 'Настройки приватности сохранены.'];
            }
            $stmt->close();
            break;

        case 'delete_account':
            $password_confirmation = $_POST['password_confirmation'] ?? '';
            
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password_confirmation, $user['password_hash'])) {
                // Очистка связанных данных перед удалением
                $conn->query("DELETE FROM auth_tokens WHERE user_id = $user_id");
                $conn->query("DELETE FROM linked_accounts WHERE user_id = $user_id");
                
                $stmt_del = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt_del->bind_param("i", $user_id);
                
                if ($stmt_del->execute()) {
                    session_unset();
                    session_destroy();
                    $response = ['success' => true, 'message' => 'Аккаунт удален.', 'redirect' => '/login.php'];
                } else {
                    $response['message'] = 'Ошибка при удалении аккаунта.';
                }
                $stmt_del->close();
            } else {
                $response['message'] = 'Неверный пароль.';
            }
            break;

        default:
            $response['message'] = 'Неизвестное действие.';
            break;
    }
} catch (Exception $e) {
    error_log("Settings update error: " . $e->getMessage());
    $response['message'] = 'Внутренняя ошибка сервера.';
}

echo json_encode($response);