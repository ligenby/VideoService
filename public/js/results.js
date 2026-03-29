document.addEventListener('DOMContentLoaded', () => {
    
    // Кешируем основные элементы DOM
    const filterPanel = document.getElementById('filter-panel');
    const page = {
        modals: {
            share: document.getElementById('share-modal'),
            addToPlaylist: document.getElementById('add-to-playlist-modal'),
            createPlaylist: document.getElementById('create-playlist-fly-modal'),
            report: document.getElementById('report-modal'),
            loginRequired: document.getElementById('login-required-modal')
        },
        forms: {
            createPlaylist: document.getElementById('create-playlist-fly-form'),
            report: document.getElementById('report-form')
        }
    };

    // Глобальное делегирование событий клика
    document.body.addEventListener('click', (event) => {
        const target = event.target;
        const actionTarget = target.closest('[data-action]');

        // Обработка фильтров
        const filterControl = target.closest('.filter-tab, .filter-option');
        if (filterControl) {
            event.preventDefault();
            const filterType = filterControl.dataset.filterType || 'filter';
            const filterValue = filterControl.dataset.filterValue || filterControl.dataset.filter;
            buildNewUrlAndNavigate(filterType, filterValue);
            return;
        }

        // Мобильная панель фильтров
        if (target.closest('#open-filters-btn')) {
            filterPanel?.classList.add('active');
            return;
        }
        if (target.closest('#close-filters-btn') || target === filterPanel) {
            filterPanel?.classList.remove('active');
            return;
        }
        
        // Подписки
        const subscribeButton = target.closest('.subscribe-button:not(.own-channel-button)');
        if (subscribeButton) {
            event.preventDefault();
            handleSubscription(subscribeButton);
            return;
        }
        
        // Роутер дата-экшенов
        if (actionTarget) {
            if (actionTarget.tagName === 'A' && actionTarget.dataset.action !== 'share-social') {
                event.preventDefault();
            }
            runActionDispatcher(actionTarget);
            return;
        }

        // UI-компоненты
        handleContextMenuToggle(target, event);
        handleCustomSelect(target);
    });
    
    // Инициализация форм
    if (page.forms.createPlaylist) {
        page.forms.createPlaylist.addEventListener('submit', handleCreatePlaylistSubmit);
        const titleInput = page.forms.createPlaylist.querySelector('input[name="title"]');
        if (titleInput) {
            titleInput.addEventListener('input', () => clearFormError(titleInput));
        }
    }

    if (page.modals.addToPlaylist) {
        page.modals.addToPlaylist.addEventListener('click', handleAddToPlaylistSelect);
    }
    
    if (page.forms.report) {
        page.forms.report.addEventListener('submit', handleReportSubmit);
        page.forms.report.addEventListener('change', () => {
            page.forms.report.querySelector('.report-button.submit').disabled = false;
            
            const otherRadio = document.getElementById('reason-other-radio');
            const otherBox = document.getElementById('other-reason-box');
            
            if (otherRadio && otherBox) {
                otherBox.style.display = otherRadio.checked ? 'block' : 'none';
            }
        });
        
        const detailsTextarea = document.getElementById('other-reason-details');
        const charCountSpan = document.getElementById('char-count');
        if (detailsTextarea && charCountSpan) {
            detailsTextarea.addEventListener('input', () => {
                charCountSpan.textContent = detailsTextarea.value.length;
            });
        }
    }

    function buildNewUrlAndNavigate(type, value) {
        const url = new URL(window.location.href);
        url.searchParams.set(type, value);
        window.location.href = url.toString();
    }
    
    function runActionDispatcher(target) {
        const action = target.dataset.action;
        const videoCard = target.closest('.video-list-item, .video-card');
        const videoId = videoCard ? videoCard.dataset.videoId : null;
        
        const requiresAuth = ['toggle-watch-later', 'add-to-playlist', 'report'];
        const isGuest = typeof isUserLoggedIn !== 'undefined' && !isUserLoggedIn;

        // Блокируем защищенные действия для неавторизованных пользователей
        if (requiresAuth.includes(action) && isGuest) {
            page.modals.loginRequired?.classList.add('active');
            return;
        }

        switch (action) {
            case 'toggle-watch-later': 
                if (videoId) handleToggleWatchLater(videoId, target); 
                break;
            case 'add-to-playlist': 
                if (videoId) openAddToPlaylistModal(videoId); 
                break;
            case 'share-video': 
                if (videoId) openShareModal(videoId); 
                break;
            case 'report': 
                if (videoId) openReportModal(videoId); 
                break;
            case 'copy-share-link': 
                copyShareLink(target); 
                break;
            case 'share-social': 
                handleSocialShare(target); 
                break;
            case 'open-create-playlist-modal': 
                openCreatePlaylistModal(); 
                break;
            case 'close-modal':
            case 'close-playlist-modal':
                const overlay = target.closest('.share-modal-overlay, .add-to-playlist-modal-overlay, .report-modal-overlay, .modal-overlay-small');
                overlay?.classList.remove('active');
                break;
        }
    }

    // --- API Handlers ---

    function handleSubscription(button) {
        if (typeof isUserLoggedIn !== 'undefined' && !isUserLoggedIn) {
            page.modals.loginRequired?.classList.add('active');
            return;
        }

        const channelId = button.dataset.channelId;
        if (!channelId) return;

        button.disabled = true;
        const formData = new URLSearchParams({ channel_id: channelId, csrf_token: csrfToken });

        fetch('/api/toggle_subscription', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.is_subscribed ? 'Вы подписались' : 'Вы отписались');
                    button.textContent = data.is_subscribed ? 'Вы подписаны' : 'Подписаться';
                    button.classList.toggle('subscribed', data.is_subscribed);
                    
                    const countSpan = document.querySelector(`[data-subscriber-count="${channelId}"]`);
                    if (countSpan && data.subscriber_count !== undefined) {
                        countSpan.textContent = `${new Intl.NumberFormat().format(data.subscriber_count)} подписчиков`;
                    }
                } else {
                    showNotification(data.message || 'Произошла ошибка', false);
                }
            })
            .catch(() => showNotification('Ошибка сети. Попробуйте позже.', false))
            .finally(() => { button.disabled = false; });
    }

    function handleToggleWatchLater(videoId, linkElement) {
        const textSpan = linkElement.querySelector('span');
        const body = new URLSearchParams({ video_id: videoId, csrf_token: csrfToken });
        
        fetch('/api/toggle_watch_later', { method: 'POST', body: body })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.is_added ? "Добавлено в 'Смотреть позже'" : "Удалено из 'Смотреть позже'");
                    if (textSpan) textSpan.textContent = data.is_added ? "Удалить из 'Смотреть позже'" : "Смотреть позже";
                } else {
                    showNotification(data.message || "Ошибка", false);
                }
            })
            .catch(() => showNotification("Ошибка сети", false));
    }

    function handleCreatePlaylistSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const titleInput = form.querySelector('input[name="title"]');
        
        if (titleInput.value.trim() === '') {
            setFormError(titleInput, 'Пожалуйста, укажите название.');
            return;
        }

        const submitButton = form.querySelector('button[type="submit"]');
        const originalButtonText = submitButton.textContent;
        submitButton.textContent = 'Создание...';
        submitButton.disabled = true;

        const formData = new FormData(form);
        formData.append('video_id_to_add', page.modals.createPlaylist.dataset.videoId);
        formData.append('csrf_token', csrfToken);

        fetch('/api/create_playlist', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                showNotification(data.message, data.success);
                if (data.success) {
                    if (data.playlist && typeof userPlaylists !== 'undefined') {
                         userPlaylists.push(data.playlist);
                    }
                    page.modals.createPlaylist?.classList.remove('active');
                    page.modals.addToPlaylist?.classList.remove('active');
                    form.reset();
                }
            })
            .catch(() => showNotification("Ошибка сети при создании плейлиста", false))
            .finally(() => {
                submitButton.textContent = originalButtonText;
                submitButton.disabled = false;
            });
    }

    function handleAddToPlaylistSelect(event) {
        const playlistItem = event.target.closest('.playlist-selection-item');
        if (!playlistItem) return;

        const playlistId = playlistItem.dataset.playlistId;
        const videoId = page.modals.addToPlaylist.dataset.videoId;
        const body = new URLSearchParams({ video_id: videoId, playlist_id: playlistId, csrf_token: csrfToken });
        
        fetch('/api/add_video_to_playlist', { method: 'POST', body: body })
            .then(res => res.json())
            .then(data => showNotification(data.message, data.success))
            .catch(() => showNotification("Ошибка сети", false))
            .finally(() => page.modals.addToPlaylist?.classList.remove('active'));
    }

    function handleReportSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const submitButton = form.querySelector('.report-button.submit');
        submitButton.disabled = true;

        const formData = new FormData(form);
        formData.append('csrf_token', csrfToken);
        
        fetch('/api/report_video', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    showNotification('Спасибо! Ваша жалоба отправлена на рассмотрение.');
                } else {
                    showNotification(data.message || 'Произошла ошибка', false);
                }
            })
            .catch(() => showNotification('Ошибка сети', false))
            .finally(() => page.modals.report?.classList.remove('active'));
    }

    // --- Modal Builders & UI Helpers ---

    function openReportModal(videoId) {
        if (!page.modals.report || !page.forms.report) return;
        
        page.forms.report.reset();
        page.modals.report.querySelector('#report-video-id').value = videoId;
        page.modals.report.querySelector('.report-button.submit').disabled = true;
        
        const otherBox = document.getElementById('other-reason-box');
        if (otherBox) otherBox.style.display = 'none';
        
        const charCounter = document.getElementById('char-count');
        if (charCounter) charCounter.textContent = '0';
        
        page.modals.report.classList.add('active');
    }

    function openAddToPlaylistModal(videoId) {
        if (!page.modals.addToPlaylist) return;
        
        const listContainer = page.modals.addToPlaylist.querySelector('.playlist-selection-list');
        page.modals.addToPlaylist.dataset.videoId = videoId;
        listContainer.innerHTML = ''; 
    
        if (typeof userPlaylists !== 'undefined' && userPlaylists.length > 0) {
            listContainer.classList.remove('empty'); 
            userPlaylists.forEach(p => {
                const item = document.createElement('div');
                item.className = 'playlist-selection-item';
                item.dataset.playlistId = p.playlist_id;
                item.textContent = p.title; 
                listContainer.appendChild(item);
            });
        } else {
            listContainer.classList.add('empty'); 
            listContainer.innerHTML = '<p class="no-playlists-message">У вас пока нет плейлистов.</p>';
        }
        
        page.modals.addToPlaylist.classList.add('active');
    }

    function openCreatePlaylistModal() {
        if (!page.modals.createPlaylist || !page.modals.addToPlaylist) return;
        
        page.forms.createPlaylist?.reset();
        clearFormError(page.forms.createPlaylist.querySelector('input[name="title"]'));
        page.modals.createPlaylist.dataset.videoId = page.modals.addToPlaylist.dataset.videoId;
        page.modals.createPlaylist.classList.add('active');
    }

    function openShareModal(videoId) {
        if (!page.modals.share) return;
        
        const input = page.modals.share.querySelector('.share-link-input');
        if (input) {
            input.value = `${window.location.origin}/watch.php?id=${videoId}`;
        }
        page.modals.share.classList.add('active');
    }

    function copyShareLink(button) {
        const input = button.closest('.share-link-container').querySelector('.share-link-input');
        if (!input || !navigator.clipboard) return;
        
        navigator.clipboard.writeText(input.value).then(() => {
            const originalText = button.textContent;
            button.textContent = 'Скопировано!';
            button.disabled = true;
            setTimeout(() => {
                button.textContent = originalText;
                button.disabled = false;
            }, 2000);
        }).catch(() => showNotification("Ошибка копирования", false));
    }

    function handleSocialShare(linkElement) {
        const network = linkElement.dataset.network;
        const urlToShare = encodeURIComponent(page.modals.share.querySelector('.share-link-input').value);
        let shareUrl = '';
        
        switch(network) {
            case 'vk': shareUrl = `https://vk.com/share.php?url=${urlToShare}`; break;
            case 'telegram': shareUrl = `https://t.me/share/url?url=${urlToShare}`; break;
            case 'twitter': shareUrl = `https://twitter.com/intent/tweet?url=${urlToShare}`; break;
        }
        
        if (shareUrl) {
            window.open(shareUrl, '_blank', 'width=600,height=400,noopener,noreferrer');
        }
    }

    function handleContextMenuToggle(target, event) {
        const menuButton = target.closest('.context-menu-button');
        
        document.querySelectorAll('.video-list-item.z-index-high, .video-card.z-index-high')
            .forEach(el => el.classList.remove('z-index-high'));
            
        if (!menuButton) {
            if (!target.closest('.context-menu-dropdown.active')) {
                document.querySelectorAll('.context-menu-dropdown.active')
                    .forEach(m => m.classList.remove('active'));
            }
            return;
        }
        
        event.preventDefault();
        const menu = menuButton.nextElementSibling;
        if (!menu || !menu.classList.contains('context-menu-dropdown')) return;
        
        const parentItem = menuButton.closest('.video-list-item, .video-card');
        const isCurrentlyActive = menu.classList.contains('active');
        
        document.querySelectorAll('.context-menu-dropdown.active')
            .forEach(m => m.classList.remove('active'));
            
        if (!isCurrentlyActive) {
            const buttonRect = menuButton.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            
            // Если снизу мало места, открываем меню вверх
            menu.classList.toggle('opens-upward', (buttonRect.bottom + 240 > viewportHeight));
            if(parentItem) parentItem.classList.add('z-index-high');
            
            menu.classList.add('active');
        }
    }

    function handleCustomSelect(target) {
        const selectContainer = target.closest('.custom-select-container');
        
        if (!selectContainer) {
            document.querySelectorAll('.custom-select-container.open')
                .forEach(c => c.classList.remove('open'));
            return;
        }
        
        if (target.closest('.custom-select-button')) {
            selectContainer.classList.toggle('open');
        } else if (target.closest('.custom-select-option')) {
            const option = target.closest('.custom-select-option');
            selectContainer.querySelector('input[name="visibility"]').value = option.dataset.value;
            selectContainer.querySelector('.custom-select-value').textContent = option.textContent.trim();
            selectContainer.classList.remove('open');
        }
    }

    function showNotification(message, isSuccess = true) {
        let notification = document.querySelector('.notification');
        
        if (!notification) {
            notification = document.createElement('div');
            notification.className = 'notification';
            document.body.appendChild(notification);
        }
        
        notification.textContent = message;
        notification.className = `notification ${isSuccess ? 'success' : 'error'}`;
        
        // setTimeout нужен для срабатывания CSS-перехода
        setTimeout(() => notification.classList.add('visible'), 10);
        setTimeout(() => notification.classList.remove('visible'), 4000);
    }
    
    function setFormError(inputElement, message) {
        const formGroup = inputElement.closest('.input-wrapper-small');
        if (!formGroup) return;
        
        const errorElement = formGroup.querySelector('.input-error-message-small');
        inputElement.classList.add('input-error');
        
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.add('visible');
        }
    }

    function clearFormError(inputElement) {
        if (!inputElement) return;
        
        const formGroup = inputElement.closest('.input-wrapper-small');
        if (!formGroup) return;
        
        const errorElement = formGroup.querySelector('.input-error-message-small');
        inputElement.classList.remove('input-error');
        
        if (errorElement) {
            errorElement.classList.remove('visible');
        }
    }
});