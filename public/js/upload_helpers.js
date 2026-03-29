document.addEventListener('DOMContentLoaded', async () => {

    if (typeof csrfToken === 'undefined') console.error('CSRF-токен не найден!');

    // --- Состояние приложения ---
    let cropper = null;            
    let ffmpeg = null;             
    let isFFmpegLoaded = false;    
    let originalVideoFile = null;  

    // --- DOM Элементы (Фото) ---
    const thumbInput = document.getElementById('thumbnail-input');
    const thumbName = document.getElementById('thumbnail-filename');
    const editThumbBtn = document.getElementById('edit-thumbnail-btn');
    const cropModal = document.getElementById('crop-image-modal');
    const imageToCrop = document.getElementById('image-to-crop');

    // --- DOM Элементы (Видео) ---
    const videoInput = document.getElementById('video-input');
    const videoName = document.getElementById('video-filename');
    const editVideoBtn = document.getElementById('edit-video-btn');
    const videoModal = document.getElementById('advanced-video-editor-modal');
    const videoPreview = document.getElementById('video-preview-player');
    const editorStatus = document.getElementById('editor-status');
    const applyVideoBtn = document.getElementById('apply-editor-btn');
    
    const uploadForm = document.getElementById('main-upload-form');

    // --- Инициализация FFmpeg (WebAssembly) ---
    const loadFFmpeg = async () => {
        if (isFFmpegLoaded && ffmpeg) return true;

        if (!window.crossOriginIsolated) {
            editorStatus.innerHTML = '<span style="color:#ff5252">Ошибка сервера: Нет заголовков COOP/COEP.</span>';
            applyVideoBtn.disabled = true;
            return false;
        }

        try {
            editorStatus.textContent = 'Загрузка компонентов редактора...';
            editorStatus.style.color = '#ffca28';

            const { createFFmpeg } = FFmpeg;
            ffmpeg = createFFmpeg({ log: true });
            
            await ffmpeg.load();
            
            isFFmpegLoaded = true;
            editorStatus.textContent = 'Редактор готов к работе';
            editorStatus.style.color = '#4caf50'; 
            applyVideoBtn.disabled = false; 
            
            return true;
        } catch (e) {
            console.error('FFmpeg Load Error:', e);
            editorStatus.textContent = 'Ошибка загрузки ядра FFmpeg (см. консоль)';
            editorStatus.style.color = '#ff5252';
            return false;
        }
    };

    // --- Утилиты ---
    const replaceInputFile = (inputElement, blob, fileName) => {
        const file = new File([blob], fileName, {
            type: blob.type,
            lastModified: new Date().getTime(),
        });
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        inputElement.files = dataTransfer.files;
    };

    const formatTime = (seconds) => {
        const m = Math.floor(seconds / 60).toString().padStart(2, '0');
        const s = Math.floor(seconds % 60).toString().padStart(2, '0');
        return `${m}:${s}`;
    };

    const setupFileInput = (input, nameDisplay, editBtn, type) => {
        input?.addEventListener('change', (event) => {
            const files = event.target.files;
            if (files.length > 0) {
                nameDisplay.textContent = files[0].name;
                if (editBtn) editBtn.style.display = 'inline-block';
                
                if (type === 'video') {
                    originalVideoFile = files[0];
                    // Требуется реинициализация FFmpeg при смене файла
                    isFFmpegLoaded = false; 
                    ffmpeg = null; 
                }
            } else {
                nameDisplay.textContent = 'Файл не выбран';
                if (editBtn) editBtn.style.display = 'none';
            }
        });
    };

    setupFileInput(thumbInput, thumbName, editThumbBtn, 'image');
    setupFileInput(videoInput, videoName, editVideoBtn, 'video');

    // --- Редактор обложки (Cropper.js) ---
    const ratioBtns = document.querySelectorAll('.ratio-btn');
    
    editThumbBtn?.addEventListener('click', () => {
        const files = thumbInput.files;
        if (!files || files.length === 0) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            imageToCrop.src = e.target.result;
            cropModal.style.display = 'flex';

            if (cropper) cropper.destroy();

            cropper = new Cropper(imageToCrop, {
                aspectRatio: 1.7777, 
                viewMode: 1,
                autoCropArea: 1,
                dragMode: 'move',
                guides: true,
                center: true,
                background: false,
            });

            ratioBtns.forEach(btn => btn.classList.remove('active'));
            document.querySelector('.ratio-btn[data-ratio="1.7777"]')?.classList.add('active');
        };
        reader.readAsDataURL(files[0]);
    });

    ratioBtns.forEach(btn => {
        btn.addEventListener('click', (event) => {
            if (!cropper) return;
            const currentBtn = event.currentTarget;
            
            ratioBtns.forEach(b => b.classList.remove('active'));
            currentBtn.classList.add('active');
            cropper.setAspectRatio(parseFloat(currentBtn.dataset.ratio));
        });
    });

    document.getElementById('save-crop-btn')?.addEventListener('click', () => {
        if (!cropper) return;
        cropper.getCroppedCanvas({
            width: 1280, 
            height: 720, 
            imageSmoothingQuality: 'high',
        }).toBlob((blob) => {
            replaceInputFile(thumbInput, blob, 'thumbnail_edited.png');
            thumbName.textContent = 'thumbnail_edited.png (Отредактировано)';
            cropModal.style.display = 'none';
            cropper.destroy(); 
            cropper = null;
        }, 'image/png');
    });

    document.getElementById('cancel-crop-btn')?.addEventListener('click', () => {
        cropModal.style.display = 'none';
        if (cropper) { 
            cropper.destroy(); 
            cropper = null; 
        }
    });

    // --- Редактор видео (Обрезка) ---
    const cancelVideoBtn = document.getElementById('cancel-editor-btn');
    const playPauseBtn = document.getElementById('play-pause-btn');
    const timeDisplay = document.getElementById('current-time-display');
    const timelineWrapper = document.querySelector('.timeline-wrapper');
    const playhead = document.getElementById('timeline-playhead');
    const trimStartHandle = document.getElementById('trim-handle-start');
    const trimEndHandle = document.getElementById('trim-handle-end');
    const trimSelection = document.getElementById('trim-selection');

    let videoDuration = 0;
    let trimStart = 0;
    let trimEnd = 0;
    let isDragging = null;

    editVideoBtn?.addEventListener('click', async () => {
        if (!videoInput.files.length) return;

        videoModal.style.display = 'flex';
        
        if (!isFFmpegLoaded) applyVideoBtn.disabled = true;

        const fileURL = URL.createObjectURL(originalVideoFile);
        videoPreview.src = fileURL;

        videoPreview.onloadedmetadata = () => {
            videoDuration = videoPreview.duration;
            trimStart = 0;
            trimEnd = videoDuration;
            updateTimelineUI();
        };

        await loadFFmpeg();
    });

    function updateTimelineUI() {
        if (!videoDuration) return;
        const pctStart = (trimStart / videoDuration) * 100;
        const pctEnd = (trimEnd / videoDuration) * 100;
        
        trimStartHandle.style.left = `calc(${pctStart}% - 6px)`;
        trimEndHandle.style.left = `calc(${pctEnd}% - 6px)`;
        trimSelection.style.left = `${pctStart}%`;
        trimSelection.style.width = `${pctEnd - pctStart}%`;
        timeDisplay.textContent = `${formatTime(videoPreview.currentTime)} / ${formatTime(trimEnd - trimStart)}`;
    }

    function getTimeFromEvent(e) {
        const rect = timelineWrapper.getBoundingClientRect();
        let x = Math.max(0, Math.min(e.clientX - rect.left, rect.width));
        return (x / rect.width) * videoDuration;
    }

    timelineWrapper?.addEventListener('mousedown', (e) => {
        if (e.target === trimStartHandle) isDragging = 'start';
        else if (e.target === trimEndHandle) isDragging = 'end';
        else {
            isDragging = 'playhead';
            videoPreview.currentTime = getTimeFromEvent(e);
        }
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDragging || videoModal.style.display === 'none') return;
        const time = getTimeFromEvent(e);
        const minDuration = 1; 

        if (isDragging === 'playhead') {
            videoPreview.currentTime = time;
        } else if (isDragging === 'start' && time < trimEnd - minDuration) {
            trimStart = time;
            videoPreview.currentTime = trimStart;
        } else if (isDragging === 'end' && time > trimStart + minDuration) {
            trimEnd = time;
            videoPreview.currentTime = trimEnd;
        }
        updateTimelineUI();
    });

    document.addEventListener('mouseup', () => { isDragging = null; });

    playPauseBtn?.addEventListener('click', () => {
        if (videoPreview.paused) {
            videoPreview.play();
            playPauseBtn.classList.add('playing');
        } else {
            videoPreview.pause();
            playPauseBtn.classList.remove('playing');
        }
    });

    videoPreview?.addEventListener('timeupdate', () => {
        if (!videoDuration || isDragging) return;
        
        if (videoPreview.currentTime >= trimEnd) {
            videoPreview.pause();
            videoPreview.currentTime = trimStart;
            playPauseBtn.classList.remove('playing');
        }
        
        const percent = (videoPreview.currentTime / videoDuration) * 100;
        playhead.style.left = `${percent}%`;
        updateTimelineUI();
    });

    // Обработка видео через FFmpeg
    applyVideoBtn?.addEventListener('click', async () => {
        if (!isFFmpegLoaded || !ffmpeg) {
            editorStatus.textContent = 'Подождите, редактор еще загружается...';
            editorStatus.style.color = '#ffca28';
            const loaded = await loadFFmpeg();
            if (!loaded) return;
        }

        applyVideoBtn.disabled = true;
        editorStatus.textContent = 'Обработка видео... Пожалуйста, ждите.';
        editorStatus.style.color = '#3ea6ff';

        try {
            const { fetchFile } = FFmpeg;
            const inputName = 'input.mp4';
            const outputName = 'output.mp4';

            // Пишем файл в виртуальную файловую систему
            ffmpeg.FS('writeFile', inputName, await fetchFile(originalVideoFile));

            // Запуск рендера
            await ffmpeg.run(
                '-ss', trimStart.toString(),
                '-to', trimEnd.toString(),
                '-i', inputName,
                '-c:v', 'libx264',
                '-preset', 'ultrafast',
                '-c:a', 'copy',
                outputName
            );

            // Читаем результат
            const data = ffmpeg.FS('readFile', outputName);
            const newBlob = new Blob([data.buffer], { type: 'video/mp4' });

            replaceInputFile(videoInput, newBlob, 'trimmed_video.mp4');
            videoName.textContent = 'trimmed_video.mp4 (Обрезано)';

            // Очистка Wasm памяти
            ffmpeg.FS('unlink', inputName);
            ffmpeg.FS('unlink', outputName);
            
            videoModal.style.display = 'none';

        } catch (err) {
            console.error('Video processing error:', err);
            editorStatus.textContent = 'Ошибка во время обработки.';
            editorStatus.style.color = '#ff5252';
        } finally {
            applyVideoBtn.disabled = false;
        }
    });

    cancelVideoBtn?.addEventListener('click', () => {
        videoModal.style.display = 'none';
        videoPreview.pause();
    });

    // --- Кастомные селекты ---
    const setupCustomSelect = (containerId) => {
        const container = document.getElementById(containerId);
        if (!container) return;

        const btn = container.querySelector('.custom-select-button');
        const val = container.querySelector('.custom-select-value');
        const inp = container.querySelector('input');
        
        btn.addEventListener('click', (e) => { 
            e.stopPropagation(); 
            container.classList.toggle('open'); 
        });
        
        container.querySelectorAll('.custom-select-option').forEach(opt => {
            opt.addEventListener('click', () => {
                val.textContent = opt.textContent.trim();
                inp.value = opt.dataset.value;
                container.classList.remove('open');
            });
        });
        
        document.addEventListener('click', (e) => { 
            if (!container.contains(e.target)) container.classList.remove('open'); 
        });
    };

    setupCustomSelect('category-select-container');
    setupCustomSelect('playlist-select-container');

    const existingPlDiv = document.getElementById('existing-playlist-container');
    document.querySelectorAll('input[name="playlist_option"]').forEach(r => {
        r.addEventListener('change', () => {
            if (existingPlDiv) {
                existingPlDiv.style.display = (r.value === 'existing') ? 'block' : 'none';
            }
        });
    });

    // --- Валидация перед отправкой ---
    uploadForm?.addEventListener('submit', (e) => {
        // Сброс старых ошибок
        document.querySelectorAll('.input-error').forEach(el => el.classList.remove('input-error'));
        document.querySelectorAll('.input-error-message').forEach(el => {
            el.textContent = '';
            el.classList.remove('visible');
            el.style.display = 'none';
        });

        let isValid = true;
        const titleInput = document.getElementById('title');
        
        if (!titleInput.value.trim()) {
            isValid = false;
            titleInput.classList.add('input-error');
            const msg = titleInput.parentElement.querySelector('.input-error-message');
            if (msg) { 
                msg.textContent = 'Введите название'; 
                msg.style.display = 'block'; 
            }
        }

        if (videoInput.files.length === 0) {
            isValid = false;
            const wrap = videoInput.parentElement;
            if (wrap) wrap.classList.add('input-error'); 
            const msg = wrap?.parentElement.querySelector('.input-error-message');
            if (msg) { 
                msg.textContent = 'Выберите видео'; 
                msg.style.display = 'block'; 
            }
        }

        if (!isValid) e.preventDefault();
    });
});