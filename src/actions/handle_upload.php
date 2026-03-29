<?php

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

// --- КОНСТАНТЫ И ЗАВИСИМОСТИ ---
define('ROOT_PATH', dirname(__DIR__, 2));
define('DEFAULT_CATEGORY_ID', 8);
require_once ROOT_PATH . '/src/libs/getid3/getid3.php';

// --- ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ ---

function redirect_with_error($error_code) {
    header('Location: /upload.php?error=' . $error_code);
    exit();
}

function handle_file_upload($file, $allowed_mime_types, $upload_dir_relative) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) { 
        error_log("Upload error code: " . $file['error']); 
        redirect_with_error('upload_failed'); 
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime_type, $allowed_mime_types)) return false;
    
    $upload_dir_full = ROOT_PATH . '/public/' . ltrim($upload_dir_relative, '/');
    if (!file_exists($upload_dir_full)) mkdir($upload_dir_full, 0755, true);
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $new_filename = bin2hex(random_bytes(16)) . '.' . $file_extension;
    $destination_full = $upload_dir_full . '/' . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination_full)) {
        return rtrim($upload_dir_relative, '/') . '/' . $new_filename;
    }
    redirect_with_error('move_failed');
}

// --- ОСНОВНАЯ ЛОГИКА СКРИПТА ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect_with_error('invalid_request');
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    redirect_with_error('invalid_request');
}

if (empty(trim($_POST['title'])) || empty($_FILES['video']['name'])) {
    redirect_with_error('fields');
}

$video_path = null;
$thumbnail_path = null;

try {
    $conn->begin_transaction();

    $user_id = (int)$_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description'] ?? '');
    $visibility = in_array($_POST['visibility'] ?? 'public', ['public', 'unlisted', 'private']) ? $_POST['visibility'] : 'public';
    $allow_comments = isset($_POST['allow_comments']) ? 1 : 0;
    
    $category_id_to_add = filter_input(INPUT_POST, 'main_category_id', FILTER_VALIDATE_INT);
    if (!$category_id_to_add) {
        $category_id_to_add = DEFAULT_CATEGORY_ID;
    } else {
        $stmt_check_cat = $conn->prepare("SELECT category_id FROM categories WHERE category_id = ?");
        $stmt_check_cat->bind_param("i", $category_id_to_add);
        $stmt_check_cat->execute();
        if ($stmt_check_cat->get_result()->num_rows === 0) {
            $category_id_to_add = DEFAULT_CATEGORY_ID;
        }
        $stmt_check_cat->close();
    }

    $thumbnail_path = handle_file_upload($_FILES['thumbnail'], ['image/jpeg', 'image/png', 'image/gif'], 'uploads/thumbnails');
    if ($thumbnail_path === false) redirect_with_error('thumb_type');
    if ($thumbnail_path === null) $thumbnail_path = 'uploads/thumbnails/default.png';
    
    $video_path = handle_file_upload($_FILES['video'], ['video/mp4', 'video/x-matroska', 'video/quicktime', 'video/webm', 'video/x-msvideo'], 'uploads/videos');
    if ($video_path === false) redirect_with_error('type');
    if ($video_path === null) redirect_with_error('fields');

    $getID3 = new getID3;
    $fileInfo = $getID3->analyze(ROOT_PATH . '/public/' . $video_path);
    $duration_seconds = isset($fileInfo['playtime_seconds']) ? round($fileInfo['playtime_seconds']) : 0;

    $playlist_option = $_POST['playlist_option'] ?? 'none';
    $playlist_id_to_add = null;

    if ($playlist_option === 'new' && !empty(trim($_POST['new_playlist_title']))) {
        $new_playlist_title = trim($_POST['new_playlist_title']);
        $stmt_new_pl = $conn->prepare("INSERT INTO playlists (user_id, title, visibility, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt_new_pl->bind_param("iss", $user_id, $new_playlist_title, $visibility);
        if (!$stmt_new_pl->execute()) throw new Exception("Ошибка при создании плейлиста: " . $stmt_new_pl->error);
        $playlist_id_to_add = $conn->insert_id;
        $stmt_new_pl->close();

    } elseif ($playlist_option === 'existing') {
        $playlist_id = filter_input(INPUT_POST, 'playlist_id', FILTER_VALIDATE_INT);
        if ($playlist_id) {
            $stmt_check_pl = $conn->prepare("SELECT visibility FROM playlists WHERE playlist_id = ? AND user_id = ?");
            $stmt_check_pl->bind_param("ii", $playlist_id, $user_id);
            $stmt_check_pl->execute();
            $result = $stmt_check_pl->get_result();
            if ($result->num_rows > 0) {
                $playlist_data = $result->fetch_assoc();
                $playlist_visibility = $playlist_data['visibility'];
                $is_video_private = ($visibility === 'private' || $visibility === 'unlisted');
                if (!($is_video_private && $playlist_visibility === 'public')) {
                    $playlist_id_to_add = $playlist_id;
                }
            }
            $stmt_check_pl->close();
        }
    }

    $public_video_id = generate_unique_id($conn, 'videos', 'public_video_id');

    $stmt_video = $conn->prepare(
        "INSERT INTO videos (user_id, public_video_id, title, description, file_path, thumbnail_url, visibility, status, allow_comments, duration_seconds, upload_date) 
         VALUES (?, ?, ?, ?, ?, ?, ?, 'published', ?, ?, NOW())"
    );
    $stmt_video->bind_param("issssssii", $user_id, $public_video_id, $title, $description, $video_path, $thumbnail_path, $visibility, $allow_comments, $duration_seconds);
    
    if (!$stmt_video->execute()) {
        throw new Exception("Ошибка при вставке видео: " . $stmt_video->error);
    }
    $new_numeric_video_id = $conn->insert_id; 
    $stmt_video->close();

    if ($new_numeric_video_id && $category_id_to_add) {
        $stmt_link_cat = $conn->prepare("INSERT INTO video_categories (video_id, category_id) VALUES (?, ?)");
        $stmt_link_cat->bind_param("ii", $new_numeric_video_id, $category_id_to_add);
        if (!$stmt_link_cat->execute()) throw new Exception("Ошибка при связывании видео с категорией: " . $stmt_link_cat->error);
        $stmt_link_cat->close();
    }

    if ($playlist_id_to_add && $new_numeric_video_id) {
        $stmt_add_to_pl = $conn->prepare("INSERT INTO playlist_videos (playlist_id, video_id) VALUES (?, ?)");
        $stmt_add_to_pl->bind_param("ii", $playlist_id_to_add, $new_numeric_video_id);
        if (!$stmt_add_to_pl->execute()) throw new Exception("Ошибка при добавлении видео в плейлист: " . $stmt_add_to_pl->error);
        $stmt_add_to_pl->close();
    }

    $conn->commit();
    
    header('Location: /watch.php?id=' . $public_video_id . '&upload_success=1');
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log($e->getMessage());
    if ($video_path && file_exists(ROOT_PATH . '/public/' . $video_path)) {
        unlink(ROOT_PATH . '/public/' . $video_path);
    }
    if ($thumbnail_path && $thumbnail_path !== 'uploads/thumbnails/default.png' && file_exists(ROOT_PATH . '/public/' . $thumbnail_path)) {
        unlink(ROOT_PATH . '/public/' . $thumbnail_path);
    }
    redirect_with_error('db_error');
}
?>