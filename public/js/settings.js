document.addEventListener('DOMContentLoaded', () => {

    if (typeof csrfToken === 'undefined') return;

    // --- Переключение вкладок ---
    const navItems = document.querySelectorAll('.st-nav-item');
    const tabContents = document.querySelectorAll('.st-tab-content');
    
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = item.getAttribute('href').split('=')[1];
            
            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');
            
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === `${targetId}-tab`) {
                    content.classList.add('active');
                }
            });
            
            history.pushState(null, '', `?tab=${targetId}`);
        });
    });

    // --- Обработчики форм ---
    handleFormSubmit('account-form', 'account');
    handleToggleChange('notifications-form', 'notifications');
    handleToggleChange('privacy-form', 'privacy');

    // --- Смена пароля ---
    const passwordForm = document.getElementById('password-form');
    const passwordModal = document.getElementById('password-confirm-modal');
    
    if (passwordForm && passwordModal) {
        
        const showInputError = (inputId, message) => {
            const input = document.getElementById(inputId);
            const wrapper = input?.closest('.form-group-material');
            const errorDiv = wrapper?.querySelector('.input-error-message');
            
            if (wrapper && errorDiv) {
                wrapper.classList.add('has-error');
                errorDiv.textContent = message;
            }
        };

        const clearInputErrors = () => {
            passwordForm.querySelectorAll('.form-group-material').forEach(el => {
                el.classList.remove('has-error');
                const err = el.querySelector('.input-error-message');
                if (err) err.textContent = '';
            });
        };

        passwordForm.addEventListener('submit', (e) => {
            e.preventDefault();
            clearInputErrors(); 

            const currPass = document.getElementById('current_password').value.trim();
            const pass1 = document.getElementById('new_password').value;
            const pass2 = document.getElementById('confirm_new_password').value;
            let hasError = false;

            if (!currPass) {
                showInputError('current_password', 'Введите текущий пароль');
                hasError = true;
            }

            if (!pass1) {
                showInputError('new_password', 'Введите новый пароль');
                hasError = true;
            } else if (pass1.length < 8) {
                showInputError('new_password', 'Пароль должен быть не менее 8 символов');
                hasError = true;
            } else if (!/[A-Z]/.test(pass1) || !/[0-9]/.test(pass1)) {
                showInputError('new_password', 'Нужны цифры и заглавные буквы');
                hasError = true;
            }

            if (!pass2) {
                showInputError('confirm_new_password', 'Подтвердите пароль');
                hasError = true;
            } else if (pass1 !== pass2) {
                showInputError('confirm_new_password', 'Пароли не совпадают');
                hasError = true;
            }

            if (hasError) return; 

            passwordModal.classList.add('active');
        });

        passwordModal.querySelector('[data-action="cancel-password"]')?.addEventListener('click', () => {
            passwordModal.classList.remove('active');
        });

        passwordModal.querySelector('[data-action="confirm-password"]')?.addEventListener('click', async () => {
            const formData = new FormData(passwordForm);
            formData.append('csrf_token', csrfToken);
            passwordModal.classList.remove('active');
            
            const btn = passwordForm.querySelector('button[type="submit"]');
            const originalText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Сохранение...';

            await sendFormData('password', formData, (result) => {
                if (result.redirect) {
                    window.location.href = result.redirect;
                } else {
                    if (!result.success && result.message.includes('Текущий')) {
                        showInputError('current_password', result.message);
                    } else {
                        passwordForm.reset();
                    }
                }
            });
            
            btn.disabled = false;
            btn.textContent = originalText;
        });
        
        passwordForm.querySelectorAll('input').forEach(input => {
            input.addEventListener('input', () => {
                const wrapper = input.closest('.form-group-material');
                if (wrapper?.classList.contains('has-error')) {
                    wrapper.classList.remove('has-error');
                    const err = wrapper.querySelector('.input-error-message');
                    if (err) err.textContent = '';
                }
            });
        });
    }

    // --- Удаление аккаунта ---
    const deleteBtn = document.getElementById('init-delete-account-btn');
    const deleteModal1 = document.getElementById('delete-step1-modal');
    const deleteModal2 = document.getElementById('delete-step2-modal');
    const deleteForm = document.getElementById('final-delete-form');
    const captchaDisplay = document.getElementById('captcha-display');
    let generatedCaptcha = '';

    if (deleteBtn && deleteModal1 && deleteModal2 && deleteForm) {
        
        deleteBtn.addEventListener('click', () => deleteModal1.classList.add('active'));
        
        deleteModal1.querySelector('[data-action="cancel-delete"]')?.addEventListener('click', () => {
            deleteModal1.classList.remove('active');
        });

        deleteModal1.querySelector('[data-action="next-delete-step"]')?.addEventListener('click', () => {
            deleteModal1.classList.remove('active');
            generatedCaptcha = generateRandomString(10); 
            if (captchaDisplay) captchaDisplay.textContent = generatedCaptcha;
            deleteForm.reset();
            deleteModal2.classList.add('active');
        });

        deleteModal2.querySelector('[data-action="cancel-delete"]')?.addEventListener('click', () => {
            deleteModal2.classList.remove('active');
        });

        deleteForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const inputCaptcha = document.getElementById('delete_captcha')?.value;
            const inputPassword = document.getElementById('delete_password')?.value;

            if (inputCaptcha !== generatedCaptcha) {
                showNotification('Неверный код с картинки.', false);
                generatedCaptcha = generateRandomString(10);
                if (captchaDisplay) captchaDisplay.textContent = generatedCaptcha;
                
                const captchaInput = document.getElementById('delete_captcha');
                if (captchaInput) captchaInput.value = '';
                return;
            }

            const formData = new FormData();
            formData.append('password_confirmation', inputPassword);
            formData.append('csrf_token', csrfToken);

            await sendFormData('delete_account', formData, (result) => {
                if (result.success && result.redirect) {
                    window.location.href = result.redirect;
                }
            });
        });
    }

    // --- Вспомогательные функции ---

    function generateRandomString(length) {
        const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'; 
        let result = '';
        for (let i = 0; i < length; i++) {
            result += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        return result;
    }

    function handleFormSubmit(formId, action) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            
            submitButton.disabled = true;
            submitButton.textContent = 'Сохранение...';
            
            const formData = new FormData(form);
            formData.append('csrf_token', csrfToken);

            await sendFormData(action, formData);
            
            submitButton.disabled = false;
            submitButton.textContent = originalText;
        });
    }

    function handleToggleChange(formId, action) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        form.addEventListener('change', async () => {
             const formData = new FormData(form);
             formData.append('csrf_token', csrfToken);
             await sendFormData(action, formData);
        });
    }

    async function sendFormData(action, formData, successCallback = null) {
        try {
            const response = await fetch(`/api/update_settings?action=${action}`, { 
                method: 'POST', 
                body: formData 
            });
            const result = await response.json();
            
            showNotification(result.message, result.success);
            
            if (result.success && successCallback) {
                successCallback(result);
            }
            return result;
        } catch (error) {
            console.error('API Error:', error);
            showNotification('Ошибка сети.', false);
            return { success: false };
        }
    }
    
    function showNotification(message, isSuccess = true) {
        let n = document.querySelector('.notification');
        if (!n) { 
            n = document.createElement('div'); 
            n.className = 'notification'; 
            document.body.appendChild(n); 
        }
        
        n.textContent = message;
        n.className = `notification ${isSuccess ? 'success' : 'error'}`;
        
        // Перезапуск CSS анимации
        n.classList.remove('visible');
        void n.offsetWidth; 
        n.classList.add('visible');
        
        setTimeout(() => n.classList.remove('visible'), 4000);
    }
});