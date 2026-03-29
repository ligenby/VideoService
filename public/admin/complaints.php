<?php

ini_set('display_errors', 0); 
error_reporting(E_ALL);

require_once __DIR__ . '/../../src/core/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ob_start();

function sendJson($data) {
    if (ob_get_length()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

try {
// post
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
// статус жалобы
        if (isset($_POST['update_status'])) {
            $report_id = (int)$_POST['report_id'];
            $new_status = $_POST['status']; 
            
            $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE report_id = ?");
            $stmt->bind_param("si", $new_status, $report_id);
            if ($stmt->execute()) sendJson(['success' => true]);
            else throw new Exception($stmt->error);
        }

// пользователь
        if (isset($_POST['moderate_user'])) {
            $user_id = (int)$_POST['user_id'];
            $action = $_POST['action']; 
            $new_status = ($action === 'ban') ? 'banned' : 'active';
            
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->bind_param("si", $new_status, $user_id);
            if ($stmt->execute()) sendJson(['success' => true]);
            else throw new Exception($stmt->error);
        }

// видео
        if (isset($_POST['moderate_video'])) {
            $video_id = $_POST['public_video_id']; 
            $action = $_POST['action']; 
            
            if ($action === 'delete') {
                $stmt = $conn->prepare("UPDATE videos SET status = 'deleted', visibility = 'private' WHERE public_video_id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE videos SET status = 'public', visibility = 'public' WHERE public_video_id = ?");
            }
            
            $stmt->bind_param("s", $video_id);
            if ($stmt->execute()) sendJson(['success' => true]);
            else throw new Exception($stmt->error);
        }
    }

// детали
    if (isset($_GET['get_report_details'])) {
        $report_id = (int)$_GET['report_id'];
        
// upload_date вместо created_at
        $sql = "SELECT 
                    r.*, 
                    -- РЕПОРТЕР (Кто отправил)
                    u_rep.username as reporter_name, 
                    u_rep.avatar_url as reporter_avatar, 
                    u_rep.email as reporter_email,
                    u_rep.registration_date as reporter_reg_date,
                    
                    -- ВИДЕО (На что жалоба)
                    v.title as video_title, 
                    v.description as video_description,
                    v.upload_date as video_created_at,  -- ИСПРАВЛЕНО ТУТ
                    v.public_video_id, 
                    v.thumbnail_url,
                    v.status as video_status,
                    v.views_count as video_views,
                    
                    -- АВТОР ВИДЕО (Нарушитель)
                    u_vid.user_id as video_author_id,
                    u_vid.username as video_author,
                    u_vid.email as video_author_email,
                    u_vid.avatar_url as video_author_avatar,
                    u_vid.status as video_author_status,
                    u_vid.registration_date as video_author_reg_date,

                    -- КОММЕНТАРИЙ
                    c.text as comment_text,
                    c.created_at as comment_created_at,
                    
                    -- АВТОР КОММЕНТАРИЯ
                    u_com.user_id as comment_author_id,
                    u_com.username as comment_author,
                    u_com.email as comment_author_email,
                    u_com.avatar_url as comment_author_avatar,
                    u_com.status as comment_author_status,
                    u_com.registration_date as comment_author_reg_date

                FROM reports r
                LEFT JOIN users u_rep ON r.user_id = u_rep.user_id
                LEFT JOIN videos v ON (r.target_type = 'video' AND r.target_id = v.video_id)
                LEFT JOIN users u_vid ON v.user_id = u_vid.user_id
                LEFT JOIN comments c ON (r.target_type = 'comment' AND r.target_id = c.comment_id)
                LEFT JOIN users u_com ON c.user_id = u_com.user_id
                
                WHERE r.report_id = ?";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Ошибка SQL: " . $conn->error);
        }
        $stmt->bind_param("i", $report_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($data = $result->fetch_assoc()) {

            $data['formatted_date'] = date('d.m.Y H:i', strtotime($data['created_at']));
            
            if (!empty($data['video_created_at'])) {
                $data['video_date_fmt'] = date('d.m.Y', strtotime($data['video_created_at']));
            }
            if (!empty($data['video_author_reg_date'])) {
                $data['video_author_reg_fmt'] = date('d.m.Y', strtotime($data['video_author_reg_date']));
            }
            if (!empty($data['reporter_reg_date'])) {
                $data['reporter_reg_fmt'] = date('d.m.Y', strtotime($data['reporter_reg_date']));
            }
            
            sendJson($data);
        } else {
            sendJson(['error' => 'Жалоба не найдена']);
        }
    }

} catch (Exception $e) {
    sendJson(['error' => 'Error: ' . $e->getMessage()]);
}

