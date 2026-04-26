<?php
session_start();
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/helpers.php";

$job_id = max(0, (int)($_GET['id'] ?? 0));

if ($job_id <= 0) {
    die("Invalid job ID.");
} 

$stmt = $conn->prepare("
    SELECT
        j.job_id,
        j.user_id AS owner_id,
        j.job_title,
        j.description,
        j.status,
        j.created_at,
        j.onet_soc_code,
        j.location,
        j.experience_level,
        j.salary_range,
        u.full_name AS publisher_name,
        od.title AS onet_title
    FROM jobs j
    LEFT JOIN users u ON u.user_id = j.user_id
    LEFT JOIN occupation_data od ON od.onetsoc_code = j.onet_soc_code
    WHERE j.job_id = ? AND j.deleted_at IS NULL
    LIMIT 1
");

if (!$stmt) {
    error_log("job_detail.php: Prepare failed: " . $conn->error);
    die("Something went wrong. Please try again later.");
}

$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    die("Job not found.");
}

// Non-open jobs should only be visible to the HR user who created them
if ($job['status'] !== 'Open') {
    $role = $_SESSION['role'] ?? '';
    $sessionUserId = $_SESSION['user_id'] ?? 0;
    // Check if this user is the job owner (HR viewing their own job)
    $isOwner = false;
    if ($sessionUserId > 0) {
        $ownerCheck = $conn->prepare("SELECT user_id FROM jobs WHERE job_id = ? AND user_id = ? LIMIT 1");
        $ownerCheck->bind_param("ii", $job_id, $sessionUserId);
        $ownerCheck->execute();
        $isOwner = $ownerCheck->get_result()->num_rows > 0;
        $ownerCheck->close();
    }
    if (!$isOwner) {
        die("This job is no longer available.");
    }
}

// Fetch skills for this job
$techSkills = [];
$genSkills = [];
$skillStmt = $conn->prepare("SELECT skill_name, skill_type FROM job_skills WHERE job_id = ? ORDER BY skill_type, skill_name");
$skillStmt->bind_param("i", $job_id);
$skillStmt->execute();
$skillRes = $skillStmt->get_result();
while ($sk = $skillRes->fetch_assoc()) {
    if ($sk['skill_type'] === 'tech') {
        $techSkills[] = $sk['skill_name'];
    } else {
        $genSkills[] = $sk['skill_name'];
    }
}
$skillStmt->close();

// Determine viewer role and ownership
$viewerRole   = $_SESSION['role'] ?? '';
$viewerId     = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$isHR         = ($viewerRole === 'HR_Manager');
$isJobOwner   = ($isHR && $viewerId > 0 && (int)$job['owner_id'] === $viewerId);
$backUrl      = $isHR ? 'dashboard_hr.php' : 'browse_jobs.php';
$backLabel    = $isHR ? '&larr; Back to Dashboard' : '&larr; Back to Jobs';

// Check if user already applied (only relevant for applicants)
$alreadyApplied = false;
if ($viewerId > 0 && !$isHR) {
    $appCheck = $conn->prepare("SELECT application_id FROM applications WHERE job_id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1");
    $appCheck->bind_param("ii", $job_id, $viewerId);
    $appCheck->execute();
    $alreadyApplied = $appCheck->get_result()->num_rows > 0;
    $appCheck->close();
}

