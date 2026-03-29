<?php require_once __DIR__ . '/../../src/core/config.php'; ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Как это работает — LensEra</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/about_landing.css">
    <link rel="icon" type="image/png" href="/images/my-icon.png">
</head>
<body>
    <?php include 'nav_landing.php'; ?>

    <section class="hero-section">
        <div class="hero-content">
            <h1 class="fade-in">Платформа для <br>творцов.</h1>
            <p class="fade-in delay-1">Всего три простых шага отделяют вас от мировой аудитории.</p>
        </div>

        <div class="hero-visual fade-in delay-2">
            <img src="/images/hero-avatar.jpg" alt="Творец" class="hero-circle-photo">
        </div>
    </section>

    <section class="steps-section">
        <div class="container">
            
            <div class="step-item scroll-reveal">
                <div class="step-text">
                    <span class="step-number">01</span>
                    <h3>Создайте канал</h3>
                    <p>Зарегистрируйтесь и настройте свой профиль. Это ваш личный бренд. Добавьте аватар, описание и баннер, чтобы зрители узнавали вас с первого взгляда.</p>
                </div>
                <div class="step-visual">
                    <img src="/images/step-1.jpg" alt="Создание канала" class="step-img">
                </div>
            </div>

            <div class="step-item scroll-reveal">
                <div class="step-text">
                    <span class="step-number">02</span>
                    <h3>Загружайте видео</h3>
                    <p>Наша система поддерживает высокие разрешения и быструю обработку. Просто перетащите файл, добавьте название, теги — и ваш контент готов к просмотру.</p>
                </div>
                <div class="step-visual">
                    <img src="/images/step-2.jpg" alt="Загрузка видео" class="step-img">
                </div>
            </div>

            <div class="step-item scroll-reveal">
                <div class="step-text">
                    <span class="step-number">03</span>
                    <h3>Набирайте аудиторию</h3>
                    <p>Общайтесь в комментариях, следите за статистикой просмотров и попадайте в рекомендации. Умные алгоритмы помогут найти именно ваших зрителей.</p>
                </div>
                <div class="step-visual">
                    <img src="/images/step-3.jpg" alt="Рост аудитории" class="step-img">
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 100px;">
                 <h2 style="margin-bottom: 40px; font-size: 2rem;">Готовы начать?</h2>
                 <a href="/register.php" class="btn-back-app" style="font-size: 1.2rem; padding: 15px 40px;">Создать аккаунт</a>
            </div>
        </div>
    </section>

    <?php include 'footer_landing.php'; ?>
    
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) entry.target.classList.add('visible');
                });
            }, { threshold: 0.2 });
            document.querySelectorAll('.scroll-reveal').forEach(el => observer.observe(el));
        });
    </script>
</body>
</html>