<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "error" => "Invalid request"]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$noteId = isset($_POST['note_id']) ? (int)$_POST['note_id'] : 0;

if ($noteId <= 0) {
    echo json_encode(["success" => false, "error" => "Invalid note_id"]);
    exit;
}

// Sadece kendi notunu silebilir
$stmt = $conn->prepare("DELETE FROM meeting_notes WHERE note_id = ? AND user_id = ?");
$stmt->bind_param("ii", $noteId, $userId);

if ($stmt->execute() && $stmt->affected_rows > 0) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => "Delete failed or not authorized"]);
}

$stmt->close();
exit;