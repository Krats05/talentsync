<?php
// job_applications.php — HR Application Management
// Owner: Qiushi



session_start();
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/helpers.php";

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$role = $_SESSION['role'] ?? null;

$allowedRoles = ['HR_Manager', 'Admin', 'Recruiter'];
if (!$userId || !$role || !in_array($role, $allowedRoles, true)) {
  header("Location: /talentsync/login.php");
  exit;
}

// Inputs
$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// Filter by status
$filterStatus = $_GET['status'] ?? 'All';
$validFilterStatuses = APP_FILTER_STATUSES;
if (!in_array($filterStatus, $validFilterStatuses, true)) $filterStatus = 'All';

$success = $_GET['success'] ?? '';
$pageError = null;

if ($jobId <= 0) {
  http_response_code(400);
  $pageError = "Missing or invalid job_id.";
}

// Badge colors (from your table)
const BG_YELLOW = '#fef9c3';
const FG_YELLOW = '#854d0e';
const BG_GREEN  = '#dcfce7';
const FG_GREEN  = '#166534';
const BG_RED    = '#fee2e2';
const FG_RED    = '#991b1b';

// DB status -> UI label + colors
function badge_for_db_status(string $dbStatus): array {
  switch ($dbStatus) {
    case 'Pending':
      return ['label' => 'Pending', 'bg' => '#e5e7eb', 'fg' => '#111827'];
    case 'Interviewing':
      return ['label' => 'Interviewing', 'bg' => BG_YELLOW, 'fg' => FG_YELLOW];
    case 'Offered':
      return ['label' => 'Offered', 'bg' => BG_GREEN, 'fg' => FG_GREEN];
    case 'Rejected':
      return ['label' => 'Rejected', 'bg' => BG_RED, 'fg' => FG_RED];
    default:
      return ['label' => $dbStatus, 'bg' => '#e5e7eb', 'fg' => '#111827'];
  }
}

// For dropdown/filter display: DB value -> human label
function label_for_db_status(string $dbStatus): string {
  return $dbStatus;
}

