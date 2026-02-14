<?php
// api/save_job.php
session_start();
require_once '../config/db.php';

// 1. Check if user is actually logged in (Ozge's login script should set this)
if (!isset($_SESSION['user_id'])) {
    // If not logged in, kick them back to the login page
    header("Location: ../login.php?error=not_logged_in");
    exit;
}

// 2. Only process the data if the form was actually submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $job_title = $_POST['job_title'];
    $onet_soc_code = $_POST['onet_soc_code'];
    $description = $_POST['description'];
    
    // Assume skills come in as a comma-separated string or an array from the frontend
    $skills_input = isset($_POST['skills']) ? $_POST['skills'] : [];

    // 3. Insert the main job draft into the 'jobs' table
    $insert_job_sql = "INSERT INTO jobs (user_id, job_title, onet_soc_code, description, status) VALUES (?, ?, ?, ?, 'Draft')";
    $stmt = $conn->prepare($insert_job_sql);
    $stmt->bind_param("isss", $user_id, $job_title, $onet_soc_code, $description);
    
    if ($stmt->execute()) {
        $new_job_id = $conn->insert_id; // Get the ID of the job we just created

        // 4. Insert the skills into the 'job_skills' table
        if (!empty($skills_input)) {
            $insert_skill_sql = "INSERT INTO job_skills (job_id, skill_name, source) VALUES (?, ?, 'ONET_Default')";
            $skill_stmt = $conn->prepare($insert_skill_sql);
            
            // If the frontend sends an array of skills, loop through them
            if (is_array($skills_input)) {
                foreach ($skills_input as $skill) {
                    $skill_stmt->bind_param("is", $new_job_id, $skill);
                    $skill_stmt->execute();
                }
            }
            $skill_stmt->close();
        }

        // 5. Success! Redirect back to the dashboard
        header("Location: ../dashboard.php?success=JobSaved");
        exit;
    } else {
        echo "Error saving job: " . $conn->error;
    }

    $stmt->close();
}

$conn->close();
?>