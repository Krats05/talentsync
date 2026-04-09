<?php
// api/submit_application.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';

// 1. Must be POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../browse_jobs.php");
    exit;
}
csrf_validate();

// 2. Session guard: must be logged in as Applicant
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Applicant') {
    header("Location: ../login.php");
    exit;
}

$user_id   = (int) $_SESSION['user_id'];
$job_id    = isset($_POST['job_id']) ? (int) $_POST['job_id'] : 0;

// Build full_name from first_name + last_name (form sends them separately)
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$full_name  = trim($first_name . ' ' . $last_name);

// Fallback: if form sends full_name directly
if ($full_name === '' && isset($_POST['full_name'])) {
    $full_name = trim($_POST['full_name']);
}

$email     = strtolower(trim($_POST['email'] ?? ''));
$phone     = trim($_POST['phone'] ?? '');
$skills    = trim($_POST['skills'] ?? '');
$cover_letter = trim($_POST['cover_letter'] ?? '');

// 3. Validate required fields
if ($job_id <= 0 || $full_name === '' || $email === '') {
    header("Location: ../apply_job.php?id=$job_id&error=MissingFields");
    exit;
}

// 4. Validate job exists and status = 'Open'
$stmt = $conn->prepare("SELECT job_id FROM jobs WHERE job_id = ? AND status = 'Open' AND deleted_at IS NULL");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: ../browse_jobs.php?error=JobNotAvailable");
    exit;
}
$stmt->close();

// 5. Check for duplicate + insert inside a transaction to prevent race condition
$conn->begin_transaction();

try {
    // Lock the row (if exists) to prevent concurrent duplicate inserts
    $stmt = $conn->prepare("SELECT application_id FROM applications WHERE job_id = ? AND user_id = ? AND deleted_at IS NULL FOR UPDATE");
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        $conn->rollback();
        header("Location: ../apply_job.php?id=$job_id&error=AlreadyApplied");
        exit;
    }
    $stmt->close();

    // 6. INSERT application
    $stmt = $conn->prepare("
        INSERT INTO applications (job_id, user_id, full_name, email, phone, cover_letter, skills)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("iisssss", $job_id, $user_id, $full_name, $email, $phone, $cover_letter, $skills);
    $stmt->execute();

    // Added by Kratika — Log initial stage in stage_history
    $app_id = $conn->insert_id;
    $sh = $conn->prepare("INSERT INTO stage_history (application_id, old_status, new_status, changed_by) VALUES (?, NULL, 'Pending', ?)");
    $sh->bind_param("ii", $app_id, $user_id);
    $sh->execute();
    $sh->close();
    $stmt->close();

    $conn->commit();
    $conn->close();
    header("Location: ../dashboard_applicant.php?success=ApplicationSubmitted");
    exit;
} catch (Exception $e) {
    $conn->rollback();
    $conn->close();
    header("Location: ../apply_job.php?id=$job_id&error=SubmitFailed");
    exit;
}
?>