// Ownership check: job belongs to logged-in HR user
$job = null;
if (!$pageError) {
  $stmt = $conn->prepare("
    SELECT job_id, job_title, status, created_at
    FROM jobs
    WHERE job_id = ? AND user_id = ? AND deleted_at IS NULL
    LIMIT 1
  ");
  $stmt->bind_param("ii", $jobId, $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  $job = $res->fetch_assoc();
  $stmt->close();

  if (!$job) {
    http_response_code(403);
    $pageError = "Access denied: this job does not belong to your account (or it does not exist).";
  }
}

// Fetch applications (ignore Shortlisted entirely)
$applications = [];
if (!$pageError) {
  if ($filterStatus === 'All') {
    $stmt = $conn->prepare("
      SELECT application_id, job_id, user_id, full_name, email, phone, cover_letter, skills, status, applied_at, updated_at
      FROM applications
      WHERE job_id = ? AND deleted_at IS NULL
      ORDER BY applied_at DESC
    ");
    $stmt->bind_param("i", $jobId);
  } else {
    $stmt = $conn->prepare("
      SELECT application_id, job_id, user_id, full_name, email, phone, cover_letter, skills, status, applied_at, updated_at
      FROM applications
      WHERE job_id = ? AND status = ? AND deleted_at IS NULL
      ORDER BY applied_at DESC
    ");
    $stmt->bind_param("is", $jobId, $filterStatus);
  }

  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $applications[] = $row;
  $stmt->close();
}

// ── Skill Match Scoring ──────────────────────────────────────────────────────
// Uses Jaccard Similarity + Weighted O*NET Importance scoring
require_once __DIR__ . '/api/skill_match.php';

$matchScores = [];
if (!$pageError && !empty($applications)) {
    foreach ($applications as $a) {
        $skills = $a['skills'] ?? '';
        $matchScores[$a['application_id']] = calculate_match_score($jobId, $skills, $conn);
    }
}

// Fetch job skills for display
$jobSkills = [];
if (!$pageError) {
    $sk_stmt = $conn->prepare("SELECT skill_name, skill_type FROM job_skills WHERE job_id = ? ORDER BY skill_type, skill_name");
    $sk_stmt->bind_param("i", $jobId);
    $sk_stmt->execute();
    $sk_res = $sk_stmt->get_result();
    while ($sk = $sk_res->fetch_assoc()) $jobSkills[] = $sk;
    $sk_stmt->close();
}

// Dropdown options for status update
$dbStatusOptions = APP_STATUSES;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Job Applications - TalentSync</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/dashboard_hr.css">
  <style>
    .app-status-cell { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .status-select { height: 34px; padding: 0 8px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 13px; cursor: pointer; outline: none; }
    .status-select:focus { border-color: #2563eb; }
    .cover-cell { max-width: 220px; font-size: 13px; color: #475569; line-height: 1.4; }
    .match-cell { text-align: center; min-width: 90px; }
    .match-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 999px; font-size: 13px; font-weight: 700; }
    .match-high { background: #dcfce7; color: #166534; }
    .match-mid  { background: #fef9c3; color: #854d0e; }
    .match-low  { background: #fee2e2; color: #991b1b; }
    .match-none { background: #f1f5f9; color: #94a3b8; }
    .match-details { font-size: 11px; color: #64748b; margin-top: 2px; }
    .match-bar { width: 100%; height: 6px; background: #e5e7eb; border-radius: 3px; margin-top: 4px; overflow: hidden; }
    .match-bar-fill { height: 100%; border-radius: 3px; transition: width 0.3s; }
    .skill-tag-matched { display: inline-block; padding: 2px 6px; margin: 1px; border-radius: 4px; font-size: 11px; background: #dcfce7; color: #166534; }
    .skill-tag-missing { display: inline-block; padding: 2px 6px; margin: 1px; border-radius: 4px; font-size: 11px; background: #fee2e2; color: #991b1b; }
    .cover-toggle { color: #2563eb; font-size: 12px; font-weight: 600; cursor: pointer; border: none; background: none; padding: 0; }
    .job-info-bar { display: flex; gap: 24px; flex-wrap: wrap; align-items: center; margin-bottom: 8px; }
    .job-info-item { font-size: 13px; color: #64748b; }
    .job-info-item strong { color: #374151; }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="container">
  <header class="page-header">
    <a href="dashboard_hr.php" style="font-size: 14px; color: #64748b; text-decoration: none;">&larr; Back to Dashboard</a>
    <h1 class="page-title" style="margin-top: 12px;">
      <?php echo e($pageError ? 'Applications' : ($job['job_title'] ?: 'Untitled Job')); ?>
    </h1>
  </header>

  <?php if ($pageError): ?>
    <section class="card">
      <p class="muted"><?php echo e($pageError); ?></p>
    </section>
  <?php else: ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="flash flash-error" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 20px;border-radius:12px;margin-bottom:16px;font-size:14px;">
        <?php
          $err = e($_GET['error']);
          if ($err === 'InvalidStatus') echo 'Invalid status value.';
          elseif ($err === 'Unauthorized') echo 'You do not have permission to update this application.';
          else echo 'An error occurred.';
        ?>
      </div>
    <?php endif; ?>
    <?php if ($success === 'StatusUpdated'): ?>
      <div class="flash flash-success">Status updated successfully.</div>
    <?php endif; ?>

    <section class="card" style="margin-bottom: 16px;">
      <div class="job-info-bar">
        <span class="job-info-item"><strong>Job ID:</strong> #<?php echo (int)$job['job_id']; ?></span>
        <span class="job-info-item"><strong>Status:</strong> <?php echo e($job['status']); ?></span>
        <span class="job-info-item"><strong>Created:</strong> <?php echo $job['created_at'] ? date('M j, Y', strtotime($job['created_at'])) : '-'; ?></span>
        <span class="job-info-item"><strong>Applications:</strong> <?php echo count($applications); ?></span>
      </div>

      <form method="GET" class="filters-form">
        <input type="hidden" name="job_id" value="<?php echo (int)$jobId; ?>">
        <div class="filter-item" style="min-width: 140px; flex: 0;">
          <label class="filter-label" for="filter-app-status">Filter by Status</label>
          <select id="filter-app-status" name="status" class="filter-control">
            <option value="All" <?php echo ($filterStatus === 'All') ? 'selected' : ''; ?>>All</option>
            <option value="Pending" <?php echo ($filterStatus === 'Pending') ? 'selected' : ''; ?>>Pending</option>
            <option value="Interviewing" <?php echo ($filterStatus === 'Interviewing') ? 'selected' : ''; ?>>Interviewing</option>
            <option value="Offered" <?php echo ($filterStatus === 'Offered') ? 'selected' : ''; ?>>Offered</option>
            <option value="Rejected" <?php echo ($filterStatus === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
          </select>
        </div>
        <div class="filter-actions">
          <button type="submit" class="btn">Apply</button>
        </div>
      </form>
    </section>

    <section class="card">
      <div class="card-header">
        <h2 class="card-title">Applications (<?php echo count($applications); ?>)</h2>
      </div>

      <?php if (empty($applications)): ?>
        <p class="muted">No applications found for this filter.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="jobs-table">
            <thead>
              <tr>
                <th>Applicant</th>
                <th>Email</th>
                <th>Match Score</th>
                <th>Status</th>
                <th>Applied</th>
                <th>Cover Letter</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($applications as $a): ?>
                <?php
                  $dbStatus = (string)$a['status'];
                  $badge = badge_for_db_status($dbStatus);
                  $appliedDate = $a['applied_at'] ? date('M j, Y', strtotime($a['applied_at'])) : '-';
                  $cl = trim((string)($a['cover_letter'] ?? ''));
                  $clShort = ($cl !== '' && strlen($cl) > 80) ? substr($cl, 0, 80) . '...' : $cl;
                ?>
                <tr>
                  <td style="font-weight: 600;"><?php echo e($a['full_name']); ?></td>
                  <td style="color: #475569;"><?php echo e($a['email']); ?></td>
                  <td class="match-cell">
                    <?php
                      $score = $matchScores[$a['application_id']] ?? null;
                      if ($score && ($score['applicant_count'] > 0)):
                        $pct = round($score['composite'] * 100);
                        $cls = $pct >= 70 ? 'match-high' : ($pct >= 40 ? 'match-mid' : 'match-low');
                        $barColor = $pct >= 70 ? '#22c55e' : ($pct >= 40 ? '#eab308' : '#ef4444');
                    ?>
                      <span class="match-badge <?= $cls ?>"><?= $pct ?>%</span>
                      <div class="match-bar"><div class="match-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div></div>
                      <div class="match-details">
                        <?= count($score['matched']) ?>/<?= $score['job_count'] ?> skills
                      </div>
                    <?php else: ?>
                      <span class="match-badge match-none">N/A</span>
                      <div class="match-details">No skills provided</div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="app-status-cell">
                      <span class="badge" style="background:<?php echo e($badge['bg']); ?>; color:<?php echo e($badge['fg']); ?>;">
                        <?php echo e($badge['label']); ?>
                      </span>
                      <form method="POST" action="api/update_application_status.php" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="application_id" value="<?php echo (int)$a['application_id']; ?>">
                        <input type="hidden" name="job_id" value="<?php echo (int)$jobId; ?>">
                        <input type="hidden" name="return_status" value="<?php echo e($filterStatus); ?>">
                        <select name="status" class="status-select" data-autosubmit="1" aria-label="Update application status">
                          <?php foreach ($dbStatusOptions as $opt): ?>
                            <option value="<?php echo e($opt); ?>" <?php echo ($dbStatus === $opt) ? 'selected' : ''; ?>>
                              <?php echo e($opt); ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </form>
                    </div>
                  </td>
                  <td style="color: #64748b; font-size: 13px;"><?php echo e($appliedDate); ?></td>
                  <td class="cover-cell">
                    <?php if ($cl === ''): ?>
                      <span style="color: #94a3b8;">-</span>
                    <?php else: ?>
                      <span><?php echo e($clShort); ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

  <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
  document.querySelectorAll('select[data-autosubmit="1"]').forEach(function(sel) {
    sel.addEventListener('change', function() {
      const form = sel.closest('form');
      if (form) form.submit();
    });
  });
</script>

</body>
</html>