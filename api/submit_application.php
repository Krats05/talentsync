<?php
// api/submit_application.php
session_start();
require_once __DIR__ . '/../config/db.php';

// 1. Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../browse_jobs.php");
    exit;
}

// 2. Session guard: must be logged in as Applicant
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Applicant') {
    header("Location: ../login.php");
    exit;
}

$user_id   = (int) $_SESSION['user_id'];
$job_id    = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;
$full_name = trim($_POST['full_name'] ?? '');
$email     = strtolower(trim($_POST['email'] ?? ''));
$phone     = trim($_POST['phone'] ?? '');
$cover_letter = trim($_POST['cover_letter'] ?? '');

// 3. Validate required fields
if ($job_id <= 0 || $full_name === '' || $email === '') {
    header("Location: ../apply_job.php?job_id=$job_id&error=MissingFields");
    exit;
}

// 4. Validate job exists and status = 'Open'
$stmt = $conn->prepare("SELECT job_id FROM jobs WHERE job_id = ? AND status = 'Open'");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: ../browse_jobs.php?error=JobNotAvailable");
    exit;
}
$stmt->close();

// 5. Check for duplicate application (job_id + user_id)
$stmt = $conn->prepare("SELECT application_id FROM applications WHERE job_id = ? AND user_id = ?");
$stmt->bind_param("ii", $job_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $stmt->close();
    header("Location: ../apply_job.php?job_id=$job_id&error=AlreadyApplied");
    exit;
}
$stmt->close();

// 6. INSERT application
$stmt = $conn->prepare("
    INSERT INTO applications (job_id, user_id, full_name, email, phone, cover_letter)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("iissss", $job_id, $user_id, $full_name, $email, $phone, $cover_letter);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    header("Location: ../dashboard_applicant.php?success=ApplicationSubmitted");
    exit;
} else {
    $stmt->close();
    $conn->close();
    header("Location: ../apply_job.php?job_id=$job_id&error=SubmitFailed");
    exit;
}
?>
