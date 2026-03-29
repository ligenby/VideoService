<?php 
// templates/partials/global_modals.php
?>

<!-- =================================================================== -->
<!-- МОДАЛЬНЫЕ ОКНА, ТРЕБУЮЩИЕ АВТОРИЗАЦИИ -->
<!-- =================================================================== -->
<?php if (isset($user_id) && $user_id): ?>
    
    <!-- 1. УНИВЕРСАЛЬНОЕ ОКНО ПОДТВЕРЖДЕНИЯ (Confirmation Modal) -->
    <div class="confirmation-modal-overlay" id="confirmation-modal-overlay">
        <div class="confirmation-modal-content">
            <h3 class="confirmation-modal-title" id="confirmation-modal-title">Вы уверены?</h3>
            <div class="confirmation-modal-body">
                <p id="confirmation-modal-text">Это действие нельзя будет отменить.</p>
            </div>
            <div class="confirmation-modal-footer">
                <button class="confirmation-modal-button cancel" data-action="close-modal">Отмена</button>
                <button class="confirmation-modal-button confirm" id="confirmation-modal-confirm-btn" data-action="confirm-action">Подтвердить</button>
            </div>
        </div>
    </div>
    
    <!-- 2. ОКНО "ДОБАВИТЬ В ПЛЕЙЛИСТ" -->
    <div class="add-to-playlist-modal-overlay" id="add-to-playlist-modal">
        <div class="add-to-playlist-modal-content">
            <div class="add-to-playlist-modal-header">
                <h3 class="add-to-playlist-modal-title">Добавить в плейлист</h3>
                <button class="add-to-playlist-modal-close" data-action="close-playlist-modal">&times;</button>
            </div>
            <div class="playlist-selection-list"></div>
            <div class="create-new-playlist-section">
                <button class="create-new-playlist-link" data-action="open-create-playlist-modal"><img src="/images/upload.png" alt="+"><span>Создать плейлист</span></button>
            </div>
        </div>
    </div>
    
    <!-- 3. ОКНО "СОЗДАТЬ ПЛЕЙЛИСТ" (Всплывающее) -->
    <div class="modal-overlay-small" id="create-playlist-fly-modal">
        <div class="modal-content-small">
            <h3>Новый плейлист</h3>
            <form id="create-playlist-fly-form" novalidate>
                <div class="form-group-small input-wrapper-small">
                    <input type="text" name="title" placeholder="Укажите название" required maxlength="100">
                    <div class="input-error-message-small"></div>
                </div>
                <div class="form-group-small">
                    <label>Доступ</label>
                    <div class="custom-select-container" id="visibility-select-container">
                        <input type="hidden" name="visibility" value="private">
                        <button type="button" class="custom-select-button">
                            <span class="custom-select-value">Ограниченный доступ</span>
                            <img src="/images/arrow-down.png" alt="v" class="custom-select-arrow">
                        </button>
                        <div class="custom-select-dropdown">
                            <div class="custom-select-option" data-value="private">Ограниченный доступ</div>
                            <div class="custom-select-option" data-value="public">Открытый доступ</div>
                            <div class="custom-select-option" data-value="unlisted">Доступ по ссылке</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer-small">
                    <button type="button" class="modal-button-small cancel" data-action="close-create-fly-modal">Отмена</button>
                    <button type="submit" class="modal-button-small create">Создать</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- =================================================================== -->
<!-- МОДАЛЬНЫЕ ОКНА, ДОСТУПНЫЕ ВСЕМ (и для гостей) -->
<!-- =================================================================== -->

<!-- 4. ОКНО "ПОДЕЛИТЬСЯ" -->
<div class="share-modal-overlay" id="share-modal">
    <div class="share-modal-content">
        <div class="share-modal-header">
            <h3 class="share-modal-title">Поделиться</h3>
            <button class="share-modal-close" data-action="close-modal">&times;</button>
        </div>
        <div class="share-social-links">
            <a href="#" class="social-link-item" data-action="share-social" data-network="vk"><img src="/images/vk.png" alt="ВКонтакте" class="social-icon"><span class="social-name">ВКонтакте</span></a>
            <a href="#" class="social-link-item" data-action="share-social" data-network="telegram"><img src="/images/telegram.png" alt="Telegram" class="social-icon"><span class="social-name">Telegram</span></a>
            <a href="#" class="social-link-item" data-action="share-social" data-network="twitter"><img src="/images/twitter.png" alt="Twitter" class="social-icon"><span class="social-name">Twitter</span></a>
        </div>
        <div class="share-link-container">
            <input type="text" class="share-link-input" readonly>
            <button class="share-copy-button" data-action="copy-share-link">Копировать</button>
        </div>
    </div>
</div>

<!-- 5. ОКНО "ТРЕБУЕТСЯ ВХОД" -->
<div class="modal-overlay-small" id="login-required-modal">
    <div class="modal-content-small login-modal-content">
        <button class="modal-close-button" data-action="close-modal">&times;</button>
        <h3>Требуется вход в аккаунт</h3>
        <p class="login-modal-text">Чтобы воспользоваться этой функцией, пожалуйста, войдите в свой аккаунт или зарегистрируйтесь.</p>
        <div class="login-modal-actions">
            <a href="/register.php" class="modal-button-small secondary">Регистрация</a>
            <a href="/login.php" class="modal-button-small create">Войти</a>
        </div>
    </div>
</div>