$createdAt = $job['created_at'] ? date('M j, Y', strtotime($job['created_at'])) : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($job['job_title']); ?> - TalentSync</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .detail-container { max-width: 900px; margin: 0 auto; padding: 32px 24px 60px; }
        .detail-back { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 500; color: #64748b; text-decoration: none; margin-bottom: 20px; transition: color 0.15s; }
        .detail-back:hover { color: #0f172a; }
        .detail-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; overflow: hidden; }
        .detail-header { padding: 32px 32px 24px; border-bottom: 1px solid #f1f5f9; }
        .detail-meta { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; margin-bottom: 12px; }
        .detail-badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #dcfce7; color: #166534; }
        .detail-date { font-size: 13px; color: #94a3b8; }
        .detail-title { font-size: 28px; font-weight: 700; color: #0f172a; margin: 0 0 8px; }
        .detail-company { font-size: 15px; color: #64748b; margin: 0 0 8px; }
        .detail-onet { display: inline-block; padding: 4px 10px; background: #f1f5f9; border-radius: 8px; font-size: 13px; color: #475569; }
        .detail-body { padding: 28px 32px; }
        .detail-section { margin-bottom: 28px; }
        .detail-section-title { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0 0 12px; }
        .detail-desc { font-size: 15px; line-height: 1.7; color: #374151; }
        .skill-tags { display: flex; flex-wrap: wrap; gap: 8px; }
        .skill-tag { display: inline-block; padding: 6px 14px; border-radius: 999px; font-size: 13px; font-weight: 500; }
        .skill-tech { background: #dbeafe; color: #1e40af; }
        .skill-gen { background: #dcfce7; color: #166534; }
        .detail-actions { padding: 0 32px 32px; display: flex; gap: 12px; }
        .btn-apply { display: inline-flex; align-items: center; justify-content: center; height: 48px; padding: 0 32px; border-radius: 12px; font-size: 16px; font-weight: 700; text-decoration: none; background: #0f172a; color: #fff; border: none; cursor: pointer; transition: all 0.15s; }
        .btn-apply:hover { background: #1e293b; }
        .btn-applied { background: #e2e8f0; color: #64748b; cursor: default; }
        .btn-applied:hover { background: #e2e8f0; }
        .btn-secondary { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
        .btn-secondary:hover { background: #f1f5f9; }
        .detail-fast-facts { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 18px; padding-top: 18px; border-top: 1px solid #f1f5f9; }
        .detail-fact { display: flex; gap: 10px; align-items: center; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; }
        .detail-fact-icon { font-size: 22px; flex-shrink: 0; }
        .detail-fact-label { font-size: 11px; font-weight: 700; color: #64748b; letter-spacing: 0.5px; text-transform: uppercase; }
        .detail-fact-value { font-size: 14px; font-weight: 700; color: #0f172a; }
        @media (max-width: 700px) {
            .detail-fast-facts { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .detail-header, .detail-body, .detail-actions { padding-left: 20px; padding-right: 20px; }
            .detail-title { font-size: 22px; }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="detail-container">
    <a class="detail-back" href="<?php echo e($backUrl); ?>"><?php echo $backLabel; ?></a>

    <div class="detail-card">
        <div class="detail-header">
            <div class="detail-meta">
                <span class="detail-badge"><?php echo e($job['status']); ?></span>
                <?php if ($createdAt): ?>
                    <span class="detail-date">Posted <?php echo e($createdAt); ?></span>
                <?php endif; ?>
            </div>
            <h1 class="detail-title"><?php echo e($job['job_title']); ?></h1>
            <p class="detail-company"><?php echo e($job['publisher_name'] ?? 'Unknown'); ?></p>
            <?php if ($job['onet_title']): ?>
                <span class="detail-onet"><?php echo e($job['onet_title']); ?> (<?php echo e($job['onet_soc_code']); ?>)</span>
            <?php endif; ?>

            <?php if (!empty($job['location']) || !empty($job['experience_level']) || !empty($job['salary_range'])): ?>
                <div class="detail-fast-facts">
                    <?php if (!empty($job['location'])): ?>
                        <div class="detail-fact">
                            <span class="detail-fact-icon">📍</span>
                            <div>
                                <div class="detail-fact-label">Location</div>
                                <div class="detail-fact-value"><?php echo e($job['location']); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($job['experience_level'])): ?>
                        <div class="detail-fact">
                            <span class="detail-fact-icon">📊</span>
                            <div>
                                <div class="detail-fact-label">Experience</div>
                                <div class="detail-fact-value"><?php echo e($job['experience_level']); ?> level</div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($job['salary_range'])): ?>
                        <div class="detail-fact">
                            <span class="detail-fact-icon">💰</span>
                            <div>
                                <div class="detail-fact-label">Salary</div>
                                <div class="detail-fact-value"><?php echo e($job['salary_range']); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="detail-body">
            <?php if ($job['description']): ?>
                <div class="detail-section">
                    <h2 class="detail-section-title">Job Description</h2>
                    <div class="detail-desc"><?php echo nl2br(e($job['description'])); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($techSkills)): ?>
                <div class="detail-section">
                    <h2 class="detail-section-title">Technology Skills</h2>
                    <div class="skill-tags">
                        <?php foreach ($techSkills as $sk): ?>
                            <span class="skill-tag skill-tech"><?php echo e($sk); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($genSkills)): ?>
                <div class="detail-section">
                    <h2 class="detail-section-title">General Skills</h2>
                    <div class="skill-tags">
                        <?php foreach ($genSkills as $sk): ?>
                            <span class="skill-tag skill-gen"><?php echo e($sk); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="detail-actions">
            <?php if ($isHR): ?>
                <?php if ($isJobOwner): ?>
                    <a href="job_applications.php?job_id=<?php echo (int)$job['job_id']; ?>" class="btn-apply">View Applications</a>
                    <a href="create_job.php?id=<?php echo (int)$job['job_id']; ?>" class="btn-apply btn-secondary">Edit Job</a>
                <?php else: ?>
                    <a href="dashboard_hr.php" class="btn-apply btn-secondary">Back to Dashboard</a>
                <?php endif; ?>
            <?php elseif ($job['status'] !== 'Open'): ?>
                <span class="btn-apply btn-applied">This job is <?php echo e($job['status']); ?></span>
            <?php elseif ($viewerId === 0): ?>
                <a href="login.php?redirect=<?php echo urlencode('apply_job.php?id=' . (int)$job['job_id']); ?>" class="btn-apply">Login to Apply</a>
            <?php elseif ($alreadyApplied): ?>
                <span class="btn-apply btn-applied">Already Applied</span>
            <?php else: ?>
                <a href="apply_job.php?id=<?php echo (int)$job['job_id']; ?>" class="btn-apply">Apply Now</a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>