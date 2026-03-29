document.addEventListener('DOMContentLoaded', () => {
    const filterButton = document.getElementById('filter-trigger-button');
    const filterMenu = document.getElementById('filter-dropdown-menu');
    const filterText = document.getElementById('filter-text');
    const complaintsList = document.getElementById('complaints-list');

    if (filterButton && filterMenu && filterText && complaintsList) {
        // Управление выпадающим списком фильтров
        filterButton.addEventListener('click', (e) => {
            e.stopPropagation();
            filterMenu.classList.toggle('active');
            filterButton.classList.toggle('active');
        });

        // Закрываем меню при клике в любом другом месте экрана
        document.addEventListener('click', () => {
            filterMenu.classList.remove('active');
            filterButton.classList.remove('active');
        });

        filterMenu.addEventListener('click', (e) => {
            e.preventDefault();
            const target = e.target.closest('.filter-option');
            if (!target) return;
            
            filterText.textContent = target.textContent;
            applyFilter(target.dataset.filter);
        });
    }

    if (complaintsList) {
        // Обработка кнопки "показать еще" для длинных текстов жалоб
        complaintsList.addEventListener('click', (e) => {
            if (e.target.classList.contains('show-more-btn')) {
                const button = e.target;
                const reasonBubble = button.previousElementSibling; 

                if (reasonBubble && reasonBubble.classList.contains('collapsible')) {
                    reasonBubble.classList.toggle('collapsed');
                    
                    // Меняем текст кнопки в зависимости от состояния блока
                    button.textContent = reasonBubble.classList.contains('collapsed') ? 'еще' : 'скрыть';
                }
            }
        });
    }
    
    // Применение фильтра по времени (скрытие/показ строк таблицы)
    function applyFilter(filter) {
        const now = new Date();
        
        // Вычисляем временные метки (в секундах) для разных периодов
        const oneWeekAgo = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 7).getTime() / 1000;
        const oneMonthAgo = new Date(now.getFullYear(), now.getMonth() - 1, now.getDate()).getTime() / 1000;
        const sixMonthsAgo = new Date(now.getFullYear(), now.getMonth() - 6, now.getDate()).getTime() / 1000;
        const oneYearAgo = new Date(now.getFullYear() - 1, now.getMonth(), now.getDate()).getTime() / 1000;
    
        const rows = complaintsList.querySelectorAll('.table-row');
        
        rows.forEach(row => {
            const timestamp = parseInt(row.dataset.timestamp, 10);
            let show = false;
    
            // Проверяем попадание даты создания жалобы в выбранный интервал
            if (filter === 'all') {
                show = true;
            } else if (filter === 'week' && timestamp >= oneWeekAgo) {
                show = true;
            } else if (filter === 'month' && timestamp >= oneMonthAgo) {
                show = true;
            } else if (filter === 'half_year' && timestamp >= sixMonthsAgo) {
                show = true;
            } else if (filter === 'year' && timestamp >= oneYearAgo) {
                show = true;
            }
    
            row.style.display = show ? 'flex' : 'none';
        });
    }
});