<?php

function get_plural_form($number, $forms) {
    $number = abs($number) % 100;
    $remainder = $number % 10;
    if ($number > 10 && $number < 20) return $forms[2];
    if ($remainder > 1 && $remainder < 5) return $forms[1];
    if ($remainder == 1) return $forms[0];
    return $forms[2];
}


function format_time_ago($datetime_str) {
    try {
        $now = new DateTime();
        $then = new DateTime($datetime_str);
    } catch (Exception $e) {
        return '';
    }
    
    $diff = $now->diff($then);

    if ($diff->y > 0) return $diff->y . ' ' . get_plural_form($diff->y, ['год', 'года', 'лет']) . ' назад';
    if ($diff->m > 0) return $diff->m . ' ' . get_plural_form($diff->m, ['месяц', 'месяца', 'месяцев']) . ' назад';
    if ($diff->d > 0) return $diff->d . ' ' . get_plural_form($diff->d, ['день', 'дня', 'дней']) . ' назад';
    if ($diff->h > 0) return $diff->h . ' ' . get_plural_form($diff->h, ['час', 'часа', 'часов']) . ' назад';
    if ($diff->i > 0) return $diff->i . ' ' . get_plural_form($diff->i, ['минуту', 'минуты', 'минут']) . ' назад';
    
    return 'Только что';
}

function format_history_date($datetime_str) {
    $months_genitive = [
        'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
    ];
    
    try {
        $date = new DateTime($datetime_str);
    } catch (Exception $e) {
        return $datetime_str; 
    }
    
    $month_index = intval($date->format('n')) - 1;
    
    return $date->format('j') . ' ' . $months_genitive[$month_index] . ' ' . $date->format('Y') . ' года';
}

/**
 * @param string $date_string Дата в формате YYYY-MM-DD (или TIMESTAMP).
 * @return string
 */
function format_date_russian(string $date_string): string {
    $months_nominative = [
        'января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
        'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'
    ];
    
    try {
        $date = new DateTime($date_string);
    } catch (Exception $e) {
        return $date_string; 
    }
    
    $day = $date->format('j'); 
    $month_index = intval($date->format('n')) - 1; 
    $year = $date->format('Y'); 
    
    $month_ru = $months_nominative[$month_index] ?? '';
    
    return "{$day} {$month_ru} {$year} г."; 
}

/**
 * Функция для безопасного сокращения текста
 */
function truncate_text($text, $length) {
    if (mb_strlen($text) > $length) {
        return mb_substr($text, 0, $length) . '...';
    }
    return $text;
}
/**
 * Форматирует длительность видео из секунд в формат ЧЧ:ММ:СС или ММ:СС
 */
function format_duration($seconds) {
    if ($seconds <= 0) {
        return "0:00";
    }
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;

    if ($hours > 0) {
        return sprintf("%d:%02d:%02d", $hours, $minutes, $secs);
    } else {
        return sprintf("%d:%02d", $minutes, $secs);
    }
}
/**
 * @param string $title Название ссылки.
 * @param string $url URL ссылки.
 * @return string Полный путь к файлу иконки относительно папки '/images/' (например, 'social/vk.png' или 'link_picture.png').
 */
function get_social_icon_filename(string $title, string $url): string {
    $title_lower = mb_strtolower($title);
    $url_lower = mb_strtolower($url);
    $social_prefix = 'social/';

    if (mb_stripos($title_lower, 'вк') !== false || mb_stripos($url_lower, 'vk.com') !== false || mb_stripos($url_lower, 'vkontakte.ru') !== false) return $social_prefix . 'vk.png'; // ИСПРАВЛЕНО: vk.png
    if (mb_stripos($title_lower, 'tele') !== false || mb_stripos($url_lower, 't.me') !== false || mb_stripos($url_lower, 'telegram') !== false) return $social_prefix . 'telegram.png'; 
    if (mb_stripos($title_lower, 'tiktok') !== false || mb_stripos($url_lower, 'tiktok.com') !== false) return $social_prefix . 'tiktok.png'; 
    if (mb_stripos($title_lower, 'inst') !== false || mb_stripos($url_lower, 'instagram.com') !== false) return $social_prefix . 'instagram.png'; 
    if (mb_stripos($title_lower, 'твиттер') !== false || mb_stripos($url_lower, 'twitter.com') !== false || mb_stripos($url_lower, 'x.com') !== false) return $social_prefix . 'twitter.png'; 
    if (mb_stripos($title_lower, 'дискорд') !== false || mb_stripos($url_lower, 'discord.gg') !== false) return $social_prefix . 'discord.png'; 
    if (mb_stripos($url_lower, 'youtube.com') !== false || mb_stripos($url_lower, 'youtu.be') !== false) return $social_prefix . 'youtube.png'; 
    if (mb_stripos($url_lower, 'facebook.com') !== false) return $social_prefix . 'facebook.png'; 
    if (mb_stripos($url_lower, 'twitch.tv') !== false) return $social_prefix . 'twitch.png'; 
    
    if (mb_stripos($title_lower, 'мем') !== false || mb_stripos($title_lower, 'изображение') !== false) return 'link.png'; 
    if (mb_stripos($title_lower, 'почта') !== false || mb_stripos($title_lower, 'email') !== false) return 'link_email.png'; 
    
    return $social_prefix . 'link.png'; 
}

function createNotification($conn, $userId, $actorId, $type, $targetId = null) {
    if ($userId === $actorId) return;

    $stmt = $conn->prepare("SELECT notify_on_comment, notify_on_new_subscriber, notify_on_like, notify_on_reply FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res->num_rows === 0) return;
    
    $prefs = $res->fetch_assoc();
    $stmt->close();

    $shouldNotify = false;
    switch ($type) {
        case 'subscription': 
            $shouldNotify = $prefs['notify_on_new_subscriber'] ?? 1; 
            break;
        case 'comment':      
            $shouldNotify = $prefs['notify_on_comment'] ?? 1; 
            break;
        case 'like':         
            $shouldNotify = $prefs['notify_on_like'] ?? 1; 
            break;
        case 'reply':        
            $shouldNotify = $prefs['notify_on_reply'] ?? 1; 
            break;
        case 'system':       
        case 'review_reply':
        case 'report_update':
            $shouldNotify = true;
            break;
    }

    if ($shouldNotify) {
        $insert = $conn->prepare("INSERT INTO notifications (user_id, actor_user_id, type, target_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        if ($insert) {
            $insert->bind_param("iisi", $userId, $actorId, $type, $targetId);
            $insert->execute();
            $insert->close();
        }
    }
}

/**
 * Очищает URL, разрешая только http и https.
 * Защищает от javascript:alert(1)
 */
function sanitize_url($url) {
    $url = trim($url);
    if (empty($url)) return '#';
    
    if (!preg_match('/^https?:\/\//i', $url)) {
        if (strpos($url, '/') === 0) {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }
        return 'https://' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
    
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

?>