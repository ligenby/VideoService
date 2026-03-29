document.addEventListener('DOMContentLoaded', () => {

    if (typeof csrfToken === 'undefined') {
        console.error('CSRF token is missing in the global scope.');
        return;
    }

    const PlaylistsManager = {
        
        state: {
            csrfToken: csrfToken,
            isToastOnCooldown: false,
            playlistIdToDelete: null
        },

        elements: {},

        init() {
            this.cacheElements();
            this.bindEvents();
        },

        cacheElements() {
            this.elements.openCreateModalBtn = document.getElementById('open-create-modal-btn');
            this.elements.createModal = document.getElementById('create-playlist-modal');
            this.elements.cancelCreateBtn = document.getElementById('cancel-playlist-creation');
            this.elements.createForm = document.getElementById('create-playlist-form');
            this.elements.titleInput = document.getElementById('playlist-title');
            this.elements.descriptionTextarea = document.getElementById('playlist-description');
            this.elements.charCounter = document.getElementById('char-counter');

            this.elements.openDeleteModalBtn = document.getElementById('open-delete-modal-btn');
            this.elements.deleteModal = document.getElementById('delete-playlist-modal');
            this.elements.cancelDeleteBtn = document.getElementById('cancel-delete-btn');
            this.elements.confirmDeleteBtn = document.getElementById('confirm-delete-btn');
            this.elements.playlistDeleteList = document.getElementById('playlist-delete-list');
            
            this.elements.confirmationModal = document.getElementById('confirmation-delete-modal');
            this.elements.confirmDeleteText = document.getElementById('confirm-delete-text');
            this.elements.finalDeleteOkBtn = document.getElementById('final-delete-ok-btn');
            this.elements.finalDeleteCancelBtn = document.getElementById('final-delete-cancel-btn');

            this.elements.playlistsGrid = document.querySelector('.playlists-grid');
        },

        bindEvents() {
            if (this.elements.openCreateModalBtn && this.elements.createModal) {
                this.elements.openCreateModalBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.openModal(this.elements.createModal);
                });

                this.elements.cancelCreateBtn.addEventListener('click', () => this.closeCreateModal());
                this.elements.createModal.addEventListener('click', (e) => {
                    if (e.target === this.elements.createModal) this.closeCreateModal();
                });

                this.elements.descriptionTextarea.addEventListener('input', () => {
                    const textarea = this.elements.descriptionTextarea;
                    this.elements.charCounter.textContent = `${textarea.value.length} / ${textarea.maxLength}`;
                    textarea.style.height = 'auto';
                    textarea.style.height = `${textarea.scrollHeight}px`;
                });
                
                this.elements.titleInput.addEventListener('input', () => this.clearTitleError());
                this.elements.createForm.addEventListener('submit', this.handleCreateSubmit.bind(this));
            }

            if (this.elements.openDeleteModalBtn && this.elements.deleteModal && this.elements.confirmationModal) {
                this.elements.openDeleteModalBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.populateDeleteList();
                });

                this.elements.cancelDeleteBtn.addEventListener('click', () => this.closeModal(this.elements.deleteModal));
                this.elements.deleteModal.addEventListener('click', (e) => {
                    if (e.target === this.elements.deleteModal) this.closeModal(this.elements.deleteModal);
                });

                this.elements.playlistDeleteList.addEventListener('click', (e) => {
                    if (e.target.classList.contains('playlist-delete-item')) {
                        this.elements.playlistDeleteList.querySelectorAll('.playlist-delete-item')
                            .forEach(item => item.classList.remove('selected'));
                        e.target.classList.add('selected');
                        this.elements.confirmDeleteBtn.disabled = false;
                    }
                });

                this.elements.confirmDeleteBtn.addEventListener('click', () => {
                    const selectedItem = this.elements.playlistDeleteList.querySelector('.playlist-delete-item.selected');
                    if (!selectedItem) return;
                    this.showDeleteConfirmation(selectedItem.dataset.playlistId, selectedItem.textContent);
                });

                this.elements.finalDeleteCancelBtn.addEventListener('click', () => this.closeModal(this.elements.confirmationModal));
                this.elements.confirmationModal.addEventListener('click', (e) => {
                    if (e.target === this.elements.confirmationModal) this.closeModal(this.elements.confirmationModal);
                });

                this.elements.finalDeleteOkBtn.addEventListener('click', () => {
                    if (this.state.playlistIdToDelete) {
                        this.processDeletion(this.state.playlistIdToDelete);
                    }
                    this.closeModal(this.elements.confirmationModal);
                });
            }

            if (this.elements.playlistsGrid) {
                this.elements.playlistsGrid.addEventListener('click', (event) => {
                    const link = event.target.closest('a.playlist-card-link[data-empty="true"]');
                    if (link) {
                        event.preventDefault();
                        this.showToast('Этот плейлист пуст. Добавьте видео, чтобы начать просмотр.', true);
                    }
                });
            }
        },

        openModal(modal) {
            if (modal) modal.classList.add('active');
        },

        closeModal(modal) {
            if (modal) modal.classList.remove('active');
        },

        closeCreateModal() {
            this.closeModal(this.elements.createModal);
            this.elements.createForm.reset();
            this.elements.charCounter.textContent = '0 / 300';
            this.elements.descriptionTextarea.style.height = 'auto';
            this.clearTitleError();
        },

        clearTitleError() {
            if (this.elements.titleInput) {
                this.elements.titleInput.classList.remove('input-error');
                const errorContainer = this.elements.createForm.querySelector('.input-error-message');
                if (errorContainer) {
                    errorContainer.classList.remove('visible');
                    errorContainer.textContent = '';
                }
            }
        },

        showToast(message, isError = false) {
            if (this.state.isToastOnCooldown) return;
            this.state.isToastOnCooldown = true;

            const toast = document.createElement('div');
            toast.className = 'toast-notification';
            if (isError) toast.classList.add('error');
            
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => toast.classList.add('visible'), 10);

            setTimeout(() => {
                toast.classList.remove('visible');
                setTimeout(() => {
                    if (document.body.contains(toast)) toast.remove();
                    this.state.isToastOnCooldown = false;
                }, 500);
            }, 3000);
        },

        handleCreateSubmit(e) {
            e.preventDefault();
            
            if (this.elements.titleInput.value.trim() === '') {
                this.elements.titleInput.classList.add('input-error');
                const errorContainer = this.elements.createForm.querySelector('.input-error-message');
                if (errorContainer) {
                    errorContainer.textContent = 'Пожалуйста, укажите название.';
                    errorContainer.classList.add('visible');
                }
                return; 
            }

            const formData = new FormData(this.elements.createForm);
            formData.append('csrf_token', this.state.csrfToken); 

            fetch('/api/create_playlist', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    this.showToast(data.message || 'Произошла ошибка при создании плейлиста.', true);
                }
            })
            .catch(() => this.showToast('Ошибка сети. Не удалось создать плейлист.', true));
        },

        populateDeleteList() {
            this.elements.playlistDeleteList.innerHTML = '';
            this.elements.confirmDeleteBtn.disabled = true;

            const userPlaylistLinks = document.querySelectorAll('.playlist-card-link[data-playlist-id]');
            
            if (userPlaylistLinks.length === 0) {
                this.showToast('У вас нет созданных плейлистов для удаления.', true);
                return;
            }

            userPlaylistLinks.forEach(link => {
                const item = document.createElement('div');
                item.className = 'playlist-delete-item';
                item.textContent = link.querySelector('h3').textContent;
                item.dataset.playlistId = link.dataset.playlistId;
                this.elements.playlistDeleteList.appendChild(item);
            });

            this.openModal(this.elements.deleteModal);
        },

        showDeleteConfirmation(playlistId, playlistTitle) {
            this.elements.confirmDeleteText.textContent = `Вы уверены, что хотите удалить плейлист "${playlistTitle}"? Это действие необратимо.`;
            this.state.playlistIdToDelete = playlistId;
            this.openModal(this.elements.confirmationModal);
        },

        processDeletion(playlistId) {
            const formData = new FormData();
            formData.append('playlist_id', playlistId);
            formData.append('csrf_token', this.state.csrfToken);

            fetch('/api/delete_playlist', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.showToast('Плейлист успешно удален.');
                    this.closeModal(this.elements.deleteModal);

                    const cardLinkToRemove = document.querySelector(`.playlist-card-link[data-playlist-id="${playlistId}"]`);
                    if (cardLinkToRemove) {
                        const placeholder = document.createElement('div');
                        placeholder.className = 'playlist-deleted-placeholder';
                        placeholder.textContent = 'Плейлист был удален';
                        cardLinkToRemove.parentNode.replaceChild(placeholder, cardLinkToRemove);
                    }
                    this.state.playlistIdToDelete = null;
                } else {
                    this.showToast(data.message || 'Произошла ошибка при удалении.', true);
                }
            })
            .catch(() => this.showToast('Ошибка сети. Не удалось удалить плейлист.', true));
        }
    };

    PlaylistsManager.init();
});