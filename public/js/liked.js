document.addEventListener('DOMContentLoaded', () => {

    // Получаем глобальный экземпляр ModalManager
    const ModalManager = window.ModalManagerInstance;
    
    // Кэшируем нужные элементы из менеджера
    const elements = ModalManager.elements; 

    const App = {
        
        // состояние

        state: {
            activeVideoItem: null, // DOM-элемент карточки
            activeVideoId: null,   // Публичный ID видео
        },

        // иниц

        init() {
            document.body.addEventListener('click', this.handleDelegatedClicks.bind(this));
            
            if (elements.modals.confirmation) {

            }
        },

        // обработчики


        /**
         * Главный обработчик кликов.
         */
        handleDelegatedClicks(event) {
            const target = event.target;
            const actionTarget = target.closest('[data-action]');
            
            // 1. Управление модальными окнами (делегируем менеджеру)
            ModalManager.handleCustomSelect(target);
            if (target.matches('.modal-overlay-small, .share-modal-overlay, .add-to-playlist-modal-overlay, .confirmation-modal-overlay')) {
                ModalManager.closeModal(target);
            }

            // 2. Управление контекстным меню
            const menuButton = target.closest('.context-menu-button');
            if (menuButton) {
                event.stopPropagation();
                const videoItem = menuButton.closest('.liked-video-item');
                this.state.activeVideoItem = videoItem;
                this.state.activeVideoId = videoItem ? videoItem.dataset.videoId : null;

                // Передаем в менеджер: кнопку, ID видео
                ModalManager.toggleDropdown(menuButton, this.state.activeVideoId); 
                return;
            }

            // 3. Закрытие всех меню
            if (!target.closest('.context-menu-container')) {
                document.querySelectorAll('.context-menu-dropdown.active').forEach(menu => menu.classList.remove('active'));
            }

            // 4. Диспетчер действий
            if (actionTarget) {
                if (actionTarget.dataset.action !== 'share-social' && actionTarget.dataset.action !== 'select-playlist') {
                    event.preventDefault();
                }
                this.runAction(actionTarget);
            }
        },
        
        // экшены


        runAction(element) {
            const action = element.dataset.action;
            const videoId = this.state.activeVideoId;
            const videoItem = this.state.activeVideoItem;
            

            const actions = {
                'remove-like': () => this.showUnlikeConfirmation(videoItem, videoId),
                'shuffle-play': this.handleShuffleRedirect,

                'share-video': () => ModalManager.openShareModal(videoId),
                'copy-share-link': () => ModalManager.handleCopyShareLink(element),
                'share-social': () => ModalManager.handleSocialShare(element),
                'toggle-watch-later': () => ModalManager.toggleWatchLater(videoId, element),
                'add-to-playlist': () => ModalManager.openAddToPlaylistModal(videoId),
                'select-playlist': () => ModalManager.handleSelectPlaylist(element),

                'open-create-playlist-modal': () => ModalManager.openCreatePlaylistModal(),
                'close-create-fly-modal': () => ModalManager.closeModal(elements.modals.createPlaylist),
                'close-modal': () => ModalManager.closeModal(element.closest('.share-modal-overlay, .confirmation-modal-overlay')),
                'close-playlist-modal': () => ModalManager.closeModal(elements.modals.addToPlaylist),
            };

            if (actions[action]) {
                actions[action]();
                element.closest('.context-menu-dropdown')?.classList.remove('active');
            }
        },
        
        // локалка

        
        /**
         * Открывает модальное окно подтверждения удаления лайка, используя универсальный шаблон.
         */
        showUnlikeConfirmation(videoItem, videoId) {
            const modal = elements.modals.confirmation;
            if (!modal) return;
            
            // заполняем модалку

            modal.querySelector('#confirmation-modal-title').textContent = "Удалить из понравившихся?";
            modal.querySelector('#confirmation-modal-text').innerHTML = "Это действие нельзя будет отменить. Видео исчезнет из этого списка.";
            
            // меняем кнопку

            const confirmBtn = modal.querySelector('#confirmation-modal-confirm-btn');
            const newConfirmBtn = confirmBtn.cloneNode(true); 
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

            newConfirmBtn.addEventListener('click', (e) => {
                e.preventDefault();
                ModalManager.closeModal(modal);
                this.handleUnlike(videoItem, videoId);
            }, {once: true}); // Сработает только один раз
            
            ModalManager.openModal(modal);
        },

        /**
         * Выполняет AJAX-запрос на удаление лайка.
         */
        handleUnlike(videoItem, videoId) {
            const body = `video_id=${videoId}&type=like&csrf_token=${ModalManager.csrfToken}`;

            fetch('/api/toggle_like', { method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    ModalManager.showNotification("Видео удалено из понравившихся");
                    
                    // Анимация удаления
                    videoItem.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    videoItem.style.opacity = '0';
                    videoItem.style.transform = 'scale(0.95)';
                    
                    setTimeout(() => {
                        videoItem.remove();
                        // Проверка на пустоту списка и замена на заглушку
                        if (document.querySelectorAll('.liked-video-item').length === 0) {
                            document.querySelector('.liked-video-list')?.remove();
                            document.querySelector('.playlist-actions')?.remove();
                            document.querySelector('.liked-page-header').insertAdjacentHTML('afterend', `<div class="empty-page-placeholder"><h2>Вам пока не понравилось ни одно видео</h2><p>Нажимайте "палец вверх" под роликами, и они появятся здесь.</p></div>`);
                        }
                    }, 300);
                } else {
                    ModalManager.showNotification(data.message || 'Не удалось удалить лайк.', false);
                }
            })
            .catch(() => ModalManager.showNotification('Произошла ошибка сети.', false));
        },
        
        /**
         * Перенаправляет на случайное видео в плейлисте.
         */
        handleShuffleRedirect() {
            const videoItems = document.querySelectorAll('.liked-video-item');
            if (videoItems.length === 0) { ModalManager.showNotification("Нет видео для перемешивания.", false); return; }
            
            const videoIds = Array.from(videoItems).map(item => item.dataset.videoId);
            const randomVideoId = videoIds[Math.floor(Math.random() * videoIds.length)];
            
            window.location.href = `/watch.php?id=${randomVideoId}&playlist=liked`;
        },
    };

    App.init();
});