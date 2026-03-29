<?php
// --- ЛОГИКА ЯЗЫКОВ ---
$languages = [
    'en' => ['name' => 'English',    'flag' => '🇺🇸'],
    'ru' => ['name' => 'Русский',    'flag' => '🇷🇺'],
    'be' => ['name' => 'Беларуская', 'flag' => '🇧🇾'],
    'es' => ['name' => 'Español',    'flag' => '🇪🇸'],
    'de' => ['name' => 'Deutsch',    'flag' => '🇩🇪'],
    'fr' => ['name' => 'Français',   'flag' => '🇫🇷'],
    'zh' => ['name' => '中文',        'flag' => '🇨🇳'],
    'pt' => ['name' => 'Português',  'flag' => '🇵🇹'],
    'ja' => ['name' => '日本語',      'flag' => '🇯🇵'],
    'hi' => ['name' => 'हिन्दी',       'flag' => '🇮🇳'],
    'ar' => ['name' => 'العربية',    'flag' => '🇸🇦'],
    'tr' => ['name' => 'Türkçe',     'flag' => '🇹🇷'],
    'it' => ['name' => 'Italiano',   'flag' => '🇮🇹'],
    'ko' => ['name' => '한국어',      'flag' => '🇰🇷'],
    'pl' => ['name' => 'Polski',     'flag' => '🇵🇱'],
];

if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $languages)) {
    $current_lang_code = $_GET['lang'];
    setcookie('lang', $current_lang_code, time() + (86400 * 30), "/");
} else {
    $current_lang_code = $_COOKIE['lang'] ?? 'ru';
    if (!array_key_exists($current_lang_code, $languages)) {
        $current_lang_code = 'ru';
    }
}

function getUrlWithLang($langCode) {
    $params = $_GET;
    $params['lang'] = $langCode;
    return '?' . http_build_query($params);
}
?>

<nav class="landing-nav">
    <a href="/" class="landing-logo">
        <span class="logo-icon">LE</span>
        <span class="logo-text">LensEra</span>
    </a>
    
    <div class="nav-links">
        <a href="/info/about.php"><?= isset($t) ? $t['nav_about'] : 'О нас' ?></a>
        <a href="/info/how_it_works.php"><?= isset($t) ? $t['nav_how'] : 'Как это работает' ?></a>
        
        <a href="/" class="btn-back-app"><?= isset($t) ? $t['nav_app'] : 'В приложение' ?></a>

        <!-- ЯЗЫКОВОЙ ПЕРЕКЛЮЧАТЕЛЬ -->
        <div class="lang-dropdown">
            <div class="lang-btn">
                <svg class="lang-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1 4-10 15.3 15.3 0 0 1-4-10z"></path>
                </svg>
                <span><?php echo strtoupper($current_lang_code); ?></span>
            </div>
            
            <div class="lang-list">
                <?php foreach ($languages as $code => $info): 
                    $isActive = ($code === $current_lang_code);
                ?>
                    <a href="<?php echo getUrlWithLang($code); ?>" 
                       class="lang-item <?php echo $isActive ? 'active' : ''; ?>">
                        
                        <div class="lang-info">
                            <span class="lang-flag"><?php echo $info['flag']; ?></span>
                            <span class="lang-name"><?php echo $info['name']; ?></span>
                        </div>

                        <?php if ($isActive): ?>
                        <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</nav>