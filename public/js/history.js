document.addEventListener('DOMContentLoaded', () => {

    if (!window.ModalManagerInstance) {
        console.error("ModalManagerInstance не найден. Убедитесь, что modal_manager.js загружен ПЕРЕД history.js.");
        return;
    }
    
    const ModalManager = window.ModalManagerInstance;

    const App = {
        
        state: {
            activeVideoItem: null,
            activeVideoId: null,
        },

        init() {
            document.body.addEventListener('click', this.handleDelegatedClicks.bind(this));
        },

        handleDelegatedClicks(event) {
            const target = event.target;
            const actionTarget = target.closest('[data-action]');
            
            const menuButton = target.closest('.context-menu-button');
            if (menuButton) {
                event.stopPropagation();
                const videoItem = menuButton.closest('.history-video-item');
                this.state.activeVideoItem = videoItem;
                this.state.activeVideoId = videoItem ? videoItem.dataset.videoId : null;
                ModalManager.toggleDropdown(menuButton);
                return;
            }

            if (!target.closest('.context-menu-container')) {
                document.querySelectorAll('.context-menu-dropdown.active').forEach(menu => menu.classList.remove('active'));
            }

            if (actionTarget) {
                if (actionTarget.dataset.action.startsWith('history-')) {
                     event.preventDefault();
                }
                this.runAction(actionTarget);
            }
        },
        
        runAction(element) {
            const action = element.dataset.action;
            const videoId = this.state.activeVideoId;
            const videoItem = this.state.activeVideoItem;

            const actions = {
                'history-delete': () => this.handleDelete(videoItem, videoId),
                'open-modal':     () => this.openConfirmationModal(element.dataset.modalId),
                'confirm-clear':  this.handleClearHistory.bind(this),
                'confirm-toggle': this.handleToggleHistory.bind(this),

                'history-share':                () => videoId && ModalManager.openShareModal(videoId),
                'history-toggle-watch-later':   () => videoId && ModalManager.toggleWatchLater(videoId, element),
                'history-add-to-playlist':      () => videoId && ModalManager.openAddToPlaylistModal(videoId),
            };

            if (actions[action]) {
                actions[action]();
                element.closest('.context-menu-dropdown')?.classList.remove('active');
            }
        },

        handleDelete(videoItem, videoId) {
            if (!videoItem || !videoId) return;
            fetch('/api/delete_from_history', {
                method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `video_id=${videoId}&csrf_token=${ModalManager.csrfToken}`
            })
            .then(res => res.json())
            .then(data => {
                ModalManager.showNotification(data.message || (data.success ? 'Видео удалено' : 'Ошибка'), data.success);
                if (data.success) {
                    videoItem.style.transition = 'opacity 0.3s ease';
                    videoItem.style.opacity = '0';
                    setTimeout(() => videoItem.remove(), 300);
                }
            }).catch(() => ModalManager.showNotification('Ошибка сети.', false));
        },

        handleClearHistory() {
            fetch('/api/clear_history', {
                method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `csrf_token=${ModalManager.csrfToken}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('history-content').innerHTML = `<div class="empty-page-placeholder"><h2>История просмотра очищена</h2><p>Посмотрите видео, чтобы она появилась снова.</p></div>`;
                    ModalManager.closeModal(document.getElementById('clear-history-modal'));
                } else { 
                    ModalManager.showNotification(data.message || 'Ошибка.', false);
                }
            }).catch(() => ModalManager.showNotification('Ошибка сети.', false));
        },

        handleToggleHistory() {
            const btnTextElement = document.getElementById('toggle-history-text');
            const isEnabled = !btnTextElement.textContent.includes('Возобновить');
            const newStatus = isEnabled ? 0 : 1;
            
            fetch('/api/toggle_history_recording', {
                method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `status=${newStatus}&csrf_token=${ModalManager.csrfToken}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const iconElement = document.getElementById('toggle-history-icon');
                    btnTextElement.textContent = data.newStatus === 1 ? 'Приостановить запись истории' : 'Возобновить запись истории';
                    if (iconElement) iconElement.src = data.newStatus === 1 ? '/images/stop.png' : '/images/play.png';
                    ModalManager.closeModal(document.getElementById('toggle-history-modal'));
                } else {
                    ModalManager.showNotification(data.message || 'Ошибка.', false);
                }
            }).catch(() => ModalManager.showNotification('Ошибка сети.', false));
        },

        openConfirmationModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;
            if (modalId === 'toggle-history-modal') {
                const isEnabled = !document.getElementById('toggle-history-text')?.textContent.includes('Возобновить');
                modal.querySelector('.confirmation-modal-title').textContent = isEnabled ? 'Приостановить запись истории?' : 'Возобновить запись истории?';
                modal.querySelector('.confirmation-modal-body p').textContent = isEnabled ? 'Если приостановить запись, вам будет сложнее находить просмотренные видео.' : 'Сведения о просмотренных вами видео будут снова сохраняться.';
                modal.querySelector('.confirmation-modal-button.confirm').textContent = isEnabled ? 'Приостановить' : 'Возобновить';
            }
            ModalManager.openModal(modal);
        },
    };

    App.init();
});