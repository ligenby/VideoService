<?php

$additional_styles = ['/css/complaints.css'];
require_once __DIR__ . '/../../templates/partials/header.php'; 
require_once __DIR__ . '/../../templates/partials/sidebar.php';


// форматируем дату
function format_russian_date($date_string) {

    $date = new DateTime($date_string);
    $months = [
        'Jan' => 'января', 'Feb' => 'февраля', 'Mar' => 'марта',
        'Apr' => 'апреля', 'May' => 'мая', 'Jun' => 'июня',
        'Jul' => 'июля', 'Aug' => 'августа', 'Sep' => 'сентября',
        'Oct' => 'октября', 'Nov' => 'ноября', 'Dec' => 'декабря'
    ];
    $month_en = $date->format('M');
    $month_ru = $months[$month_en];
    return $date->format('j ') . $month_ru . $date->format(' Y г.');
}

// Проверяем, авторизован ли пользователь
if (!isset($_SESSION['user_id'])):
?>
    <main class="main-content">
        <div class="empty-page-placeholder">
            <img src="/images/complaint.png" alt="Правила сообщества">
            <h2>Войдите в аккаунт</h2>
            <p>Чтобы отправлять жалобы на нарушения и просматривать их историю, необходимо войти в свой профиль.</p>
            <a href="/login.php" class="empty-page-button">Войти</a>
        </div>
    </main>
<?php
else:
// юзер ид
    $user_id = (int)$_SESSION['user_id'];

    
    // запрос жалоб
    $stmt = $conn->prepare("

        SELECT 
            r.reason, r.status, r.created_at,
            v.public_video_id, v.title,
            u.username as author_username, u.channel_name as author_channel_name
        FROM reports r
        JOIN videos v ON r.target_id = v.video_id
        JOIN users u ON v.user_id = u.user_id
        WHERE r.user_id = ? AND r.target_type = 'video'
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
?>
    <main class="main-content">
        <div class="complaints-container">
            <div class="complaints-header">
                <div class="header-text">
                    <h1>Центр рассмотрения жалоб</h1>
                    <p>
                        Мы ценим вашу помощь в поддержании порядка на VideoService. Каждая жалоба проходит ручную проверку нашими модераторами. Вот как мы поступаем после получения вашего сигнала:
                    </p>
                    <ul>
                        <li><strong>Проверка на соответствие правилам:</strong> Если видео действительно нарушает <a href="/info/rules.php">правила сообщества</a>, мы его удаляем.</li>
                        <li><strong>Анализ возрастного контента:</strong> Если материал не подходит для всех возрастов, мы устанавливаем возрастное ограничение.</li>
                        <li><strong>Отслеживание статуса контента:</strong> Если автор уже удалил видео, мы закрываем вашу жалобу.</li>
                    </ul>
                    <p>Подробнее о условиях использования проекта написано <a href="/info/terms.php">здесь</a>.</p>
                </div>
                <img src="/images/report-illustration.png" alt="Иллюстрация" class="header-illustration">
            </div>

            <div class="complaints-controls">
                <div class="filter-dropdown-container">
                    <button class="filter-button" id="filter-trigger-button">
                        <span id="filter-text">Все</span>
                        <img src="/images/arrow-down.png" alt="v">
                    </button>
                    <div class="filter-dropdown" id="filter-dropdown-menu">
                        <a href="#" class="filter-option" data-filter="all">Все</a>
                        <a href="#" class="filter-option" data-filter="week">За неделю</a>
                        <a href="#" class="filter-option" data-filter="month">За месяц</a>
                        <a href="#" class="filter-option" data-filter="half_year">За 6 месяцев</a>
                        <a href="#" class="filter-option" data-filter="year">За год</a>
                    </div>
                </div>
            </div>

            <?php if (!empty($reports)): ?>
                <div class="complaints-table-wrapper">
                    <div class="table-header">
                        <div class="col-type">Тип</div>
                        <div class="col-content">Контент</div>
                        <div class="col-reason">Причина жалобы</div>
                        <div class="col-status">Статус</div>
                    </div>
                    <div class="table-body" id="complaints-list">
                        <?php foreach ($reports as $report): ?>
                            <?php
                                $report_date = new DateTime($report['created_at']);
                                $data_timestamp = $report_date->getTimestamp();
                                $reason_text = htmlspecialchars($report['reason']);
                                $is_long_reason = mb_strlen($reason_text) > 100;
                            ?>
                            <div class="table-row" data-timestamp="<?php echo $data_timestamp; ?>">
                                <div class="col-type">
                                    <img src="/images/video-icon.png" alt="Видео">
                                </div>
                                <div class="col-content">
                                    <a href="/watch.php?id=<?php echo htmlspecialchars($report['public_video_id']); ?>"><?php echo htmlspecialchars($report['title']); ?></a>
                                    <span><?php echo htmlspecialchars($report['author_channel_name'] ?? $report['author_username']); ?></span>
                                </div>
                                <div class="col-reason">
                                    <div class="reason-bubble <?php if ($is_long_reason) echo 'collapsible collapsed'; ?>">
                                        <p><?php echo $reason_text; ?></p>
                                    </div>
                                    <?php if ($is_long_reason): ?>
                                        <button class="show-more-btn">еще</button>
                                    <?php endif; ?>
                                    <span class="report-date"><?php echo format_russian_date($report['created_at']); ?></span>
                                </div>
                                <div class="col-status">
                                    <span class="status-badge status-<?php echo htmlspecialchars($report['status']); ?>">
                                        <?php
                                            switch ($report['status']) {
                                                case 'open': echo 'На рассмотрении'; break;
                                                case 'resolved': echo 'Приняты меры'; break;
                                                case 'dismissed': echo 'Отклонено'; break;
                                                default: echo htmlspecialchars($report['status']);
                                            }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="empty-placeholder">
                    <h2>Вы ещё не сообщали о нарушениях в видео.</h2>
                </div>
            <?php endif; ?>
        </div>
    </main>
<?php 
endif; 
?>

</div>

<script src="/js/complaints.js"></script>
<script src="/js/script.js"></script>
</body>
</html>