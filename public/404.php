<?php

$request_uri = $_SERVER['REQUEST_URI'] ?? '/';

if (strpos($request_uri, '/info/') === 0) {
    http_response_code(404);

    if (file_exists(__DIR__ . '/404_landing.php')) {
        require __DIR__ . '/404_landing.php';
    } else {
        echo "Error 404: Page not found (Landing theme missing).";
    }
    
    exit();
}


http_response_code(404);

$error_type = $_GET['error'] ?? 'default';

$title = '404';
$message = 'Страница не найдена';
$sub_message = 'Возможно, она была удалена или перемещена по другому адресу.';

switch ($error_type) {
    case 'video':
        $message = 'Видео недоступно';
        $sub_message = 'Автор удалил это видео, либо ссылка некорректна.';
        break;
    case 'user':
        $message = 'Пользователь не найден';
        $sub_message = 'Этот канал был закрыт или никогда не существовал.';
        break;
    case 'comment':
        $message = 'Комментарий удален';
        $sub_message = 'К сожалению, этот комментарий больше не доступен.';
        break;
    case 'file':
        $message = 'Файл не найден';
        $sub_message = 'Запрашиваемый медиа-файл отсутствует на сервере.';
        break;
    case 'blocked':
        $message = 'Доступ ограничен';
        $sub_message = 'Этот контент был заблокирован модерацией за нарушение правил сообщества.';
        break;
    case 'private':
        $message = 'Ограниченный доступ';
        $sub_message = 'Это видео доступно только автору. Войдите в аккаунт, если это ваше видео.';
        break;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($message); ?> — VideoService</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/404.css">
</head>
<body>

    <div class="container-404">
        <div class="content-wrapper">
            <h1 class="glitch-text" data-text="404">404</h1>
            
            <h2 class="error-title"><?php echo htmlspecialchars($message); ?></h2>
            
            <p class="error-desc"><?php echo htmlspecialchars($sub_message); ?></p>

            <div class="action-buttons">
                <a href="/" class="btn-home">На главную</a>
                <button onclick="history.back()" class="btn-back">Назад</button>
            </div>
        </div>
    </div>

</body>
</html>