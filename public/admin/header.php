<?php
// public/admin/header.php
require_once __DIR__ . '/../../src/core/config.php';

// админка
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: /');
    exit();
}

$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LensEra Admin</title>
    

    <link rel="stylesheet" href="/css/admin.css">
    

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    

    <link rel="icon" type="image/png" href="/images/my-icon.png">
</head>
<body class="admin-body">

<aside class="admin-sidebar">
    <a href="/admin/users.php" class="admin-logo">
        <span class="admin-logo-icon">🛡️</span> LensEra Admin
    </a>
    
    <nav class="admin-nav">
        <div class="nav-group">
            <span class="nav-label">Управление</span>
            
            <a href="/admin/users.php" class="<?= $currentPage == 'users.php' ? 'active' : '' ?>">
                <img src="/images/users.png" class="nav-icon" alt=""> 
                Пользователи
            </a>
            
            <a href="/admin/reviews.php" class="<?= $currentPage == 'reviews.php' ? 'active' : '' ?>">
                <img src="/images/alert.png" class="nav-icon" alt="">
                Отзывы
            </a>
            
            <a href="/admin/complaints.php" class="<?= $currentPage == 'complaints.php' ? 'active' : '' ?>">
                <img src="/images/complaint.png" class="nav-icon" alt="">
                Жалобы
            </a>
        </div>
        
        <div class="nav-group bottom-group">
            <a href="/" class="logout">
                <img src="/images/home.png" class="nav-icon" alt="">
                На сайт
            </a>
        </div>
    </nav>
</aside>

<div class="admin-content">