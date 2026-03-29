document.addEventListener('DOMContentLoaded', () => {

    const ModalManager = window.ModalManagerInstance;
    
    const page = {
        sortableList: document.getElementById('sortable-video-list'),
        state: {
            undoTimer: null,
            csrfToken: ModalManager.csrfToken 
        }
    };

    initSortable();
    
    document.body.addEventListener('mousedown', (event) => {
        if (event.target.classList.contains('drag-handle-icon')) {
            const urlParams = new URLSearchParams(window.location.search);
            const isManualSort = !urlParams.has('sort') || urlParams.get('sort') === 'manual';
            if (!isManualSort) {
                ModalManager.showNotification('Перетаскивание отключено. Чтобы изменить порядок, выберите "Моя сортировка".', false);
            }
        }
    });

    document.body.addEventListener('click', (event) => {
        const target = event.target;
        const actionTarget = target.closest('[data-action]');

        const contextMenuButton = target.closest('.context-menu-button');
        if (contextMenuButton) {
            event.stopPropagation();
            const videoItem = contextMenuButton.closest('.video-item');
            const videoId = videoItem ? videoItem.dataset.videoId : null;
            ModalManager.toggleDropdown(contextMenuButton, videoId);
            return;
        }

        if (!target.closest('.context-menu-container')) {
            document.querySelectorAll('.context-menu-dropdown.active').forEach(menu => menu.classList.remove('active'));
        }

        handleSortMenu(target);

        if (actionTarget) {
            if (actionTarget.dataset.action !== 'share-social' && actionTarget.dataset.action !== 'select-playlist') {
                event.preventDefault();
            }
            const action = actionTarget.dataset.action;
            const videoItem = actionTarget.closest('.video-item');
            const videoId = videoItem ? videoItem.dataset.videoId : null;

            runAction(action, { videoItem, videoId, actionTarget });
        }

        ModalManager.handleCustomSelect(target);
    });

    function runAction(action, context) {
        const { videoItem, videoId, actionTarget } = context;

        switch (action) {
            case 'remove-watch-later': 
                handleRemoveWithUndo(videoItem, videoId); 
                break;
            case 'shuffle-play': 
                handleShuffleRedirect(); 
                break;

            case 'add-to-playlist': 
                ModalManager.openAddToPlaylistModal(videoId); 
                break;
            case 'share-video': 
                ModalManager.openShareModal(videoId); 
                break;
            case 'copy-share-link': 
                ModalManager.handleCopyShareLink(actionTarget); 
                break;
            case 'share-social': 
                ModalManager.handleSocialShare(actionTarget); 
                break;
            case 'open-create-playlist-modal':
                ModalManager.openCreatePlaylistModal();
                break;
        }
    }

    function handleSortMenu(target) {
        const sortContainer = document.querySelector('.sort-menu-container');
        if (!sortContainer) return;
        
        const sortButton = target.closest('.sort-menu-button');
        const sortOption = target.closest('.sort-option');

        if (sortButton) {
            ModalManager.positionDropdown(sortButton, sortContainer.querySelector('.sort-menu-dropdown'));
            sortContainer.classList.toggle('open');
        } else if (sortOption) {
            sortContainer.classList.remove('open');
            window.location.href = sortOption.href;
        } else if (!target.closest('.sort-menu-container')) {
            sortContainer.classList.remove('open');
        }
    }

    function handleRemoveWithUndo(videoItem, videoId) {
        if (!videoItem) return;
        let isUndone = false;

        videoItem.style.transition = 'opacity 0.3s, transform 0.3s';
        videoItem.style.opacity = '0';
        videoItem.style.transform = 'scale(0.95)';
        
        const body = `video_id=${videoId}&csrf_token=${page.state.csrfToken}`;

        fetch('/api/toggle_watch_later', {
            method: 'POST', 
            headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
            body: body
        })
        .then(res => res.json())
        .then(data => {
            if (data.success && !data.is_added) {
                showUndoNotification(videoId, () => {
                    isUndone = true;
                    videoItem.style.display = 'flex';
                    setTimeout(() => {
                        videoItem.style.opacity = '1';
                        videoItem.style.transform = 'scale(1)';
                    }, 10);
                });
                setTimeout(() => { if (!isUndone) videoItem.style.display = 'none'; }, 300);
            } else {
                ModalManager.showNotification(data.message || 'Произошла ошибка', false);
                videoItem.style.opacity = '1';
                videoItem.style.transform = 'scale(1)';
            }
        })
        .catch(() => ModalManager.showNotification('Ошибка сети', false));
    }

    function showUndoNotification(videoId, onUndoCallback) {
        let notification = document.querySelector('.undo-notification');
        if (notification) notification.remove();
        clearTimeout(page.state.undoTimer);

        notification = document.createElement('div');
        notification.className = 'undo-notification';
        notification.innerHTML = `<span>Видео удалено.</span><button class="undo-notification-button">Отменить</button>`;
        document.body.appendChild(notification);
        setTimeout(() => notification.classList.add('visible'), 10);

        notification.querySelector('button').onclick = () => {
            const body = `video_id=${videoId}&csrf_token=${page.state.csrfToken}`;
            fetch('/api/toggle_watch_later', {
                method: 'POST', 
                headers: {'Content-Type': 'application/x-www-form-urlencoded'}, 
                body: body
            })
            .then(res => res.json())
            .then(data => { if (data.success && data.is_added) onUndoCallback(); });
            
            notification.classList.remove('visible');
            clearTimeout(page.state.undoTimer);
        };
        
        page.state.undoTimer = setTimeout(() => {
            notification.classList.remove('visible');
            const removedItem = document.querySelector(`.video-item[data-video-id="${videoId}"][style*="display: none"]`);
            if (removedItem) removedItem.remove();
        }, 7000);
    }

    function handleShuffleRedirect() {
        const videoItems = document.querySelectorAll('.video-item');
        if (videoItems.length === 0) {
            ModalManager.showNotification("Нет видео для перемешивания.", false);
            return;
        }
        const urlParams = new URLSearchParams(window.location.search);
        const sortOrder = urlParams.get('sort') || 'manual';
        const videoIds = Array.from(videoItems).map(item => item.dataset.videoId);
        const randomVideoId = videoIds[Math.floor(Math.random() * videoIds.length)];
        
        window.location.href = `/watch.php?id=${randomVideoId}&playlist=watch_later&sort=${sortOrder}`;
    }

    function initSortable() {
        if (!page.sortableList || typeof Sortable === 'undefined') return;
        
        const urlParams = new URLSearchParams(window.location.search);
        const isManualSort = !urlParams.has('sort') || urlParams.get('sort') === 'manual';
        
        new Sortable(page.sortableList, {
            animation: 150,
            handle: '.drag-handle-icon',
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',
            forceFallback: true,
            fallbackOnBody: true,
            disabled: !isManualSort,
            swapThreshold: 0.65,
            onEnd: function (evt) {
                const videoIdsOrder = Array.from(evt.target.children).map(item => item.dataset.videoId);

                fetch('/api/update_watch_later_order', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        video_ids: videoIdsOrder,
                        csrf_token: page.state.csrfToken
                    })
                }).then(response => response.json()).catch(error => console.error(error));
            }
        });
    }
});