document.addEventListener('DOMContentLoaded', () => {
    
    const ModalManager = window.ModalManagerInstance;
    
    // Состояние страницы
    const page = {
        state: {
            requestsInProgress: new Set(),
            undoTimer: null,
            activeContextElement: null, 
            activeEntityId: null,       
        }
    };
    
    // --- Глобальное делегирование событий ---
    
    document.body.addEventListener('click', (event) => {
        const target = event.target;
        
        handleSortMenu(target);
        
        // Обработка контекстного меню (троеточие)
        const menuButton = target.closest('.channel-item-menu-button, .video-item-menu-button');
        if (menuButton) {
            event.stopPropagation();
            
            const channelCard = menuButton.closest('.channel-card');
            const videoItem = menuButton.closest('.video-list-item');
            
            // Сохраняем контекст открытого меню
            if (channelCard) {
                page.state.activeContextElement = channelCard;
                page.state.activeEntityId = channelCard.dataset.channelId;
            } else if (videoItem) {
                page.state.activeContextElement = videoItem;
                page.state.activeEntityId = videoItem.dataset.videoId;
            } else {
                page.state.activeContextElement = null;
                page.state.activeEntityId = null;
            }
            
            ModalManager.toggleDropdown(menuButton, page.state.activeEntityId);
            return;
        }

        // Закрытие меню при клике вне
        if (!target.closest('.channel-item-menu') && !target.closest('.video-item-menu')) {
             document.querySelectorAll('.channel-item-menu.active, .video-item-menu.active')
                .forEach(menu => menu.classList.remove('active'));
        }

        // Диспетчер действий (data-action)
        const actionTarget = target.closest('[data-action]');
        if (actionTarget) {
            // Разрешаем стандартный переход для конкретных действий
            if (!['share-social', 'select-playlist'].includes(actionTarget.dataset.action)) {
                event.preventDefault(); 
            }
            runAction(actionTarget);
        }
    });
    
    // Привязка очистки ошибок для формы плейлиста
    const createPlaylistForm = document.getElementById('create-playlist-fly-form');
    if (createPlaylistForm) {
        const titleInput = createPlaylistForm.querySelector('input[name="title"]');
        if (titleInput && ModalManager.clearCreatePlaylistError) {
             titleInput.addEventListener('input', ModalManager.clearCreatePlaylistError.bind(ModalManager));
        }
    }

    // --- Роутер действий ---

    function runAction(actionTarget) {
        const action = actionTarget.dataset.action;
        const id = page.state.activeEntityId; 
        const element = page.state.activeContextElement;

        switch (action) {
            // Уникальные действия для страницы подписок:
            case 'confirm-unsubscribe':
                if (element && id) showUnsubscribeModal(element, id);
                break;
            case 'share-channel':
                if (id) handleShareChannel(id);
                break;
            case 'toggle-hide':
                if (element && id) handleToggleHidden(element, id);
                break;
                
            // Общие действия (делегируем в ModalManager):
            case 'share-video':
                if (id) ModalManager.openShareModal(id);
                break;
            case 'toggle-watch-later':
                if (id) ModalManager.toggleWatchLater(id, actionTarget);
                break;
            case 'add-to-playlist':
                if (id) ModalManager.openAddToPlaylistModal(id);
                break;
        }
    }

    // --- Обработчики уникальных действий ---
    
    function showUnsubscribeModal(channelCard, channelId) {
        const modal = ModalManager.elements.modals.confirmation;
        if (!modal) return;
        
        const channelName = channelCard.querySelector('.channel-name')?.textContent.trim() || 'канала';

        modal.querySelector('#confirmation-modal-title').textContent = `Отписаться от канала "${channelName}"?`;
        modal.querySelector('#confirmation-modal-text').innerHTML = "Если вы отмените подписку, вам придется заново искать этот канал, чтобы снова на него подписаться.";
        
        // Пересоздаем кнопку для сброса старых слушателей событий
        const confirmBtn = modal.querySelector('#confirmation-modal-confirm-btn');
        const newConfirmBtn = confirmBtn.cloneNode(true); 
        confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

        newConfirmBtn.addEventListener('click', (e) => {
            e.preventDefault();
            ModalManager.closeModal(modal);
            handleUnsubscribe(channelCard, channelId);
        }, { once: true });
        
        ModalManager.openModal(modal);
    }
    
    function handleUnsubscribe(channelCard, channelId) {
        const requestBody = new URLSearchParams({ channel_id: channelId, csrf_token: csrfToken });
        
        fetch('/api/toggle_subscription', { 
            method: 'POST', 
            body: requestBody 
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && !data.is_subscribed) {
                const channelName = channelCard.querySelector('.channel-name')?.textContent.trim();
                const messageDiv = document.createElement('div');
                
                messageDiv.className = 'channel-unsubscribed-message';
                messageDiv.textContent = `Вы отписались от канала "${channelName}"`;
                channelCard.parentNode.replaceChild(messageDiv, channelCard);
                
                ModalManager.showNotification("Вы отписались от канала");
            } else { 
                ModalManager.showNotification(data.message || 'Ошибка отписки.', false);
            }
        })
        .catch(() => ModalManager.showNotification('Ошибка сети.', false));
    }
    
    function handleShareChannel(publicChannelId) {
        const shareModal = ModalManager.elements.modals.share;
        if (!shareModal) return;
        
        const input = shareModal.querySelector('.share-link-input');
        if (input) {
            input.value = `${window.location.origin}/profile.php?id=${publicChannelId}`;
        }
        ModalManager.openModal(shareModal);
    }

    function handleToggleHidden(videoItem, videoId) {
        clearTimeout(page.state.undoTimer);
        const requestBody = new URLSearchParams({ video_id: videoId, csrf_token: csrfToken });
        
        fetch('/api/toggle_hidden', { method: 'POST', body: requestBody })
            .then(res => res.json())
            .then(data => {
                if (data.success && data.is_hidden) {
                    videoItem.classList.add('is-hiding');
                    
                    const undoAction = () => {
                        clearTimeout(page.state.undoTimer);
                        fetch('/api/toggle_hidden', { method: 'POST', body: requestBody })
                            .then(() => videoItem.classList.remove('is-hiding'))
                            .catch(() => ModalManager.showNotification('Ошибка отмены скрытия.', false));
                    };
                    
                    ModalManager.showNotification('Видео скрыто.', false); 
                    
                    // Добавляем кастомную кнопку отмены в тост уведомления
                    const notification = document.querySelector('.notification:not(.visible)'); 
                    if (notification) {
                        const actionButton = document.createElement('button');
                        actionButton.className = 'notification-action';
                        actionButton.textContent = 'ОТМЕНИТЬ';
                        actionButton.onclick = () => {
                            undoAction();
                            notification.remove();
                            clearTimeout(page.state.undoTimer);
                        };
                        notification.appendChild(actionButton);
                    }

                    // Удаляем элемент из DOM через 5 секунд, если не было отмены
                    page.state.undoTimer = setTimeout(() => videoItem.remove(), 5000);
                } else {
                    ModalManager.showNotification(data.message || 'Произошла ошибка.', false);
                }
            })
            .catch(() => ModalManager.showNotification('Ошибка сети.', false));
    }
    
    // --- Вспомогательные UI функции ---

    function handleSortMenu(target) {
        const sortContainer = document.querySelector('.sort-menu-container');
        if (!sortContainer) return;
        
        if (target.closest('#sort-menu-button')) {
            sortContainer.classList.toggle('open');
            document.getElementById('sort-menu-dropdown')?.classList.toggle('active');
        } else if (!target.closest('.sort-menu-container')) {
            sortContainer.classList.remove('open');
            document.getElementById('sort-menu-dropdown')?.classList.remove('active');
        }
    }
});