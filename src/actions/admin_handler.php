<?php

require_once __DIR__ . '/../core/config.php';

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    if ($is_ajax) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    die('Access denied'); // Жесткая остановка скрипта, если прав нет
}

$action = $_POST['action'] ?? '';
$redirect = $_SERVER['HTTP_REFERER'] ?? '/admin/users.php';
$admin_id = (int)$_SESSION['user_id'];

try {
    switch ($action) {
        case 'ban_user':
            $target_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
            $status = ($_POST['status'] ?? '') === 'active' ? 'banned' : 'active';
            
            if ($target_user_id) {
                $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
                $stmt->bind_param("si", $status, $target_user_id);
                $stmt->execute();
                $stmt->close();
            }
            break;

        case 'resolve_review':
            $review_id = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
            $response_text = trim($_POST['admin_response'] ?? '');
            $status = 'рассмотрен';
            $allow_pub = isset($_POST['allow_publication']) ? 1 : 0;

            if ($review_id) {
                $stmt = $conn->prepare("UPDATE reviews SET admin_response = ?, status = ?, allow_publication = ? WHERE review_id = ?");
                $stmt->bind_param("ssii", $response_text, $status, $allow_pub, $review_id);
                
                if ($stmt->execute() && function_exists('createNotification')) {
                    $u_stmt = $conn->prepare("SELECT user_id FROM reviews WHERE review_id = ?");
                    $u_stmt->bind_param("i", $review_id);
                    $u_stmt->execute();
                    $target_user = $u_stmt->get_result()->fetch_assoc();
                    $u_stmt->close();

                    if ($target_user) {
                        createNotification($conn, (int)$target_user['user_id'], $admin_id, 'review_reply', $review_id);
                    }
                }
                $stmt->close();
            }
            break;

        case 'delete_review':
            $review_id = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
            if ($review_id) {
                $stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
                $stmt->bind_param("i", $review_id);
                $stmt->execute();
                $stmt->close();
            }
            break;

        case 'resolve_report':
            $report_id = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
            $status = $_POST['status'] ?? '';
            
            if ($report_id && in_array($status, ['resolved', 'dismissed'], true)) {
                $stmt = $conn->prepare("UPDATE reports SET status = ? WHERE report_id = ?");
                $stmt->bind_param("si", $status, $report_id);
                
                if ($stmt->execute() && function_exists('createNotification')) {
                    $r_stmt = $conn->prepare("SELECT user_id FROM reports WHERE report_id = ?");
                    $r_stmt->bind_param("i", $report_id);
                    $r_stmt->execute();
                    $reporter = $r_stmt->get_result()->fetch_assoc();
                    $r_stmt->close();

                    if ($reporter) {
                        createNotification($conn, (int)$reporter['user_id'], $admin_id, 'report_update', $report_id);
                    }
                }
                $stmt->close();
            }
            break;
    }
} catch (Exception $e) {
    error_log("Admin Action Error [Action: {$action}]: " . $e->getMessage());
}

if ($is_ajax) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

header("Location: $redirect");
exit;