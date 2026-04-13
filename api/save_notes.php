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

$userId        = (int)$_SESSION['user_id'];
$jobId         = isset($_POST['job_id'])         && (int)$_POST['job_id']         > 0 ? (int)$_POST['job_id']         : 0;
$applicationId = isset($_POST['application_id']) && (int)$_POST['application_id'] > 0 ? (int)$_POST['application_id'] : 0;
$candidateName = trim($_POST['candidate_name']   ?? '');
$title         = trim($_POST['title']            ?? 'Untitled Meeting');
$rawTranscript = trim($_POST['raw_transcript']   ?? '');
$userNotes     = trim($_POST['user_notes']       ?? '');
$aiSummary     = trim($_POST['ai_summary']       ?? '');
$duration      = (int)($_POST['duration_seconds'] ?? 0);

// "iiissssi i" = 9 chars for 9 params:
// user_id(i) job_id(i) application_id(i) candidate_name(s) title(s) raw_transcript(s) user_notes(s) ai_summary(s) duration_seconds(i)
$stmt = $conn->prepare("
    INSERT INTO meeting_notes
        (user_id, job_id, application_id, candidate_name, title, raw_transcript, user_notes, ai_summary, duration_seconds)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("iiisssssi",
    $userId,
    $jobId,
    $applicationId,
    $candidateName,
    $title,
    $rawTranscript,
    $userNotes,
    $aiSummary,
    $duration
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "note_id" => $stmt->insert_id]);
} else {
    echo json_encode(["success" => false, "error" => $stmt->error]);
}

$stmt->close();
exit;