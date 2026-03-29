<?php
require_once __DIR__ . '/../../src/core/config.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// API: Получение деталей пользователя (JSON)
if (isset($_GET['get_user_details'])) {
    error_reporting(0);
    header('Content-Type: application/json');

    $user_id = (int)$_GET['user_id'];

    $sql = "SELECT u.*, 
            (SELECT COUNT(*) FROM videos v WHERE v.user_id = u.user_id) as video_count 
            FROM users u 
            WHERE u.user_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        unset($row['password_hash']);
        
        $row['formatted_date'] = !empty($row['registration_date']) 
            ? date('d.m.Y H:i', strtotime($row['registration_date'])) 
            : 'Неизвестно';
        
        // Получение привязанных соцсетей
        $links_sql = "SELECT link_title, link_url FROM user_links WHERE user_id = ?";
        $stmt_links = $conn->prepare($links_sql);
        $stmt_links->bind_param("i", $user_id);
        $stmt_links->execute();
        $links_result = $stmt_links->get_result();
        
        $row['links'] = [];
        while ($link = $links_result->fetch_assoc()) {
            $row['links'][] = $link;
        }

        echo json_encode($row);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
    
    $stmt->close();
    exit();
}

// API: Живой поиск (AJAX HTML)
if (isset($_GET['ajax_search'])) {
    $search = trim($_GET['search'] ?? '');
    
    $sql = "SELECT * FROM users";
    $params = [];
    $types = "";

    if (!empty($search)) {
        $sql .= " WHERE user_id LIKE ? OR username LIKE ? OR email LIKE ?";
        $searchLike = "%" . $search . "%";
        $params = [$searchLike, $searchLike, $searchLike];
        $types = "sss";
    }

    $sql .= " ORDER BY user_id DESC LIMIT 50";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            renderUserRow($row);
        }
    } else {
        echo '<tr><td colspan="7" style="text-align:center; padding: 20px; color: #aaa;">Ничего не найдено</td></tr>';
    }
    
    $stmt->close();
    exit(); 
}

// Шаблон отрисовки строки пользователя
function renderUserRow($row) {
    $statusMap = [
        'active'  => 'Активен',
        'banned'  => 'Забанен',
        'pending' => 'Ожидает',
        'deleted' => 'Удален'
    ];
    $statusText = $statusMap[$row['status']] ?? $row['status'];
    ?>
    <tr>
        <td><?= $row['user_id'] ?></td>
        <td>
            <div class="user-cell">
                <img src="/<?= htmlspecialchars($row['avatar_url'] ?? 'assets/img/default.png') ?>" alt="avatar">
                <?= htmlspecialchars($row['username']) ?>
            </div>
        </td>
        <td><?= htmlspecialchars($row['email']) ?></td>
        <td><?= htmlspecialchars($row['channel_name'] ?? '-') ?></td>
        <td><?= $row['role'] ?></td>
        <td>
            <span class="badge <?= $row['status'] ?>">
                <?= $statusText ?>
            </span>
        </td>
        <td class="actions-cell">
            <button type="button" class="btn-mini btn-blue view-details-btn" data-id="<?= $row['user_id'] ?>">Инфо</button>

            <?php if ($row['role'] !== 'admin'): ?>
            <form action="/api/index.php?endpoint=admin_action" method="POST" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="ban_user">
                <input type="hidden" name="user_id" value="<?= $row['user_id'] ?>">
                <input type="hidden" name="status" value="<?= $row['status'] ?>">
                <?php if ($row['status'] == 'active'): ?>
                    <button class="btn-mini btn-red">Бан</button>
                <?php else: ?>
                    <button class="btn-mini btn-green">Разбан</button>
                <?php endif; ?>
            </form>
            <?php endif; ?>
        </td>
    </tr>
    <?php
}

require_once __DIR__ . '/header.php'; 
?>

<div class="admin-header">
    <h1>Управление пользователями</h1>

    <div class="admin-search-form">
        <div class="search-input-wrapper">
            <input 
                type="text" 
                id="live-search-input"
                class="admin-search-input" 
                placeholder="Поиск по (ID, login, email)..." 
                autocomplete="off"
            >
            <div id="search-spinner" class="spinner-border" style="display: none;"></div>
        </div>
        <button id="search-clear" class="search-clear-btn" title="Очистить">✕</button>
    </div>
</div>

<div class="admin-table-container">
    <table class="admin-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Пользователь</th>
                <th>Email</th>
                <th>Канал</th>
                <th>Роль</th>
                <th>Статус</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody id="users-table-body">
            <?php
            $result = $conn->query("SELECT * FROM users ORDER BY user_id DESC LIMIT 50");
            while ($row = $result->fetch_assoc()) {
                renderUserRow($row);
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Модальное окно сведений о пользователе -->
<div id="user-details-modal" class="admin-modal">
    <div class="admin-modal-content">
        <div class="modal-header">
            <h2>Сведения о канале</h2>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body" id="modal-body-content">
            <div class="spinner-border" style="position: relative; margin: 20px auto; display: block;"></div>
        </div>
    </div>
</div>

</div> <!-- Закрытие admin-content -->

<script src="/admin/js/users.js"></script>
</body>
</html>