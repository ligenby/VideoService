<?php

if (isset($_GET['lang'])) {
    $lang_code = $_GET['lang'];
} else {

    $lang_code = $_COOKIE['lang'] ?? 'ru';
}


$lang_path = __DIR__ . "/../../src/lang/landing_{$lang_code}.php";

// fallback на ru
if (file_exists($lang_path)) {
    $t = require $lang_path;
} else {
    $t = require __DIR__ . "/../../src/lang/landing_ru.php";
    $lang_code = 'ru';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang_code) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t['nav_about'] ?> — LensEra</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="/css/about_landing.css">
    <link rel="icon" type="image/png" href="/images/my-icon.png">
</head>
<body>

    <?php include 'nav_landing.php'; ?>

    <section class="hero-section">
        <div class="hero-content">
            <h1 class="fade-in"><?= $t['hero_title'] ?></h1>
            <p class="fade-in delay-1"><?= $t['hero_desc'] ?></p>
        </div>
        <div class="hero-visual fade-in delay-2">
            <div class="circle"></div>
            <div class="square"></div>
        </div>
    </section>

    <section class="manifesto-section">
        <div class="container">
            <h2><?= $t['manifesto_title'] ?></h2>
            <div class="values-grid">
                <div class="value-card scroll-reveal">
                    <span class="number">01</span>
                    <h3><?= $t['val_1_title'] ?></h3>
                    <p><?= $t['val_1_desc'] ?></p>
                </div>
                <div class="value-card scroll-reveal">
                    <span class="number">02</span>
                    <h3><?= $t['val_2_title'] ?></h3>
                    <p><?= $t['val_2_desc'] ?></p>
                </div>
                <div class="value-card scroll-reveal">
                    <span class="number">03</span>
                    <h3><?= $t['val_3_title'] ?></h3>
                    <p><?= $t['val_3_desc'] ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="stats-section">
        <div class="container">
            <div class="bento-grid">
                <!-- Карточка 1 -->
                <div class="bento-item big-item scroll-reveal">
                    <div class="bento-image-wrapper">
                         <!-- Вставьте свою картинку в src -->
                         <img src="/images/bento-story.jpg" alt="Stories illustration" class="bento-img">
                    </div>
                    <div class="bento-text-wrapper">
                        <h4><?= $t['bento_millions'] ?></h4>
                        <p><?= $t['bento_millions_desc'] ?></p>
                    </div>
                </div>

                <!-- Карточка 2: График -->
                <div class="bento-item photo-item scroll-reveal">
                    <div class="visual-overlay">
                        <span class="tag"><?= $t['chart_tag'] ?></span>
                    </div>
                    
                    <div class="chart-container">
                        <!-- Колонка 1 -->
                        <div class="chart-col">
                            <div class="stars-wrapper">★★★</div>
                            <div class="bar bar-yt"></div>
                            <span class="bar-label"><?= $t['chart_others'] ?></span>
                        </div>
                        
                        <!-- Колонка 2 -->
                        <div class="chart-col">
                            <div class="stars-wrapper">★★★★</div>
                            <div class="bar bar-tt"></div>
                            <span class="bar-label"><?= $t['chart_socials'] ?></span>
                        </div>
                        
                        <!-- Колонка 3 (LensEra) -->
                        <div class="chart-col main-col">
                            <div class="stars-wrapper winner-stars">★★★★★</div>
                            <div class="bar bar-le"></div>
                            <span class="bar-label label-bold">LensEra</span>
                        </div>
                    </div>
                </div>

                <!-- Карточка 3: Цифры -->
                <div class="bento-item stat-item scroll-reveal">
                    <span class="big-number">100+</span>
                    <span class="label"><?= $t['stat_countries'] ?></span>
                </div>

                <!-- Карточка 4: Призыв -->
                <a href="/register.php" class="bento-item dark-item scroll-reveal">
                    <div class="cta-top">
                        <h4><?= $t['cta_join'] ?></h4>
                        <p><?= $t['cta_future'] ?></p>
                    </div>
                    <div class="cta-bottom">
                        <div class="arrow-circle">
                            <span class="arrow-symbol">→</span>
                        </div>
                    </div>
                </a>

            </div>
        </div>
    </section>

    <!-- Подключаем Футер (Создайте файл footer_landing.php, если его нет) -->
    <?php include 'footer_landing.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, { threshold: 0.1 });

            document.querySelectorAll('.scroll-reveal').forEach(el => observer.observe(el));
        });
    </script>
</body>
</html>