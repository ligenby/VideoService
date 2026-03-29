document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.auth-form form');

    if (form) {
        // Вспомогательные функции для работы с отображением ошибок
        const resetErrors = () => {
            form.querySelectorAll('.input-error-message').forEach(msg => {
                msg.textContent = '';
                msg.classList.remove('visible');
            });
            form.querySelectorAll('input.input-error').forEach(input => {
                input.classList.remove('input-error');
            });
        };

        const showError = (input, message) => {
            input.classList.add('input-error');
            const errorContainer = input.closest('.input-wrapper').querySelector('.input-error-message');
            if (errorContainer) {
                errorContainer.textContent = message;
                errorContainer.classList.add('visible');
            }
        };

        // Клиентская валидация перед отправкой формы
        form.addEventListener('submit', function(event) {
            resetErrors();
            let isFormValid = true;

            // Проверяем все обязательные поля на заполненность
            const requiredInputs = form.querySelectorAll('input[required]');
            
            requiredInputs.forEach(input => {
                if (input.value.trim() === '') {
                    isFormValid = false;
                    showError(input, 'Пожалуйста, заполните это поле.');
                }
            });

            // Прерываем отправку, если есть незаполненные поля.
            // Иначе форма отправится на сервер стандартным способом.
            if (!isFormValid) {
                event.preventDefault();
            }
        });

        // Логика для стилизации полей, заполненных браузером (автозаполнение)
        const checkAutofill = () => {
            form.querySelectorAll('input').forEach(input => {
                const wrapper = input.closest('.input-wrapper');
                if (!wrapper) return;

                if (input.matches(':-webkit-autofill') || input.matches(':autofill')) {
                    wrapper.classList.add('is-autofilled');
                } else {
                    wrapper.classList.remove('is-autofilled');
                }
            });
        };
        
        // Проверяем автозаполнение после инициализации DOM и при каждом вводе
        setTimeout(checkAutofill, 100);
        form.addEventListener('input', checkAutofill);
    }
});