<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard_hr.php");
    exit();
}
csrf_validate();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'HR_Manager') {
    header("Location: ../dashboard_hr.php");
    exit();
}

$application_id = $_POST['application_id'] ?? null;
$status = $_POST['status'] ?? null;
$job_id = $_POST['job_id'] ?? null;
$user_id = $_SESSION['user_id'];

if (!$application_id || !$status || !$job_id) {
    header("Location: ../dashboard_hr.php");
    exit();
}

$allowed = ['Pending','Interviewing','Offered','Rejected'];

if (!in_array($status, $allowed)) {
    header("Location: ../job_applications.php?job_id=$job_id&error=InvalidStatus");
    exit();
}

$sql = "
SELECT a.status AS old_status
FROM applications a
JOIN jobs j ON a.job_id = j.job_id
WHERE a.application_id = ? AND j.user_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $application_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../job_applications.php?job_id=$job_id&error=Unauthorized");
    exit();
}

$row = $result->fetch_assoc();
$old_status = $row['old_status'];

$stmt->close();

$update = $conn->prepare("
UPDATE applications
SET status = ?
WHERE application_id = ?
");

$update->bind_param("si", $status, $application_id);
$update->execute();
$update->close();

$history = $conn->prepare("
INSERT INTO stage_history (application_id, old_status, new_status, changed_by)
VALUES (?, ?, ?, ?)
");

$history->bind_param("issi", $application_id, $old_status, $status, $user_id);
$history->execute();
$history->close();

header("Location: ../job_applications.php?job_id=$job_id&success=StatusUpdated");
exit();
?>