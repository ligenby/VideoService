class ModalManager {
    
    constructor() {
        // Определение состояния авторизации на основе переданных с сервера данных
        this.isUserLoggedIn = typeof userPlaylists !== 'undefined' && userPlaylists !== null;
        this.csrfToken = typeof csrfToken !== 'undefined' ? csrfToken : '';
        this.notificationTimer = null;

        // Кэширование DOM-элементов для быстрого доступа
        this.elements = {
            modals: {
                loginRequired: document.getElementById('login-required-modal'),
                share: document.getElementById('share-modal'),
                addToPlaylist: document.getElementById('add-to-playlist-modal'),
                createPlaylist: document.getElementById('create-playlist-fly-modal'),
                confirmation: document.getElementById('confirmation-modal-overlay'),
            },
            forms: {
                createPlaylist: document.getElementById('create-playlist-fly-form'),
            },
        };
        
        // Привязка локальных обработчиков форм
        if (this.elements.forms.createPlaylist) {
            this.elements.forms.createPlaylist.addEventListener('submit', this.handleCreatePlaylistSubmit.bind(this));
            
            const titleInput = this.elements.forms.createPlaylist.querySelector('input[name="title"]');
            if (titleInput) {
                titleInput.addEventListener('input', this.clearCreatePlaylistError.bind(this));
            }
        }
        
        this.initGlobalListeners();
    }

    /**
     * Инициализирует глобальные слушатели на уровне body
     * с использованием паттерна "Делегирование событий".
     */
    initGlobalListeners() {
        document.body.addEventListener('click', (event) => {
            const target = event.target;
            const actionTarget = target.closest('[data-action]');
            
            // 1. Управление кастомным select (например, фильтр "Доступ" в модале)
            this.handleCustomSelect(target);
            
            // 2. Обработка закрытия модальных окон (клик по оверлею или крестику)
            const isCloseTrigger = target.matches('.share-modal-overlay, .confirmation-modal-overlay, .add-to-playlist-modal-overlay, .modal-close-button, .share-modal-close, .add-to-playlist-modal-close');
            
            if (isCloseTrigger) {
                const modal = target.closest('.share-modal-overlay') || 
                              target.closest('.confirmation-modal-overlay') || 
                              target.closest('.add-to-playlist-modal-overlay') || 
                              target.closest('.modal-overlay-small');
                if (modal) {
                     this.closeModal(modal);
                     event.preventDefault();
                }
            }
            
            // 3. Выполнение общих действий (кнопки внутри модалов)
            if (actionTarget) {
                const action = actionTarget.dataset.action;
                
                // Предотвращаем стандартный переход для всех кнопок-действий, кроме специфичных ссылок
                if (actionTarget.tagName === 'BUTTON' || (actionTarget.tagName === 'A' && action !== 'share-social' && action !== 'select-playlist')) {
                     event.preventDefault();
                }

                // Карта общих действий (Action Dispatcher)
                const commonActions = {
                    'close-modal': () => this.closeModal(actionTarget.closest('[class*="-modal-overlay"]')),
                    'close-playlist-modal': () => this.closeModal(this.elements.modals.addToPlaylist),
                    'close-create-fly-modal': () => this.closeModal(this.elements.modals.createPlaylist),
                    
                    'copy-share-link': () => this.handleCopyShareLink(actionTarget),
                    'share-social': () => this.handleSocialShare(actionTarget),

                    'select-playlist': () => this.handleSelectPlaylist(actionTarget),
                    'open-create-playlist-modal': () => this.openCreatePlaylistModal(),
                };
                
                if (commonActions[action]) {
                    commonActions[action]();
                    // Закрываем контекстное меню, если действие было вызвано из него
                    actionTarget.closest('.context-menu-dropdown')?.classList.remove('active');
                }
            }
        });
    }

    // --- Управление UI: Модальные окна и уведомления ---

    openModal(modal) {
        if (modal) modal.classList.add('active');
    }

    closeModal(modal) {
        if (modal) modal.classList.remove('active');
    }

    /**
     * Показывает всплывающее уведомление (toast) в нижней части экрана.
     */
    showNotification(message, isSuccess = true) {
        clearTimeout(this.notificationTimer);
        
        let notification = document.querySelector('.notification');
        if (!notification) {
            notification = document.createElement('div');
            notification.className = 'notification';
            document.body.appendChild(notification);
        }
        
        notification.textContent = message;
        notification.className = `notification ${isSuccess ? '' : 'error'}`;
        
        // Небольшая задержка для срабатывания CSS transition
        setTimeout(() => notification.classList.add('visible'), 10);
        
        this.notificationTimer = setTimeout(() => {
            notification.classList.remove('visible');
        }, 5000);
    }
    
    // --- Управление элементами UI ---

    /**
     * Позиционирует выпадающее меню в зависимости от доступного места на экране.
     */
    positionDropdown(trigger, dropdown) {
        const rect = trigger.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        
        dropdown.style.visibility = 'hidden';
        dropdown.style.display = 'block';
        const dropdownHeight = dropdown.offsetHeight;
        dropdown.style.visibility = '';
        dropdown.style.display = '';
        
        if (spaceBelow < (dropdownHeight + 20)) {
            dropdown.classList.add('opens-upward');
        } else {
            dropdown.classList.remove('opens-upward');
        }
    }

    /**
     * Открывает или закрывает контекстное меню (по клику на "три точки").
     */
    toggleDropdown(button, activeVideoId) {
        const menu = button.nextElementSibling;
        if (!menu) return;

        const isActive = menu.classList.contains('active');
        
        // Закрываем все другие открытые меню
        document.querySelectorAll('.context-menu-dropdown.active').forEach(openMenu => {
            if (openMenu !== menu) openMenu.classList.remove('active');
        });

        if (!isActive) {
            this.positionDropdown(button, menu);
            menu.classList.add('active');
        } else {
            menu.classList.remove('active');
        }
    }
    
    /**
     * Обработка кастомного выпадающего списка (замена стандартного <select>).
     */
    handleCustomSelect(target) {
        const selectContainer = document.getElementById('visibility-select-container');
        if (!selectContainer) return;

        if (target.closest('.custom-select-button')) {
            selectContainer.classList.toggle('open');
        } else if (target.closest('.custom-select-option')) {
            const option = target.closest('.custom-select-option');
            selectContainer.querySelector('input[name="visibility"]').value = option.dataset.value;
            selectContainer.querySelector('.custom-select-value').textContent = option.textContent.trim();
            selectContainer.classList.remove('open');
        } else if (!target.closest('.custom-select-container')) {
            selectContainer.classList.remove('open');
        }
    }
    
    // --- Логика открытия конкретных модальных окон ---

    openLoginRequiredModal() {
        this.openModal(this.elements.modals.loginRequired);
    }

    openShareModal(videoId) {
        if (!this.elements.modals.share) return;
        this.elements.modals.share.querySelector('.share-link-input').value = `${window.location.origin}/watch.php?id=${videoId}`;
        this.openModal(this.elements.modals.share);
    }
    
    openAddToPlaylistModal(videoId) {
        const modal = this.elements.modals.addToPlaylist;
        if (!modal || !videoId) return;
        
        modal.dataset.videoId = videoId;
        const listContainer = modal.querySelector('.playlist-selection-list');
        listContainer.innerHTML = '';

        if (this.isUserLoggedIn && userPlaylists.length > 0) {
            userPlaylists.forEach(playlist => {
                listContainer.innerHTML += `
                    <div class="playlist-selection-item" data-playlist-id="${playlist.playlist_id}" data-action="select-playlist">
                        ${playlist.title}
                    </div>`;
            });
        } else {
            listContainer.innerHTML = '<p class="no-playlists-message">У вас пока нет плейлистов.</p>';
        }
        this.openModal(modal);
    }
    
    openCreatePlaylistModal() {
        const modal = this.elements.modals.createPlaylist;
        if (!modal) return;
        this.clearCreatePlaylistError();
        modal.dataset.videoId = this.elements.modals.addToPlaylist.dataset.videoId;
        this.openModal(modal);
    }
    
    clearCreatePlaylistError() {
        const form = this.elements.forms.createPlaylist;
        if (!form) return;
        
        const titleInput = form.querySelector('input[name="title"]');
        const errorContainer = form.querySelector('.input-error-message-small');
        
        titleInput.classList.remove('input-error');
        if (errorContainer) {
            errorContainer.classList.remove('visible');
            errorContainer.textContent = '';
        }
    }

    // --- AJAX-запросы ---

    /**
     * Добавление/удаление видео из системного плейлиста "Смотреть позже".
     */
    toggleWatchLater(videoId, linkElement) {
        if (!videoId) return;
        
        const textSpan = linkElement.querySelector('span');
        const body = `video_id=${videoId}&csrf_token=${this.csrfToken}`;

        fetch('/api/toggle_watch_later', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.showNotification(data.is_added ? "Добавлено в 'Смотреть позже'" : "Удалено из 'Смотреть позже'");
                if (textSpan) {
                    textSpan.textContent = data.is_added ? "Удалить из 'Смотреть позже'" : "Смотреть позже";
                }
            } else {
                this.showNotification(data.message || "Ошибка выполнения операции", false);
            }
        })
        .catch(() => this.showNotification("Ошибка сети", false));
    }
    
    /**
     * Добавление видео в пользовательский плейлист.
     */
    handleSelectPlaylist(element) {
        const playlistId = element.dataset.playlistId;
        const videoId = this.elements.modals.addToPlaylist.dataset.videoId;

        if (!playlistId || !videoId) return;
        
        const body = `video_id=${videoId}&playlist_id=${playlistId}&csrf_token=${this.csrfToken}`;

        fetch('/api/add_video_to_playlist', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(res => res.json())
        .then(data => this.showNotification(data.message || "Действие выполнено", data.success))
        .catch(() => this.showNotification("Ошибка сети", false))
        .finally(() => this.closeModal(this.elements.modals.addToPlaylist));
    }

    /**
     * Создание нового плейлиста и опциональное добавление туда текущего видео.
     */
    handleCreatePlaylistSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const titleInput = form.querySelector('input[name="title"]');
        
        this.clearCreatePlaylistError();

        if (titleInput.value.trim() === '') {
            titleInput.classList.add('input-error');
            const errorContainer = form.querySelector('.input-error-message-small');
            if (errorContainer) {
                errorContainer.textContent = 'Пожалуйста, укажите название плейлиста.';
                errorContainer.classList.add('visible');
            }
            return;
        }

        const formData = new FormData(form);
        const videoId = this.elements.modals.createPlaylist.dataset.videoId;
        
        if (videoId) {
            formData.append('video_id_to_add', videoId);
        }
        formData.append('csrf_token', this.csrfToken);
        
        fetch('/api/create_playlist', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            this.showNotification(data.message || "Плейлист создан", data.success);
            if (data.success) {
                this.closeModal(this.elements.modals.createPlaylist);
                this.closeModal(this.elements.modals.addToPlaylist);
                form.reset();
                
                // Добавляем созданный плейлист в глобальный массив для UI
                if (this.isUserLoggedIn && data.playlist && typeof userPlaylists !== 'undefined') {
                    userPlaylists.push(data.playlist);
                }
            }
        })
        .catch(() => this.showNotification("Ошибка сети", false));
    }
    
    // --- Логика компонента "Поделиться" ---
    
    /**
     * Копирование ссылки в буфер обмена.
     */
    handleCopyShareLink(button) {
        const linkInput = this.elements.modals.share?.querySelector('.share-link-input');
        if (!linkInput) return;
        
        navigator.clipboard.writeText(linkInput.value).then(() => {
            this.showNotification("Ссылка скопирована в буфер обмена");
            
            const originalText = button.textContent;
            button.textContent = 'Скопировано!';
            button.disabled = true;
            
            setTimeout(() => {
                button.textContent = originalText;
                button.disabled = false;
            }, 2000);
        }).catch(() => this.showNotification("Не удалось скопировать ссылку", false));
    }

    /**
     * Генерация ссылки для репоста в популярные социальные сети.
     */
    handleSocialShare(linkElement) {
        const network = linkElement.dataset.network;
        const inputField = this.elements.modals.share.querySelector('.share-link-input');
        const urlToShare = encodeURIComponent(inputField.value);
        
        let shareUrl = '';
        
        switch(network) {
            case 'vk': 
                shareUrl = `https://vk.com/share.php?url=${urlToShare}`; 
                break;
            case 'telegram': 
                shareUrl = `https://t.me/share/url?url=${urlToShare}`; 
                break;
            case 'twitter': 
                shareUrl = `https://twitter.com/intent/tweet?url=${urlToShare}`; 
                break;
        }
        
        if (shareUrl) {
            window.open(shareUrl, '_blank', 'width=600,height=400,noopener,noreferrer');
        }
    }
}

// Инициализация синглтона
window.ModalManagerInstance = new ModalManager();