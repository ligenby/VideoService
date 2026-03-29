<?php
require_once __DIR__ . '/../../src/core/config.php'; 

// Обработка POST-запросов (действия над отзывами)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    $review_id = intval($_POST['review_id'] ?? 0);

    try {
        if ($review_id <= 0) {
            throw new Exception('Invalid ID');
        }

        if ($action === 'approve') {
            $conn->query("UPDATE reviews SET status = 'одобрен', allow_publication = 1 WHERE review_id = $review_id");
            echo json_encode(['success' => true]);
        } elseif ($action === 'reject') {
            $conn->query("UPDATE reviews SET status = 'отклонен', allow_publication = 0 WHERE review_id = $review_id");
            echo json_encode(['success' => true]);
        } elseif ($action === 'delete') {
            $conn->query("DELETE FROM review_reactions WHERE review_id = $review_id");
            $conn->query("DELETE FROM reviews WHERE review_id = $review_id");
            echo json_encode(['success' => true]);
        } elseif ($action === 'reply') {
            $response = trim($_POST['response_text'] ?? '');
            $stmt = $conn->prepare("UPDATE reviews SET admin_response = ?, status = 'одобрен', allow_publication = 1 WHERE review_id = ?");
            $stmt->bind_param("si", $response, $review_id);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Unknown action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// API: Поиск и фильтрация (AJAX HTML)
if (isset($_GET['ajax_search'])) {
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? 'all';

    $sql = "SELECT r.*, u.username, u.avatar_url,
            (SELECT COUNT(*) FROM review_reactions WHERE review_id = r.review_id AND reaction_type = 'helpful') as helpful_count,
            (SELECT COUNT(*) FROM review_reactions WHERE review_id = r.review_id AND reaction_type = 'unhelpful') as unhelpful_count
            FROM reviews r
            JOIN users u ON r.user_id = u.user_id
            WHERE 1=1";

    $params = [];
    $types = "";

    if (!empty($search)) {
        $sql .= " AND (r.review_id LIKE ? OR u.username LIKE ? OR r.content LIKE ?)";
        $searchLike = "%$search%";
        $params = array_merge($params, [$searchLike, $searchLike, $searchLike]);
        $types .= "sss";
    }

    if ($status !== 'all') {
        if ($status === 'pending') {
            $sql .= " AND r.status = 'в обработке'";
        } elseif ($status === 'approved') {
            $sql .= " AND r.status = 'одобрен'";
        } elseif ($status === 'rejected') {
            $sql .= " AND r.status = 'отклонен'";
        } elseif ($status === 'replied') {
            $sql .= " AND r.admin_response IS NOT NULL AND r.admin_response != ''";
        }
    }

    $sql .= " ORDER BY r.created_at DESC LIMIT 50";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($rev = $result->fetch_assoc()) {
            renderReviewRow($rev);
        }
    } else {
        echo '<tr><td colspan="7" style="text-align:center; padding: 40px; color: #777;">Отзывы не найдены</td></tr>';
    }
    exit;
}

// Шаблон отрисовки строки отзыва
function renderReviewRow($rev) {
    $badgeClass = 'pending'; 
    $statusText = $rev['status'];

    if ($rev['status'] === 'одобрен') {
        $badgeClass = 'approved';
        $statusText = 'Опубликован';
    } elseif ($rev['status'] === 'отклонен') {
        $badgeClass = 'rejected';
        $statusText = 'Отклонен';
    }
    
    $hasResponse = !empty($rev['admin_response']);
    $shortContent = mb_strimwidth($rev['content'], 0, 80, '...');
    $fullContent = htmlspecialchars($rev['content']);
    $safeContentJS = str_replace(["\r", "\n", '"'], [" ", " ", '&quot;'], $fullContent);

    $iconReply   = '/images/icons/reply.png';
    $iconApprove = '/images/icons/check.png';
    $iconReject  = '/images/icons/cross.png';
    $iconDelete  = '/images/icons/trash.png';
    ?>
    <tr data-id="<?= $rev['review_id'] ?>">
        <td>
            <div style="font-family:monospace; color:var(--text-muted);">#<?= $rev['review_id'] ?></div>
            <div style="font-size:11px; color:#666; margin-top:4px;"><?= date('d.m.Y', strtotime($rev['created_at'])) ?></div>
        </td>
        <td>
            <div class="user-cell-small">
                <img src="/<?= htmlspecialchars($rev['avatar_url'] ?? 'images/default-avatar.png') ?>" alt="avatar">
                <div style="display:flex; flex-direction:column;">
                    <span style="font-weight:600; font-size:13px;"><?= htmlspecialchars($rev['username']) ?></span>
                </div>
            </div>
        </td>
        <td style="max-width: 400px;">
            <?php if (!empty($rev['subject'])): ?>
                <div style="font-weight:bold; font-size:13px; margin-bottom:4px; color:#fff;">
                    <?= htmlspecialchars($rev['subject']) ?>
                </div>
            <?php endif; ?>
            
            <div class="review-preview">
                <?= htmlspecialchars($shortContent) ?>
                <?php if (mb_strlen($rev['content']) > 80): ?>
                    <span class="text-toggle-btn" onclick="alert('<?= $safeContentJS ?>')">ещё</span>
                <?php endif; ?>
            </div>
            
            <?php if ($hasResponse): ?>
                <div style="margin-top:6px; font-size:11px; color:var(--accent); display:flex; align-items:center; gap:4px;">
                    <span>↩ Вы ответили</span>
                </div>
            <?php endif; ?>
        </td>
        <td>
            <div class="rating-stars" style="color:#ffd700; font-size:16px;">
                <?= str_repeat('★', $rev['rating']) ?><span style="color:#444;"><?= str_repeat('★', 5 - $rev['rating']) ?></span>
            </div>
        </td>
        <td>
            <span class="badge <?= $badgeClass ?>"><?= $statusText ?></span>
        </td>
        <td>
            <div style="display:flex; gap:10px; font-size:12px; font-weight:500;">
                <span style="color:#81c784;">👍 <?= $rev['helpful_count'] ?></span>
                <span style="color:#e57373;">👎 <?= $rev['unhelpful_count'] ?></span>
            </div>
        </td>
        <td>
            <div class="actions-cell" style="justify-content: flex-end; gap: 4px;">
                <button type="button" class="btn-img-action reply" onclick="openReply(<?= $rev['review_id'] ?>)" title="Ответить">
                    <img src="<?= $iconReply ?>" alt="Reply">
                </button>
                
                <?php if ($rev['status'] !== 'одобрен'): ?>
                    <button type="button" class="btn-img-action approve" onclick="setStatus(<?= $rev['review_id'] ?>, 'approve')" title="Одобрить">
                        <img src="<?= $iconApprove ?>" alt="Approve">
                    </button>
                <?php endif; ?>
                
                <?php if ($rev['status'] !== 'отклонен'): ?>
                    <button type="button" class="btn-img-action reject" onclick="setStatus(<?= $rev['review_id'] ?>, 'reject')" title="Отклонить">
                        <img src="<?= $iconReject ?>" alt="Reject">
                    </button>
                <?php endif; ?>
                
                <button type="button" class="btn-img-action delete" onclick="confirmDelete(<?= $rev['review_id'] ?>)" title="Удалить">
                    <img src="<?= $iconDelete ?>" alt="Delete">
                </button>
            </div>
        </td>
    </tr>
    <?php
}

require_once __DIR__ . '/header.php';
?>

<div class="admin-header">
    <h1>Управление отзывами</h1>
</div>

<div class="filters-panel">
    <div class="search-input-wrapper">
        <input type="text" id="review-search" class="admin-search-input" placeholder="Поиск (ID, автор, текст)..." autocomplete="off">
        <div id="search-spinner" class="spinner-border" style="display: none;"></div>
    </div>
    
    <div class="custom-select-wrapper" style="z-index: 20;">
        <div class="custom-select-trigger"><span>Все статусы</span><div class="arrow"></div></div>
        <div class="custom-options">
            <span class="custom-option selected" data-value="all">Все статусы</span>
            <span class="custom-option" data-value="pending">В обработке</span>
            <span class="custom-option" data-value="approved">Опубликованные</span>
            <span class="custom-option" data-value="rejected">Отклоненные</span>
            <span class="custom-option" data-value="replied">С ответом</span>
        </div>
        <input type="hidden" id="filter-status" value="all">
    </div>
</div>

<div class="admin-table-container">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID / Дата</th>
                <th>Автор</th>
                <th>Отзыв</th>
                <th>Рейтинг</th>
                <th>Статус</th>
                <th>Реакции</th>
                <th style="text-align: right">Действия</th>
            </tr>
        </thead>
        <tbody id="reviews-table-body">
            <!-- Данные загружаются через AJAX -->
        </tbody>
    </table>
</div>

<!-- Модальное окно: Ответ на отзыв -->
<div id="replyModal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="modal-header-custom">
            <h2>Ответ на отзыв #<span id="reply-review-id-display"></span></h2>
            <span class="close-modal" onclick="closeReplyModal()">&times;</span>
        </div>
        <form id="replyForm">
            <input type="hidden" name="action" value="reply">
            <input type="hidden" name="review_id" id="reply-review-id">
            
            <div class="form-group">
                <label style="color:#aaa; display:block; margin-bottom:8px;">Ваш ответ (будет виден всем):</label>
                <textarea name="response_text" id="reply-text" rows="5" class="admin-search-input" style="width:100%; height:auto;" placeholder="Спасибо за ваш отзыв..." required></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-confirm-no" onclick="closeReplyModal()">Отмена</button>
                <button type="submit" class="btn-confirm-yes">Отправить ответ</button>
            </div>
        </form>
    </div>
</div>

<!-- Модальное окно: Подтверждение удаления -->
<div class="confirm-modal-overlay" id="confirmModal">
    <div class="confirm-box">
        <h3>Удалить отзыв?</h3>
        <p>Действие необратимо.</p>
        <div class="confirm-buttons">
            <button class="btn-confirm-no" onclick="closeConfirm()">Отмена</button>
            <button class="btn-confirm-yes" id="btn-do-delete">Удалить</button>
        </div>
    </div>
</div>

<script src="/admin/js/reviews.js"></script>
</body>
</html>