document.addEventListener('DOMContentLoaded', () => {

    // Инициализация компонентов страницы отзывов
    initTabs();
    initSorting();
    initEditModal();
    initReactions();
    initNewReviewForm();
    initCharCounters(); 
    initTextToggles();

    function initTextToggles() {
        const myReviewsSection = document.getElementById('my-reviews');
        if (!myReviewsSection) return;

        myReviewsSection.addEventListener('click', (event) => {
            if (!event.target.classList.contains('text-toggle-btn')) return;

            const btn = event.target;
            const container = btn.closest('.review-detail-value');
            const hiddenText = container.querySelector('.text-rest');

            if (!hiddenText) return;

            const isVisible = hiddenText.classList.contains('visible');
            hiddenText.classList.toggle('visible', !isVisible);
            btn.textContent = isVisible ? '... еще' : 'скрыть';
        });
    }

    function setupCharCounter(inputId, counterId) {
        const input = document.getElementById(inputId);
        const counter = document.getElementById(counterId);

        if (!input || !counter) return;

        const maxLen = parseInt(input.getAttribute('maxlength'), 10) || 0;
        if (!maxLen) return;

        const update = () => {
            const currentLen = input.value.length;
            counter.textContent = `${currentLen}/${maxLen}`;
            counter.classList.toggle('limit-reached', currentLen >= maxLen);
        };

        input.addEventListener('input', update);
        update();
        
        // Кэшируем метод в DOM-узле для принудительного обновления извне (например, при открытии модалки)
        input.updateCounter = update; 
    }

    function initCharCounters() {
        setupCharCounter('subject', 'counter-subject');
        setupCharCounter('content', 'counter-content');
    }

    function initNewReviewForm() {
        const formContainer = document.querySelector('.review-form-container');
        const reviewForm = document.getElementById('review-form');
        if (!formContainer && !reviewForm) return;
        
        const step1 = document.getElementById('review-step-1');
        const step2 = document.getElementById('review-step-2');
        const successStep = document.getElementById('review-success-step');
        const nextStepBtn = document.getElementById('next-step-btn');

        // Валидация рейтинга (Шаг 1)
        if (nextStepBtn && step1 && step2) {
            nextStepBtn.addEventListener('click', () => {
                const selectedRating = formContainer.querySelector('input[name="rating"]:checked');
                const ratingError = document.getElementById('rating-error');
                const ratingValueInput = document.getElementById('rating-value');

                if (selectedRating) {
                    if (ratingError) ratingError.style.display = 'none';
                    if (ratingValueInput) ratingValueInput.value = selectedRating.value;
                    
                    step1.classList.remove('active');
                    step2.classList.add('active');
                } else if (ratingError) {
                    ratingError.textContent = 'Пожалуйста, поставьте оценку от 1 до 5.';
                    ratingError.style.display = 'block';
                }
            });
        }
        
        // Обработка текстовых полей и отправка (Шаг 2)
        if (reviewForm) {
            // Сброс состояния ошибок при вводе
            reviewForm.querySelectorAll('input, textarea').forEach(input => {
                input.addEventListener('input', function() {
                    this.classList.remove('input-error');
                    const errorMsg = this.parentElement.querySelector('.validation-message');
                    if (errorMsg) errorMsg.classList.remove('active');
                });
            });

            reviewForm.addEventListener('submit', (event) => {
                event.preventDefault();
                
                const subjectInput = document.getElementById('subject');
                const contentInput = document.getElementById('content');
                const submitBtn = document.getElementById('submit-review-btn');
                
                let isValid = true;

                if (!subjectInput.value.trim()) {
                    subjectInput.classList.add('input-error');
                    document.getElementById('error-subject')?.classList.add('active');
                    isValid = false;
                }

                if (!contentInput.value.trim()) {
                    contentInput.classList.add('input-error');
                    document.getElementById('error-content')?.classList.add('active');
                    isValid = false;
                }
                
                if (!isValid) return;

                submitBtn.disabled = true;
                submitBtn.classList.add('is-loading');
                submitBtn.innerHTML = '<span class="btn-loader"></span>Отправка...';
                
                const formData = new FormData(reviewForm);
                if (typeof csrfToken !== 'undefined') {
                    formData.append('csrf_token', csrfToken);
                }

                fetch('/api/index.php?endpoint=handle_review', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && step2 && successStep) {
                            step2.classList.remove('active');
                            successStep.innerHTML = `
                                <div class="review-success-screen">
                                    <div class="success-icon" style="font-size: 48px; color: #66bb6a; margin-bottom: 16px;">&#10004;</div>
                                    <h2>Спасибо, ваш отзыв отправлен!</h2>
                                    <p>Он появится в общем списке после проверки модератором.</p>
                                    <div class="form-actions" style="margin-top: 24px;">
                                        <a href="/other/reviews.php" class="btn btn-secondary">Посмотреть мои отзывы</a>
                                    </div>
                                </div>`;
                            successStep.classList.add('active');
                        } else {
                            // TODO: заменить на UI-нотификации
                            alert(data.message || 'Произошла ошибка при отправке.');
                            resetSubmitButton(submitBtn);
                        }
                    })
                    .catch((err) => {
                        console.error('Submit review error:', err);
                        alert('Ошибка сети. Пожалуйста, попробуйте снова.');
                        resetSubmitButton(submitBtn);
                    });
            });
        }

        function resetSubmitButton(btn) {
            btn.disabled = false;
            btn.classList.remove('is-loading');
            btn.textContent = 'Отправить';
        }
    }

    function initEditModal() {
        const myReviewsSection = document.getElementById('my-reviews');
        const editModal = document.getElementById('edit-review-modal');
        if (!myReviewsSection || !editModal) return;

        const editForm = document.getElementById('edit-review-form');
        const editContentTextarea = editModal.querySelector('#edit-content');
        const editSubjectInput = editModal.querySelector('#edit-subject');

        setupCharCounter('edit-subject', 'counter-edit-subject');
        setupCharCounter('edit-content', 'counter-edit-content');

        const autoResizeTextarea = (el) => {
            el.style.height = 'auto';
            el.style.height = el.scrollHeight + 'px';
        };

        if (editContentTextarea) {
            editContentTextarea.addEventListener('input', function() {
                autoResizeTextarea(this);
            });
        }

        myReviewsSection.addEventListener('click', (event) => {
            const editButton = event.target.closest('.edit-review-btn');
            if (!editButton) return;

            const card = editButton.closest('.my-review-card');
            editModal.querySelector('#edit-review-id').value = card.dataset.reviewId;

            const subjectEl = card.querySelector('.my-review-card-subject');
            if (subjectEl) {
                editSubjectInput.value = subjectEl.textContent.trim();
            }

            // Сборка полного текста из усеченной и скрытой частей
            const visiblePart = card.querySelector('.review-detail-value.body .text-visible');
            const hiddenPart = card.querySelector('.review-detail-value.body .text-rest');
            
            let fullContent = '';
            if (visiblePart) fullContent += visiblePart.innerText;
            if (hiddenPart) fullContent += hiddenPart.innerText;
            
            if (!visiblePart && !hiddenPart) {
                const bodyEl = card.querySelector('.review-detail-value.body');
                if (bodyEl) fullContent = bodyEl.innerText;
            }

            editContentTextarea.value = fullContent.trim();
            editModal.style.display = 'flex';

            // Даем DOM время отрисоваться перед пересчетом высоты и счетчиков
            setTimeout(() => {
                autoResizeTextarea(editContentTextarea);
                editSubjectInput.updateCounter?.();
                editContentTextarea.updateCounter?.();
            }, 0);
        });

        editModal.addEventListener('click', (event) => {
            if (event.target === editModal || event.target.closest('.review-modal-close')) {
                editModal.style.display = 'none';
            }
        });

        if (editForm) {
            editForm.addEventListener('submit', (event) => {
                event.preventDefault();
                const btn = event.target.querySelector('button[type="submit"]');
                btn.disabled = true;
                btn.textContent = 'Сохранение...';
                
                const formData = new FormData(event.target);
                if (typeof csrfToken !== 'undefined') formData.append('csrf_token', csrfToken);
                
                fetch('/api/index.php?endpoint=update_review', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload(); 
                        } else {
                            alert(data.message);
                            btn.disabled = false;
                            btn.textContent = 'Сохранить';
                        }
                    })
                    .catch(err => {
                        console.error('Update review error:', err);
                        alert('Ошибка сети.');
                        btn.disabled = false;
                        btn.textContent = 'Сохранить';
                    });
            });
        }
    }

    function initTabs() {
        const tabButtons = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        if (!tabButtons.length || !tabContents.length) return;

        const switchTab = (targetTabId) => {
            tabContents.forEach(c => c.classList.remove('active'));
            tabButtons.forEach(b => b.classList.remove('active'));

            document.getElementById(targetTabId)?.classList.add('active');
            document.querySelector(`.tab-btn[data-tab="${targetTabId}"]`)?.classList.add('active');
        };

        tabButtons.forEach(button => {
            button.addEventListener('click', () => switchTab(button.dataset.tab));
        });

        // Глобальный перехватчик для ссылок, ведущих на вкладки
        document.body.addEventListener('click', (event) => {
            const linkToTab = event.target.closest('.link-to-tab');
            if (linkToTab) {
                event.preventDefault();
                switchTab(linkToTab.dataset.tab);
            }
        });
    }

    function initSorting() {
        const sortContainer = document.querySelector('.sort-menu-container');
        const sortButton = sortContainer?.querySelector('.sort-menu-button');
        
        if (!sortContainer || !sortButton) return;
        
        sortButton.addEventListener('click', (event) => {
            event.stopPropagation();
            sortContainer.classList.toggle('open');
        });

        document.addEventListener('click', (event) => {
            if (!sortContainer.contains(event.target)) {
                sortContainer.classList.remove('open');
            }
        });
    }

    function initReactions() {
        const publicReviewsSection = document.getElementById('public-reviews');
        if (!publicReviewsSection) return;

        publicReviewsSection.addEventListener('click', (event) => {
            const reactionBtn = event.target.closest('.reaction-btn');
            if (!reactionBtn) return;

            const wasDisabled = reactionBtn.disabled;
            const card = reactionBtn.closest('.review-card');
            
            const formData = new FormData();
            formData.append('review_id', card.dataset.reviewId);
            formData.append('reaction_type', reactionBtn.dataset.reaction);
            if (typeof csrfToken !== 'undefined') formData.append('csrf_token', csrfToken);

            fetch('/api/index.php?endpoint=rate_review', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const helpfulBtn = card.querySelector('.reaction-btn.helpful');
                        const unhelpfulBtn = card.querySelector('.reaction-btn.unhelpful');
                        
                        helpfulBtn.querySelector('.count').textContent = data.data.helpful_count;
                        unhelpfulBtn.querySelector('.count').textContent = data.data.unhelpful_count;
                        
                        helpfulBtn.disabled = false;
                        unhelpfulBtn.disabled = false;
                        
                        if (!wasDisabled) {
                            reactionBtn.disabled = true;
                        }
                    } else {
                        alert(data.message);
                    }
                })
                .catch(err => {
                    console.error('Reaction error:', err);
                    alert('Ошибка сети при отправке реакции.');
                });
        });
    }
});