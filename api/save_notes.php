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
$title = trim($_POST['title'] ?? 'Untitled Meeting');
$rawTranscript = trim($_POST['raw_transcript'] ?? '');
$userNotes = trim($_POST['user_notes'] ?? '');
$aiSummary = trim($_POST['ai_summary'] ?? '');
$duration = (int)($_POST['duration_seconds'] ?? 0);

$stmt = $conn->prepare("
    INSERT INTO meeting_notes (user_id, title, raw_transcript, user_notes, ai_summary, duration_seconds)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("issssi", $userId, $title, $rawTranscript, $userNotes, $aiSummary, $duration);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "note_id" => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        "success" => false,
        "error" => "Save failed"
    ]);
}

$stmt->close();
exit;