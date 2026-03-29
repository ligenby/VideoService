<?php
require_once __DIR__ . '/../src/core/config.php';

$code = '404';
$head = 'Страница не найдена';
$desc = 'Кажется, вы забрели туда, где ничего нет. Возможно, ссылка устарела.';
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $head; ?> — LensEra</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/about_landing.css"> 
    <link rel="stylesheet" href="/css/404_landing.css">
</head>
<body>

    <nav class="landing-nav">
        <a href="/" class="landing-logo">
            <span class="logo-icon">LE</span>
            <span class="logo-text">LensEra</span>
        </a>
        <div class="nav-links">
            <a href="/">На главную</a>
        </div>
    </nav>

    <section class="error-light-section">
        <div class="content">
            <span class="huge-number"><?php echo $code; ?></span>
            <h1><?php echo $head; ?></h1>
            <p><?php echo $desc; ?></p>
            <div class="actions">
                <a href="/" class="btn-black">Домой</a>
            </div>
        </div>
    </section>

</body>
</html>