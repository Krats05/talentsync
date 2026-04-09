<?php
// api/delete_job.php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// 2. Only accept POST requests with a job_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id'])) {
    csrf_validate();
    $job_id = (int)$_POST['job_id'];
    $user_id = $_SESSION['user_id'];

    // 3. Security Check: Ensure the job actually belongs to this specific user
    $check_sql = "SELECT job_id FROM jobs WHERE job_id = ? AND user_id = ? AND deleted_at IS NULL";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // 4. Soft-delete associated applications (preserve for compliance/history)
        $soft_apps = $conn->prepare("UPDATE applications SET deleted_at = NOW() WHERE job_id = ? AND deleted_at IS NULL");
        $soft_apps->bind_param("i", $job_id);
        $soft_apps->execute();
        $soft_apps->close();

        // 5. Soft-delete the job itself
        $soft_job = $conn->prepare("UPDATE jobs SET deleted_at = NOW() WHERE job_id = ?");
        $soft_job->bind_param("i", $job_id);

        if ($soft_job->execute()) {
            header("Location: ../dashboard_hr.php?success=JobDeleted");
            exit;
        } else {
            error_log("delete_job.php: Error soft-deleting job: " . $conn->error);
            header("Location: ../dashboard_hr.php?error=DeleteFailed");
            exit;
        }
        $soft_job->close();
    } else {
        header("Location: ../dashboard_hr.php?error=Unauthorized");
        exit;
    }
    $stmt->close();
} else {
    header("Location: ../dashboard_hr.php");
    exit;
}
$conn->close();
?>