<?php

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit();
}
if (empty(trim($_GET['q'] ?? ''))) {
    echo json_encode([]);
    exit();
}

$current_user_id = (int)$_SESSION['user_id'];
$query = trim($_GET['q']);
$search_term = "%" . $query . "%";

$stmt = $conn->prepare("
    SELECT public_user_id, username, channel_name, avatar_url 
    FROM users 
    WHERE 
        (username LIKE ? OR (channel_name IS NOT NULL AND channel_name LIKE ?))
        AND user_id != ?
    LIMIT 10
");
$stmt->bind_param("ssi", $search_term, $search_term, $current_user_id);
$stmt->execute();
$result = $stmt->get_result();

$channels = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode($channels);
?>