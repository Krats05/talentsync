<?php
session_start();
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT note_id, title, ai_summary, created_at
    FROM meeting_notes
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$notes = [];

while ($row = $result->fetch_assoc()) {
    $notes[] = $row;
}

$stmt->close();

echo json_encode([
    "success" => true,
    "notes" => $notes
]);
exit;