document.addEventListener('DOMContentLoaded', () => {

    const ModalManager = window.ModalManagerInstance;

    const page = {
        state: {
            activeVideoId: null,
            csrfToken: ModalManager.csrfToken,
            undoTimer: null,
            notificationTimer: null,
            context: window.playlistContext || { id: null, type: null } 
        },
        // Хранит контекст последнего деструктивного действия для возможности отмены
        lastActionUndoData: null
    };

    // --- Делегирование событий ---

    document.body.addEventListener('click', (event) => {
        const target = event.target;
        const actionTarget = target.closest('[data-action]');
        
        const contextMenuButton = target.closest('.context-menu-button');
        if (contextMenuButton) {
            event.stopPropagation();
            const videoItem = contextMenuButton.closest('.playlist-video-item');
            
            page.state.activeVideoId = videoItem ? videoItem.dataset.videoId : null; 
            ModalManager.toggleDropdown(contextMenuButton, page.state.activeVideoId);
            return;
        }

        if (actionTarget) {
            if (actionTarget.dataset.action !== 'share-social' && actionTarget.dataset.action !== 'select-playlist') {
                event.preventDefault();
            }
            
            const videoItem = actionTarget.closest('.playlist-video-item');
            
            if (videoItem && !contextMenuButton) {
                page.state.activeVideoId = videoItem.dataset.videoId;
            }
            
            runAction(actionTarget.dataset.action, { videoItem, actionTarget });
        }
    });

    // --- Диспетчер действий ---

    function runAction(action, context) {
        const { videoItem, actionTarget } = context;
        const videoId = page.state.activeVideoId; 

        // При запуске нового действия очищаем кэш отмены предыдущего
        page.lastActionUndoData = null; 
        
        const actions = {
            'share-playlist': () => ModalManager.openShareModal(window.location.href),
            'share-video': () => ModalManager.openShareModal(videoId),
            'copy-share-link': () => ModalManager.handleCopyShareLink(actionTarget),
            'share-social': () => ModalManager.handleSocialShare(actionTarget),
            'open-create-playlist-modal': () => ModalManager.openCreatePlaylistModal(),
            
            'toggle-watch-later': () => handleRemove(videoItem, 'toggle_watch_later', { video_id: videoId }, 'Добавлено в Смотреть позже', 'Видео возвращено'), 
            'add-to-playlist': () => ModalManager.openAddToPlaylistModal(videoId), 
            'select-playlist': () => ModalManager.handleSelectPlaylist(actionTarget),
            
            'shuffle-play': handleShuffleRedirect,
            
            'remove-from-playlist': () => {
                 handleRemove(videoItem, 'remove_from_playlist', { 
                     video_id: videoId, 
                     playlist_id: page.state.context.id 
                 }, 'Видео удалено из плейлиста', 'Видео возвращено');
            },

            'remove-from-watch-later': () => handleRemove(videoItem, 'toggle_watch_later', { video_id: videoId }, 'Видео удалено', 'Добавлено обратно'),
            'remove-from-liked': () => handleRemove(videoItem, 'toggle_like', { video_id: videoId, type: 'like' }, 'Видео удалено', 'Добавлено обратно'), 
            'unhide-video': () => handleRemove(videoItem, 'toggle_hidden', { video_id: videoId, status: 0 }, 'Видео возвращено', 'Скрыто обратно'),
        };

        if (actions[action]) {
            actions[action]();
        }
    }

    // --- Локальные функции ---

    /**
     * Реализует паттерн Soft Delete. Визуально скрывает элемент и дает таймаут на отмену
     * перед физическим удалением из DOM.
     */
    function handleRemove(item, apiEndpoint, bodyData, successMessage, undoMessage) {
        if (!item) return;
        
        page.lastActionUndoData = {
            apiEndpoint: apiEndpoint,
            bodyData: bodyData,
            item: item,
            successMessage: successMessage,
            undoMessage: undoMessage
        };
        
        item.classList.add('is-hiding');
        
        const formData = new FormData();
        for (const key in bodyData) formData.append(key, bodyData[key]);
        formData.append('csrf_token', page.state.csrfToken);

        fetch(`/api/${apiEndpoint}`, { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showUndoNotification(successMessage, () => {
                    undoLastAction();
                });
                
                // Физическое удаление элемента происходит только если таймаут не был прерван функцией отмены
                page.state.undoTimer = setTimeout(() => {
                    item.remove();
                    updateVideoCount();
                }, 5000);
            } else {
                ModalManager.showNotification(data.message || 'Ошибка обработки запроса', false);
                item.classList.remove('is-hiding');
            }
        }).catch(() => {
            ModalManager.showNotification('Сбой сетевого подключения', false);
            item.classList.remove('is-hiding');
        });
    }

    /**
     * Прерывает процесс удаления, восстанавливает элемент в DOM и отправляет компенсирующий запрос.
     */
    function undoLastAction() {
        const undoData = page.lastActionUndoData;
        if (!undoData) return;
        
        const item = undoData.item;
        
        clearTimeout(page.state.undoTimer);
        
        item.classList.remove('is-hiding');
        item.style.opacity = '1';
        item.style.transform = 'scale(1)';

        const formData = new FormData();
        for (const key in undoData.bodyData) formData.append(key, undoData.bodyData[key]);
        formData.append('csrf_token', page.state.csrfToken);
        
        let reverseApiEndpoint = '';
        
        // Маппинг прямых действий на компенсирующие
        if (undoData.apiEndpoint.startsWith('toggle')) {
            reverseApiEndpoint = undoData.apiEndpoint;
        } else if (undoData.apiEndpoint === 'remove_from_playlist') {
            reverseApiEndpoint = 'add_video_to_playlist'; 
        }

        if (reverseApiEndpoint) {
            fetch(`/api/${reverseApiEndpoint}`, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    // Синхронизация состояния: если обратный запрос провалился, удаляем элемент
                    if (data.is_added === false && undoData.apiEndpoint.startsWith('toggle')) {
                        item.remove();
                    }
                })
                .catch(() => ModalManager.showNotification('Ошибка при попытке отмены', false));
        }
        
        page.lastActionUndoData = null;
    }
    
    function handleShuffleRedirect() {
        const videoItems = document.querySelectorAll('.playlist-video-item');
        if (videoItems.length === 0) return;
    
        const publicVideoIds = Array.from(videoItems)
            .map(item => item.dataset.videoId)
            .filter(id => id && id.length > 0); 
    
        if (publicVideoIds.length === 0) {
            ModalManager.showNotification('В плейлисте нет доступных видео.', false);
            return;
        }
    
        const randomIndex = Math.floor(Math.random() * publicVideoIds.length);
        const randomPublicId = publicVideoIds[randomIndex];
        
        // Формирование URL для сохранения контекста плейлиста в плеере
        let playlistParam = '';
        if (page.state.context.id) {
             playlistParam = `list=${page.state.context.id}`;
        } else if (page.state.context.type) {
             playlistParam = `playlist=${page.state.context.type}`;
        }
        
        window.location.href = `/watch.php?id=${randomPublicId}&${playlistParam}`;
    }

    /**
     * Кастомный компонент уведомления с кнопкой обратного действия.
     */
    function showUndoNotification(message, undoCallback) {
        clearTimeout(page.state.notificationTimer);
        ModalManager.closeModal(document.querySelector('.notification')); 
        
        const existingNotification = document.querySelector('.undo-notification');
        if (existingNotification) existingNotification.remove();

        const notification = document.createElement('div');
        notification.className = 'undo-notification';
        notification.innerHTML = `
            <span>${message}</span>
            <button class="undo-notification-button">ОТМЕНИТЬ</button>
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => notification.classList.add('visible'), 10);
        
        notification.querySelector('button').onclick = () => {
            undoCallback();
            notification.classList.remove('visible');
            setTimeout(() => notification.remove(), 400);
            clearTimeout(page.state.notificationTimer);
        };
        
        page.state.notificationTimer = setTimeout(() => {
            notification.classList.remove('visible');
            setTimeout(() => notification.remove(), 400);
        }, 5000);
    }
    
    function updateVideoCount() {
        const countEl = document.getElementById('video-count-display');
        if (countEl) {
            // Считаем только те элементы, которые не находятся в процессе скрытия
            const currentCount = document.querySelectorAll('.playlist-video-item:not(.is-hiding)').length;
            countEl.textContent = `• ${currentCount} видео`;
        }
    }
});