document.addEventListener('DOMContentLoaded', function () {

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

    document.body.addEventListener('click', function(event) {
        const target = event.target;
        handleContextMenuToggle(target, event);
        handleCustomSelect(target);
        const actionTarget = target.closest('[data-action]');
        if (actionTarget) {
            if (actionTarget.tagName === 'A' && actionTarget.dataset.action !== 'share-social') {
                event.preventDefault();
            }
            runAction(actionTarget);
        }
    });
    
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
            const submitButton = page.forms.report.querySelector('.report-button.submit');
            if (submitButton) submitButton.disabled = false;
        });

        const otherRadio = document.getElementById('reason-other-radio');
        const otherBox = document.getElementById('other-reason-box');
        if (otherRadio && otherBox) {
            otherRadio.addEventListener('change', () => {
                otherBox.style.display = otherRadio.checked ? 'block' : 'none';
            });
        }

        const detailsTextarea = document.getElementById('other-reason-details');
        const charCountSpan = document.getElementById('char-count');

        if (detailsTextarea && charCountSpan) {
            detailsTextarea.addEventListener('input', () => {
                charCountSpan.textContent = detailsTextarea.value.length;
                detailsTextarea.style.height = 'auto';
                detailsTextarea.style.height = (detailsTextarea.scrollHeight) + 'px';
            });
        }
    }

    function runAction(actionTarget) {
        const action = actionTarget.dataset.action;
        const videoCard = actionTarget.closest('.video-card');
        const videoId = videoCard ? videoCard.dataset.videoId : null;

        const requiresAuth = [
            'toggle-watch-later',
            'add-to-playlist',
            'not-interested',
            'report'
        ];

        if (requiresAuth.includes(action) && !isUserLoggedIn) {
            if (page.modals.loginRequired) {
                page.modals.loginRequired.classList.add('active');
            }
            return;
        }

        switch (action) {
            case 'toggle-watch-later':
                if (videoId) handleToggleWatchLater(videoId, actionTarget);
                break;
            case 'add-to-playlist':
                if (videoId) openAddToPlaylistModal(videoId);
                break;
            case 'share-video':
                if (videoId) openShareModal(videoId);
                break;
            case 'not-interested':
                if (videoId) handleNotInterested(videoId, videoCard);
                break;
            case 'report':
                if (videoId) openReportModal(videoId);
                break;
            case 'copy-share-link':
                copyShareLink(actionTarget);
                break;
            case 'share-social':
                handleSocialShare(actionTarget);
                break;
            case 'open-create-playlist-modal':
                openCreatePlaylistModal();
                break;
            case 'close-create-fly-modal':
                if (page.modals.createPlaylist) page.modals.createPlaylist.classList.remove('active');
                break;
            case 'close-report-modal':
                if (page.modals.report) page.modals.report.classList.remove('active');
                break;
            case 'close-modal':
            case 'close-playlist-modal':
                actionTarget.closest('.share-modal-overlay, .add-to-playlist-modal-overlay, .modal-overlay-small')?.classList.remove('active');
                break;
        }
    }

    function handleToggleWatchLater(videoId, linkElement) {
        const textSpan = linkElement.querySelector('span');
        const body = new URLSearchParams({ video_id: videoId, csrf_token: csrfToken });

        fetch('/api/toggle_watch_later', { method: 'POST', body })
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
    
    function handleNotInterested(videoId, videoCard) {
        const body = new URLSearchParams({ video_id: videoId, csrf_token: csrfToken });

        fetch('/api/not_interested', { method: 'POST', body })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification('Видео скрыто. Мы постараемся не показывать его снова.');
                    videoCard.style.transition = 'opacity 0.3s, transform 0.3s';
                    videoCard.style.opacity = '0';
                    videoCard.style.transform = 'scale(0.95)';
                    setTimeout(() => videoCard.remove(), 300);
                } else {
                    showNotification(data.message || "Не удалось скрыть видео.", false);
                }
            })
            .catch(() => showNotification("Ошибка сети. Не удалось скрыть видео.", false));
    }
    
    function handleCreatePlaylistSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const titleInput = form.querySelector('input[name="title"]');
        const submitButton = form.querySelector('button[type="submit"]');
        
        if (titleInput.value.trim() === '') {
            setFormError(titleInput, 'Пожалуйста, укажите название.');
            return;
        }

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
                    if (page.modals.createPlaylist) page.modals.createPlaylist.classList.remove('active');
                    if (page.modals.addToPlaylist) page.modals.addToPlaylist.classList.remove('active');
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
        
        fetch('/api/add_video_to_playlist', { method: 'POST', body })
            .then(res => res.json())
            .then(data => showNotification(data.message, data.success))
            .catch(() => showNotification("Ошибка сети", false))
            .finally(() => {
                if(page.modals.addToPlaylist) page.modals.addToPlaylist.classList.remove('active');
            });
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
            .finally(() => {
                if (page.modals.report) page.modals.report.classList.remove('active');
            });
    }
    
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
            userPlaylists.forEach(p => {
                listContainer.innerHTML += `<div class="playlist-selection-item" data-playlist-id="${p.playlist_id}">${p.title}</div>`;
            });
        } else {
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
        if (input) input.value = `${window.location.origin}/watch.php?id=${videoId}`;
        page.modals.share.classList.add('active');
    }

    function copyShareLink(button) {
        const input = page.modals.share?.querySelector('.share-link-input');
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
        
        if (!menuButton) {
            if (!target.closest('.context-menu-dropdown.active')) {
                document.querySelectorAll('.context-menu-dropdown.active').forEach(m => m.classList.remove('active'));
            }
            return;
        }

        event.preventDefault();
        const menu = menuButton.nextElementSibling;
        if (!menu || !menu.classList.contains('context-menu-dropdown')) return;

        const isCurrentlyActive = menu.classList.contains('active');
        document.querySelectorAll('.context-menu-dropdown.active').forEach(m => m.classList.remove('active'));

        if (!isCurrentlyActive) {
            const buttonRect = menuButton.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            const estimatedMenuHeight = 240;
            
            menu.classList.toggle('opens-upward', (buttonRect.bottom + estimatedMenuHeight > viewportHeight));
            menu.classList.add('active');
        }
    }

    function handleCustomSelect(target) {
        const selectContainer = target.closest('.custom-select-container');
        if (!selectContainer) {
            document.querySelectorAll('.custom-select-container.open').forEach(c => c.classList.remove('open'));
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
        setTimeout(() => notification.classList.add('visible'), 10);
        setTimeout(() => notification.classList.remove('visible'), 4000);
    }
    
    function setFormError(inputElement, message) {
        const formGroup = inputElement.closest('.input-wrapper-small');
        const errorElement = formGroup.querySelector('.input-error-message-small');
        inputElement.classList.add('input-error');
        errorElement.textContent = message;
        errorElement.classList.add('visible');
    }

    function clearFormError(inputElement) {
        if (!inputElement) return;
        const formGroup = inputElement.closest('.input-wrapper-small');
        const errorElement = formGroup.querySelector('.input-error-message-small');
        inputElement.classList.remove('input-error');
        if (errorElement) errorElement.classList.remove('visible');
    }
});