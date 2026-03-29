<?php

function send_json_response($success, $message, $data = [], $http_code = 200) {
    http_response_code($http_code);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['user_id'])) {
    send_json_response(false, 'Для оценки отзыва необходимо войти в аккаунт.', [], 401);
}

$user_id = $_SESSION['user_id'];
$review_id = filter_input(INPUT_POST, 'review_id', FILTER_VALIDATE_INT);
$reaction_type = $_POST['reaction_type'] ?? '';

if (!$review_id || !in_array($reaction_type, ['helpful', 'unhelpful'])) {
    send_json_response(false, 'Неверные данные запроса.', [], 400);
}

$conn->begin_transaction();
try {
    $stmt = $conn->prepare("SELECT reaction_type FROM review_reactions WHERE user_id = ? AND review_id = ?");
    $stmt->bind_param("ii", $user_id, $review_id);
    $stmt->execute();
    $existing_reaction = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing_reaction) {
        if ($existing_reaction['reaction_type'] === $reaction_type) {
            $stmt_delete = $conn->prepare("DELETE FROM review_reactions WHERE user_id = ? AND review_id = ?");
            $stmt_delete->bind_param("ii", $user_id, $review_id);
            $stmt_delete->execute();
            $stmt_delete->close();
        } else {
            $stmt_update = $conn->prepare("UPDATE review_reactions SET reaction_type = ? WHERE user_id = ? AND review_id = ?");
            $stmt_update->bind_param("sii", $reaction_type, $user_id, $review_id);
            $stmt_update->execute();
            $stmt_update->close();
        }
    } else {
        $stmt_insert = $conn->prepare("INSERT INTO review_reactions (user_id, review_id, reaction_type) VALUES (?, ?, ?)");
        $stmt_insert->bind_param("iis", $user_id, $review_id, $reaction_type);
        $stmt_insert->execute();
        $stmt_insert->close();
    }

    $stmt_count = $conn->prepare("
        UPDATE reviews SET 
        helpful_count = (SELECT COUNT(*) FROM review_reactions WHERE review_id = ? AND reaction_type = 'helpful'),
        unhelpful_count = (SELECT COUNT(*) FROM review_reactions WHERE review_id = ? AND reaction_type = 'unhelpful')
        WHERE review_id = ?
    ");
    $stmt_count->bind_param("iii", $review_id, $review_id, $review_id);
    $stmt_count->execute();
    $stmt_count->close();

    $conn->commit();

    $stmt_new_counts = $conn->prepare("SELECT helpful_count, unhelpful_count FROM reviews WHERE review_id = ?");
    $stmt_new_counts->bind_param("i", $review_id);
    $stmt_new_counts->execute();
    $new_counts = $stmt_new_counts->get_result()->fetch_assoc();
    $stmt_new_counts->close();

    send_json_response(true, 'Ваш голос учтён.', $new_counts);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Reaction handling failed: " . $e->getMessage());
    send_json_response(false, 'Произошла ошибка на сервере.', [], 500);
}