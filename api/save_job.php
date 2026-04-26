<?php
// api/save_job.php
session_start();
require_once '../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';

// 1. Session Guard: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php?error=not_logged_in');
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// 2. Only process the data if the form was actually submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    
    // Check if we are updating an existing job or creating a new one
    $job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
    
    $job_title       = trim($_POST['job_title'] ?? '');
    $onet_soc_code   = trim($_POST['onet_soc_code'] ?? '');
    $description     = trim($_POST['description'] ?? '');
    $status          = $_POST['status'] ?? 'Draft';

    // New fields (Option B alignment with applicant questionnaire)
    $location         = trim($_POST['location'] ?? '');
    $experience_level = trim($_POST['experience_level'] ?? '');
    $salary_range     = trim($_POST['salary_range'] ?? '');

    // Whitelist enums to keep DB clean
    if (!in_array($location,         ['Remote', 'On-site', 'Hybrid'], true)) $location = null;
    if (!in_array($experience_level, ['Entry', 'Mid', 'Senior'],      true)) $experience_level = null;
    if ($salary_range === '') $salary_range = null;

    if (empty($job_title)) {
        die("Error: Job title is required.");
    }

    // 3. Insert or Update the Jobs Table
    if ($job_id > 0) {
        // UPDATE EXISTING JOB
        $update_sql = "UPDATE jobs
                       SET job_title = ?, onet_soc_code = ?, description = ?, status = ?,
                           location = ?, experience_level = ?, salary_range = ?
                       WHERE job_id = ? AND user_id = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sssssssii",
            $job_title, $onet_soc_code, $description, $status,
            $location, $experience_level, $salary_range,
            $job_id, $user_id
        );
        $stmt->execute();
        $stmt->close();

        // Delete the old skills so we can replace them with the fresh list
        $stmt_del = $conn->prepare("DELETE FROM job_skills WHERE job_id = ?");
        $stmt_del->bind_param("i", $job_id);
        $stmt_del->execute();
        $stmt_del->close();
    } else {
        // CREATE NEW JOB
        $insert_job_sql = "INSERT INTO jobs
                           (user_id, job_title, onet_soc_code, description, status,
                            location, experience_level, salary_range)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_job_sql);
        $stmt->bind_param("isssssss",
            $user_id, $job_title, $onet_soc_code, $description, $status,
            $location, $experience_level, $salary_range
        );

        if ($stmt->execute()) {
            $job_id = $conn->insert_id;
        } else {
            error_log("save_job.php: Error saving job: " . $conn->error);
            header("Location: ../dashboard_hr.php?error=SaveFailed");
            exit;
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
    header("Location: ../dashboard_hr.php?success=JobSaved");
    exit;
}

$conn->close();
?>