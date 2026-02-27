<?php
// api/delete_job.php
session_start();
require_once '../config/db.php';

// 1. Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

// 2. Only accept POST requests with a job_id
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['job_id'])) {
    $job_id = (int)$_POST['job_id'];
    $user_id = $_SESSION['user_id'];

    // 3. Security Check: Ensure the job actually belongs to this specific user
    $check_sql = "SELECT job_id FROM jobs WHERE job_id = ? AND user_id = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ii", $job_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // 4. First, delete associated skills from job_skills to prevent orphaned data
        $delete_skills = $conn->prepare("DELETE FROM job_skills WHERE job_id = ?");
        $delete_skills->bind_param("i", $job_id);
        $delete_skills->execute();
        $delete_skills->close();

        // 5. Finally, delete the job itself
        $delete_job = $conn->prepare("DELETE FROM jobs WHERE job_id = ?");
        $delete_job->bind_param("i", $job_id);
        
        if ($delete_job->execute()) {
            // Success! Send them back to the dashboard
            header("Location: ../Dashboard.php?success=JobDeleted");
            exit;
        } else {
            echo "Error deleting job: " . $conn->error;
        }
        $delete_job->close();
    } else {
        echo "Unauthorized access or job not found.";
    }
    $stmt->close();
} else {
    header("Location: ../Dashboard.php");
    exit;
}
$conn->close();
?>