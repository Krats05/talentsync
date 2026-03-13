<?php
// job_applications.php — HR Application Management
// Owner: Qiushi



session_start();
require_once __DIR__ . "/config/db.php";

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Session compatibility
function get_session_user_id(): ?int {
  if (isset($_SESSION['user_id'])) return (int)$_SESSION['user_id'];
  if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['user_id'])) {
    return (int)$_SESSION['user']['user_id'];
  }
  return null;
}
function get_session_role(): ?string {
  if (isset($_SESSION['role'])) return (string)$_SESSION['role'];
  if (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['role'])) {
    return (string)$_SESSION['user']['role'];
  }
  return null;
}

$userId = get_session_user_id();
$role = get_session_role();

$allowedRoles = ['HR_Manager', 'Admin', 'Recruiter'];
if (!$userId || !$role || !in_array($role, $allowedRoles, true)) {
  header("Location: /talentsync/login.php");
  exit;
}

// Inputs
$jobId = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;

// Filter uses DB values, but only 3 statuses (Shortlisted ignored)
$filterStatus = $_GET['status'] ?? 'All'; // All | Pending | Reviewed | Rejected
$validFilterStatuses = ['All', 'Pending', 'Reviewed', 'Rejected'];
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

// DB status -> UI label + colors (ONLY 3)
function badge_for_db_status(string $dbStatus): array {
  switch ($dbStatus) {
    case 'Pending':
      return ['label' => 'Interviewing', 'bg' => BG_YELLOW, 'fg' => FG_YELLOW];
    case 'Reviewed':
      return ['label' => 'Offered', 'bg' => BG_GREEN, 'fg' => FG_GREEN];
    case 'Rejected':
      return ['label' => 'Rejected', 'bg' => BG_RED, 'fg' => FG_RED];
    default:
      // Shortlisted or anything unexpected is ignored by query; this is just a safe fallback
      return ['label' => $dbStatus, 'bg' => '#e5e7eb', 'fg' => '#111827'];
  }
}

// For dropdown/filter display: DB value -> human label
function label_for_db_status(string $dbStatus): string {
  if ($dbStatus === 'Pending') return 'Interviewing';
  if ($dbStatus === 'Reviewed') return 'Offered';
  if ($dbStatus === 'Rejected') return 'Rejected';
  return $dbStatus;
}

// Ownership check: job belongs to logged-in HR user
$job = null;
if (!$pageError) {
  $stmt = $conn->prepare("
    SELECT job_id, job_title, status, created_at
    FROM jobs
    WHERE job_id = ? AND user_id = ?
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
    // show only Pending/Reviewed/Rejected
    $stmt = $conn->prepare("
      SELECT application_id, job_id, user_id, full_name, email, phone, cover_letter, status, applied_at, updated_at
      FROM applications
      WHERE job_id = ? AND status IN ('Pending','Reviewed','Rejected')
      ORDER BY applied_at DESC
    ");
    $stmt->bind_param("i", $jobId);
  } else {
    $stmt = $conn->prepare("
      SELECT application_id, job_id, user_id, full_name, email, phone, cover_letter, status, applied_at, updated_at
      FROM applications
      WHERE job_id = ? AND status = ?
      ORDER BY applied_at DESC
    ");
    $stmt->bind_param("is", $jobId, $filterStatus);
  }

  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) $applications[] = $row;
  $stmt->close();
}

// Dropdown options (DB values only; Shortlisted removed)
$dbStatusOptions = ['Pending', 'Reviewed', 'Rejected'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Job Applications</title>
  <link rel="stylesheet" href="/talentsync/assets/style.css">
</head>
<body>

<?php
$navbarPath = __DIR__ . "/includes/navbar.php";
if (file_exists($navbarPath)) include $navbarPath;
?>

<main class="footer-container">
  <h1>HR Application Management</h1>

  <div style="margin: 12px 0;">
    <a class="btn btn-white" href="/talentsync/dashboard.php">Back to Dashboard</a>
  </div>

  <?php if ($pageError): ?>
    <p><?php echo e($pageError); ?></p>
  <?php else: ?>

    <?php if ($success === 'StatusUpdated'): ?>
      <div style="margin: 12px 0;">
        <span class="btn btn-white" style="cursor: default;">Status updated</span>
      </div>
    <?php endif; ?>

    <section style="margin: 12px 0;">
      <h2><?php echo e($job['job_title'] ?: 'Untitled Job'); ?></h2>
      <p>
        Job ID: <?php echo (int)$job['job_id']; ?> ·
        Job Status: <?php echo e($job['status']); ?> ·
        Created: <?php echo e($job['created_at'] ?? '-'); ?>
      </p>
    </section>

    <!-- Filter (only 3 DB statuses + All) -->
    <section style="margin: 18px 0;">
      <form method="GET" style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
        <input type="hidden" name="job_id" value="<?php echo (int)$jobId; ?>">

        <label for="status">Filter</label>
        <select id="status" name="status">
          <option value="All" <?php echo ($filterStatus === 'All') ? 'selected' : ''; ?>>All</option>
          <option value="Pending" <?php echo ($filterStatus === 'Pending') ? 'selected' : ''; ?>>Interviewing</option>
          <option value="Reviewed" <?php echo ($filterStatus === 'Reviewed') ? 'selected' : ''; ?>>Offered</option>
          <option value="Rejected" <?php echo ($filterStatus === 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
        </select>

        <button class="btn btn-black" type="submit">Apply</button>
      </form>
    </section>

    <section style="margin: 18px 0;">
      <h2>Applications (<?php echo count($applications); ?>)</h2>

      <?php if (empty($applications)): ?>
        <p>No applications found for this filter.</p>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>Applicant Name</th>
              <th>Email</th>
              <th>Phone</th>
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
              ?>
              <tr>
                <td><?php echo e($a['full_name']); ?></td>
                <td><?php echo e($a['email']); ?></td>
                <td><?php echo e($a['phone'] ?? '-'); ?></td>

                <td>
                  <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <!-- Colored badge -->
                    <span style="
                      display:inline-block;
                      padding:6px 10px;
                      border-radius:999px;
                      background:<?php echo e($badge['bg']); ?>;
                      color:<?php echo e($badge['fg']); ?>;
                      font-weight:600;
                      font-size:13px;
                    ">
                      <?php echo e($badge['label']); ?>
                    </span>

                    <!-- Inline dropdown (DB values only; Shortlisted ignored) -->
                    <form method="POST" action="/talentsync/api/update_application_status.php">
                      <input type="hidden" name="application_id" value="<?php echo (int)$a['application_id']; ?>">
                      <input type="hidden" name="job_id" value="<?php echo (int)$jobId; ?>">
                      <input type="hidden" name="return_status" value="<?php echo e($filterStatus); ?>">

                      <select name="status" data-autosubmit="1">
                        <?php foreach ($dbStatusOptions as $opt): ?>
                          <option value="<?php echo e($opt); ?>" <?php echo ($dbStatus === $opt) ? 'selected' : ''; ?>>
                            <?php echo e(label_for_db_status($opt)); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </form>
                  </div>
                </td>

                <td><?php echo e($a['applied_at'] ?? '-'); ?></td>

                <td>
                  <?php
                    $cl = trim((string)($a['cover_letter'] ?? ''));
                    echo $cl === '' ? '-' : e($cl);
                  ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

  <?php endif; ?>
</main>

<?php
$footerPath = __DIR__ . "/includes/footer.php";
if (file_exists($footerPath)) include $footerPath;
?>

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