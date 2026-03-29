document.addEventListener('DOMContentLoaded', () => {

    // Получаем глобальный экземпляр ModalManager, инициализированный ранее
    const ModalManager = window.ModalManagerInstance;

    // Инкапсулируем логику страницы в объект App
    const App = {
        
        elements: ModalManager.elements, 
        
        state: {
            isUserLoggedIn: ModalManager.isUserLoggedIn,
            activeVideoId: null,
            requestsInProgress: new Set(),
        },

        init() {
            // Используем делегирование событий для обработки динамически созданных элементов
            document.body.addEventListener('click', this.handleDelegatedClicks.bind(this));
        },

        handleDelegatedClicks(event) {
            const target = event.target;
            const actionTarget = target.closest('[data-action]');
            
            // Управление кастомными селектами и закрытие модальных окон при клике на оверлей
            ModalManager.handleCustomSelect(target);
            if (target.matches('.modal-overlay-small, .share-modal-overlay, .add-to-playlist-modal-overlay')) {
                ModalManager.closeModal(target);
            }

            // Обработка клика по кнопке контекстного меню (три точки)
            const menuButton = target.closest('.context-menu-button');
            if (menuButton) {
                event.stopPropagation();
                const videoCard = menuButton.closest('.video-card');
                const videoId = videoCard ? videoCard.dataset.videoId : null;
                ModalManager.toggleDropdown(menuButton, videoId); 
                return;
            }

            // Закрытие всех выпадающих меню при клике вне их области
            if (!target.closest('.context-menu-container')) {
                document.querySelectorAll('.context-menu-dropdown.active').forEach(menu => menu.classList.remove('active'));
            }

            // Диспетчер действий, описанных через атрибут data-action
            if (actionTarget) {
                if (actionTarget.dataset.action !== 'share-social') {
                    event.preventDefault();
                }
                this.runAction(actionTarget);
            }
        },
        
        runAction(element) {
            const action = element.dataset.action;
            const videoCard = element.closest('.video-card');
            const videoId = videoCard ? videoCard.dataset.videoId : null;

            // Список действий, требующих обязательной авторизации
            const authRequiredActions = [
                'toggle-watch-later', 
                'add-to-playlist', 
                'select-playlist', 
                'open-create-playlist-modal', 
                'login-required'
            ];

            // Проверяем права пользователя перед выполнением действия
            if (authRequiredActions.includes(action) && !this.state.isUserLoggedIn) {
                ModalManager.openLoginRequiredModal();
                return;
            }

            // Карта доступных действий (Action Dispatcher)
            const actions = {
                'close-modal': () => ModalManager.closeModal(element.closest('.modal-overlay-small, .share-modal-overlay')),
                'close-playlist-modal': () => ModalManager.closeModal(this.elements.modals.addToPlaylist),
                'close-create-fly-modal': () => ModalManager.closeModal(this.elements.modals.createPlaylist),
                
                'open-create-playlist-modal': () => ModalManager.openCreatePlaylistModal(),
                'toggle-watch-later': () => ModalManager.toggleWatchLater(videoId, element),
                'add-to-playlist': () => ModalManager.openAddToPlaylistModal(videoId),
                'select-playlist': () => ModalManager.handleSelectPlaylist(element),

                'share': () => ModalManager.openShareModal(videoId),
                'copy-share-link': () => ModalManager.handleCopyShareLink(element),
                'share-social': () => ModalManager.handleSocialShare(element),
            };

            // Выполняем запрошенное действие, если оно существует, и закрываем меню
            if (actions[action]) {
                actions[action]();
                element.closest('.context-menu-dropdown')?.classList.remove('active');
            }
        },
    };

    App.init();
});