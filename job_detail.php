<?php
session_start();
require_once __DIR__ . "/config/db.php";

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$job_id = max(0, (int)($_GET['id'] ?? 0));

if ($job_id <= 0) {
    die("Invalid job ID.");
} 

$stmt = $conn->prepare("
    SELECT
        j.job_id,
        j.job_title,
        j.description,
        j.status,
        j.created_at,
        j.onet_soc_code,
        u.full_name AS publisher_name,
        od.title AS onet_title
    FROM jobs j
    LEFT JOIN users u ON u.user_id = j.user_id
    LEFT JOIN occupation_data od ON od.onetsoc_code = j.onet_soc_code
    WHERE j.job_id = ?
    LIMIT 1
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    die("Job not found.");
}

$navbarPath = __DIR__ . "/includes/navbar.php";
if (file_exists($navbarPath)) include $navbarPath;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($job['job_title']); ?> - TalentSync</title>

    <link rel="stylesheet" href="/talentsync/assets/style.css">
    <link rel="stylesheet" href="/talentsync/assets/auth.css">
</head>
<body>

<main style="max-width: 900px; margin: 40px auto; padding: 0 20px;">
    <a href="browse_jobs.php">← Back to Jobs</a>

    <h1><?php echo e($job['job_title']); ?></h1>

    <p><strong>Publisher:</strong> <?php echo e($job['publisher_name'] ?? 'Unknown'); ?></p>
    <p><strong>Status:</strong> <?php echo e($job['status']); ?></p>
    <p><strong>O*NET Title:</strong> <?php echo e($job['onet_title'] ?? '-'); ?></p>
    <p><strong>O*NET Code:</strong> <?php echo e($job['onet_soc_code']); ?></p>
    <p><strong>Created:</strong> <?php echo e($job['created_at']); ?></p>

    <h3>Description</h3>
    <p><?php echo nl2br(e($job['description'] ?? 'No description available.')); ?></p>

    <a href="apply_job.php?id=<?php echo (int)$job['job_id']; ?>" class="Mbtn Mbtn-black">
    Apply Now
    </a>
</main>

<?php
$footerPath = __DIR__ . "/includes/footer.php";
if (file_exists($footerPath)) include $footerPath;
?>

</body>
</html>