<?php
require_once __DIR__ . '/../../src/core/config.php';

// Устанавливаем интервал смены данных, если он не задан глобально
if (!defined('CHANGE_INTERVAL_DAYS')) {
    define('CHANGE_INTERVAL_DAYS', 7);
}

$additional_styles = ['/css/settings.css'];
require_once __DIR__ . '/../../templates/partials/header.php'; 
require_once __DIR__ . '/../../templates/partials/sidebar.php';
?>

<main class="main-content">
    <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="empty-page-placeholder">
            <img src="/images/settings.png" alt="Настройки" style="width: 120px; height: 120px; filter: invert(0.8);">
            <h2>Настройки аккаунта</h2>
            <p>Чтобы изменять настройки, необходимо войти в аккаунт.</p>
            <a href="/login.php?redirect=settings" class="empty-page-button">Войти</a>
        </div>
    <?php else:
        $user_id = (int)$_SESSION['user_id'];
        
        // Получаем текущие настройки пользователя
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        $active_tab = $_GET['tab'] ?? 'account';
    ?>
        <div class="settings-modern-container">
            <h1 class="settings-page-title">Настройки</h1>

            <div class="settings-layout">
                <!-- Боковое меню -->
                <nav class="settings-sidebar-nav">
                    <a href="?tab=account" class="st-nav-item <?= $active_tab === 'account' ? 'active' : '' ?>">Аккаунт</a>
                    <a href="?tab=channel" class="st-nav-item <?= $active_tab === 'channel' ? 'active' : '' ?>">Мой канал</a>
                    <a href="?tab=notifications" class="st-nav-item <?= $active_tab === 'notifications' ? 'active' : '' ?>">Уведомления</a>
                    <a href="?tab=privacy" class="st-nav-item <?= $active_tab === 'privacy' ? 'active' : '' ?>">Конфиденциальность</a>
                    <a href="?tab=security" class="st-nav-item <?= $active_tab === 'security' ? 'active' : '' ?>">Безопасность</a>
                    <a href="?tab=danger" class="st-nav-item danger <?= $active_tab === 'danger' ? 'active' : '' ?>">Удаление аккаунта</a>
                </nav>

                <!-- Область контента -->
                <div class="settings-content-area">
                    
                    <!-- Аккаунт / Почта -->
                    <div id="account-tab" class="st-tab-content <?= $active_tab === 'account' ? 'active' : '' ?>">
                        <div class="st-card">
                            <div class="st-card-header"><h2>Учетная запись</h2></div>
                            <form id="account-form" class="st-form">
                                <div class="form-group-material">
                                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" placeholder=" " required>
                                    <label for="email">Электронная почта</label>
                                </div>
                                <p class="st-hint">Используется для входа и важных уведомлений.</p>
                                <button type="submit" class="st-btn primary">Сохранить</button>
                            </form>
                        </div>
                    </div>

                    <!-- Профиль канала -->
                    <div id="channel-tab" class="st-tab-content <?= $active_tab === 'channel' ? 'active' : '' ?>">
                        <div class="channel-card">
                            <div class="channel-card-avatar">
                                <img src="/<?= htmlspecialchars($user['avatar_url'] ?? 'images/default_avatar.png') ?>" alt="Аватар">
                            </div>
                            <div class="channel-card-info">
                                <div class="channel-card-name"><?= htmlspecialchars($user['channel_name'] ?? $user['username']) ?></div>
                                <div class="channel-card-meta">
                                    @<?= htmlspecialchars($user['username']) ?> • <?= htmlspecialchars($user['subscriber_count'] ?? 0) ?> подписчиков
                                </div>
                                <div class="channel-card-actions">
                                    <a href="/edit_profile.php?from=settings" class="st-btn secondary-light">Настроить вид канала</a>
                                    <a href="/profile.php?id=<?= htmlspecialchars($user['public_user_id']) ?>&tab=videos" class="st-btn secondary-light">Управление видео</a>
                                </div>
                            </div>
                        </div>

                        <div class="st-card" style="margin-top: 24px;">
                            <div class="st-card-header">
                                <h2>Статус канала</h2>
                                <p>Здесь можно посмотреть статус вашего канала и доступные функции.</p>
                            </div>
                            <div class="channel-features-list">
                                <div class="feature-item">
                                    <div class="feature-icon"><img src="/images/check.png" style="width:20px; filter:invert(1);"></div>
                                    <div class="feature-text">Стандартные функции включены</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Уведомления -->
                    <div id="notifications-tab" class="st-tab-content <?= $active_tab === 'notifications' ? 'active' : '' ?>">
                        <div class="st-card">
                            <div class="st-card-header">
                                <h2>Настройки уведомлений</h2>
                                <p>Выберите, о каких событиях вы хотите узнавать.</p>
                            </div>
                            <form id="notifications-form" class="st-form">
                                <div class="st-option-row">
                                    <div class="st-option-info"><span class="st-label">Подписки</span><span class="st-sublabel">Уведомлять о новых подписчиках</span></div>
                                    <label class="toggle-switch"><input type="checkbox" name="notify_on_new_subscriber" <?= ($user['notify_on_new_subscriber'] ?? 0) ? 'checked' : '' ?>><span class="slider round"></span></label>
                                </div>
                                <div class="st-option-row">
                                    <div class="st-option-info"><span class="st-label">Комментарии</span><span class="st-sublabel">Уведомлять о комментариях</span></div>
                                    <label class="toggle-switch"><input type="checkbox" name="notify_on_comment" <?= ($user['notify_on_comment'] ?? 0) ? 'checked' : '' ?>><span class="slider round"></span></label>
                                </div>
                                <div class="st-option-row">
                                    <div class="st-option-info"><span class="st-label">Отметки "Нравится"</span><span class="st-sublabel">Уведомлять об оценках</span></div>
                                    <label class="toggle-switch"><input type="checkbox" name="notify_on_like" <?= ($user['notify_on_like'] ?? 1) ? 'checked' : '' ?>><span class="slider round"></span></label>
                                </div>
                                <div class="st-option-row">
                                    <div class="st-option-info"><span class="st-label">Ответы</span><span class="st-sublabel">Уведомлять об ответах</span></div>
                                    <label class="toggle-switch"><input type="checkbox" name="notify_on_reply" <?= ($user['notify_on_reply'] ?? 1) ? 'checked' : '' ?>><span class="slider round"></span></label>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Безопасность / Пароль -->
                    <div id="security-tab" class="st-tab-content <?= $active_tab === 'security' ? 'active' : '' ?>">
                        <div class="st-card">
                            <div class="st-card-header"><h2>Пароль и вход</h2></div>
                            <p>Рекомендуем периодически менять пароль для защиты вашей учетной записи от несанкционированного доступа.</p>
                            
                            <form id="password-form" class="st-form" novalidate>
                                <div class="form-group-material">
                                    <input type="password" id="current_password" name="current_password" placeholder=" " required>
                                    <label for="current_password">Текущий пароль</label>
                                    <div class="input-error-message"></div>
                                </div>

                                <div class="st-row-inputs">
                                    <div class="form-group-material">
                                        <input type="password" id="new_password" name="new_password" placeholder=" " required>
                                        <label for="new_password">Новый пароль</label>
                                        <div class="input-error-message"></div>
                                    </div>
                                    <div class="form-group-material">
                                        <input type="password" id="confirm_new_password" name="confirm_new_password" placeholder=" " required>
                                        <label for="confirm_new_password">Подтвердите пароль</label>
                                        <div class="input-error-message"></div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="st-btn primary">Изменить пароль</button>
                            </form>
                        </div>
                    </div>

                    <!-- Конфиденциальность -->
                    <div id="privacy-tab" class="st-tab-content <?= $active_tab === 'privacy' ? 'active' : '' ?>">
                        <div class="st-card">
                            <div class="st-card-header"><h2>Конфиденциальность</h2></div>
                            <form id="privacy-form" class="st-form">
                                <div class="st-option-row">
                                    <div class="st-option-info"><span class="st-label">История просмотра</span><span class="st-sublabel">Сохранять просмотренные видео</span></div>
                                    <label class="toggle-switch"><input type="checkbox" name="history_enabled" <?= ($user['history_enabled'] ?? 1) ? 'checked' : '' ?>><span class="slider round"></span></label>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Опасная зона -->
                    <div id="danger-tab" class="st-tab-content <?= $active_tab === 'danger' ? 'active' : '' ?>">
                        <div class="st-card danger-card">
                            <div class="st-card-header">
                                <h2>Удаление аккаунта</h2>
                                <p>Это действие необратимо. Все ваши данные будут потеряны.</p>
                            </div>
                            <button id="init-delete-account-btn" class="st-btn danger">Удалить аккаунт</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Модальные окна -->
        <div class="st-modal-overlay" id="password-confirm-modal">
            <div class="st-modal">
                <h3>Смена пароля</h3>
                <p>Вы собираетесь изменить пароль. После этого действия будет выполнен <strong>автоматический выход из аккаунта на всех устройствах</strong> (включая текущее).</p>
                <p>Вы уверены, что хотите продолжить?</p>
                <div class="st-modal-actions">
                    <button class="st-btn secondary" data-action="cancel-password">Отмена</button>
                    <button class="st-btn primary" data-action="confirm-password">Да, изменить и выйти</button>
                </div>
            </div>
        </div>

        <div class="st-modal-overlay" id="delete-step1-modal">
            <div class="st-modal danger">
                <h3>Удаление аккаунта</h3>
                <p>Вы уверены? Это действие <strong>необратимо</strong>. Все ваши видео, комментарии и история будут удалены безвозвратно.</p>
                <div class="st-modal-actions">
                    <button class="st-btn secondary" data-action="cancel-delete">Отмена</button>
                    <button class="st-btn danger" data-action="next-delete-step">Продолжить</button>
                </div>
            </div>
        </div>

        <div class="st-modal-overlay" id="delete-step2-modal">
            <div class="st-modal danger">
                <h3>Подтверждение удаления</h3>
                <p>Для подтверждения введите ваш пароль и случайный код ниже.</p>
                
                <form id="final-delete-form">
                    <div class="form-group-material">
                        <input type="password" id="delete_password" name="password" placeholder=" " required>
                        <label for="delete_password">Ваш пароль</label>
                    </div>
                    
                    <div class="captcha-container">
                        <div class="captcha-code unselectable" id="captcha-display">Loading...</div>
                        <p class="st-hint">Введите символы (копирование запрещено):</p>
                        <div class="form-group-material">
                            <input type="text" id="delete_captcha" name="captcha" placeholder=" " autocomplete="off" required>
                            <label for="delete_captcha">Символы с картинки</label>
                        </div>
                    </div>

                    <div class="st-modal-actions">
                        <button type="button" class="st-btn secondary" data-action="cancel-delete">Отмена</button>
                        <button type="submit" class="st-btn danger">Удалить навсегда</button>
                    </div>
                </form>
            </div>
        </div>

    <?php endif; ?>
</main>

<?php if(isset($_SESSION['user_id'])): ?>
<script>
    const csrfToken = '<?= $csrf_token ?? '' ?>';
</script>
<?php endif; ?>

<script src="/js/settings.js"></script>
<script src="/js/script.js"></script>
</body>
</html>