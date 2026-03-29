<?php
require_once __DIR__ . '/../../src/core/config.php';

if (isset($_GET['lang'])) {
    $lang_code = $_GET['lang'];
} else {
    $lang_code = $_COOKIE['lang'] ?? 'ru';
}

$lang_path = __DIR__ . "/../../src/lang/landing_{$lang_code}.php";
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
    <title><?= $t['contacts_page_title'] ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/about_landing.css">
</head>
<body>
    <?php include 'nav_landing.php'; ?>

    <section class="legal-section">
        <div class="legal-container">
            <h1 class="legal-title"><?= $t['contacts_title'] ?></h1>
            <p><?= $t['contacts_desc'] ?></p>

            <div class="contacts-grid">
                <div class="contact-card">
                    <span class="contact-role"><?= $t['role_support'] ?></span>
                    <a href="mailto:support@lensera.com" class="contact-email">support@lensera.com</a>
                </div>

                <div class="contact-card">
                    <span class="contact-role"><?= $t['role_press'] ?></span>
                    <a href="mailto:press@lensera.com" class="contact-email">press@lensera.com</a>
                </div>

                <div class="contact-card">
                    <span class="contact-role"><?= $t['role_legal'] ?></span>
                    <a href="mailto:legal@lensera.com" class="contact-email">legal@lensera.com</a>
                </div>

                <div class="contact-card">
                    <span class="contact-role"><?= $t['role_dev'] ?></span>
                    <a href="https://t.me/m9woh" class="contact-email">Telegram: @m9woh</a>
                </div>
            </div>
        </div>
    </section>

    <?php include 'footer_landing.php'; ?>
</body>
</html>