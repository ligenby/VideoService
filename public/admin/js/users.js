document.addEventListener('DOMContentLoaded', function() {
    // Элементы
    const input = document.getElementById('live-search-input');
    const tbody = document.getElementById('users-table-body');
    const spinner = document.getElementById('search-spinner');
    const clearBtn = document.getElementById('search-clear');
    const modal = document.getElementById('user-details-modal');
    const modalContent = document.getElementById('modal-body-content');
    const closeModal = document.querySelector('.close-modal');
    
    let timeout = null;

    // === ПОИСК ===
    function performSearch(query) {
        spinner.style.display = 'block';
        tbody.style.opacity = '0.5';

        fetch('users.php?ajax_search=1&search=' + encodeURIComponent(query))
            .then(response => response.text())
            .then(html => {
                tbody.innerHTML = html;
                tbody.style.opacity = '1';
                spinner.style.display = 'none';
            })
            .catch(err => {
                console.error('Error:', err);
                tbody.style.opacity = '1';
                spinner.style.display = 'none';
            });
    }

    input.addEventListener('input', function() {
        const query = this.value.trim();
        clearBtn.style.display = query.length > 0 ? 'flex' : 'none';
        clearTimeout(timeout);
        timeout = setTimeout(() => performSearch(query), 300);
    });

    clearBtn.addEventListener('click', function() {
        input.value = '';
        this.style.display = 'none';
        clearTimeout(timeout);
        performSearch('');
        input.focus();
    });

    // === МОДАЛЬНОЕ ОКНО ===
    tbody.addEventListener('click', function(e) {
        if (e.target.classList.contains('view-details-btn')) {
            const userId = e.target.getAttribute('data-id');
            openModal(userId);
        }
    });

    function openModal(userId) {
        modal.classList.add('active');
        modalContent.innerHTML = '<div style="text-align:center; padding:40px;"><div class="spinner-border" style="position:relative;display:inline-block;"></div></div>';

        fetch(`users.php?get_user_details=1&user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if(data.error) {
                    modalContent.innerHTML = `<p style="color:var(--danger); text-align:center;">Ошибка: ${data.error}</p>`;
                } else {
                    renderModalContent(data);
                }
            })
            .catch(err => {
                console.error(err);
                modalContent.innerHTML = '<p style="color:var(--danger); text-align:center;">Ошибка соединения</p>';
            });
    }

    function renderModalContent(user) {
        // 1. ПЕРЕВОД СТАТУСОВ
        const statusMap = {
            'active': 'Активен',
            'banned': 'Забанен',
            'deleted': 'Удален',
            'pending': 'Ожидает'
        };
        const statusText = statusMap[user.status] || user.status;

        // 2. ОБРАБОТКА ССЫЛОК (ИСПРАВЛЕНИЕ http)
        let linksHtml = '';
        if (user.links && user.links.length > 0) {
            linksHtml = '<div class="profile-links"><strong>Ссылки:</strong><div class="links-list">';
            user.links.forEach(link => {
                let url = escapeHtml(link.link_url);
                if (!url.match(/^https?:\/\//)) {
                    url = 'https://' + url;
                }
                
                linksHtml += `<a href="${url}" target="_blank" class="user-link-item">${escapeHtml(link.link_title)}</a>`;
            });
            linksHtml += '</div></div>';
        } else {
            linksHtml = '<div class="profile-links text-muted">Нет привязанных ссылок</div>';
        }

        // 3. Обработка описания
        let aboutText = escapeHtml(user.about_text || '');
        let aboutHtml = '';
        const maxLength = 150; 

        if (aboutText.length > maxLength) {
            aboutHtml = `
                <div class="description-container collapsed" id="desc-container">
                    <p class="desc-text">${aboutText}</p>
                </div>
                <button class="expand-desc-btn" id="expand-btn">Читать полностью</button>
            `;
        } else {
            aboutHtml = `<p class="desc-text">${aboutText || 'Описание отсутствует'}</p>`;
        }

        // 4. Сборка HTML
        const bannerUrl = user.banner_image_url ? `/${user.banner_image_url}` : 'assets/img/default_banner.jpg';

        const html = `
            <div class="modal-banner-top" style="background-image: url('${bannerUrl}');"></div>
            
            <div class="user-profile-header relative-header">
                <img src="/${user.avatar_url}" alt="Avatar" class="profile-avatar-large">
                <div class="profile-info-main">
                    <h3>${escapeHtml(user.channel_name || user.username)}</h3>
                    <div class="sub-info">
                        <span class="text-muted">@${escapeHtml(user.username)}</span>
                        <span class="badge ${user.status}">${statusText}</span>
                    </div>
                </div>
            </div>
            
            <div class="profile-stats-grid">
                <div class="stat-box">
                    <span class="stat-label">Подписчиков</span>
                    <span class="stat-value">${user.subscriber_count}</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Видео</span>
                    <span class="stat-value">${user.video_count}</span>
                </div>
                <div class="stat-box">
                    <span class="stat-label">Роль</span>
                    <span class="stat-value">${user.role}</span>
                </div>
            </div>

            <div class="profile-section">
                <h4>Информация</h4>
                <div class="detail-row"><strong>ID:</strong> <span>${user.user_id}</span></div>
                <div class="detail-row"><strong>Email:</strong> <span class="text-selectable">${escapeHtml(user.email)}</span></div>
                <div class="detail-row"><strong>Регистрация:</strong> <span>${user.formatted_date}</span></div>
                <div class="detail-row"><strong>История:</strong> <span>${user.history_enabled ? 'Включена' : 'Выключена'}</span></div>
            </div>

            <div class="profile-section">
                <h4>О канале</h4>
                <div class="profile-about-box">
                    ${aboutHtml}
                </div>
            </div>

            <div class="profile-section">
                ${linksHtml}
            </div>
        `;
        modalContent.innerHTML = html;

        const expandBtn = document.getElementById('expand-btn');
        if (expandBtn) {
            expandBtn.addEventListener('click', function() {
                const container = document.getElementById('desc-container');
                if (container.classList.contains('collapsed')) {
                    container.classList.remove('collapsed');
                    container.classList.add('expanded');
                    this.textContent = 'Свернуть';
                } else {
                    container.classList.remove('expanded');
                    container.classList.add('collapsed');
                    this.textContent = 'Читать полностью';
                }
            });
        }
    }

    closeModal.addEventListener('click', () => modal.classList.remove('active'));
    window.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('active');
    });

    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }
});