<?php

require_once __DIR__ . '/../../src/core/config.php';
require_once __DIR__ . '/../../src/core/helpers.php';

$page_title = 'Отзывы';
$additional_styles = ['/css/reviews.css'];
$body_class = 'reviews-page';

$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$csrf_token = $_SESSION['csrf_token'] ?? '';

// --- 1. Логика сортировки ---
$sort_key = $_GET['sort'] ?? 'date_desc';

$order_by_sql = match($sort_key) {
    'date_asc'     => 'ORDER BY r.created_at ASC',
    'helpful_desc' => 'ORDER BY r.helpful_count DESC, r.created_at DESC',
    'rating_desc'  => 'ORDER BY r.rating DESC, r.created_at DESC',
    'rating_asc'   => 'ORDER BY r.rating ASC, r.created_at DESC',
    default        => 'ORDER BY r.created_at DESC', // date_desc
};

$current_sort_label = match($sort_key) {
    'date_asc'     => 'Сначала старые',
    'helpful_desc' => 'Самые полезные',
    'rating_desc'  => 'Высокая оценка',
    'rating_asc'   => 'Низкая оценка',
    default        => 'Сначала новые',
};

// --- 2. Получение данных ---
$my_reviews = [];
$public_reviews = [];
$user_reactions = [];

