document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('review-search');
    const tbody = document.getElementById('reviews-table-body');
    const spinner = document.getElementById('search-spinner');
    let searchTimeout = null;

    // поиск
    function loadReviews() {
        spinner.style.display = 'block';
        tbody.style.opacity = '0.5';

        const params = new URLSearchParams({
            ajax_search: 1,
            search: searchInput.value,
            status: document.getElementById('filter-status').value
        });

        fetch(`reviews.php?${params.toString()}`)
            .then(res => res.text())
            .then(html => {
                tbody.innerHTML = html;
                spinner.style.display = 'none';
                tbody.style.opacity = '1';
            })
            .catch(err => console.error(err));
    }


    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(loadReviews, 300);
    });

    // фильтр
    document.querySelectorAll('.custom-select-wrapper').forEach(wrapper => {
        const trigger = wrapper.querySelector('.custom-select-trigger');
        const options = wrapper.querySelectorAll('.custom-option');
        const hiddenInput = wrapper.querySelector('input[type="hidden"]');
        const triggerText = trigger.querySelector('span');

        trigger.addEventListener('click', (e) => { e.stopPropagation(); wrapper.classList.toggle('open'); });
        
        options.forEach(option => {
            option.addEventListener('click', (e) => {
                e.stopPropagation();
                options.forEach(opt => opt.classList.remove('selected'));
                option.classList.add('selected');
                triggerText.textContent = option.textContent;
                hiddenInput.value = option.getAttribute('data-value');
                wrapper.classList.remove('open');
                loadReviews();
            });
        });
    });

    window.addEventListener('click', () => document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open')));
    

    loadReviews();

    // смена статуса
    window.setStatus = function(id, action) {
        const formData = new FormData();
        formData.append('action', action);
        formData.append('review_id', id);

        fetch('reviews.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) loadReviews();
                else alert('Ошибка: ' + data.error);
            });
    };

    // ответ
    const replyModal = document.getElementById('replyModal');
    
    window.openReply = function(id) {
        document.getElementById('reply-review-id').value = id;
        document.getElementById('reply-review-id-display').textContent = id;
        document.getElementById('reply-text').value = '';
        replyModal.classList.add('active');
    };

    window.closeReplyModal = function() {
        replyModal.classList.remove('active');
    };

    document.getElementById('replyForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('reviews.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    closeReplyModal();
                    loadReviews();
                } else {
                    alert('Ошибка: ' + data.error);
                }
            });
    });

    // логика удаления
    const confirmModal = document.getElementById('confirmModal');
    let deleteId = 0;

    window.confirmDelete = function(id) {
        deleteId = id;
        confirmModal.classList.add('active');
    };

    window.closeConfirm = function() {
        confirmModal.classList.remove('active');
    };

    document.getElementById('btn-do-delete').addEventListener('click', function() {
        if(!deleteId) return;
        setStatus(deleteId, 'delete');
        closeConfirm();
    });
});