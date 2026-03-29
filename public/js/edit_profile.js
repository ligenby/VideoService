(function() {
    'use strict'; 

    const ProfileEditorApp = {
        
        // --- Состояние и конфигурация ---

        state: {
            isSubmitting: false,
            recommendationSectionIndex: 0, 
        },

        // Кэш DOM-элементов для минимизации обращений к документу
        elements: {},

        // Карта для сопоставления доменов и иконок социальных сетей
        socialIconMap: {
            'youtube.com': '/images/social/youtube.png',
            'youtu.be': '/images/social/youtube.png',
            't.me': '/images/social/telegram.png',
            'telegram.me': '/images/social/telegram.png',
            'vk.com': '/images/social/vk.png',
            'vkontakte.ru': '/images/social/vk.png',
            'twitter.com': '/images/social/twitter.png',
            'x.com': '/images/social/twitter.png',
            'twitch.tv': '/images/social/twitch.png',
            'discord.gg': '/images/social/discord.png',
            'discord.com': '/images/social/discord.png',
            'instagram.com': '/images/social/instagram.png',
            'facebook.com': '/images/social/facebook.png',
            'tiktok.com': '/images/social/tiktok.png',
        },

        defaultIcon: '/images/social/link.png',
        sortableLinks: null,

        // --- Инициализация ---

        init() {
            this.cacheElements();
            
            if (!this.elements.form) {
                console.warn("Форма редактирования профиля не найдена. Инициализация прервана.");
                return;
            }

            this.bindEvents();
            this.updateAllCountersAndHeights();
            this.setupTooltips();
            this.showInitialNotifications();
            this.initRecommendations();
            this.initLinks();
        },

        cacheElements() {
            this.elements.form = document.querySelector('form[action="/edit_profile.php"]');
            if (!this.elements.form) return;

            this.elements.submitButton = this.elements.form.querySelector('.submit-button');
            this.elements.cancelButton = document.getElementById('cancel-changes-btn');
            this.elements.copyUrlButton = document.querySelector('.copy-url-button');
            
            this.elements.textInputs = this.elements.form.querySelectorAll('input[type="text"]:not(.channel-search-input), textarea, input[type="email"], input[type="url"]');
            this.elements.fileInputs = this.elements.form.querySelectorAll('input[type="file"]');
            
            this.elements.imagePreviews = this.elements.form.querySelectorAll('.image-preview');
            this.elements.liveBannerPreview = document.getElementById('live-banner-preview');
            this.elements.liveAvatarPreview = document.getElementById('live-avatar-preview');
            this.elements.liveChannelNamePreview = document.getElementById('live-channel-name-preview');
            this.elements.liveUsernamePreview = document.getElementById('live-username-preview');
            this.elements.previewToggleButtons = document.querySelectorAll('.preview-toggle-button');
            this.elements.livePreviewContainer = document.getElementById('live-preview-container');
            this.elements.scrollToPreviewLinks = document.querySelectorAll('.scroll-to-preview-link');
            
            this.elements.recommendationsContainer = document.getElementById('recommendations-container');
            this.elements.addSectionBtn = document.getElementById('add-recommendation-section-btn');
            this.elements.linksContainer = document.getElementById('links-container');
            this.elements.addLinkBtn = document.getElementById('add-link-btn');
            this.elements.infoIcons = document.querySelectorAll('.info-icon');
        },

        bindEvents() {
            // Базовые события формы
            this.elements.form.addEventListener('submit', this.handleFormSubmit.bind(this));
            this.elements.cancelButton.addEventListener('click', this.handleCancelChanges.bind(this));
            this.elements.copyUrlButton.addEventListener('click', this.handleCopyUrl.bind(this));
            
            // Динамическое обновление UI при вводе
            this.elements.textInputs.forEach(input => {
                input.addEventListener('input', () => {
                    this.updateCharCounter(input);
                    if (input.tagName === 'TEXTAREA') this.updateTextareaHeight(input);
                    this.updateLiveTextPreview(input);
                });
            });

            this.elements.fileInputs.forEach(input => {
                input.addEventListener('change', (e) => this.handleImagePreview(e.target));
            });
            
            // Управление блоком Live-предпросмотра
            this.elements.previewToggleButtons.forEach(button => {
                button.addEventListener('click', this.toggleLivePreview.bind(this));
            });
            this.elements.scrollToPreviewLinks.forEach(link => {
                link.addEventListener('click', this.handleScrollToPreview.bind(this));
            });
            
            // Инициализация событий для блока рекомендаций (используется делегирование)
            if (this.elements.addSectionBtn) {
                this.elements.addSectionBtn.addEventListener('click', () => this.addSection());
            }
            if (this.elements.recommendationsContainer) {
                this.elements.recommendationsContainer.addEventListener('click', this.handleRecommendationsClick.bind(this));
                this.elements.recommendationsContainer.addEventListener('input', this.handleRecommendationsInput.bind(this));
                this.elements.recommendationsContainer.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') e.preventDefault(); // Запрещаем сабмит формы при поиске каналов
                });
            }

            // Инициализация событий для блока ссылок
            if (this.elements.addLinkBtn) {
                this.elements.addLinkBtn.addEventListener('click', this.addLinkRow.bind(this));
            }
            if (this.elements.linksContainer) {
                this.elements.linksContainer.addEventListener('click', (e) => {
                    if (e.target.closest('.delete-link-btn')) {
                        this.removeLinkRow(e.target.closest('.link-row'));
                    }
                });
                
                this.elements.linksContainer.addEventListener('input', (e) => {
                    if (e.target.matches('input[type="url"]')) {
                        this.updateSocialIcon(e.target);
                    }
                });

                this.elements.linksContainer.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter') e.preventDefault();
                });
            }
        },

        // --- Обработчики событий ---

        handleFormSubmit(event) {
            event.preventDefault();
            if (this.state.isSubmitting) return;

            this.clearAllFieldErrors();
            let isFormValid = true;

            const baseErrors = this.validateForm();
            if (Object.keys(baseErrors).length > 0) {
                isFormValid = false;
                for (const fieldName in baseErrors) {
                    this.showFieldError(fieldName, baseErrors[fieldName]);
                }
            }

            if (!this.validateLinks()) {
                isFormValid = false;
            }

            // Если данные валидны, готовим массивы для PHP и отправляем
            if (isFormValid) {
                this.reindexLinks();
                this.setSubmittingState(true);
                this.elements.form.submit();
            }
        },

        handleCancelChanges() {
            if (this.state.isSubmitting) return;

            // Восстановление текстовых значений
            this.elements.form.querySelectorAll('[data-original-value]').forEach(field => {
                field.value = field.dataset.originalValue;
                this.updateLiveTextPreview(field);
            });

            // Восстановление изображений
            this.elements.imagePreviews.forEach(preview => {
                const originalImage = preview.dataset.originalImage;
                preview.style.backgroundImage = originalImage;
                
                if (preview.id === 'avatar-preview') {
                    const placeholder = preview.querySelector('.avatar-placeholder');
                    if (originalImage.includes('default_avatar.png')) {
                        preview.classList.add('has-placeholder');
                        if (placeholder) placeholder.style.display = 'flex';
                    } else {
                        preview.classList.remove('has-placeholder');
                        if (placeholder) placeholder.style.display = 'none';
                    }
                }
                
                const livePreview = preview.id.includes('banner') ? this.elements.liveBannerPreview : this.elements.liveAvatarPreview;
                if (livePreview) {
                    livePreview.style.backgroundImage = originalImage;
                }
            });

            this.elements.fileInputs.forEach(input => input.value = '');

            // Полный сброс динамических блоков
            if (this.elements.recommendationsContainer) this.elements.recommendationsContainer.innerHTML = '';
            if (this.elements.linksContainer) this.elements.linksContainer.innerHTML = '';
            
            this.state.recommendationSectionIndex = 0;
            this.initRecommendations();
            this.initLinks();

            this.updateAllCountersAndHeights();
            this.clearAllFieldErrors();
            this.showNotification("Изменения отменены");
        },

        handleCopyUrl() {
            const button = this.elements.copyUrlButton;
            if (button.disabled) return;

            navigator.clipboard.writeText(button.dataset.clipboardText).then(() => {
                this.showNotification('Ссылка скопирована');
                const originalContent = button.innerHTML;
                button.innerHTML = `<img src="/images/check.png" alt="OK">`;
                button.disabled = true;
                
                setTimeout(() => {
                    button.innerHTML = originalContent;
                    button.disabled = false;
                }, 2000);
            }).catch(() => this.showNotification('Ошибка копирования', 'error'));
        },

        handleImagePreview(input) {
            const file = input.files && input.files[0];
            if (!file) return;
        
            const reader = new FileReader();
            reader.onload = (e) => {
                const imageUrl = `url(${e.target.result})`;
                const isBanner = input.id === 'banner-input';
                
                if (isBanner) {
                    const formBannerPreview = document.getElementById('banner-preview');
                    if (formBannerPreview) {
                        formBannerPreview.style.backgroundImage = imageUrl;
                        formBannerPreview.classList.remove('has-placeholder');
                        
                        const placeholderIcon = formBannerPreview.querySelector('.preview-placeholder-icon');
                        if (placeholderIcon) placeholderIcon.style.display = 'none'; 
                    }
                    
                    if (this.elements.liveBannerPreview) {
                        this.elements.liveBannerPreview.style.backgroundImage = imageUrl;
                        this.elements.liveBannerPreview.classList.remove('has-placeholder');
                    }
                } else {
                    const formAvatarPreview = document.getElementById('avatar-preview');
                    if (formAvatarPreview) {
                        formAvatarPreview.style.backgroundImage = imageUrl;
                        formAvatarPreview.classList.remove('has-placeholder');
                        
                        const placeholder = formAvatarPreview.querySelector('.avatar-placeholder');
                        if (placeholder) placeholder.style.display = 'none'; 
                    }

                    if (this.elements.liveAvatarPreview) {
                        this.elements.liveAvatarPreview.style.backgroundImage = imageUrl;
                        this.elements.liveAvatarPreview.classList.remove('has-placeholder');
                    }
                }
            };
            reader.readAsDataURL(file);
        },

        // --- Управление предпросмотром ---

        handleScrollToPreview(event) {
            event.preventDefault();
            const previewContainer = this.elements.livePreviewContainer;
            if (!previewContainer) return;

            if (!previewContainer.classList.contains('visible')) {
                previewContainer.classList.add('visible');
                this.elements.previewToggleButtons.forEach(button => {
                    button.textContent = 'Скрыть предпросмотр';
                });
            }
            previewContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        },

        toggleLivePreview() {
            if (!this.elements.livePreviewContainer) return;
            const isVisible = this.elements.livePreviewContainer.classList.toggle('visible');
            this.elements.previewToggleButtons.forEach(button => {
                button.textContent = isVisible ? 'Скрыть предпросмотр' : 'Предпросмотр';
            });
        },

        updateLiveTextPreview(input) {
            if (input.name === 'channel_name' && this.elements.liveChannelNamePreview) {
                this.elements.liveChannelNamePreview.textContent = input.value || input.placeholder;
            }
            if (input.name === 'username' && this.elements.liveUsernamePreview) {
                this.elements.liveUsernamePreview.textContent = '@' + (input.value || input.placeholder.replace('Введите ', ''));
            }
        },

        // --- Логика секции рекомендаций ---

        initRecommendations() {
            if (typeof existingRecommendations !== 'undefined' && Array.isArray(existingRecommendations)) {
                existingRecommendations.forEach(section => {
                    this.addSection(section.title, section.channels);
                });
            }
        },

        addSection(title = '', channels = []) {
            if (this.elements.recommendationsContainer.children.length >= 3) return; 
            
            const index = this.state.recommendationSectionIndex;
            const sectionNode = document.createElement('div');
            sectionNode.innerHTML = this._createSectionHTML(index, title);
            const sectionElement = sectionNode.firstElementChild;
            
            const pillContainer = sectionElement.querySelector('.added-channels-list');
            if (Array.isArray(channels)) {
                channels.forEach(channel => {
                    const channelName = channel.channel_name || channel.username;
                    pillContainer.innerHTML += this._createPillHTML(channel.user_id, channelName, channel.avatar_url, index);
                });
            }

            this.elements.recommendationsContainer.appendChild(sectionElement);
            this.state.recommendationSectionIndex++;

            if (this.elements.recommendationsContainer.children.length >= 3) {
                this.elements.addSectionBtn.style.display = 'none';
            }
        },
        
        handleRecommendationsClick(e) {
            const deleteBtn = e.target.closest('.delete-section-btn');
            if (deleteBtn) {
                deleteBtn.closest('.recommendation-section-block').remove();
                this.elements.addSectionBtn.style.display = 'inline-flex';
            }

            const removePillBtn = e.target.closest('.remove-channel-btn');
            if (removePillBtn) {
                removePillBtn.closest('.added-channel-pill').remove();
            }

            const suggestion = e.target.closest('.suggestion-item');
            if (suggestion) {
                const sectionBlock = suggestion.closest('.recommendation-section-block');
                const pillContainer = sectionBlock.querySelector('.added-channels-list');
                
                // Ограничение на 10 каналов в одной секции
                if (pillContainer.children.length < 10) { 
                    const { id, name, avatar } = suggestion.dataset;
                    const sectionIdx = sectionBlock.dataset.index;
                    
                    if (!pillContainer.querySelector(`[data-id="${id}"]`)) {
                        pillContainer.innerHTML += this._createPillHTML(id, name, avatar, sectionIdx);
                    }
                }
                
                const searchInput = sectionBlock.querySelector('.channel-search-input');
                const suggestionsBox = sectionBlock.querySelector('.channel-suggestions');
                searchInput.value = '';
                suggestionsBox.style.display = 'none';
            }
        },

        async handleRecommendationsInput(e) {
            if (e.target.classList.contains('channel-search-input')) {
                const input = e.target;
                const suggestionsBox = input.nextElementSibling;
                const query = input.value.trim();

                if (query.length < 2) {
                    suggestionsBox.style.display = 'none';
                    return;
                }

                try {
                    const response = await fetch(`/api/search_channels?q=${encodeURIComponent(query)}`);
                    const channels = await response.json();
                    
                    suggestionsBox.innerHTML = '';
                    if (Array.isArray(channels) && channels.length > 0) {
                        channels.forEach(channel => {
                            suggestionsBox.innerHTML += this._createSuggestionHTML(channel);
                        });
                        suggestionsBox.style.display = 'block';
                    } else {
                        suggestionsBox.style.display = 'none';
                    }
                } catch (error) {
                    console.error("Ошибка при поиске каналов:", error);
                    suggestionsBox.style.display = 'none';
                }
            }
        },

        // --- Логика ссылок канала ---

        initLinks() {
            if (typeof existingLinks !== 'undefined' && Array.isArray(existingLinks)) {
                existingLinks.forEach(link => this.addLinkRow(link.link_title, link.link_url));
            }
            this.updateAddLinkButtonState();

            // Инициализация Drag-and-Drop для ссылок
            if (this.elements.linksContainer && typeof Sortable !== 'undefined') {
                this.sortableLinks = new Sortable(this.elements.linksContainer, {
                    animation: 150, 
                    handle: '.link-drag-handle',
                    ghostClass: 'sortable-ghost',
                    dragClass: 'sortable-drag',
                });
            }
        },

        addLinkRow(title = '', url = '') {
            // Предполагается, что MAX_LINKS объявлена глобально
            if (!this.elements.linksContainer || this.elements.linksContainer.children.length >= MAX_LINKS) return;
            
            const index = Date.now();
            const linkRow = document.createElement('div');
            linkRow.className = 'link-row';
            linkRow.innerHTML = this._createLinkRowHTML(index, title, url);
            this.elements.linksContainer.appendChild(linkRow);
            
            this.updateSocialIcon(linkRow.querySelector('input[type="url"]'));
            this.updateAddLinkButtonState();
        },

        removeLinkRow(rowElement) {
            rowElement.remove();
            this.updateAddLinkButtonState();
        },

        updateAddLinkButtonState() {
            if (!this.elements.addLinkBtn) return;
            this.elements.addLinkBtn.disabled = this.elements.linksContainer.children.length >= MAX_LINKS;
        },

        updateSocialIcon(urlInput) {
            const iconElement = urlInput.parentElement.querySelector('.social-icon-preview');
            if (iconElement) {
                const iconData = this._getSocialIcon(urlInput.value);
                
                if (iconElement.src !== iconData.src) {
                    iconElement.src = iconData.src;
                }
                
                if (iconData.isDefault) {
                    iconElement.classList.add('is-default-icon');
                } else {
                    iconElement.classList.remove('is-default-icon');
                }
            }
        },

        // --- Валидация ---

        validateForm() {
            const errors = {};
            const channelNameInput = this.elements.form.querySelector('[name="channel_name"]');
            const usernameInput = this.elements.form.querySelector('[name="username"]');

            if (channelNameInput && !channelNameInput.disabled) {
                const channelName = channelNameInput.value.trim();
                if (channelName === '') errors['channel_name'] = 'Название канала не может быть пустым.';
                else if (channelName.length < 3 || channelName.length > 50) errors['channel_name'] = 'Название канала должно быть от 3 до 50 символов.';
            }

            if (usernameInput && !usernameInput.disabled) {
                const username = usernameInput.value.trim();
                if (username === '') errors['username'] = 'Псевдоним не может быть пустым.';
                else if (username.length < 3 || username.length > 20) errors['username'] = 'Псевдоним должен быть от 3 до 20 символов.';
                else if (!/^[a-zA-Z0-9_]+$/.test(username)) errors['username'] = 'Псевдоним может содержать только латиницу, цифры и "_".';
            }
            return errors;
        },

        validateLinks() {
            let isValid = true;
            this.elements.linksContainer.querySelectorAll('.link-row').forEach(row => {
                const titleInput = row.querySelector('input[name*="[title]"]');
                const urlInput = row.querySelector('input[name*="[url]"]');
                const inputsContainer = row.querySelector('.link-inputs');
                inputsContainer.classList.remove('has-error');

                const titleValue = titleInput.value.trim();
                const urlValue = urlInput.value.trim();
                
                if ((titleValue !== '' && urlValue === '') || (titleValue === '' && urlValue !== '')) {
                    isValid = false;
                    inputsContainer.classList.add('has-error');
                } 
                else if (urlValue !== '' && !this._isValidUrl(urlValue)) {
                    isValid = false;
                    inputsContainer.classList.add('has-error');
                }
            });
            return isValid;
        },

        // --- UI и Обратная связь ---

        showFieldError(fieldName, message) {
            const field = this.elements.form.querySelector(`[name="${fieldName}"]`);
            if (!field) return;
            const formGroup = field.closest('.form-group');
            if (!formGroup) return;

            field.classList.add('has-error');
            
            let errorBubble = formGroup.querySelector('.input-error-bubble');
            if (!errorBubble) {
                errorBubble = document.createElement('div');
                errorBubble.className = 'input-error-bubble';
                const targetElement = formGroup.querySelector('.upload-info') || formGroup.querySelector('.input-with-icon') || field;
                targetElement.after(errorBubble);
            }
            errorBubble.textContent = message;
            setTimeout(() => errorBubble.classList.add('visible'), 10);
        },

        clearAllFieldErrors() {
            this.elements.form.querySelectorAll('.input-error-bubble').forEach(b => b.remove());
            this.elements.form.querySelectorAll('.has-error').forEach(el => el.classList.remove('has-error'));
        },

        setSubmittingState(isSubmitting) {
            this.state.isSubmitting = isSubmitting;
            this.elements.submitButton.disabled = isSubmitting;
            this.elements.cancelButton.disabled = isSubmitting;
            this.elements.submitButton.textContent = isSubmitting ? 'Сохранение...' : 'Сохранить';
        },

        showNotification(message, type = 'default') {
            const notification = document.createElement('div');
            notification.className = 'copy-toast-notification';
            notification.classList.add(type);
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => notification.classList.add('visible'), 10);
            setTimeout(() => {
                notification.classList.remove('visible');
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        },

        showInitialNotifications() {
            const notificationData = document.getElementById('php-notifications');
            if (notificationData) {
                try {
                    const messages = JSON.parse(notificationData.textContent);
                    messages.forEach(msg => {
                        if (msg.field) this.showFieldError(msg.field, msg.message);
                        else this.showNotification(msg.message, msg.type);
                    });
                } catch (e) { console.error("Ошибка парсинга PHP уведомлений:", e); }
            }
        },

        // --- Вспомогательные утилиты ---

        updateAllCountersAndHeights() {
            this.elements.textInputs.forEach(input => {
                this.updateCharCounter(input);
                if (input.tagName === 'TEXTAREA') this.updateTextareaHeight(input);
            });
        },
        
        updateCharCounter(input) {
            const counter = input.closest('.form-group')?.querySelector('.char-counter span');
            if (counter) counter.textContent = input.value.length;
        },

        updateTextareaHeight(textarea) {
            textarea.style.height = 'auto'; 
            textarea.style.height = `${textarea.scrollHeight}px`; 
        },

        setupTooltips() {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            document.body.appendChild(tooltip);

            this.elements.infoIcons.forEach(icon => {
                icon.addEventListener('mouseenter', () => {
                    const tooltipText = icon.dataset.tooltip;
                    if (!tooltipText) return;
                    
                    tooltip.textContent = tooltipText;
                    const iconRect = icon.getBoundingClientRect();
                    
                    tooltip.style.left = `${iconRect.left + (iconRect.width / 2) - (tooltip.offsetWidth / 2)}px`;
                    tooltip.style.top = `${window.scrollY + iconRect.top - tooltip.offsetHeight - 8}px`;
                    tooltip.classList.add('visible');
                });
                icon.addEventListener('mouseleave', () => tooltip.classList.remove('visible'));
            });
        },
        
        /**
         * Подготавливает индексы ссылок (0, 1, 2...) для корректного парсинга массива на стороне PHP.
         */
        reindexLinks() {
             this.elements.linksContainer.querySelectorAll('.link-row').forEach((row, newIndex) => {
                row.querySelector('input[name*="[title]"]').name = `links[${newIndex}][title]`;
                row.querySelector('input[name*="[url]"]').name = `links[${newIndex}][url]`;
            });
        },

        _isValidUrl(string) {
            let urlToTest = string.trim();
            
            if (urlToTest === '') return false;

            // Добавляем протокол для UX: пользователи часто вводят просто "vk.com" вместо "https://vk.com"
            if (!/^(?:f|ht)tps?:\/\//i.test(urlToTest)) {
                urlToTest = 'https://' + urlToTest;
            }

            try {
                new URL(urlToTest);
                return true;
            } catch (_) {
                return false;
            }
        },

        _escapeHTML(str) {
            if (typeof str !== 'string') return '';
            return str.replace(/[&<>'"]/g, tag => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            }[tag] || tag));
        },
        
        _getSocialIcon(url) {
            const cleanUrl = (url || '').trim();

            if (cleanUrl === '') {
                return { src: this.defaultIcon, isDefault: true };
            }
            
            for (const domain in this.socialIconMap) {
                if (cleanUrl.includes(domain)) {
                    return { src: this.socialIconMap[domain], isDefault: false };
                }
            }
            
            return { src: this.defaultIcon, isDefault: true };
        },

        // --- Генераторы HTML-разметки ---

        _createSectionHTML(index, title = '') {
            return `
                <div class="recommendation-section-block" data-index="${index}">
                    <div class="recommendation-section-header">
                        <input type="text" name="recommendations[${index}][title]" placeholder="Название секции (например, 'Любимые авторы')" required maxlength="100" value="${this._escapeHTML(title)}">
                        <button type="button" class="delete-section-btn" title="Удалить секцию">
                            <img src="/images/musor.png" alt="Удалить">
                        </button>
                    </div>
                    <div class="channel-search-wrapper">
                        <input type="text" class="channel-search-input" placeholder="Введите название или @логин канала..." autocomplete="off">
                        <div class="channel-suggestions" style="display: none;"></div>
                    </div>
                    <div class="added-channels-list"></div>
                </div>`;
        },

        _createSuggestionHTML(channel) {
            const name = this._escapeHTML(channel.channel_name || channel.username);
            const idForSaving = channel.user_id || channel.public_user_id;

            return `
                <div class="suggestion-item" data-id="${idForSaving}" data-name="${name}" data-avatar="${channel.avatar_url}">
                    <img src="/${channel.avatar_url}" alt="Аватар канала ${name}">
                    <div class="suggestion-details">
                        <span class="name">${name}</span>
                        <span class="username">@${channel.username}</span>
                    </div>
                </div>`;
        },
        
        _createPillHTML(id, name, avatar, sectionIdx) {
            return `
                <div class="added-channel-pill" data-id="${id}">
                    <input type="hidden" name="recommendations[${sectionIdx}][channels][]" value="${id}">
                    <img src="/${avatar}" alt="Аватар канала ${this._escapeHTML(name)}">
                    <span>${this._escapeHTML(name)}</span>
                    <button type="button" class="remove-channel-btn" title="Удалить канал из списка">
                        <img src="/images/close322.png" alt="Удалить" class="remove-icon-img">
                    </button>
                </div>`;
        },
        
        _createLinkRowHTML(index, title, url) {
            const iconData = this._getSocialIcon(url); 
            const defaultClass = iconData.isDefault ? 'is-default-icon' : ''; 

            return `
                <div class="link-drag-handle" title="Перетащить">
                    <img src="/images/drag_handle.png" alt="=">
                </div>
                <div class="link-inputs">
                    <div class="form-group-material">
                        <input type="text" id="link-title-${index}" name="links[${index}][title]" placeholder=" " value="${this._escapeHTML(title)}" maxlength="30">
                        <label for="link-title-${index}">Название ссылки</label>
                    </div>
                    <div class="form-group-material has-icon">
                        <img src="${iconData.src}" class="social-icon-preview ${defaultClass}" alt="Иконка ссылки">
                        <input type="url" id="link-url-${index}" name="links[${index}][url]" placeholder=" " value="${this._escapeHTML(url)}" required>
                        <label for="link-url-${index}">URL (обязательно)</label>
                    </div>
                </div>
                <button type="button" class="delete-link-btn" title="Удалить ссылку">
                    <img src="/images/musor.png" alt="Удалить">
                </button>
            `;
        }
    };
    
    document.addEventListener('DOMContentLoaded', () => ProfileEditorApp.init());

})();