if ($current_user_id) {
    // Мои отзывы
    $stmt_my = $conn->prepare("
        SELECT r.*, u.username 
        FROM reviews r 
        JOIN users u ON r.user_id = u.user_id 
        WHERE r.user_id = ? 
        ORDER BY r.created_at DESC
    ");
    $stmt_my->bind_param("i", $current_user_id);
    $stmt_my->execute();
    $my_reviews = $stmt_my->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_my->close();

    // Реакции текущего пользователя на публичные отзывы
    $stmt_reactions = $conn->prepare("SELECT review_id, reaction_type FROM review_reactions WHERE user_id = ?");
    $stmt_reactions->bind_param("i", $current_user_id);
    $stmt_reactions->execute();
    $reactions_result = $stmt_reactions->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($reactions_result as $reaction) {
        $user_reactions[$reaction['review_id']] = $reaction['reaction_type'];
    }
    $stmt_reactions->close();
}

// Публичные отзывы
$stmt_all = $conn->prepare("
    SELECT r.*, u.username, u.avatar_url 
    FROM reviews r 
    JOIN users u ON r.user_id = u.user_id 
    WHERE r.status = 'рассмотрен' AND r.allow_publication = 1 
    {$order_by_sql}
");
$stmt_all->execute();
$public_reviews = $stmt_all->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_all->close();

require_once __DIR__ . '/../../templates/partials/header.php';
require_once __DIR__ . '/../../templates/partials/sidebar.php';
?>

<script>
    const csrfToken = "<?= htmlspecialchars($csrf_token) ?>";
</script>

<main class="main-content">
    <div class="reviews-page-container">
        <div class="reviews-page-content">

            <div class="tabs-navigation">
                <button class="tab-btn active" data-tab="my-reviews">Мои отзывы</button>
                <button class="tab-btn" data-tab="public-reviews">Отзывы пользователей</button>
                <button class="tab-btn" data-tab="add-review">Оставить отзыв</button>
            </div>

            <div class="tabs-content">

                <!-- ВКЛАДКА: МОИ ОТЗЫВЫ -->
                <section id="my-reviews" class="tab-content active">
                    <div class="reviews-toolbar">
                        <h2>Ваши обращения</h2>
                    </div>
                    
                    <?php if ($current_user_id): ?>
                        <div class="reviews-list">
                            <?php if (!empty($my_reviews)): ?>
                                <?php foreach ($my_reviews as $review): 
                                    
                                    $status_raw = $review['status'];
                                    
                                    // Маппинг статусов
                                    $status_display = match($status_raw) {
                                        'в обработке' => 'На проверке',
                                        'одобрен', 'рассмотрен' => 'Опубликован',
                                        'отклонен' => 'Отклонен',
                                        default => htmlspecialchars($status_raw)
                                    };
                                    
                                    $status_class = match($status_raw) {
                                        'в обработке' => 'status-wait',
                                        'одобрен', 'рассмотрен' => 'status-ok',
                                        'отклонен' => 'status-bad',
                                        default => 'status-wait'
                                    };

                                    // Логика обрезки длинного текста
                                    $content_full = $review['content'];
                                    $limit = 150;
                                    $is_long = mb_strlen($content_full) > $limit;
                                    $part_visible = $is_long ? mb_substr($content_full, 0, $limit) : $content_full;
                                    $part_hidden = $is_long ? mb_substr($content_full, $limit) : '';
                                ?>
                                    <div class="my-review-card" data-review-id="<?= htmlspecialchars($review['review_id']) ?>">
                                        
                                        <div class="my-review-meta-top">
                                            <span class="meta-id">№<?= htmlspecialchars($review['review_id']) ?></span>
                                            <span class="meta-divider">|</span>
                                            <span class="meta-author">Автор: <?= htmlspecialchars($review['username']) ?></span>
                                        </div>

                                        <div class="my-review-card-header">
                                            <div class="review-subject-block">
                                                <span class="review-detail-label">Тема:</span>
                                                <div class="review-detail-value subject my-review-card-subject">
                                                    <?= htmlspecialchars($review['subject']) ?>
                                                </div>
                                            </div>
                                            <span class="status-badge <?= $status_class ?>"><?= $status_display ?></span>
                                        </div>

                                        <div class="my-review-card-content">
                                            <span class="review-detail-label">Содержание:</span>
                                            <div class="review-detail-value body">
                                                <span class="text-visible"><?= nl2br(htmlspecialchars($part_visible)) ?></span>
                                                <?php if ($is_long): ?>
                                                    <span class="text-rest" style="display: none;"><?= nl2br(htmlspecialchars($part_hidden)) ?></span>
                                                    <button class="text-toggle-btn">... еще</button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (in_array($status_raw, ['рассмотрен', 'одобрен'], true) && !empty($review['admin_response'])): ?>
                                            <div class="admin-response">
                                                <p><strong>Ответ администратора:</strong><br> <?= nl2br(htmlspecialchars($review['admin_response'])) ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($status_raw === 'в обработке'): ?>
                                            <div class="my-review-card-footer">
                                                <button class="edit-review-btn" title="Редактировать">
                                                    <span class="btn-icon">
                                                        <img src="/images/edit.png" alt="Ред.">
                                                    </span>
                                                    <span>Редактировать</span>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="no-reviews-message">Вы еще не оставляли отзывов. <a href="#" class="link-to-tab" data-tab="add-review">Хотите оставить первый?</a></p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="no-reviews-message">Пожалуйста, <a href="/login.php">войдите в аккаунт</a>, чтобы просмотреть свои отзывы.</p>
                    <?php endif; ?>
                </section>

                <!-- ВКЛАДКА: ПУБЛИЧНЫЕ ОТЗЫВЫ -->
                <section id="public-reviews" class="tab-content">
                    <div class="reviews-toolbar">
                        <h2>Что говорят другие</h2>
                        <div class="sort-menu-container">
                            <button class="sort-menu-button">
                                <span><?= htmlspecialchars($current_sort_label) ?></span>
                                <img src="/images/arrow-down.png" class="sort-arrow-icon" alt="v">
                            </button>
                            <div class="sort-menu-dropdown">
                                <a href="?sort=date_desc" class="sort-option">Сначала новые</a>
                                <a href="?sort=date_asc" class="sort-option">Сначала старые</a>
                                <a href="?sort=helpful_desc" class="sort-option">Самые полезные</a>
                                <a href="?sort=rating_desc" class="sort-option">Высокая оценка</a>
                                <a href="?sort=rating_asc" class="sort-option">Низкая оценка</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="reviews-list">
                        <?php if (!empty($public_reviews)): ?>
                            <?php foreach ($public_reviews as $review): ?>
                                <article class="review-card" data-review-id="<?= htmlspecialchars($review['review_id']) ?>">
                                    <header class="review-header">
                                        <div class="user-info">
                                            <img src="/<?= htmlspecialchars($review['avatar_url'] ?? 'images/default-avatar.png') ?>" alt="Аватар">
                                            <div>
                                                <div class="username"><?= htmlspecialchars($review['username']) ?></div>
                                                <div class="date"><?= format_time_ago($review['created_at']) ?></div>
                                            </div>
                                        </div>
                                        <div class="stars-display">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <span class="star <?= ($i <= (int)$review['rating']) ? 'filled' : '' ?>">★</span>
                                            <?php endfor; ?>
                                        </div>
                                    </header>
                                    
                                    <div class="review-body">
                                        <strong><?= htmlspecialchars($review['subject']) ?></strong>
                                        <p><?= nl2br(htmlspecialchars($review['content'])) ?></p>
                                    </div>
                                    
                                    <footer class="review-footer">
                                        <div class="reactions">
                                            <?php $current_reaction = $user_reactions[$review['review_id']] ?? ''; ?>
                                            <button class="reaction-btn helpful" data-reaction="helpful" <?= $current_reaction === 'helpful' ? 'disabled' : '' ?>>
                                                👍 <span class="count"><?= (int)$review['helpful_count'] ?></span>
                                            </button>
                                            <button class="reaction-btn unhelpful" data-reaction="unhelpful" <?= $current_reaction === 'unhelpful' ? 'disabled' : '' ?>>
                                                👎 <span class="count"><?= (int)$review['unhelpful_count'] ?></span>
                                            </button>
                                        </div>
                                    </footer>
                                </article>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="no-reviews-message">Опубликованных отзывов пока нет.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- ВКЛАДКА: ОСТАВИТЬ ОТЗЫВ -->
                <section id="add-review" class="tab-content">
                    <?php if ($current_user_id): ?>
                        <div class="review-form-container review-form-wrapper">
                            
                            <div id="review-step-1" class="form-step active">
                                <h2>Оцените качество сервиса</h2>
                                <p class="step-description">
                                    Пожалуйста, поставьте оценку. Это поможет нам стать лучше, а другим пользователям — принять правильное решение. Честность превыше всего!
                                </p>
                                <div class="star-rating">
                                    <input type="radio" id="star5" name="rating" value="5"><label for="star5">★</label>
                                    <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
                                    <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
                                    <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
                                    <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
                                </div>
                                <div id="rating-error" class="form-error-message"></div>
                                <div class="form-actions"><button id="next-step-btn" class="btn btn-primary">Далее</button></div>
                            </div>

                            <div id="review-step-2" class="form-step">
                                <h2>Расскажите подробнее</h2>
                                <form id="review-form" novalidate>
                                    <input type="hidden" id="rating-value" name="rating">
                                    
                                    <div class="form-group">
                                        <label for="subject">Тема</label>
                                        <input type="text" id="subject" name="subject" maxlength="30" placeholder="Например: Отличная скорость..." required>
                                        <div id="counter-subject" class="char-counter">0/30</div>
                                        <div class="validation-message" id="error-subject">Пожалуйста, введите название темы</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="content">Содержание</label>
                                        <textarea id="content" name="content" rows="4" maxlength="500" placeholder="Опишите ваш опыт использования..." required></textarea>
                                        <div id="counter-content" class="char-counter">0/500</div>
                                        <div class="validation-message" id="error-content">Пожалуйста, напишите текст отзыва</div>
                                    </div>

                                    <div class="form-group checkbox-group">
                                        <label for="consent-checkbox">
                                            <input type="checkbox" id="consent-checkbox" name="consent" value="1" checked>
                                            Я даю согласие на публикацию
                                        </label>
                                    </div>
                                    <button type="submit" id="submit-review-btn" class="btn btn-primary btn-full-width">Отправить</button>
                                </form>
                            </div>
                            
                            <div id="review-success-step" class="form-step"></div>
                        </div>
                    <?php else: ?>
                        <p class="no-reviews-message">Пожалуйста, <a href="/login.php">войдите в аккаунт</a>, чтобы оставить отзыв.</p>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
</main>

<!-- МОДАЛЬНОЕ ОКНО РЕДАКТИРОВАНИЯ -->
<div id="edit-review-modal" class="review-modal-overlay" style="display: none;">
    <div class="review-modal-content">
        <button class="review-modal-close">&times;</button>
        <h2>Редактировать отзыв</h2>
        <form id="edit-review-form">
            <input type="hidden" name="review_id" id="edit-review-id">
            
            <div class="form-group">
                <label for="edit-subject">Тема</label>
                <input type="text" id="edit-subject" name="subject" maxlength="30" required>
                <div id="counter-edit-subject" class="char-counter">0/30</div>
            </div>
            
            <div class="form-group">
                <label for="edit-content">Содержание</label>
                <textarea id="edit-content" name="content" maxlength="500" required></textarea>
                <div id="counter-edit-content" class="char-counter">0/500</div>
            </div>
            
            <button type="submit" class="btn btn-primary">Сохранить</button>
        </form>
    </div>
</div>

</div> <!-- Закрытие обертки из sidebar -->

<script src="/js/reviews.js"></script>
<script src="/js/script.js"></script>
</body>
</html>