document.addEventListener('DOMContentLoaded', () => {

    let isToastOnCooldown = false;

    function showToast(message, isError = false) {
        if (isToastOnCooldown) return;
        isToastOnCooldown = true;

        const toast = document.createElement('div');
        toast.className = `toast-notification ${isError ? 'error' : ''}`;
        toast.textContent = message;
        document.body.appendChild(toast);

        setTimeout(() => toast.classList.add('visible'), 10);

        setTimeout(() => {
            toast.classList.remove('visible');
            setTimeout(() => {
                if (document.body.contains(toast)) toast.remove();
                isToastOnCooldown = false;
            }, 500);
        }, 3000);
    }

    // Блокировка клика по пустым плейлистам
    document.addEventListener('click', (e) => {
        const playlistLink = e.target.closest('.playlist-card-link');
        
        if (playlistLink?.dataset.videoCount) {
            const videoCount = parseInt(playlistLink.dataset.videoCount, 10);
            const firstVideoId = playlistLink.dataset.firstVideoId;

            if (isNaN(videoCount) || videoCount === 0 || !firstVideoId) {
                e.preventDefault();
                showToast('Плейлист пуст.', true);
            }
        }
    });

    // Подписка
    const subscribeBtn = document.getElementById('subscribe-btn');
    if (subscribeBtn) {
        subscribeBtn.addEventListener('click', function() {
            if (typeof isUserLoggedIn === 'undefined' || !isUserLoggedIn) {
                window.location.href = '/login.php';
                return;
            }

            const formData = new FormData();
            formData.append('channel_id', this.dataset.channelId);
            formData.append('csrf_token', typeof csrfToken !== 'undefined' ? csrfToken : ''); 

            fetch("/api/toggle_subscription", {
                method: "POST",
                body: formData
            })
            .then(res => {
                if (!res.ok) throw new Error('Network error');
                return res.json();
            })
            .then(data => {
                if (data.success) {
                    const subCountDisplay = document.getElementById('subscriber-count-display');
                    if (subCountDisplay) {
                        const word = getPluralForm(data.subscriber_count, ['подписчик', 'подписчика', 'подписчиков']);
                        subCountDisplay.textContent = `${new Intl.NumberFormat('ru-RU').format(data.subscriber_count)} ${word}`;
                    }

                    this.textContent = data.is_subscribed ? "Вы подписаны" : "Подписаться";
                    this.classList.toggle("subscribed", data.is_subscribed);
                } else if (data.message) { 
                    showToast(data.message, true);
                }
            })
            .catch(error => {
                console.error("Subscription error:", error);
                showToast("Произошла ошибка. Пожалуйста, попробуйте снова.", true);
            });
        });
    }

    // Показ кнопок управления контентом (для владельца)
    const activeTab = document.querySelector('.profile-tabs a.active');
    const createVideoBtn = document.getElementById('create-video-btn');
    const createPlaylistBtn = document.getElementById('create-playlist-btn');
    const deleteVideoBtn = document.getElementById('delete-video-open-btn');
    const deletePlaylistBtn = document.getElementById('delete-playlist-open-btn');

    if (activeTab && createVideoBtn && createPlaylistBtn && deleteVideoBtn && deletePlaylistBtn) {
        const url = activeTab.getAttribute('href');

        if (url.includes('tab=videos')) {
            createVideoBtn.style.display = 'inline-flex';
            deleteVideoBtn.style.display = 'inline-flex';
        } else if (url.includes('tab=playlists')) {
            createPlaylistBtn.style.display = 'inline-flex';
            deletePlaylistBtn.style.display = 'inline-flex';
        }
    }
    
    // Создание плейлиста
    const createModal = document.getElementById('create-playlist-modal');
    if (createPlaylistBtn && createModal) {
        const cancelBtn = document.getElementById('cancel-playlist-creation');
        const form = document.getElementById('create-playlist-form');
        const descTextarea = document.getElementById('playlist-description');
        const charCounter = document.getElementById('char-counter');
        const titleInput = document.getElementById('playlist-title');

        const clearTitleError = () => {
            if (!titleInput) return;
            titleInput.classList.remove('input-error');
            const errSpan = form.querySelector('.input-error-message');
            if (errSpan) {
                errSpan.classList.remove('visible');
                errSpan.textContent = '';
            }
        };

        const closeModal = () => {
            createModal.classList.remove('active');
            if (form) {
                form.reset();
                charCounter.textContent = '0 / 300';
                descTextarea.style.height = 'auto';
                clearTitleError();
            }
        };

        createPlaylistBtn.addEventListener('click', (e) => {
            e.preventDefault(); 
            createModal.classList.add('active');
        });

        cancelBtn?.addEventListener('click', closeModal);
        createModal.addEventListener('click', (e) => {
            if (e.target === createModal) closeModal();
        });

        descTextarea?.addEventListener('input', () => {
            charCounter.textContent = `${descTextarea.value.length} / ${descTextarea.maxLength}`;
            descTextarea.style.height = 'auto';
            descTextarea.style.height = `${descTextarea.scrollHeight}px`;
        });

        titleInput?.addEventListener('input', clearTitleError);
        
        form?.addEventListener('submit', function(e) {
            e.preventDefault();

            if (!titleInput.value.trim()) {
                titleInput.classList.add('input-error');
                const errSpan = this.querySelector('.input-error-message');
                if (errSpan) {
                    errSpan.textContent = 'Пожалуйста, укажите название.';
                    errSpan.classList.add('visible');
                }
                return;
            }

            const formData = new FormData(this);
            formData.append('csrf_token', typeof csrfToken !== 'undefined' ? csrfToken : ''); 

            fetch('/api/create_playlist', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload(); 
                    } else {
                        showToast(data.message || 'Ошибка при создании плейлиста.', true);
                    }
                })
                .catch(() => showToast('Ошибка сети. Не удалось создать плейлист.', true));
        });
    }
    
    // Удаление видео
    const deleteVideoModal = document.getElementById('delete-video-modal');
    const confirmVideoModal = document.getElementById('confirmation-video-delete-modal');

    if (deleteVideoBtn && deleteVideoModal && confirmVideoModal) {
        const list = document.getElementById('video-delete-list');
        const confirmBtn = document.getElementById('confirm-video-delete-btn');
        const confirmText = document.getElementById('confirm-video-delete-text');
        
        const closeModals = () => {
            deleteVideoModal.classList.remove('active');
            confirmVideoModal.classList.remove('active');
        };

        deleteVideoBtn.addEventListener('click', (e) => {
            e.preventDefault();
            list.innerHTML = '';
            confirmBtn.disabled = true;
            
            const cards = document.querySelectorAll('.video-card[data-public-video-id]');
            if (!cards.length) return showToast('На канале нет видео для удаления.', true);

            cards.forEach(card => {
                const item = document.createElement('div');
                item.className = 'video-delete-item';
                item.textContent = card.querySelector('.video-title').textContent;
                item.dataset.id = card.dataset.publicVideoId;
                list.appendChild(item);
            });
            deleteVideoModal.classList.add('active');
        });

        document.getElementById('cancel-video-delete-btn').addEventListener('click', closeModals);
        document.getElementById('final-video-delete-cancel-btn').addEventListener('click', closeModals);
        
        [deleteVideoModal, confirmVideoModal].forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModals();
            });
        });

        list.addEventListener('click', (e) => {
            if (e.target.classList.contains('video-delete-item')) {
                list.querySelectorAll('.video-delete-item').forEach(i => i.classList.remove('selected'));
                e.target.classList.add('selected');
                confirmBtn.disabled = false;
            }
        });

        confirmBtn.addEventListener('click', () => {
            const selected = list.querySelector('.video-delete-item.selected');
            if (!selected) return;

            confirmText.textContent = `Вы уверены, что хотите удалить видео "${selected.textContent}"? Это действие нельзя отменить.`;
            confirmVideoModal.dataset.id = selected.dataset.id;
            confirmVideoModal.classList.add('active');
        });
        
        document.getElementById('final-video-delete-ok-btn').addEventListener('click', () => {
            const videoId = confirmVideoModal.dataset.id;
            if (videoId) handleDeleteVideo(videoId);
            closeModals();
        });
        
        function handleDeleteVideo(videoId) {
            const formData = new FormData();
            formData.append('public_video_id', videoId);
            formData.append('csrf_token', typeof csrfToken !== 'undefined' ? csrfToken : ''); 

            fetch('/api/delete_video', { method: 'POST', body: formData })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast('Видео успешно удалено.');
                        closeModals();
                        
                        document.querySelector(`.video-card[data-public-video-id="${videoId}"]`)?.remove();
                        if (!document.querySelectorAll('.video-card[data-public-video-id]').length) {
                             window.location.reload(); 
                        }
                    } else {
                        showToast(data.message || 'Ошибка при удалении видео.', true);
                    }
                })
                .catch(err => {
                    console.error("Delete video error:", err);
                    showToast('Ошибка сети. Не удалось удалить видео.', true);
                });
        }
    }
    
    // Удаление плейлиста
    const deletePlaylistModal = document.getElementById('delete-playlist-modal');
    const confirmPlaylistModal = document.getElementById('confirmation-playlist-delete-modal');

    if (deletePlaylistBtn && deletePlaylistModal && confirmPlaylistModal) {
        const list = document.getElementById('playlist-delete-list');
        const confirmBtn = document.getElementById('confirm-playlist-delete-btn');
        const confirmText = document.getElementById('confirm-playlist-delete-text');

        const closeModals = () => {
            deletePlaylistModal.classList.remove('active');
            confirmPlaylistModal.classList.remove('active');
        };

        deletePlaylistBtn.addEventListener('click', (e) => {
            e.preventDefault();
            list.innerHTML = '';
            confirmBtn.disabled = true;

            const links = document.querySelectorAll('.playlist-grid-profile .playlist-card-link[data-playlist-id]');
            if (!links.length) return showToast('У вас нет плейлистов для удаления.', true);

            links.forEach(link => {
                const item = document.createElement('div');
                item.className = 'playlist-delete-item';
                const title = link.querySelector('.playlist-info h3'); 
                item.textContent = title ? title.textContent : 'Плейлист без названия';
                item.dataset.id = link.dataset.playlistId;
                list.appendChild(item);
            });
            deletePlaylistModal.classList.add('active');
        });
        
        document.getElementById('cancel-playlist-delete-btn').addEventListener('click', closeModals);
        document.getElementById('final-playlist-delete-cancel-btn').addEventListener('click', closeModals);

        [deletePlaylistModal, confirmPlaylistModal].forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) closeModals();
            });
        });

        list.addEventListener('click', (e) => {
            if (e.target.classList.contains('playlist-delete-item')) {
                list.querySelectorAll('.playlist-delete-item').forEach(i => i.classList.remove('selected'));
                e.target.classList.add('selected');
                confirmBtn.disabled = false;
            }
        });

        confirmBtn.addEventListener('click', () => {
            const selected = list.querySelector('.playlist-delete-item.selected');
            if (!selected) return;
            
            confirmText.textContent = `Вы уверены, что хотите удалить плейлист "${selected.textContent}"? Это действие нельзя отменить.`;
            confirmPlaylistModal.dataset.id = selected.dataset.id;
            confirmPlaylistModal.classList.add('active');
        });

        document.getElementById('final-playlist-delete-ok-btn').addEventListener('click', () => {
            const playlistId = confirmPlaylistModal.dataset.id;
            if (playlistId) handleDeletePlaylist(playlistId);
            closeModals();
        });

        function handleDeletePlaylist(playlistId) {
            const formData = new FormData();
            formData.append('playlist_id', playlistId);
            formData.append('csrf_token', typeof csrfToken !== 'undefined' ? csrfToken : '');

            fetch('/api/delete_playlist', { method: 'POST', body: formData })
                .then(res => {
                    if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
                    return res.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast('Плейлист успешно удален.');
                        closeModals();

                        document.querySelector(`.playlist-card-link[data-playlist-id="${playlistId}"]`)?.remove();
                        if (!document.querySelectorAll('.playlist-card-link[data-playlist-id]').length) {
                             window.location.reload();
                        }
                    } else {
                        showToast(data.message || 'Ошибка при удалении плейлиста.', true);
                    }
                })
                .catch(err => {
                    console.error("Delete playlist error:", err);
                    showToast('Ошибка сети. Не удалось удалить плейлист.', true);
                });
        }
    }

    // Склонение числительных (1 подписчик, 2 подписчика, 5 подписчиков)
    function getPluralForm(num, forms) {
        const n = Math.abs(num) % 100;
        const n1 = n % 10;
        
        if (n > 10 && n < 20) return forms[2];
        if (n1 > 1 && n1 < 5) return forms[1];
        if (n1 === 1) return forms[0];
        
        return forms[2];
    }
});