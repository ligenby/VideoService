document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('complaint-search');
    const tbody = document.getElementById('complaints-table-body');
    const spinner = document.getElementById('search-spinner');
    
    // модалки
    const modal = document.getElementById('report-modal');
    const modalBody = document.getElementById('modal-report-body');
    const closeModal = document.querySelector('.close-modal');
    const modalTitleId = document.getElementById('modal-report-id');
    const confirmModal = document.getElementById('confirm-modal');
    const confirmTitle = document.getElementById('confirm-title');
    const confirmText = document.getElementById('confirm-text');
    const confirmYesBtn = document.getElementById('confirm-yes');
    const confirmNoBtn = document.getElementById('confirm-no');

    let searchTimeout = null;
    let confirmCallback = null;

    // фильтры
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
                loadComplaints();
            });
        });
    });
    window.addEventListener('click', () => document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open')));

    // поиск и загрузка
    function loadComplaints() {
        if(!tbody) return;
        spinner.style.display = 'block';
        tbody.style.opacity = '0.5';
        const params = new URLSearchParams({
            ajax_search: 1,
            search: searchInput ? searchInput.value : '',
            status: document.getElementById('filter-status').value,
            type: document.getElementById('filter-type').value
        });
        fetch(`complaints.php?${params.toString()}`).then(res => res.text()).then(html => {
            tbody.innerHTML = html; spinner.style.display = 'none'; tbody.style.opacity = '1';
        });
    }

    if(searchInput) {
        searchInput.addEventListener('input', () => { clearTimeout(searchTimeout); searchTimeout = setTimeout(loadComplaints, 300); });
        loadComplaints();
    }

    // клик по строке - открываем детали
    if(tbody) {
        tbody.addEventListener('click', (e) => {
            if (e.target.classList.contains('view-report-btn')) {
                openReportModal(e.target.getAttribute('data-id'));
            }
        });
    }

    function openReportModal(id) {
        modal.classList.add('active');
        modalTitleId.textContent = id;
        modalBody.innerHTML = `<div style="padding:40px; text-align:center; color:#888;"><p>Загрузка...</p></div>`;

        fetch(`complaints.php?get_report_details=1&report_id=${id}`)
            .then(res => res.json())
            .then(data => {
                if (data.error) modalBody.innerHTML = `<p style="color:var(--danger); text-align:center;">${data.error}</p>`;
                else renderModalContent(data);
            })
            .catch(err => modalBody.innerHTML = `<p>Ошибка соединения</p>`);
    }

    function renderModalContent(data) {
        let target = {};
        let detailsHtml = '';

        if (data.target_type === 'video') {
            target = {
                title: escapeHtml(data.video_title),
                author: escapeHtml(data.video_author),
                authorId: data.video_author_id,
                authorStatus: data.video_author_status,
                authorEmail: escapeHtml(data.video_author_email),
                authorReg: data.video_author_reg_fmt || '-',
                avatar: data.video_author_avatar,
                link: `/watch.php?id=${data.public_video_id}`,
                banner: data.thumbnail_url ? `/${data.thumbnail_url}` : '/images/default_banner.jpg',
                isDeleted: data.video_status === 'deleted',
                publicId: data.public_video_id,
                uploaded: data.video_date_fmt || '-',
                views: data.video_views || 0,
                desc: data.video_description ? escapeHtml(data.video_description).substring(0, 100) + '...' : 'Нет описания'
            };
            
            // инфа о видео
            detailsHtml = `
                <div class="info-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; font-size:13px; margin-bottom:15px; background:rgba(255,255,255,0.03); padding:10px; border-radius:8px;">
                    <div>
                        <div style="color:#888; font-size:11px; text-transform:uppercase;">О видео</div>
                        <div>📅 Загружено: ${target.uploaded}</div>
                        <div>👁 Просмотров: ${target.views}</div>
                        <div style="color:#aaa; font-style:italic; margin-top:4px;">"${target.desc}"</div>
                    </div>
                    <div>
                        <div style="color:#888; font-size:11px; text-transform:uppercase;">Об авторе</div>
                        <div>ID: ${target.authorId}</div>
                        <div>✉ ${target.authorEmail}</div>
                        <div>📅 Рег: ${target.authorReg}</div>
                    </div>
                </div>
            `;

        } else {
            target = {
                title: "Комментарий",
                author: escapeHtml(data.comment_author),
                authorId: data.comment_author_id,
                authorStatus: data.comment_author_status,
                authorEmail: escapeHtml(data.comment_author_email),
                avatar: data.comment_author_avatar,
                text: data.comment_text,
                isDeleted: false
            };
            detailsHtml = `<div class="profile-section"><h4>Текст</h4><div class="profile-about-box">"${escapeHtml(target.text)}"</div></div>`;
        }
        
        target.avatar = target.avatar ? `/${target.avatar}` : '/images/default_avatar.png';
        const reporterAvatar = data.reporter_avatar ? `/${data.reporter_avatar}` : '/images/default_avatar.png';

        // баннер видео
        let contentHTML = '';
        if (data.target_type === 'video') {
            contentHTML = `
                <div class="modal-banner-top" style="background-image: url('${target.banner}'); height:140px; position:relative;">
                    ${target.isDeleted ? '<div style="position:absolute; inset:0; background:rgba(0,0,0,0.7); display:flex; align-items:center; justify-content:center; color:red; font-weight:bold;">УДАЛЕНО</div>' : ''}
                </div>`;
        }

        // кнопки статуса жалобы
        let statusButtons = '';
        if (data.status === 'open') {
            statusButtons = `
                <div style="display:flex; gap:10px; margin-top:10px;">
                    <button class="btn-mini btn-green action-btn" data-action="resolve_report" data-id="${data.report_id}">✔ Жалоба решена</button>
                    <button class="btn-mini btn-red action-btn" data-action="dismiss_report" data-id="${data.report_id}">✕ Отклонить</button>
                </div>`;
        } else {
            statusButtons = `
                <div style="display:flex; gap:10px; margin-top:10px;">
                    <button class="btn-mini action-btn" data-action="reopen_report" data-id="${data.report_id}" style="background:#555;">↺ Вернуть в открытые</button>
                    <span class="badge ${data.status}" style="font-size:12px; align-self:center;">${data.status === 'resolved' ? 'Решена' : 'Отклонена'}</span>
                </div>`;
        }

        // Кнопки модерации
        let moderationButtons = '';
        const isBanned = target.authorStatus === 'banned';
        moderationButtons += `
            <button class="btn-mini ${isBanned ? 'btn-green' : 'btn-red'} mod-btn" 
                    data-type="user" data-id="${target.authorId}" data-current="${isBanned ? 'banned' : 'active'}">
                ${isBanned ? 'Разблокировать автора' : 'Заблокировать автора'}
            </button>
        `;
        if (data.target_type === 'video') {
            moderationButtons += `
                <button class="btn-mini ${target.isDeleted ? 'btn-green' : 'btn-red'} mod-btn" 
                        data-type="video" data-id="${target.publicId}" data-current="${target.isDeleted ? 'deleted' : 'public'}">
                    ${target.isDeleted ? 'Восстановить видео' : 'Удалить видео'}
                </button>
            `;
        }

        // СБОРКА HTML
        const html = `
            ${contentHTML}
            <div class="user-profile-header" style="${data.target_type !== 'video' ? 'margin-top:0' : ''}">
                <div class="profile-avatar-large" style="width:60px; height:60px; display:flex; justify-content:center; align-items:center; background:#222;">
                    <img src="${target.avatar}" style="width:100%; height:100%; object-fit:cover; border-radius:50%;"> 
                </div>
                <div class="profile-info-main">
                    <h3 style="font-size:18px;"><a href="${target.link}" target="_blank" style="color:inherit; text-decoration:none;">${target.title}</a></h3>
                    <div class="sub-info">
                        <span class="text-muted">Автор: ${target.author}</span>
                        <span class="type-badge ${data.target_type}">${data.target_type}</span>
                    </div>
                </div>
            </div>

            ${detailsHtml}

            <div class="profile-section" style="background:rgba(255, 82, 82, 0.05); padding:15px; border-radius:8px; border:1px solid rgba(255,82,82,0.2);">
                <h4 style="color:#ff8a80;">Действия с контентом</h4>
                <div style="display:flex; flex-wrap:wrap; gap:10px;">${moderationButtons}</div>
            </div>

            <div class="profile-section"><h4>Статус жалобы</h4>${statusButtons}</div>
            <div class="profile-section"><h4>Причина</h4><div class="reason-full">${escapeHtml(data.reason)}</div></div>
            
            <div class="profile-section"><h4>Отправитель жалобы</h4>
                <div class="reporter-info" style="background:var(--bg-dark); padding:10px; border-radius:8px;">
                    <img src="${reporterAvatar}" class="reporter-avatar">
                    <div style="flex:1;">
                        <strong>${escapeHtml(data.reporter_name)}</strong><br>
                        <small class="text-muted">${escapeHtml(data.reporter_email)}</small>
                    </div>
                    <div style="text-align:right; font-size:11px; color:#666;">
                        <div>Рег: ${data.reporter_reg_fmt || '-'}</div>
                        <div>Жалоба: ${data.formatted_date}</div>
                    </div>
                </div>
            </div>
        `;
        modalBody.innerHTML = html;
    }

    // --- 4. КЛИКИ В МОДАЛКЕ (С ПОДТВЕРЖДЕНИЕМ ДЛЯ ВСЕХ ВАЖНЫХ ДЕЙСТВИЙ) ---
    modalBody.addEventListener('click', function(e) {
        const btn = e.target;
        
        // 1. Статус жалобы (Решить / Отклонить / Вернуть)
        if (btn.classList.contains('action-btn')) {
            const action = btn.dataset.action;
            const id = btn.dataset.id;
            let status = 'open';
            let confirmMsg = '';

            if (action === 'resolve_report') {
                status = 'resolved';
                confirmMsg = 'Отметить жалобу как решенную?';
            } else if (action === 'dismiss_report') {
                status = 'dismissed';
                confirmMsg = 'Отклонить жалобу (нарушений нет)?';
            } else if (action === 'reopen_report') {
                status = 'open';
                confirmMsg = 'Вернуть жалобу в статус "Открыта" (на рассмотрение)?';
            }

            // Вызываем окно подтверждения
            showCustomConfirm(
                'Подтверждение статуса',
                confirmMsg,
                () => {
                    performAction(btn, { update_status: 1, report_id: id, status: status }, () => openReportModal(id));
                }
            );
        }

        // 2. Модерация (Бан пользователя / Удаление видео)
        if (btn.classList.contains('mod-btn')) {
            const type = btn.dataset.type;
            const id = btn.dataset.id;
            const currentStatus = btn.dataset.current; 

            if (type === 'user') {
                const action = (currentStatus === 'banned') ? 'unban' : 'ban';
                showCustomConfirm(
                    `Действие с пользователем`,
                    `Вы точно хотите ${action === 'ban' ? 'ЗАБАНИТЬ' : 'РАЗБАНИТЬ'} этого пользователя?`,
                    () => {
                        performAction(btn, { moderate_user: 1, user_id: id, action: action }, () => {
                            // Меняем кнопку локально
                            btn.dataset.current = (action === 'ban' ? 'banned' : 'active');
                            btn.textContent = (action === 'ban' ? 'Разблокировать автора' : 'Заблокировать автора');
                            btn.classList.toggle('btn-red'); btn.classList.toggle('btn-green');
                        });
                    }
                );
            }

            if (type === 'video') {
                const action = (currentStatus === 'deleted') ? 'restore' : 'delete';
                showCustomConfirm(
                    `Действие с видео`,
                    `Вы точно хотите ${action === 'delete' ? 'УДАЛИТЬ' : 'ВОССТАНОВИТЬ'} это видео?`,
                    () => {
                        performAction(btn, { moderate_video: 1, public_video_id: id, action: action }, () => {
                            // Перезагружаем модалку, чтобы обновить плашку "УДАЛЕНО"
                            openReportModal(modalTitleId.textContent);
                        });
                    }
                );
            }
        }
    });

    function showCustomConfirm(title, text, onYes) {
        confirmTitle.textContent = title;
        confirmText.textContent = text;
        confirmModal.classList.add('active');
        confirmCallback = onYes;
    }

    confirmYesBtn.addEventListener('click', () => {
        confirmModal.classList.remove('active');
        if (confirmCallback) confirmCallback();
    });
    confirmNoBtn.addEventListener('click', () => confirmModal.classList.remove('active'));
    confirmModal.addEventListener('click', (e) => { if (e.target === confirmModal) confirmModal.classList.remove('active'); });

    function performAction(btn, formDataObj, onSuccess) {
        const originalText = btn.textContent;
        btn.textContent = '...'; btn.disabled = true;
        const formData = new FormData();
        for (const key in formDataObj) formData.append(key, formDataObj[key]);

        fetch('complaints.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.success) { onSuccess(); loadComplaints(); } 
                else { alert('Ошибка: ' + data.error); btn.textContent = originalText; btn.disabled = false; }
            })
            .catch(() => { alert('Ошибка сети'); btn.textContent = originalText; btn.disabled = false; });
    }

    closeModal.addEventListener('click', () => modal.classList.remove('active'));
    window.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('active'); });

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
    }
});