// поиск
if (isset($_GET['ajax_search'])) {
    if (ob_get_length()) ob_end_clean();
    $search = trim($_GET['search'] ?? '');
    $status_filter = $_GET['status'] ?? 'all';
    $type_filter = $_GET['type'] ?? 'all';

    $sql = "SELECT r.*, u.username, u.avatar_url FROM reports r LEFT JOIN users u ON r.user_id = u.user_id WHERE 1=1";
    $params = []; $types = "";

    if (!empty($search)) {
        $sql .= " AND (r.report_id LIKE ? OR r.reason LIKE ? OR u.username LIKE ?)";
        $searchLike = "%$search%"; $params = array_merge($params, [$searchLike, $searchLike, $searchLike]); $types .= "sss";
    }
    if ($status_filter !== 'all') { $sql .= " AND r.status = ?"; $params[] = $status_filter; $types .= "s"; }
    if ($type_filter !== 'all') { $sql .= " AND r.target_type = ?"; $params[] = $type_filter; $types .= "s"; }

    $sql .= " ORDER BY r.created_at DESC LIMIT 50";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) $stmt->bind_param($types, ...$params);
        $stmt->execute(); $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) renderReportRow($row);
        } else {
            echo '<tr><td colspan="7" style="text-align:center; padding:30px; color:#777;">Жалоб не найдено</td></tr>';
        }
    }
    exit();
}

function renderReportRow($row) {
    $statusMap = ['open' => 'Открыта', 'resolved' => 'Решена', 'dismissed' => 'Отклонена'];
    $statusText = $statusMap[$row['status']] ?? $row['status'];
    $avatar = !empty($row['avatar_url']) ? '/'.$row['avatar_url'] : '/images/default_avatar.png';
    $short_reason = mb_strlen($row['reason']) > 30 ? mb_substr($row['reason'], 0, 30).'...' : $row['reason'];
    $typeLabel = ($row['target_type'] === 'video') ? 'ВИДЕО' : 'КОММЕНТ';
    ?>
    <tr>
        <td>#<?= $row['report_id'] ?></td>
        <td><?= date('d.m.Y', strtotime($row['created_at'])) ?></td>
        <td>
            <div class="user-cell-small">
                <img src="<?= htmlspecialchars($avatar) ?>" alt="">
                <span><?= htmlspecialchars($row['username']) ?></span>
            </div>
        </td>
        <td><span class="type-badge <?= $row['target_type'] ?>"><?= $typeLabel ?></span></td>
        <td title="<?= htmlspecialchars($row['reason']) ?>"><?= htmlspecialchars($short_reason) ?></td>
        <td><span class="badge <?= $row['status'] ?>"><?= $statusText ?></span></td>
        <td>
            <button class="btn-mini btn-blue view-report-btn" data-id="<?= $row['report_id'] ?>">Инфо</button>
        </td>
    </tr>
    <?php
}

require_once __DIR__ . '/header.php'; 
?>

<!-- ОСНОВНОЙ КОНТЕНТ -->
<div class="admin-header">
    <h1>Жалобы (Reports)</h1>
</div>

<div class="filters-panel">
    <div class="search-input-wrapper">
        <input type="text" id="complaint-search" class="admin-search-input" placeholder="Поиск..." autocomplete="off">
        <div id="search-spinner" class="spinner-border" style="display: none;"></div>
    </div>
    
    <div class="custom-select-wrapper" style="z-index: 20;">
        <div class="custom-select-trigger"><span>Все статусы</span><div class="arrow"></div></div>
        <div class="custom-options">
            <span class="custom-option selected" data-value="all">Все статусы</span>
            <span class="custom-option" data-value="open">Открытые</span>
            <span class="custom-option" data-value="resolved">Решенные</span>
            <span class="custom-option" data-value="dismissed">Отклоненные</span>
        </div>
        <input type="hidden" id="filter-status" value="all">
    </div>
    <div class="custom-select-wrapper" style="z-index: 19;">
        <div class="custom-select-trigger"><span>Все типы</span><div class="arrow"></div></div>
        <div class="custom-options">
            <span class="custom-option selected" data-value="all">Все типы</span>
            <span class="custom-option" data-value="video">Видео</span>
            <span class="custom-option" data-value="comment">Комментарии</span>
        </div>
        <input type="hidden" id="filter-type" value="all">
    </div>
</div>

<div class="admin-table-container">
    <table class="admin-table">
        <thead>
            <tr><th>ID</th><th>Дата</th><th>От кого</th><th>Тип</th><th>Причина</th><th>Статус</th><th>Действия</th></tr>
        </thead>
        <tbody id="complaints-table-body"></tbody>
    </table>
</div>

<!-- МОДАЛКА ДЕТАЛЕЙ -->
<div id="report-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="modal-header-custom">
            <h2>Детали жалобы #<span id="modal-report-id">...</span></h2>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body" id="modal-report-body"></div>
    </div>
</div>

<!-- МОДАЛКА ПОДТВЕРЖДЕНИЯ -->
<div id="confirm-modal" class="confirm-modal-overlay">
    <div class="confirm-box">
        <h3 id="confirm-title">Подтвердите действие</h3>
        <p id="confirm-text">Вы точно хотите это сделать?</p>
        <div class="confirm-buttons">
            <button id="confirm-yes" class="btn-confirm-yes">OK</button>
            <button id="confirm-no" class="btn-confirm-no">Отмена</button>
        </div>
    </div>
</div>

</div>
<script src="/admin/js/complaints.js"></script>
</body>
</html>