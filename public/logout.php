<?php

require_once __DIR__ . '/../src/core/config.php';

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' || 
    empty($_POST['csrf_token']) || 
    !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])
) {
    header("Location: /");
    exit;
}

if (!empty($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    
    $stmt_unlink = $conn->prepare("DELETE FROM linked_accounts WHERE user_id = ?");
    if ($stmt_unlink) {
        $stmt_unlink->bind_param("i", $user_id);
        $stmt_unlink->execute();
        $stmt_unlink->close();
    }
}

if (!empty($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
    
    $parts = explode(':', $_COOKIE['remember_token'], 2);
    if (count($parts) === 2) {
        $selector = $parts[0];
        
        $stmt_token = $conn->prepare("DELETE FROM auth_tokens WHERE selector = ?");
        if ($stmt_token) {
            $stmt_token->bind_param("s", $selector);
            $stmt_token->execute();
            $stmt_token->close();
        }
    }
}

session_unset();
session_destroy();

header("Location: /");
exit;