document.addEventListener('DOMContentLoaded', function() {
    
    const faqItems = document.querySelectorAll('.faq-item');
    const searchInput = document.getElementById('faq-search');
    const suggestionsBox = document.getElementById('search-suggestions');

    // Логика Аккордеона
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        const answer = item.querySelector('.faq-answer');
        
        question.addEventListener('click', () => {
            const isActive = item.classList.contains('active');
            
            faqItems.forEach(otherItem => {
                if (otherItem !== item) {
                    otherItem.classList.remove('active');
                    otherItem.querySelector('.faq-answer').style.maxHeight = null;
                }
            });

            if (isActive) {
                item.classList.remove('active');
                answer.style.maxHeight = null;
            } else {
                item.classList.add('active');
                answer.style.maxHeight = answer.scrollHeight + "px";
            }
        });
    });

    // Логика Поиска
    if (searchInput && suggestionsBox) {
        
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            suggestionsBox.innerHTML = ''; 
            
            if (query.length === 0) {
                suggestionsBox.classList.remove('active');
                return;
            }

            let hasMatch = false;

            faqItems.forEach(item => {
                const questionText = item.querySelector('.faq-question span').textContent;
                
                if (questionText.toLowerCase().includes(query)) {
                    hasMatch = true;
                    
                    const div = document.createElement('div');
                    div.className = 'suggestion-item';
                    
                    const iconImg = document.createElement('img');
                    iconImg.src = '/images/search.png';
                    iconImg.className = 'suggestion-icon-small';
                    
                    const textSpan = document.createElement('span');
                    const regex = new RegExp(`(${query})`, 'gi');
                    textSpan.innerHTML = questionText.replace(regex, '<span style="color: #3ea6ff;">$1</span>');

                    div.appendChild(iconImg);
                    div.appendChild(textSpan);
                    
                    div.addEventListener('click', () => {
                        activateQuestion(item);
                        suggestionsBox.classList.remove('active');
                        searchInput.value = '';
                    });

                    suggestionsBox.appendChild(div);
                }
            });

            if (hasMatch) {
                suggestionsBox.classList.add('active');
            } else {
                suggestionsBox.classList.remove('active');
            }
        });

        // Закрытие при клике вне
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-bar-container')) {
                suggestionsBox.classList.remove('active');
            }
        });
    }

    function activateQuestion(item) {
        faqItems.forEach(otherItem => {
            otherItem.classList.remove('active');
            otherItem.classList.remove('highlighted');
            otherItem.querySelector('.faq-answer').style.maxHeight = null;
        });

        const answer = item.querySelector('.faq-answer');
        item.classList.add('active');
        answer.style.maxHeight = answer.scrollHeight + "px";

        item.scrollIntoView({ behavior: 'smooth', block: 'center' });

        item.classList.add('highlighted');
        setTimeout(() => {
            item.classList.remove('highlighted');
        }, 2000);
    }
});