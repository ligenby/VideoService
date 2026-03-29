document.addEventListener('DOMContentLoaded', () => {

    // --- Кастомный плеер ---
    const videoContainer = document.querySelector('.video-container');
    if (videoContainer) {
        initPlayer(videoContainer);
    }

    function initPlayer(container) {
        const video = container.querySelector('.main-video');
        const playPauseBtn = container.querySelector('.play-pause-btn');
        const playPauseIcon = playPauseBtn?.querySelector('img');
        const nextBtn = container.querySelector('.next-btn');
        const muteBtn = container.querySelector('.mute-btn');
        const volumeSlider = container.querySelector('.volume-slider');
        const currentTimeElem = container.querySelector('.current-time');
        const totalTimeElem = container.querySelector('.total-time');
        const timelineContainer = container.querySelector('.timeline-container');
        const fullscreenBtn = container.querySelector('.fullscreen-btn');
        const settingsBtn = container.querySelector('.settings-btn');
        const settingsMenuContainer = container.querySelector('.settings-menu-container');

        let isScrubbing = false;
        let wasPaused;
        let inactivityTimeout;

        // Принудительно включаем громкость на максимум
        if (video) video.volume = 1; 
        if (volumeSlider) volumeSlider.value = 1;

        // --- Управление воспроизведением ---
        
        const updatePlayPauseIcon = () => {
            if (!playPauseIcon || !video) return;
            playPauseIcon.src = video.paused ? '/images/play1.png' : '/images/stop1.png';
        };

        const togglePlay = () => {
            if (!video) return;
            video.paused ? video.play() : video.pause();
        };

        const toggleMute = () => {
            if (!video) return;
            video.muted = !video.muted;
            if (!video.muted && video.volume === 0) {
                video.volume = 1;
                if (volumeSlider) volumeSlider.value = 1;
            }
        };

        // Умный автозапуск
        const attemptAutoplay = () => {
            if (!video) return;
            video.muted = false; 
            const playPromise = video.play();
            
            if (playPromise !== undefined) {
                playPromise
                    .then(() => updatePlayPauseIcon())
                    .catch(() => {
                        console.log("Автозапуск со звуком заблокирован браузером. Включаем Mute.");
                        video.muted = true;
                        video.play();
                        updatePlayPauseIcon();

                        const unmuteOnInteract = () => {
                            video.muted = false;
                            video.volume = 1;
                            if (volumeSlider) volumeSlider.value = 1;
                            container.removeEventListener('click', unmuteOnInteract);
                        };
                        container.addEventListener('click', unmuteOnInteract, { once: true });
                    });
            }
        };
        attemptAutoplay();

        playPauseBtn?.addEventListener('click', togglePlay);
        video?.addEventListener('click', togglePlay);
        
        video?.addEventListener('play', () => {
            container.classList.remove('paused');
            updatePlayPauseIcon();
            resetInactivity();
        });
        
        video?.addEventListener('pause', () => {
            container.classList.add('paused');
            updatePlayPauseIcon();
            clearTimeout(inactivityTimeout);
            container.classList.remove('inactive');
        });

        // --- Громкость ---
        muteBtn?.addEventListener('click', toggleMute);
        
        volumeSlider?.addEventListener('input', (e) => {
            if (!video) return;
            video.volume = e.target.value;
            video.muted = e.target.value == 0;
        });

        video?.addEventListener('volumechange', () => {
            if (volumeSlider) volumeSlider.value = video.volume;
            let volumeLevel = 'high';
            if (video.muted || video.volume === 0) volumeLevel = 'muted';
            else if (video.volume < 0.5) volumeLevel = 'low';
            container.dataset.volumeLevel = volumeLevel;
        });

        // --- Время и прогресс ---
        video?.addEventListener('loadeddata', () => {
            if (totalTimeElem) totalTimeElem.textContent = formatDuration(video.duration);
        });

        video?.addEventListener('timeupdate', () => {
            if (currentTimeElem) currentTimeElem.textContent = formatDuration(video.currentTime);
            const percent = video.currentTime / video.duration;
            timelineContainer?.style.setProperty("--progress-position", `${percent * 100}%`);
        });

        // --- Логика экрана окончания (End Screen) и автоперехода ---
        const endScreen = container.querySelector('.end-screen');
        const progressCircle = container.querySelector('.progress-ring__circle');
        const cancelButton = container.querySelector('#cancel-autoplay');
        const playNextLink = container.querySelector('#play-next-link');
        let autoplayTimer = null;
        let countdownValue = 15;

        const stopAutoplay = () => {
            if (autoplayTimer) {
                clearTimeout(autoplayTimer); 
                autoplayTimer = null;
            }
            if (progressCircle) {
                progressCircle.style.transition = 'none';
                progressCircle.style.display = 'none';
            }
            if (cancelButton) cancelButton.style.display = 'none';
        };

        const startAutoplay = () => {
            if (!playNextLink || !progressCircle) return;
            
            if (endScreen) {
                endScreen.style.display = 'flex';
                setTimeout(() => endScreen.classList.add('visible'), 50);
            }
            
            progressCircle.style.display = 'block';
            if (cancelButton) cancelButton.style.display = 'block';
            
            const circumference = 163;
            progressCircle.style.strokeDasharray = `${circumference} ${circumference}`;
            progressCircle.style.strokeDashoffset = circumference;
            
            progressCircle.getBoundingClientRect(); // Триггер рефлоу
            progressCircle.style.transition = `stroke-dashoffset ${countdownValue}s linear`;
            progressCircle.style.strokeDashoffset = '0';

            autoplayTimer = setTimeout(() => {
                window.location.href = playNextLink.href;
            }, countdownValue * 1000);
        };

        video?.addEventListener('ended', startAutoplay);
        
        video?.addEventListener('play', () => {
            if (endScreen) {
                endScreen.classList.remove('visible');
                setTimeout(() => {
                     if (!endScreen.classList.contains('visible')) endScreen.style.display = 'none';
                }, 300);
            }
            stopAutoplay();
        });

        cancelButton?.addEventListener('click', (e) => {
            e.stopPropagation();
            stopAutoplay();
        });

        // --- Управление скраббингом (прокруткой) ---
        const handleTimelineUpdate = (e) => {
            if (!timelineContainer || !video) return;
            const rect = timelineContainer.getBoundingClientRect();
            const percent = Math.min(Math.max(0, e.x - rect.x), rect.width) / rect.width;
            timelineContainer.style.setProperty("--preview-position", `${percent * 100}%`);
            
            if (isScrubbing) {
                e.preventDefault();
                timelineContainer.style.setProperty("--progress-position", `${percent * 100}%`);
                video.currentTime = percent * video.duration;
            }
        };

        const toggleScrubbing = (e) => {
            if (!timelineContainer || !video) return;
            if (e.button !== 0) return;
            
            const rect = timelineContainer.getBoundingClientRect();
            const percent = Math.min(Math.max(0, e.x - rect.x), rect.width) / rect.width;
            isScrubbing = (e.buttons & 1) === 1;
            container.classList.toggle('scrubbing', isScrubbing);
            
            if (isScrubbing) {
                wasPaused = video.paused;
                video.pause();
            } else {
                video.currentTime = percent * video.duration;
                if (!wasPaused) video.play();
            }
            handleTimelineUpdate(e);
        };

        timelineContainer?.addEventListener('mousedown', toggleScrubbing);
        document.addEventListener('mouseup', e => {
            if (isScrubbing) toggleScrubbing(e);
        });
        document.addEventListener('mousemove', e => {
            if (isScrubbing) handleTimelineUpdate(e);
        });

        // --- Полный экран и переключение видео ---
        const handleNextVideo = () => {
            const activePlaylistItem = document.querySelector('.playlist-video-item.active');
            let nextVideoUrl = null;
            
            if (activePlaylistItem) {
                const nextItem = activePlaylistItem.nextElementSibling;
                if (nextItem && nextItem.matches('a.playlist-video-item')) {
                    nextVideoUrl = nextItem.href;
                }
            }
            
            if (!nextVideoUrl) {
                const firstRecommendation = document.querySelector('a.recommendation-card-link');
                if (firstRecommendation) nextVideoUrl = firstRecommendation.href;
            }
            
            if (nextVideoUrl) window.location.href = nextVideoUrl;
        };

        nextBtn?.addEventListener('click', handleNextVideo);

        const toggleFullscreen = () => {
            if (document.fullscreenElement == null) {
                container.requestFullscreen().catch(err => console.error(`Fullscreen request failed: ${err.message}`));
            } else {
                document.exitFullscreen();
            }
        };
        
        fullscreenBtn?.addEventListener('click', toggleFullscreen);
        document.addEventListener("fullscreenchange", () => {
            container.classList.toggle("fullscreen", document.fullscreenElement != null);
        });

        // --- Настройки плеера (шестеренка) ---
        if (settingsBtn && settingsMenuContainer) {
            settingsBtn.addEventListener('click', (e) => {
                e.stopPropagation(); 
                settingsMenuContainer.classList.toggle('active');
                if (settingsMenuContainer.classList.contains('active')) {
                    settingsMenuContainer.classList.remove('submenu-open');
                    settingsMenuContainer.querySelectorAll('.submenu.active').forEach(sm => sm.classList.remove('active'));
                }
            });

            settingsMenuContainer.addEventListener('click', (e) => {
                e.stopPropagation();
                const target = e.target;
                const menuItem = target.closest('.menu-item');
                const backBtn = target.closest('.back-btn');
                const option = target.closest('.menu-option');

                if (menuItem) {
                    if (menuItem.id === 'pip-menu-item') {
                        if (document.pictureInPictureElement) document.exitPictureInPicture();
                        else if (document.pictureInPictureEnabled) video.requestPictureInPicture();
                        settingsMenuContainer.classList.remove('active');
                    } else {
                        const submenuId = menuItem.dataset.opens;
                        const submenu = document.getElementById(`${submenuId}-menu`);
                        if (submenu) {
                            submenu.classList.add('active');
                            settingsMenuContainer.classList.add('submenu-open');
                        }
                    }
                } else if (backBtn) {
                    const submenu = backBtn.closest('.submenu');
                    if (submenu) {
                        submenu.classList.remove('active');
                        settingsMenuContainer.classList.remove('submenu-open');
                    }
                } else if (option) {
                    const parentMenu = option.closest('.submenu');
                    const value = option.dataset.value;
                    
                    parentMenu.querySelectorAll('.menu-option').forEach(o => {
                        o.classList.remove('active');
                        o.textContent = o.textContent.replace('✓ ', '');
                    });
                    
                    option.classList.add('active');
                    if (!option.textContent.includes('✓')) option.textContent = `✓ ${option.textContent}`;

                    if (parentMenu.id.includes('speed')) {
                        video.playbackRate = parseFloat(value);
                        const displayValue = value == 1 ? 'Обычная' : value;
                        document.getElementById('speed-value').textContent = `${displayValue} >`;
                    }
                    if (parentMenu.id.includes('quality')) {
                        document.getElementById('quality-value').textContent = `${option.textContent.replace('✓ ', '').trim()} >`;
                    }
                    if (parentMenu.id.includes('subtitles')) {
                         document.getElementById('subtitles-value').textContent = `${option.textContent.replace('✓ ', '').trim()} >`;
                    }
                    
                    setTimeout(() => {
                        parentMenu.classList.remove('active');
                        settingsMenuContainer.classList.remove('submenu-open', 'active');
                    }, 300);
                }
            });
        }

        document.addEventListener('click', (e) => {
            if (settingsMenuContainer?.classList.contains('active')) {
                if (!e.target.closest('.settings-btn')) {
                    settingsMenuContainer.classList.remove('active', 'submenu-open');
                    settingsMenuContainer.querySelectorAll('.submenu.active').forEach(sm => sm.classList.remove('active'));
                }
            }
        });

        // --- Перемешивание плейлиста ---
        document.querySelector('.playlist-shuffle-btn')?.addEventListener('click', () => {
            const playlistVideosContainer = document.querySelector('.playlist-panel-videos');
            const videoItems = playlistVideosContainer ? playlistVideosContainer.querySelectorAll('.playlist-video-item') : [];
            if (videoItems.length === 0) return;
            
            const videoIds = Array.from(videoItems).map(item => item.dataset.videoId);
            const randomPublicId = videoIds[Math.floor(Math.random() * videoIds.length)];
            const urlParams = new URLSearchParams(window.location.search);
            const listParam = urlParams.get('list') || urlParams.get('playlist');
            
            if (randomPublicId && listParam) {
                window.location.href = `/watch.php?id=${randomPublicId}&list=${listParam}`;
            }
        });
        
        // --- Обработка неактивности курсора ---
        const resetInactivity = () => {
            container.classList.remove('inactive');
            clearTimeout(inactivityTimeout);
            if (video && !video.paused) {
                inactivityTimeout = setTimeout(() => container.classList.add('inactive'), 3000);
            }
        };
        resetInactivity();
        container.addEventListener('mousemove', resetInactivity);
        container.addEventListener('mouseleave', () => { 
            if (video && !video.paused) container.classList.add('inactive'); 
        });
    }

    // --- Обработка действий страницы (UI) ---
    const page = {
        mainVideoId: document.getElementById('comments-section')?.dataset.videoId,
        modals: {
            share: document.getElementById('share-modal'),
            addToPlaylist: document.getElementById('add-to-playlist-modal'),
            createPlaylist: document.getElementById('create-playlist-fly-modal'),
            report: document.getElementById('report-modal'),
            loginRequired: document.getElementById('login-required-modal'),
            reportComment: document.getElementById('report-comment-modal')
        },
        forms: {
            createPlaylist: document.getElementById('create-playlist-fly-form'),
            report: document.getElementById('report-form'),
            reportComment: document.getElementById('report-comment-form')
        }
    };

    document.body.addEventListener('click', (event) => {
        const target = event.target;
        
        const menuButton = target.closest('.context-menu-button, .more-actions-btn');
        const actionTarget = target.closest('[data-action]');

        if (menuButton) {
            handleMenuToggle(menuButton);
            return; 
        }

        if (actionTarget) {
            event.preventDefault();
            const action = actionTarget.dataset.action;
            const videoCard = actionTarget.closest('.video-card');
            const videoId = videoCard ? videoCard.dataset.videoId : page.mainVideoId;
            
            runAction(action, { actionTarget, videoId });

            const parentMenu = actionTarget.closest('.context-menu-dropdown, .more-actions-dropdown');
            if (parentMenu) parentMenu.classList.remove('active');
            return;
        }

        if (!target.closest('.context-menu-dropdown, .more-actions-dropdown')) {
            document.querySelectorAll('.context-menu-dropdown.active, .more-actions-dropdown.active')
                .forEach(menu => menu.classList.remove('active'));
        }
        
        handleCustomSelect(target);
    });

    if (page.forms.reportComment) {
        page.forms.reportComment.addEventListener('change', () => {
            const submitButton = page.forms.reportComment.querySelector('.submit');
            if (submitButton) submitButton.disabled = false;
        });

        page.forms.reportComment.addEventListener('submit', (event) => {
            event.preventDefault();
            const form = event.target;
            const submitButton = form.querySelector('.submit');
            submitButton.disabled = true;

            const formData = new FormData(form);
            if (typeof csrfToken !== 'undefined') formData.append('csrf_token', csrfToken);

            fetch('/api/report_comment', { 
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                showNotification(data.success ? 'Жалоба на комментарий отправлена' : (data.message || 'Ошибка'), data.success);
            })
            .catch(() => showNotification('Ошибка сети', false))
            .finally(() => {
                page.modals.reportComment?.classList.remove('active');
                submitButton.disabled = false;
            });
        });
    }
    
    function handleMenuToggle(button) {
        const container = button.closest('.context-menu-container, .more-actions-container');
        if (!container) return;

        const menu = container.querySelector('.context-menu-dropdown, .more-actions-dropdown');
        if (!menu) return;

        const isCurrentlyActive = menu.classList.contains('active');
        document.querySelectorAll('.context-menu-dropdown.active, .more-actions-dropdown.active')
            .forEach(m => m.classList.remove('active'));

        if (!isCurrentlyActive) {
            if (menu.classList.contains('context-menu-dropdown')) {
                const buttonRect = button.getBoundingClientRect();
                const viewportHeight = window.innerHeight;
                menu.classList.toggle('opens-upward', (buttonRect.bottom + 240 > viewportHeight));
            }
            menu.classList.add('active');
        }
    }

    if (page.forms.createPlaylist) {
        page.forms.createPlaylist.addEventListener('submit', handleCreatePlaylistSubmit);
        page.forms.createPlaylist.querySelector('input[name="title"]')?.addEventListener('input', (e) => clearFormError(e.target));
    }
    
    page.modals.addToPlaylist?.addEventListener('click', handleAddToPlaylistSelect);
    
    if (page.forms.report) {
        page.forms.report.addEventListener('submit', handleReportSubmit);
        page.forms.report.addEventListener('change', () => {
            const submitButton = page.forms.report.querySelector('.submit');
            if (submitButton) submitButton.disabled = false;
        });
    }

    function runAction(action, context) {
        const requiresAuth = ['toggle-watch-later', 'add-to-playlist', 'report', 'toggle-like', 'toggle-subscription', 'open-report-comment-modal'];
        
        if (requiresAuth.includes(action) && (typeof isUserLoggedIn === 'undefined' || !isUserLoggedIn)) {
            page.modals.loginRequired?.classList.add('active');
            return;
        }

        switch (action) {
            case 'toggle-like':
                handleToggleLike(context.actionTarget.dataset.type, context.actionTarget.dataset.video);
                break;
            case 'toggle-subscription':
                handleToggleSubscription(context.actionTarget.dataset.channelId);
                break;
            case 'toggle-watch-later':
                if (context.videoId) handleToggleWatchLater(context.videoId, context.actionTarget);
                break;
            case 'add-to-playlist':
                if (context.videoId) openAddToPlaylistModal(context.videoId);
                break;
            case 'share-video':
                if (context.videoId) openShareModal(context.videoId);
                break;
            case 'report':
                if (context.videoId) openReportModal(context.videoId);
                break;
            case 'copy-share-link':
                copyShareLink(context.actionTarget);
                break;
            case 'open-create-playlist-modal':
                openCreatePlaylistModal();
                break;
            case 'close-modal':
            case 'close-playlist-modal':
            case 'close-create-fly-modal':
            case 'close-report-modal':
            case 'close-report-comment-modal':
                context.actionTarget.closest('[class*="modal-overlay"]')?.classList.remove('active');
                break;
        }
    }

    // --- Обработчики API ---

    function handleToggleLike(type, videoId) {
        const body = new URLSearchParams({ type, video_id: videoId, csrf_token: csrfToken });
        fetch("/api/toggle_like", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    document.getElementById("like-count").textContent = data.likes;
                    document.getElementById("dislike-count").textContent = data.dislikes;
                    document.querySelector(".like-button")?.classList.toggle("active", data.user_like == 1);
                    document.querySelector(".dislike-button")?.classList.toggle("active", data.user_like == -1);
                } else {
                    showNotification(data.message || 'Произошла ошибка', false);
                }
            })
            .catch(() => showNotification("Ошибка сети", false));
    }

    function handleToggleSubscription(channelId) {
        const body = new URLSearchParams({ channel_id: channelId, csrf_token: csrfToken });
        fetch("/api/toggle_subscription", { method: "POST", headers: { "Content-Type": "application/x-www-form-urlencoded" }, body })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const subCountElem = document.getElementById("sub-count");
                    const subButton = document.getElementById("subscribe-btn");
                    if (subCountElem) subCountElem.textContent = `${data.subscriber_count} подписчиков`;
                    if (subButton) {
                        subButton.textContent = data.is_subscribed ? "Вы подписаны" : "Подписаться";
                        subButton.classList.toggle("subscribed", data.is_subscribed);
                    }
                    showNotification(data.is_subscribed ? 'Вы подписались' : 'Вы отписались');
                } else {
                    showNotification(data.message || 'Произошла ошибка', false);
                }
            })
            .catch(() => showNotification("Ошибка сети", false));
    }
    
    function handleToggleWatchLater(videoId, linkElement) {
        const body = new URLSearchParams({ video_id: videoId, csrf_token: csrfToken });
        fetch('/api/toggle_watch_later', { method: 'POST', body })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.is_added ? "Добавлено в 'Смотреть позже'" : "Удалено из 'Смотреть позже'");
                    const textSpan = linkElement.querySelector('span');
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
        if (typeof csrfToken !== 'undefined') formData.append('csrf_token', csrfToken);
        
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
            .catch(() => showNotification("Ошибка сети", false))
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
            .finally(() => page.modals.addToPlaylist?.classList.remove('active'));
    }

    function handleReportSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const submitButton = form.querySelector('.submit');
        submitButton.disabled = true;
        
        const formData = new FormData(form);
        if (typeof csrfToken !== 'undefined') formData.append('csrf_token', csrfToken);
        
        fetch('/api/report_video', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                showNotification(data.success ? 'Жалоба отправлена' : (data.message || 'Ошибка'), data.success);
            })
            .catch(() => showNotification('Ошибка сети', false))
            .finally(() => page.modals.report?.classList.remove('active'));
    }
    
    // --- Управление UI Модалок ---
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

    function openReportModal(videoId) {
        if (!page.modals.report || !page.forms.report) return;
        page.forms.report.reset();
        const videoIdInput = page.modals.report.querySelector('#report-video-id');
        if (videoIdInput) videoIdInput.value = videoId;
        
        const submitButton = page.modals.report.querySelector('.submit');
        if (submitButton) submitButton.disabled = true;
        
        page.modals.report.classList.add('active');
    }

    function copyShareLink(button) {
        const input = page.modals.share?.querySelector('.share-link-input');
        if (!input || !navigator.clipboard) return;
        
        navigator.clipboard.writeText(input.value).then(() => {
            const originalText = button.textContent;
            button.textContent = 'Скопировано!';
            setTimeout(() => { button.textContent = originalText; }, 2000);
        });
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
            const hiddenInput = selectContainer.querySelector('input[type="hidden"]');
            const valueSpan = selectContainer.querySelector('.custom-select-value');
            
            if (hiddenInput && valueSpan) {
                hiddenInput.value = option.dataset.value;
                valueSpan.textContent = option.textContent.trim();
            }
            selectContainer.classList.remove('open');
        }
    }

   // --- Логика комментариев ---
   const commentsSection = document.getElementById('comments-section');
   if (commentsSection) {
       initComments(commentsSection);
   }

   function initComments(section) {
       const videoId = section.dataset.videoId;
       const commentsList = document.getElementById('comments-list');
       if (!commentsList) return;
       
       const commentForm = document.getElementById('comment-form');
       const textInput = document.getElementById('comment-text-input');
       const actionsContainer = document.querySelector('.comment-form-actions');
       const cancelBtn = document.getElementById('cancel-comment-btn');
       const submitBtn = document.getElementById('submit-comment-btn');
       const inputWrapper = document.querySelector('.comment-input-wrapper');
       const sortControls = document.querySelector('.sort-controls');
       
       let currentSort = 'popular';
       
       fetchAndRenderComments(videoId, commentsList, currentSort);

       if (commentForm) {
           textInput.addEventListener('input', () => {
               textInput.style.height = 'auto';
               textInput.style.height = `${textInput.scrollHeight}px`;
               submitBtn.disabled = textInput.value.trim() === '';
           });
           
           textInput.addEventListener('focus', () => {
               actionsContainer.style.display = 'flex';
               inputWrapper.classList.add('focused');
           });
           
           cancelBtn.addEventListener('click', () => {
               textInput.value = '';
               submitBtn.disabled = true;
               actionsContainer.style.display = 'none';
               inputWrapper.classList.remove('focused');
               textInput.style.height = 'auto';
           });
           
           commentForm.addEventListener('submit', (e) => {
               e.preventDefault();
               handleCommentSubmit(textInput.value, videoId, null, () => {
                   cancelBtn.click(); 
                   fetchAndRenderComments(videoId, commentsList, currentSort); 
               });
           });
       }

       if (sortControls) {
           sortControls.addEventListener('click', (e) => {
               const button = e.target.closest('.sort-btn');
               if (!button || button.classList.contains('active')) return;
               
               sortControls.querySelector('.active')?.classList.remove('active');
               button.classList.add('active');
               currentSort = button.dataset.sort;
               fetchAndRenderComments(videoId, commentsList, currentSort);
           });
       }

       commentsList.addEventListener('click', (event) => {
           const target = event.target;
           const menuBtn = target.closest('.comment-menu-btn');
           
           if (menuBtn) {
               event.stopPropagation();
               const dropdown = menuBtn.nextElementSibling;
               document.querySelectorAll('.comment-dropdown.active').forEach(d => {
                   if (d !== dropdown) d.classList.remove('active');
               });
               dropdown?.classList.toggle('active');
               return;
           }

           const actionBtn = target.closest('[data-action]');
           if (!actionBtn) return;
           
           const action = actionBtn.dataset.action;
           const commentThread = actionBtn.closest('.comment-thread');
           const commentId = commentThread ? commentThread.dataset.id : null; 

           if (action === 'toggle-reply-form') {
               toggleReplyForm(actionBtn, commentId, videoId);
           } else if (action === 'open-report-comment-modal') {
               document.querySelectorAll('.comment-dropdown.active').forEach(d => d.classList.remove('active'));
               openReportCommentModal(commentId);
           } else if (action === 'toggle-comment-like') {
               handleCommentLike(commentId, actionBtn.dataset.type, commentThread);
           }
       });

       document.addEventListener('click', (e) => {
           if (!e.target.closest('.comment-actions-container')) {
               document.querySelectorAll('.comment-dropdown.active').forEach(d => d.classList.remove('active'));
           }
       });
   }

   function fetchAndRenderComments(videoId, container, sort = 'popular') {
       fetch(`/api/get_comments?video_id=${videoId}&sort=${sort}`)
       .then(res => res.json())
       .then(data => {
           if (data.success) {
               const commentsCount = document.getElementById('comments-count');
               if (commentsCount) commentsCount.textContent = data.comments.length;
               
               container.innerHTML = '';

               if (data.comments.length === 0) {
                   container.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">Пока здесь нет ни одного комментария.</p>';
                   return;
               }

               const parents = data.comments.filter(c => !c.parent_comment_id);
               const replies = data.comments.filter(c => c.parent_comment_id);

               if (sort === 'newest') {
                   parents.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
               } 

               parents.forEach(parent => {
                   container.innerHTML += renderComment(parent);
               });

               replies.forEach(reply => {
                   const parentContainer = document.getElementById(`replies-${reply.parent_comment_id}`);
                   if (parentContainer) parentContainer.innerHTML += renderComment(reply, true);
               });

               const urlParams = new URLSearchParams(window.location.search);
               const linkedCommentId = urlParams.get('lc');
               if (linkedCommentId) {
                   const targetComment = document.getElementById(`comment-${linkedCommentId}`);
                   if (targetComment) {
                       targetComment.scrollIntoView({ behavior: 'smooth', block: 'center' });
                       targetComment.classList.add('highlighted');
                       setTimeout(() => targetComment.classList.remove('highlighted'), 3000);
                   }
               }
           } else {
               container.innerHTML = '<p>Не удалось загрузить комментарии.</p>';
           }
       }).catch((e) => {
           console.error('Fetch comments error:', e);
           container.innerHTML = '<p>Ошибка при загрузке комментариев.</p>';
       });
   }

   function renderComment(comment, isReply = false) {
       const timeAgo = (date) => {
           const seconds = Math.floor((new Date() - new Date(date)) / 1000);
           if (seconds < 60) return "Только что";
           const minutes = Math.floor(seconds / 60);
           if (minutes < 60) return `${minutes} мин. назад`;
           const hours = Math.floor(minutes / 60);
           if (hours < 24) return `${hours} ч. назад`;
           const d = new Date(date);
           const months = ["янв.", "фев.", "мар.", "апр.", "мая", "июн.", "июл.", "авг.", "сен.", "окт.", "ноя.", "дек."];
           return `${d.getDate()} ${months[d.getMonth()]}`; 
       };
       
       const sanitizedText = comment.text.replace(/</g, "&lt;").replace(/>/g, "&gt;");
       const replyClass = isReply ? 'is-reply' : '';
       
       const likeActive = comment.user_action == 1 ? 'active' : '';
       const dislikeActive = comment.user_action == -1 ? 'active' : '';

       const likeCount = comment.like_count > 0 ? comment.like_count : '';
       const dislikeCount = comment.dislike_count > 0 ? comment.dislike_count : '';

       return `
       <div class="comment-thread ${replyClass}" id="comment-${comment.comment_id}" data-id="${comment.comment_id}">
           <a href="/profile.php?id=${comment.public_user_id}">
               <img src="/${comment.avatar_url || 'images/default_avatar.png'}" alt="Аватар" class="comment-avatar">
           </a>
           <div class="comment-main">
               <div class="comment-header">
                   <a href="/profile.php?id=${comment.public_user_id}" class="comment-author">@${comment.username}</a>
                   <span class="comment-meta">${timeAgo(comment.created_at)}</span>
               </div>
               <p class="comment-text">${sanitizedText}</p>
               
               <div class="comment-actions">
                   <button class="action-btn ${likeActive}" data-action="toggle-comment-like" data-type="like">
                       <img src="/images/like.png" alt="Like"> 
                       <span class="like-count-val">${likeCount}</span>
                   </button>
                   
                   <button class="action-btn ${dislikeActive}" data-action="toggle-comment-like" data-type="dislike">
                       <img src="/images/dislike.png" alt="Dislike">
                       <span class="dislike-count-val">${dislikeCount}</span>
                   </button>
                   
                   <button class="action-btn reply-btn-text" data-action="toggle-reply-form">Ответить</button>
                   
                   <div class="comment-actions-container" style="position: relative; margin-left: auto;">
                       <button class="action-btn comment-menu-btn">
                           <img src="/images/menu-history.png" alt="More" style="transform: rotate(90deg);">
                       </button>
                       <div class="comment-dropdown">
                           <div class="menu-item" data-action="open-report-comment-modal">
                               <img src="/images/complaint.png" alt="Report">
                               <span>Пожаловаться</span>
                           </div>
                       </div>
                   </div>
               </div>
               <div class="replies-list" id="replies-${comment.comment_id}"></div>
           </div>
       </div>`;
   }

   function toggleReplyForm(replyButton, parentId, videoId) {
       const currentForm = document.querySelector('.reply-form-container');
       if (currentForm) currentForm.remove();

       const commentMain = replyButton.closest('.comment-main');
       const formContainer = document.createElement('div');
       formContainer.className = 'reply-form-container';
       const userAvatar = document.querySelector('.comment-form-avatar')?.src || '/images/default_avatar.png';
       
       formContainer.innerHTML = `
           <img src="${userAvatar}" alt="Ваш аватар" class="comment-form-avatar" style="width:24px;height:24px;">
           <form class="comment-form reply-form" style="flex-grow:1;">
               <div class="comment-input-wrapper focused">
                   <textarea name="text" class="reply-input" placeholder="Оставьте ответ..." required></textarea>
               </div>
               <div class="reply-actions">
                   <button type="button" class="comment-action-btn cancel">Отмена</button>
                   <button type="submit" class="comment-action-btn submit" disabled>Ответить</button>
               </div>
           </form>`;
           
       const repliesList = commentMain.querySelector('.replies-list');
       commentMain.insertBefore(formContainer, repliesList);

       const newForm = formContainer.querySelector('.reply-form');
       const newTextInput = newForm.querySelector('textarea');
       const newSubmitBtn = newForm.querySelector('.submit');
       
       newTextInput.focus();
       newTextInput.addEventListener('input', () => {
           newTextInput.style.height = 'auto';
           newTextInput.style.height = `${newTextInput.scrollHeight}px`;
           newSubmitBtn.disabled = newTextInput.value.trim() === '';
       });
       
       newForm.querySelector('.cancel').addEventListener('click', () => formContainer.remove());
       
       newForm.addEventListener('submit', (e) => {
           e.preventDefault();
           handleCommentSubmit(newTextInput.value, videoId, parentId, () => {
               formContainer.remove();
               const sortControls = document.querySelector('.sort-controls .active');
               const currentSort = sortControls ? sortControls.dataset.sort : 'popular';
               fetchAndRenderComments(videoId, document.getElementById('comments-list'), currentSort);
           });
       });
   }

   function handleCommentSubmit(text, videoId, parentId = null, onSuccess) {
       const formData = new FormData();
       formData.append('video_id', videoId);
       formData.append('text', text.trim());
       if (typeof csrfToken !== 'undefined') formData.append('csrf_token', csrfToken); 
       if (parentId) formData.append('parent_comment_id', parentId);
       
       fetch('/api/add_comment', { method: 'POST', body: formData })
       .then(res => res.json())
       .then(data => {
           if (data.success) {
               showNotification(parentId ? 'Ответ отправлен' : 'Комментарий добавлен');
               onSuccess();
           } else {
               showNotification(data.message || 'Ошибка', false);
           }
       }).catch(() => showNotification('Ошибка сети', false));
   }

   function handleCommentLike(commentId, type, container) {
       const formData = new FormData();
       formData.append('comment_id', commentId);
       formData.append('type', type);
       if (typeof csrfToken !== 'undefined') formData.append('csrf_token', csrfToken);

       fetch('/api/toggle_comment_like', { method: 'POST', body: formData })
       .then(res => res.json())
       .then(data => {
           if (data.success) {
               const likeBtn = container.querySelector('[data-type="like"]');
               const dislikeBtn = container.querySelector('[data-type="dislike"]');
               
               if (likeBtn && dislikeBtn) {
                   likeBtn.querySelector('.like-count-val').textContent = data.likes > 0 ? data.likes : '';
                   dislikeBtn.querySelector('.dislike-count-val').textContent = data.dislikes > 0 ? data.dislikes : '';

                   likeBtn.classList.remove('active');
                   dislikeBtn.classList.remove('active');

                   if (data.user_action === 1) likeBtn.classList.add('active');
                   if (data.user_action === -1) dislikeBtn.classList.add('active');
               }
           } else {
               showNotification(data.message || 'Ошибка', false);
           }
       })
       .catch(err => console.error('Comment like error:', err));
   }

   function openReportCommentModal(commentId) {
       const modal = document.getElementById('report-comment-modal');
       if (modal) {
           const idInput = modal.querySelector('#report-comment-id');
           const form = modal.querySelector('form');
           const btn = modal.querySelector('.submit');
           
           if (idInput) idInput.value = commentId;
           if (form) form.reset();
           if (btn) btn.disabled = true;
           
           modal.classList.add('active');
       }
   }

    // --- Утилиты ---
    const leadingZeroFormatter = new Intl.NumberFormat(undefined, { minimumIntegerDigits: 2 });
    
    function formatDuration(time) {
        if (isNaN(time) || time < 0) return "0:00";
        const seconds = Math.floor(time % 60);
        const minutes = Math.floor(time / 60) % 60;
        const hours = Math.floor(time / 3600);
        
        if (hours === 0) {
            return `${minutes}:${leadingZeroFormatter.format(seconds)}`;
        }
        return `${hours}:${leadingZeroFormatter.format(minutes)}:${leadingZeroFormatter.format(seconds)}`;
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
        
        requestAnimationFrame(() => notification.classList.add('visible'));
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
        if (errorElement) errorElement.classList.remove('visible');
    }

    // --- Логика описания видео ---
    const descContainer = document.getElementById('video-description');
    const toggleBtn = document.getElementById('toggle-description-btn');

    if (descContainer && toggleBtn) {
        const textElement = descContainer.querySelector('.video-text-description');
        const textContent = textElement ? textElement.textContent.trim() : '';
        const charLimit = 60; 

        if (textContent.length > charLimit || textContent.includes('\n')) {
            toggleBtn.style.display = 'inline-block';
            
            toggleBtn.addEventListener('click', () => {
                const isCollapsed = descContainer.classList.contains('collapsed');
                
                if (isCollapsed) {
                    descContainer.classList.remove('collapsed');
                    descContainer.classList.add('expanded');
                    toggleBtn.textContent = 'Свернуть';
                } else {
                    descContainer.classList.remove('expanded');
                    descContainer.classList.add('collapsed');
                    toggleBtn.textContent = '...ещё';
                }
            });
        } else {
            descContainer.classList.remove('collapsed');
            toggleBtn.style.display = 'none';
        }
    }

});