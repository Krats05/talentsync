<?php
// api/save_job.php
session_start();
require_once '../config/db.php';

// 1. Session Guard: Check if user is logged in 
// (Matches the logic used in create_job.php)
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $user_id = (int)$_SESSION['user']['user_id'];
} elseif (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
} else {
    header('Location: ../login.php?error=not_logged_in');
    exit;
}

// 2. Only process the data if the form was actually submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Check if we are updating an existing job or creating a new one
    $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
    
    $job_title = trim($_POST['job_title'] ?? '');
    $onet_soc_code = trim($_POST['onet_soc_code'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = isset($_POST['status']) ? $_POST['status'] : 'Draft';

    if (empty($job_title)) {
        die("Error: Job title is required.");
    }

    // 3. Insert or Update the Jobs Table
    if ($job_id > 0) {
        // UPDATE EXISTING JOB
        $update_sql = "UPDATE jobs SET job_title = ?, onet_soc_code = ?, description = ?, status = ? WHERE job_id = ? AND user_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ssssii", $job_title, $onet_soc_code, $description, $status, $job_id, $user_id);
        $stmt->execute();
        $stmt->close();
        
        // Delete the old skills so we can replace them with the fresh list
        $stmt_del = $conn->prepare("DELETE FROM job_skills WHERE job_id = ?");
        $stmt_del->bind_param("i", $job_id);
        $stmt_del->execute();
        $stmt_del->close();
    } else {
        // CREATE NEW JOB
        $insert_job_sql = "INSERT INTO jobs (user_id, job_title, onet_soc_code, description, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_job_sql);
        $stmt->bind_param("issss", $user_id, $job_title, $onet_soc_code, $description, $status);
        
        if ($stmt->execute()) {
            $job_id = $conn->insert_id; // Get the ID of the job we just created
        } else {
            die("Error saving job: " . $conn->error);
        }
        $stmt->close();
    }

    // 4. Insert the new categorized skills into the 'job_skills' table
    
    // Insert Technology Skills
    if (isset($_POST['tech_skills']) && is_array($_POST['tech_skills'])) {
        $stmt_tech = $conn->prepare("INSERT INTO job_skills (job_id, skill_name, skill_type, source) VALUES (?, ?, 'tech', 'ONET_Default')");
        foreach ($_POST['tech_skills'] as $skill) {
            $stmt_tech->bind_param("is", $job_id, $skill);
            $stmt_tech->execute();
        }
        $stmt_tech->close();
    }

    // Insert General Skills
    if (isset($_POST['general_skills']) && is_array($_POST['general_skills'])) {
        $stmt_gen = $conn->prepare("INSERT INTO job_skills (job_id, skill_name, skill_type, source) VALUES (?, ?, 'general', 'ONET_Default')");
        foreach ($_POST['general_skills'] as $skill) {
            $stmt_gen->bind_param("is", $job_id, $skill);
            $stmt_gen->execute();
        }
        $stmt_gen->close();
    }

    // 5. Success! Redirect back to the dashboard
    header("Location: ../dashboard.php?success=JobSaved");
    exit;
}

$conn->close();
?>