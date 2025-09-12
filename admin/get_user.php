<?php
include '../db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo json_encode(null);
    exit();
}

$stmt = $conn->prepare("SELECT user_id, username, email, phone_number, user_type, status, face_encoding FROM users WHERE user_id = ? AND deleted_at IS NULL");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

echo json_encode($user ?: null);
