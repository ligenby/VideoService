document.addEventListener('DOMContentLoaded', () => {

    // --- Локальная фильтрация ---
    const filtersContainer = document.querySelector('.category-filters');
    if (filtersContainer) {
        const filterButtons = filtersContainer.querySelectorAll('.filter-item');
        const contentItems = document.querySelectorAll('.main-content .content-item');
        const placeholder = document.getElementById('main-page-placeholder');
        const placeholderTitle = document.getElementById('placeholder-title');
        const placeholderText = document.getElementById('placeholder-text');

        const filterContent = (filterType, filterText) => {
            let visibleItemCount = 0;
            const now = new Date();

            contentItems.forEach(item => {
                let show = false;
                const isVideo = item.classList.contains('video-item');
                const isPlaylist = item.classList.contains('playlist-item');

                switch (filterType) {
                    case 'video':
                        show = isVideo;
                        break;
                    case 'playlist':
                        show = isPlaylist;
                        break;
                    case 'recent':
                        if (isVideo && item.dataset.uploadDate) {
                            const uploadDate = new Date(item.dataset.uploadDate);
                            const hoursDifference = (now - uploadDate) / (1000 * 60 * 60);
                            show = hoursDifference < 12;
                        }
                        break;
                    default:
                        show = isVideo && item.classList.contains(`${filterType}-item`);
                        break;
                }
                
                item.style.display = show ? '' : 'none';
                if (show) visibleItemCount++;
            });

            if (visibleItemCount === 0 && placeholder) {
                if (placeholderTitle) placeholderTitle.textContent = `В категории "${filterText}" пока ничего нет`;
                if (placeholderText) placeholderText.textContent = 'Как только здесь появится контент, вы сразу его увидите.';
                placeholder.style.display = 'flex';
            } else if (placeholder) {
                placeholder.style.display = 'none';
            }
        };

        filterButtons.forEach(button => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                filterButtons.forEach(btn => btn.classList.remove('active'));
                
                const currentBtn = event.currentTarget;
                currentBtn.classList.add('active');
                
                filterContent(currentBtn.dataset.filter, currentBtn.textContent.trim());
            });
        });
        
        const activeFilter = filtersContainer.querySelector('.filter-item.active');
        if (activeFilter) {
            filterContent(activeFilter.dataset.filter, activeFilter.textContent.trim());
        }
    }

    // --- Переключение бокового меню ---
    const menuButton = document.querySelector('.menu-button');
    const pageContainer = document.querySelector('.page-container');
    
    if (menuButton && pageContainer) {
        menuButton.addEventListener('click', () => {
            pageContainer.classList.toggle('sidebar-collapsed');
        });
    }

    // --- Поиск и автодополнение ---
    const searchForm = document.getElementById('search-form');
    const searchInput = document.getElementById('search-input');
    const suggestionsContainer = document.getElementById('search-suggestions-container');
    const clearSearchBtn = document.getElementById('clear-search-btn');
    let searchTimeout; 

    if (searchForm && searchInput && suggestionsContainer) {
        
        const toggleClearButton = () => {
            if (clearSearchBtn) {
                clearSearchBtn.style.display = searchInput.value.length > 0 ? 'flex' : 'none';
            }
        };

        searchForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const query = searchInput.value.trim();
            
            if (query) {
                if (typeof isUserLoggedIn !== 'undefined' && isUserLoggedIn) {
                    const formData = new FormData();
                    formData.append('query', query);
                    if (typeof csrfToken !== 'undefined') formData.append('csrf_token', csrfToken);
                    
                    fetch('/api/log_search', { method: 'POST', body: formData }).catch(console.error);
                }
                window.location.href = `/results.php?search_query=${encodeURIComponent(query)}`;
            }
        });

        searchInput.addEventListener('input', (event) => {
            toggleClearButton();
            clearTimeout(searchTimeout); 
            
            const query = event.target.value.trim();

            if (query.length < 1) {
                suggestionsContainer.style.display = 'none';
                return;
            }

            suggestionsContainer.innerHTML = `<div class="search-loader"><div class="search-loader-spinner"></div></div>`;
            suggestionsContainer.style.display = 'block';

            searchTimeout = setTimeout(() => {
                fetch(`/api/search_suggestions?q=${encodeURIComponent(query)}`)
                    .then(response => response.ok ? response.json() : Promise.reject('Network error'))
                    .then(suggestions => {
                        suggestionsContainer.innerHTML = '';
                        if (suggestions?.length > 0) {
                            suggestions.forEach(suggestion => {
                                const link = document.createElement('a');
                                link.classList.add('suggestion-item');
                                link.href = `/results.php?search_query=${encodeURIComponent(suggestion.title)}`;
                                
                                let iconSrc = '/images/search.png';
                                let thumbnailHTML = '';
                                
                                switch(suggestion.type) {
                                    case 'history': 
                                        iconSrc = '/images/history.png'; 
                                        break;
                                    case 'channel': 
                                        if (suggestion.thumbnail) thumbnailHTML = `<img src="/${suggestion.thumbnail}" class="suggestion-thumbnail suggestion-thumbnail-channel">`; 
                                        break;
                                    case 'video': 
                                        if (suggestion.thumbnail) thumbnailHTML = `<img src="/${suggestion.thumbnail}" class="suggestion-thumbnail">`; 
                                        break;
                                }
                                
                                link.innerHTML = `<img src="${iconSrc}" class="suggestion-icon"><span class="suggestion-text">${suggestion.title}</span>${thumbnailHTML}`;
                                suggestionsContainer.appendChild(link);
                            });
                        } else {
                            suggestionsContainer.style.display = 'none';
                        }
                    }).catch(() => { 
                        suggestionsContainer.style.display = 'none'; 
                    });
            }, 300);
        });

        clearSearchBtn?.addEventListener('click', () => {
            searchInput.value = '';
            toggleClearButton();
            suggestionsContainer.style.display = 'none';
            searchInput.focus();
        });

        document.addEventListener('click', (event) => {
            if (!searchForm.contains(event.target)) {
                suggestionsContainer.style.display = 'none';
            }
        });

        searchInput.addEventListener('focus', () => {
            if (searchInput.value.length >= 1 && suggestionsContainer.innerHTML.trim() !== '') {
                suggestionsContainer.style.display = 'block';
            }
        });
        
        toggleClearButton();
    }

    // --- Модальное окно создания канала ---
    const channelModal = document.getElementById('create-channel-modal');
    if (channelModal) {
        const closeModalBtn = channelModal.querySelector('.modal-close-btn');
        const channelForm = document.getElementById('create-channel-form');
        const modalError = document.getElementById('modal-error');
        const avatarInput = document.getElementById('channel_avatar');
        const avatarPreview = document.getElementById('avatar-preview');
        const avatarIcon = document.getElementById('avatar-icon');
        
        const showModal = () => {
            if (typeof currentUserAvatarUrl !== 'undefined' && currentUserAvatarUrl) {
                avatarPreview.style.backgroundImage = `url('${currentUserAvatarUrl}')`;
                if (avatarIcon) avatarIcon.style.display = 'none';
            } else {
                avatarPreview.style.backgroundImage = '';
                if (avatarIcon) avatarIcon.style.display = 'block';
            }
            channelModal.classList.add('visible');
        };
        
        const hideModal = () => channelModal.classList.remove('visible');

        avatarInput?.addEventListener('change', (event) => {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    if (avatarPreview) avatarPreview.style.backgroundImage = `url('${e.target.result}')`;
                    if (avatarIcon) avatarIcon.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });

        document.querySelectorAll('.requires-channel').forEach(element => {
            element.addEventListener('click', (event) => {
                if (typeof isUserLoggedIn !== 'undefined' && isUserLoggedIn && 
                    (typeof userHasChannel === 'undefined' || !userHasChannel)) {
                    event.preventDefault();
                    showModal();
                }
            });
        });

        closeModalBtn?.addEventListener('click', hideModal);
        
        channelForm?.addEventListener('submit', (event) => {
            event.preventDefault(); 
            const submitButton = channelForm.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.textContent;
            
            submitButton.textContent = 'Создание...';
            submitButton.disabled = true;

            const formData = new FormData(channelForm);
            if (typeof csrfToken !== 'undefined') formData.append('csrf_token', csrfToken);

            fetch('/api/create_channel', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else if (modalError) {
                        modalError.textContent = data.message || 'Произошла ошибка';
                        modalError.style.display = 'block';
                    }
                })
                .catch(() => {
                    if (modalError) {
                        modalError.textContent = 'Произошла сетевая ошибка. Попробуйте позже.';
                        modalError.style.display = 'block';
                    }
                })
                .finally(() => {
                    submitButton.textContent = originalButtonText;
                    submitButton.disabled = false;
                });
        });
    }

    // --- Выпадающие меню в шапке ---
    document.querySelectorAll('.menu-trigger-btn').forEach(trigger => {
        const dropdown = trigger.nextElementSibling;
        if (!dropdown?.classList.contains('dropdown-menu')) return;

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            
            document.querySelectorAll('.dropdown-menu.active').forEach(activeMenu => {
                if (activeMenu !== dropdown) {
                    activeMenu.classList.remove('active', 'show-submenu');
                }
            });
            dropdown.classList.toggle('active');
        });

        dropdown.querySelector('.theme-menu-trigger')?.addEventListener('click', () => {
            dropdown.classList.add('show-submenu');
            const themeSubmenu = dropdown.querySelector('[data-submenu="theme"]');
            if (themeSubmenu) themeSubmenu.style.transform = 'translateX(0)';
        });

        dropdown.querySelector('.language-menu-trigger')?.addEventListener('click', () => {
            dropdown.classList.add('show-submenu');
            const langSubmenu = dropdown.querySelector('[data-submenu="language"]');
            if (langSubmenu) langSubmenu.style.transform = 'translateX(0)';
        });

        dropdown.querySelectorAll('.user-submenu-back-btn').forEach(button => {
            button.addEventListener('click', () => {
                dropdown.classList.remove('show-submenu');
                const parentSubmenu = button.closest('.user-submenu');
                if (parentSubmenu) parentSubmenu.style.transform = 'translateX(100%)';
            });
        });
    });

    document.addEventListener('click', (event) => {
        const activeDropdown = document.querySelector('.dropdown-menu.active');
        if (activeDropdown && !activeDropdown.contains(event.target) && !event.target.closest('.menu-trigger-btn')) {
            activeDropdown.classList.remove('active', 'show-submenu');
            activeDropdown.querySelectorAll('.user-submenu').forEach(submenu => {
                submenu.style.transform = 'translateX(100%)';
            });
        }
    });

    // --- Голосовой поиск ---
    const voiceSearchButton = document.querySelector('.voice-search-button');
    const voiceModal = document.getElementById('voice-search-modal');

    if (voiceSearchButton && voiceModal) {
        const closeVoiceModalBtn = document.getElementById('close-voice-modal');
        const voiceModalStatus = document.getElementById('voice-modal-status');
        const voiceInterimResults = document.getElementById('voice-interim-results');
        const micIconContainer = document.getElementById('voice-mic-icon-container');
        
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        let recognition;

        if (SpeechRecognition) {
            recognition = new SpeechRecognition();
            recognition.lang = 'ru-RU';
            recognition.continuous = false;
            recognition.interimResults = true;

            recognition.onstart = () => {
                micIconContainer.classList.add('listening');
                voiceInterimResults.textContent = '';
                voiceModalStatus.textContent = 'Распознавание...';
            };

            recognition.onresult = (event) => {
                let interimTranscript = '';
                let finalTranscript = '';

                for (let i = event.resultIndex; i < event.results.length; ++i) {
                    if (event.results[i].isFinal) {
                        finalTranscript += event.results[i][0].transcript;
                    } else {
                        interimTranscript += event.results[i][0].transcript;
                    }
                }
                
                voiceInterimResults.textContent = interimTranscript;

                if (finalTranscript) {
                    if (searchInput) searchInput.value = finalTranscript.trim();
                    if (clearSearchBtn) clearSearchBtn.style.display = 'flex';
                    
                    searchForm?.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
                    recognition.stop();
                }
            };

            recognition.onerror = (event) => {
                if (event.error === 'no-speech') {
                    voiceModalStatus.textContent = 'Ничего не расслышал. Попробуйте еще раз.';
                } else if (event.error === 'not-allowed') {
                    voiceModalStatus.textContent = 'Вы не разрешили доступ к микрофону.';
                } else {
                    voiceModalStatus.textContent = `Произошла ошибка: ${event.error}`;
                }
            };
            
            recognition.onend = () => {
                micIconContainer.classList.remove('listening');
                if (voiceModal.classList.contains('visible')) {
                    setTimeout(() => voiceModal.classList.remove('visible'), 800);
                }
            };
        }

        voiceSearchButton.addEventListener('click', () => {
            if (!SpeechRecognition) {
                alert('Извините, ваш браузер не поддерживает голосовой ввод.');
                return;
            }
            voiceModal.classList.add('visible');
            try {
                recognition.start();
            } catch (e) {
                voiceModalStatus.textContent = "Не удалось начать распознавание.";
            }
        });

        closeVoiceModalBtn?.addEventListener('click', () => {
            if (recognition) recognition.stop();
            voiceModal.classList.remove('visible');
        });
    }

    // --- Настройки темы оформления и языка ---
    const applyTheme = (theme) => {
        document.body.classList.remove('light-theme', 'dark-theme');
        let themeName = 'Как на устройстве';
        
        if (theme === 'light') {
            document.body.classList.add('light-theme');
            themeName = 'Светлая';
        } else if (theme === 'dark') {
            document.body.classList.add('dark-theme');
            themeName = 'Темная';
        } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            document.body.classList.add('dark-theme');
        }
        
        localStorage.setItem('theme', theme);
        
        document.querySelectorAll('.theme-menu-trigger span:not(.arrow-right)').forEach(span => {
            span.textContent = `Тема: ${themeName}`;
        });
        document.querySelectorAll('[data-theme]').forEach(opt => {
            opt.classList.toggle('active', opt.dataset.theme === theme);
        });
    };

    const setLanguage = (lang) => {
        localStorage.setItem('language', lang);
        let langName = 'Русский';
        
        document.querySelectorAll('[data-lang]').forEach(opt => {
            const isActive = opt.dataset.lang === lang;
            opt.classList.toggle('active', isActive);
            if (isActive) {
                const span = opt.querySelector('span');
                if (span) langName = span.textContent;
            }
        });
        
        document.querySelectorAll('.language-menu-trigger span:not(.arrow-right)').forEach(span => {
            span.textContent = `Язык: ${langName}`;
        });
        document.documentElement.lang = lang; 
    };

    // Применение настроек при инициализации
    applyTheme(localStorage.getItem('theme') || 'system');
    setLanguage(localStorage.getItem('language') || 'ru');

    document.querySelectorAll('[data-theme]').forEach(option => {
        option.addEventListener('click', () => applyTheme(option.dataset.theme));
    });
    
    document.querySelectorAll('[data-lang]').forEach(option => {
        option.addEventListener('click', () => setLanguage(option.dataset.lang));
    });

    // --- Уведомления ---
    const notifBtn = document.getElementById('notification-btn');
    const notifDropdown = document.getElementById('notification-dropdown');
    const notifList = document.getElementById('notification-list');
    const notifBadge = document.getElementById('notification-badge');
    const notifHeader = document.querySelector('.notification-header');

    if (notifBtn && notifDropdown) {
        
        updateNotificationCount();

        notifBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            document.querySelectorAll('.dropdown-menu').forEach(el => el.classList.remove('active'));
            
            if (!notifDropdown.classList.contains('active')) {
                notifDropdown.classList.add('active');
                loadNotifications(true);
            } else {
                notifDropdown.classList.remove('active');
            }
        });

        document.addEventListener('click', (e) => {
            if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                notifDropdown.classList.remove('active');
            }
        });

        if (notifHeader) {
            notifHeader.innerHTML = `
                <h3>Уведомления</h3>
                <div class="notif-actions">
                    <button class="notif-action-btn" id="mark-all-read" title="Пометить все как прочитанные">
                        <img src="/images/check_read.png" alt="Read All">
                    </button>
                    <a href="/other/settings.php?tab=notifications" class="notif-action-btn" title="Настройки уведомлений">
                        <img src="/images/settings.png" alt="Settings">
                    </a>
                </div>
            `;
            
            document.getElementById('mark-all-read')?.addEventListener('click', (e) => {
                e.stopPropagation();
                markAllAsRead();
            });
        }
    }

    function updateNotificationCount() {
        fetch('/api/get_notifications')
            .then(r => r.json())
            .then(data => updateBadgeUI(data.unread_count))
            .catch(console.error);
    }

    function updateBadgeUI(count) {
        if (!notifBadge) return;
        if (count > 0) {
            notifBadge.style.display = 'flex';
            notifBadge.textContent = count > 99 ? '99+' : count;
        } else {
            notifBadge.style.display = 'none';
        }
    }

    function loadNotifications(render = false) {
        fetch('/api/get_notifications')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    updateBadgeUI(data.unread_count);
                    if (render) renderNotificationList(data.notifications);
                }
            })
            .catch(() => {
                if (render && notifList) {
                    notifList.innerHTML = '<div class="notification-empty">Ошибка загрузки</div>';
                }
            });
    }

    function renderNotificationList(notifications) {
        if (!notifList) return;
        if (!notifications || notifications.length === 0) {
            notifList.innerHTML = '<div class="notification-empty">Нет новых уведомлений</div>';
            return;
        }

        let html = '';
        notifications.forEach(n => {
            const actor = n.actor_name || 'Пользователь';
            const avatar = n.actor_avatar ? `/${n.actor_avatar}` : '/images/default_avatar.png';
            const profileLink = `/profile.php?id=${n.actor_public_id || '0'}`; 
            const safeVideoTitle = n.video_title || 'видео'; 
            
            let mainText = '';
            let commentHtml = '';

            switch(n.type) {
                case 'subscription': 
                    mainText = `<a href="${profileLink}" class="notif-user-link" onclick="event.stopPropagation()">${actor}</a> подписался на ваш канал.`; 
                    break;
                case 'comment': 
                    mainText = `<a href="${profileLink}" class="notif-user-link" onclick="event.stopPropagation()">${actor}</a> прокомментировал: «${safeVideoTitle}»`;
                    if (n.comment_text) commentHtml = `<div class="notif-comment-preview">"${n.comment_text}"</div>`;
                    break;
                case 'reply':
                    mainText = `<a href="${profileLink}" class="notif-user-link" onclick="event.stopPropagation()">${actor}</a> ответил на ваш комментарий.`;
                    if (n.comment_text) commentHtml = `<div class="notif-comment-preview">"${n.comment_text}"</div>`;
                    break;
                case 'like': 
                    mainText = `<a href="${profileLink}" class="notif-user-link" onclick="event.stopPropagation()">${actor}</a> оценил видео «${safeVideoTitle}»`; 
                    break;
                case 'report_update':
                    mainText = `Статус вашей жалобы обновлен.`;
                    break;
            }

            html += `
                <div class="notification-item-container ${n.is_read == 0 ? 'unread' : ''}" 
                     onclick="handleNotificationClick(this, ${n.notification_id}, '${n.link_url}')">
                    
                    <a href="${profileLink}" class="notif-avatar-link" onclick="event.stopPropagation()">
                        <img src="${avatar}" class="notif-avatar" alt="">
                    </a>

                    <div class="notif-content">
                        <div>${mainText}</div>
                        ${commentHtml}
                        <span class="notif-time">${n.time_ago}</span>
                    </div>
                </div>
            `;
        });
        notifList.innerHTML = html;
    }

    window.handleNotificationClick = function(element, notifId, url) {
        element.classList.remove('unread');
        
        if (notifBadge) {
            let count = parseInt(notifBadge.textContent);
            if (!isNaN(count) && count > 0) {
                updateBadgeUI(count - 1);
            }
        }

        const formData = new FormData();
        formData.append('id', notifId);
        if (typeof csrfToken !== 'undefined') formData.append('csrf_token', csrfToken);
        
        fetch('/api/mark_one_read.php', { method: 'POST', body: formData }).catch(console.error);

        if (url && url !== '#' && url !== 'undefined') {
            window.location.href = url;
        }
    };

    function markAllAsRead() {
        const formData = new FormData();
        if (typeof csrfToken !== 'undefined') formData.append('csrf_token', csrfToken);

        fetch('/api/mark_notifications_read', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item-container.unread').forEach(el => el.classList.remove('unread'));
                    updateBadgeUI(0);
                }
            })
            .catch(console.error);
    }
});