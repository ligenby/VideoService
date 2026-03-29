<?php
require_once __DIR__ . '/../../src/core/config.php';
header('Content-Type: application/json');

$endpoint = trim($_GET['endpoint'] ?? '', '/');
$endpoint = str_replace('.php', '', $endpoint);

$routes = [
    'add_video_to_playlist' => 'add_video_to_playlist.php',
    'clear_history' => 'clear_history.php',
    'create_channel' => 'create_channel.php',
    'create_playlist' => 'create_playlist.php',
    'delete_from_history' => 'delete_from_history.php',
    'delete_playlist' => 'delete_playlist.php',
    'get_public_playlists' => 'get_public_playlists.php',
    'handle_review' => 'handle_review.php',
    'remove_from_playlist' => 'remove_from_playlist.php',
    'remove_linked_account' => 'remove_linked_account.php',
    'search_channels' => 'search_channels.php',
    'log_search' => 'log_search.php',
    'search_suggestions' => 'search_suggestions.php',
    'not_interested' => 'not_interested.php',
    'switch_account' => 'switch_account.php',
    'toggle_hidden' => 'toggle_hidden.php',
    'report_video' => 'report_video.php',
    'toggle_history_recording' => 'toggle_history_recording.php',
    'toggle_like' => 'toggle_like.php',
    'toggle_subscription' => 'toggle_subscription.php',
    'toggle_watch_later' => 'toggle_watch_later.php',
    'update_watch_later_order' => 'update_watch_later_order.php',
    'update_settings' => 'update_settings.php',
    'update_review' => 'update_review.php',
    'rate_review' => 'rate_review.php',
    'add_comment' => 'add_comment.php',
    'get_comments' => 'get_comments.php',
    'report_comment' => 'report_comment.php',
    'delete_video' => 'delete_video.php',
    'admin_action' => 'admin_handler.php',
    'get_notifications' => 'get_notifications.php',
    'mark_one_read' => 'mark_one_read.php',
    'toggle_comment_like' => 'toggle_comment_like.php',
];

$actionFile = $routes[$endpoint] ?? null;
$actionPath = __DIR__ . '/../../src/actions/' . $actionFile;

if (!$actionFile || !file_exists($actionPath)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'API endpoint not found.']);
    exit();
}

// Централизованная CSRF-защита для POST-запросов
$csrf_exempt_routes = [
    'search_channels', 
    'search_suggestions', 
    'get_public_playlists',
    'get_comments'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !in_array($endpoint, $csrf_exempt_routes)) {
    $post_data = $_POST;
    if (empty($post_data)) {
        $json_data = json_decode(file_get_contents('php://input'), true);
        if ($json_data) {
            $post_data = $json_data;
        }
    }

    if (!isset($post_data['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $post_data['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Ошибка безопасности (CSRF).']);
        exit();
    }
}

require_once $actionPath;
?>