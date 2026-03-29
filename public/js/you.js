(() => {
    'use strict';
    
    const ModalManager = window.ModalManagerInstance;

    const YouPageApp = {

        state: {
            activeVideoId: null,
        },
        
        elements: {}, 

        init() {
            this.cacheElements();
            this.bindEvents();
        },

        cacheElements() {
            this.elements.popups = {
                accountSwitcherPanel: document.getElementById('account-switcher-panel'),
            };
            this.elements.manageAccountsBtn = document.getElementById('manage-accounts-btn');
            this.elements.manageAccountsBtnSpan = this.elements.manageAccountsBtn?.querySelector('span');
        },

        bindEvents() {
            document.body.addEventListener('click', this.handleGlobalClick.bind(this));
            
            if (this.elements.manageAccountsBtn) {
                this.elements.manageAccountsBtn.addEventListener('click', this.toggleAccountManagement.bind(this));
            }
            
            // Валидация модального окна создания плейлиста
            const createPlaylistForm = ModalManager?.elements?.forms?.createPlaylist;
            if (createPlaylistForm) {
                const titleInput = createPlaylistForm.querySelector('input[name="title"]');
                if (titleInput && ModalManager.clearCreatePlaylistError) {
                    titleInput.addEventListener('input', ModalManager.clearCreatePlaylistError.bind(ModalManager));
                }
            }
        },

        handleGlobalClick(event) {
            const target = event.target;
            const accountTrigger = target.closest('#account-switcher-trigger');
            const actionElement = target.closest('[data-action]');
            const contextMenuTrigger = target.closest('.context-menu-button'); 

            // Открытие контекстного меню
            if (contextMenuTrigger) {
                event.stopPropagation();
                const videoCard = contextMenuTrigger.closest('.video-card');
                this.state.activeVideoId = videoCard ? videoCard.dataset.videoId : null;
                
                if (ModalManager) ModalManager.toggleDropdown(contextMenuTrigger, this.state.activeVideoId); 
                return;
            }
            
            // Переключение аккаунтов
            if (accountTrigger) {
                event.stopPropagation();
                this.toggleAccountPanel();
                return;
            }

            // Диспетчер роутинга действий
            if (actionElement) {
                const videoCard = actionElement.closest('.video-card');
                if (videoCard) {
                    this.state.activeVideoId = videoCard.dataset.videoId;
                }
                
                if (actionElement.dataset.action !== 'share-social') {
                    event.preventDefault();
                }
                
                this.runAction(actionElement);
                return;
            }

            this.closeAllPopups();
        },

        runAction(element) {
            const action = element.dataset.action;
            
            if (!this.state.activeVideoId && ['toggle-watch-later', 'add-to-playlist', 'share'].includes(action)) {
                 return;
            }
            
            if (action === 'select-playlist' && this.state.activeVideoId) {
                this.addVideoToPlaylist(element.dataset.playlistId, element); 
                return;
            }

            if (!ModalManager) return;

            const commonActions = {
                'toggle-watch-later': () => ModalManager.toggleWatchLater(this.state.activeVideoId, element),
                'add-to-playlist': () => ModalManager.openAddToPlaylistModal(this.state.activeVideoId),
                'share': () => ModalManager.openShareModal(this.state.activeVideoId),
                'open-create-playlist-modal': () => ModalManager.openCreatePlaylistModal(),
                
                // Закрытие окон
                'close-modal': () => ModalManager.closeModal(element.closest('[class*="-modal-overlay"]')),
                'close-playlist-modal': () => ModalManager.closeModal(ModalManager.elements.modals.addToPlaylist),
                'close-create-fly-modal': () => ModalManager.closeModal(ModalManager.elements.modals.createPlaylist),

                // Соцсети
                'copy-share-link': () => ModalManager.handleCopyShareLink(element),
                'share-social': () => ModalManager.handleSocialShare(element),
            };

            if (commonActions[action]) {
                commonActions[action]();
            }
        },

        addVideoToPlaylist(playlistId, element) {
            if (!this.state.activeVideoId || !playlistId || !ModalManager) return;
            
            const body = new URLSearchParams({
                video_id: this.state.activeVideoId,
                playlist_id: playlistId,
                csrf_token: ModalManager.csrfToken
            });
            const playlistTitle = element.textContent.trim();

            fetch('/api/add_video_to_playlist', { 
                method: 'POST', 
                body: body
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    let message = data.message || "Операция прошла успешно.";
                    
                    if (data.message.includes('уже есть')) {
                        message = `Видео уже есть в плейлисте '${playlistTitle}'.`;
                    } else if (data.message.includes('успешно добавлено')) {
                        message = `Видео успешно добавлено в плейлист '${playlistTitle}'.`;
                    }
                    
                    ModalManager.showNotification(message, true);
                } else {
                    ModalManager.showNotification(data.message || "Ошибка добавления в плейлист.", false);
                }
            })
            .catch(() => ModalManager.showNotification("Сетевая ошибка", false))
            .finally(() => ModalManager.closeModal(ModalManager.elements.modals.addToPlaylist));
        },

        closeAllPopups() {
            const panel = this.elements.popups.accountSwitcherPanel;
            if (panel) {
                panel.classList.remove('active', 'is-editing');
                if (this.elements.manageAccountsBtnSpan) {
                    this.elements.manageAccountsBtnSpan.textContent = "Управлять аккаунтами";
                }
            }
        },

        toggleAccountPanel() {
            const panel = this.elements.popups.accountSwitcherPanel;
            if (!panel) return;
            
            const isActive = panel.classList.contains('active');
            this.closeAllPopups();
            if (!isActive) panel.classList.add('active');
        },
        
        toggleAccountManagement(event) {
            event.stopPropagation();
            const panel = this.elements.popups.accountSwitcherPanel;
            const buttonSpan = this.elements.manageAccountsBtnSpan;
            
            if (!panel || !buttonSpan) return;
            
            panel.classList.toggle('is-editing');
            const isEditing = panel.classList.contains('is-editing');
            buttonSpan.textContent = isEditing ? "Готово" : "Управлять аккаунтами";
        },
    };
    
    document.addEventListener('DOMContentLoaded', () => YouPageApp.init());
})();