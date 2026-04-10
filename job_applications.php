<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();


// job_applications.php — HR Application Management
// Owner: Qiushi

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
$summaryText = $_GET['summary'] ?? '';
$summaryAppId = isset($_GET['app_id']) ? (int)$_GET['app_id'] : 0;

if ($jobId <= 0) {
  http_response_code(400);
  $pageError = "Missing or invalid job_id.";
}

// Badge colors
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

// For dropdown/filter display
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

// ── FIX: feedback_summary artik her iki sorguda da var ──────────────────────
$applications = [];
if (!$pageError) {
  if ($filterStatus === 'All') {
    $stmt = $conn->prepare("
      SELECT application_id, job_id, user_id, full_name, email, phone,
             cover_letter, skills, status, feedback_summary, applied_at, updated_at
      FROM applications
      WHERE job_id = ? AND deleted_at IS NULL
      ORDER BY applied_at DESC
    ");
    $stmt->bind_param("i", $jobId);
  } else {
    // DUZELTME: feedback_summary bu sorguda da eklendi
    $stmt = $conn->prepare("
      SELECT application_id, job_id, user_id, full_name, email, phone,
             cover_letter, skills, status, feedback_summary, applied_at, updated_at
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

    /* ── AI Analysis Modal ─────────────────────────────────────────────────── */
    .ai-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1000; align-items:center; justify-content:center; }
    .ai-modal-overlay.open { display:flex; }
    .ai-modal { background:#fff; border-radius:16px; padding:28px 32px; max-width:580px; width:92%; box-shadow:0 8px 32px rgba(0,0,0,0.2); position:relative; max-height:80vh; overflow-y:auto; }
    .ai-modal-close { position:absolute; top:14px; right:18px; background:none; border:none; font-size:20px; cursor:pointer; color:#64748b; }
    .ai-modal-title { font-size:16px; font-weight:700; margin-bottom:16px; color:#1e293b; }
    .ai-modal-body { font-size:14px; color:#374151; line-height:1.8; white-space:pre-wrap; }
    .ai-btn { display:inline-flex; align-items:center; gap:4px; margin-top:6px; padding:4px 11px; border-radius:8px; border:1px solid #2563eb; background:#eff6ff; color:#2563eb; font-size:12px; font-weight:600; cursor:pointer; }
    .ai-btn:hover { background:#dbeafe; }
    .rec-strong { color:#166534; font-weight:700; }
    .rec-hire   { color:#1d4ed8; font-weight:700; }
    .rec-maybe  { color:#854d0e; font-weight:700; }
    .rec-no     { color:#991b1b; font-weight:700; }

    /* ── Feedback Box ──────────────────────────────────────────────────────── */
    .feedback-box { margin-top: 10px; }
    .feedback-box textarea {
      width: 100%;
      min-height: 100px;
      border: 1px solid #d1d5db;
      border-radius: 10px;
      padding: 10px;
      font-size: 13px;
      resize: vertical;
      box-sizing: border-box;
    }
    .feedback-btn {
      margin-top: 8px;
      padding: 8px 12px;
      border: 1px solid #0f766e;
      background: #ccfbf1;
      color: #0f766e;
      border-radius: 8px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
    }
    .feedback-btn:hover { background: #99f6e4; }
    .feedback-summary {
      margin-top: 10px;
      padding: 12px;
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: 10px;
      font-size: 13px;
      line-height: 1.6;
      white-space: pre-wrap;
    }
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
          elseif ($err === 'missing_api_key') echo 'Claude API key is missing.';
          elseif ($err === 'api_failed') echo 'Claude summary request failed.';
          elseif ($err === 'invalid') echo 'Missing application, job, or feedback.';
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
            <option value="All"          <?php echo ($filterStatus === 'All')          ? 'selected' : ''; ?>>All</option>
            <option value="Pending"      <?php echo ($filterStatus === 'Pending')      ? 'selected' : ''; ?>>Pending</option>
            <option value="Interviewing" <?php echo ($filterStatus === 'Interviewing') ? 'selected' : ''; ?>>Interviewing</option>
            <option value="Offered"      <?php echo ($filterStatus === 'Offered')      ? 'selected' : ''; ?>>Offered</option>
            <option value="Rejected"     <?php echo ($filterStatus === 'Rejected')     ? 'selected' : ''; ?>>Rejected</option>
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
                <th>Interview Feedback</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($applications as $a): ?>
                <?php
                  $dbStatus    = (string)$a['status'];
                  $badge       = badge_for_db_status($dbStatus);
                  $appliedDate = $a['applied_at'] ? date('M j, Y', strtotime($a['applied_at'])) : '-';
                  $cl          = trim((string)($a['cover_letter'] ?? ''));
                  $clShort     = ($cl !== '' && strlen($cl) > 80) ? substr($cl, 0, 80) . '...' : $cl;
                  // feedback_summary null kontrolu
                  $feedbackSummary = $a['feedback_summary'] ?? '';
                ?>
                <tr>
                  <!-- Applicant -->
                  <td style="font-weight: 600;"><?php echo e($a['full_name']); ?></td>

                  <!-- Email -->
                  <td style="color: #475569;"><?php echo e($a['email']); ?></td>

                  <!-- Match Score -->
                  <td class="match-cell">
                    <?php
                      $score = $matchScores[$a['application_id']] ?? null;
                      if ($score && ($score['applicant_count'] > 0)):
                        $pct      = round($score['composite'] * 100);
                        $cls      = $pct >= 70 ? 'match-high' : ($pct >= 40 ? 'match-mid' : 'match-low');
                        $barColor = $pct >= 70 ? '#22c55e' : ($pct >= 40 ? '#eab308' : '#ef4444');
                    ?>
                      <span class="match-badge <?= $cls ?>"><?= $pct ?>%</span>
                      <div class="match-bar">
                        <div class="match-bar-fill" style="width:<?= $pct ?>%; background:<?= $barColor ?>;"></div>
                      </div>
                      <div class="match-details">
                        <?= count($score['matched']) ?>/<?= $score['job_count'] ?> skills
                      </div>
                    <?php else: ?>
                      <span class="match-badge match-none">N/A</span>
                      <div class="match-details">No skills provided</div>
                    <?php endif; ?>

                    <!-- AI Analysis Button -->
                    <button class="ai-btn"
                        data-appid="<?= (int)$a['application_id'] ?>"
                        data-name="<?= e($a['full_name']) ?>"
                        onclick="openAiModal(this)">
                      🤖 AI Analysis
                    </button>
                  </td>

                  <!-- Status -->
                  <td>
                    <div class="app-status-cell">
                      <span class="badge" style="background:<?php echo e($badge['bg']); ?>; color:<?php echo e($badge['fg']); ?>;">
                        <?php echo e($badge['label']); ?>
                      </span>
                      <form method="POST" action="api/update_application_status.php" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="application_id" value="<?php echo (int)$a['application_id']; ?>">
                        <input type="hidden" name="job_id"         value="<?php echo (int)$jobId; ?>">
                        <input type="hidden" name="return_status"  value="<?php echo e($filterStatus); ?>">
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

                  <!-- Applied Date -->
                  <td style="color: #64748b; font-size: 13px;"><?php echo e($appliedDate); ?></td>

                  <!-- Cover Letter -->
                  <td class="cover-cell">
                    <?php if ($cl === ''): ?>
                      <span style="color: #94a3b8;">-</span>
                    <?php else: ?>
                      <span><?php echo e($clShort); ?></span>
                    <?php endif; ?>
                  </td>

                  <!-- Interview Feedback (Qiushi) -->
                  <td style="min-width: 320px;">
                    <div class="feedback-box">
                     <?php if (!empty($a['feedback_summary'])): ?>

                      <div class="feedback-summary">
                        <?php echo htmlspecialchars($a['feedback_summary']); ?>
                      </div>

                      <form method="POST" action="api/edit_feedback_summary.php">
                        <input type="hidden" name="application_id" value="<?php echo (int)$a['application_id']; ?>">
                        <input type="hidden" name="job_id" value="<?php echo (int)$jobId; ?>">

                        <button type="submit" class="feedback-btn">Edit</button>
                      </form>

                    <?php else: ?>

                       <form method="POST" action="api/summarize_feedback.php">
                        <input type="hidden" name="application_id" value="<?php echo (int)$a['application_id']; ?>">
                        <input type="hidden" name="job_id" value="<?php echo (int)$jobId; ?>">

                        <textarea name="feedback" placeholder="Write interview feedback here..."></textarea>

                        <button type="submit" class="feedback-btn">Summarize Feedback</button>
                      </form>

                    <?php endif; ?>
                    </div>
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

<!-- AI Analysis Modal -->
<div class="ai-modal-overlay" id="aiModal">
  <div class="ai-modal">
    <button class="ai-modal-close" id="aiModalClose" aria-label="Close">✕</button>
    <div class="ai-modal-title" id="aiModalTitle">AI Analysis</div>
    <div class="ai-modal-body"  id="aiModalBody">Analyzing…</div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
  // Status dropdown auto-submit
  document.querySelectorAll('select[data-autosubmit="1"]').forEach(function(sel) {
    sel.addEventListener('change', function() {
      const form = sel.closest('form');
      if (form) form.submit();
    });
  });

  // ── AI Analysis Modal ────────────────────────────────────────────────────
  const modal      = document.getElementById('aiModal');
  const modalBody  = document.getElementById('aiModalBody');
  const modalTitle = document.getElementById('aiModalTitle');

  document.getElementById('aiModalClose').addEventListener('click', () => modal.classList.remove('open'));
  modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('open'); });
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') modal.classList.remove('open'); });

  function openAiModal(btn) {
    const appId = btn.dataset.appid;
    const name  = btn.dataset.name;

    modalTitle.textContent = '🤖 AI Analysis — ' + name;
    modalBody.innerHTML = '<em style="color:#64748b;">Analyzing candidate… please wait.</em>';
    modal.classList.add('open');

    fetch('api/ai_score.php?application_id=' + encodeURIComponent(appId))
      .then(res => {
        if (!res.ok) throw new Error('Server error ' + res.status);
        return res.json();
      })
      .then(data => {
        if (data.success) {
          let text = data.message || '';
          text = text.replace(/Strong Hire/g, '<span class="rec-strong">Strong Hire</span>');
          text = text.replace(/No Hire/g,     '<span class="rec-no">No Hire</span>');
          text = text.replace(/\bMaybe\b/g,   '<span class="rec-maybe">Maybe</span>');
          text = text.replace(/\bHire\b/g,    '<span class="rec-hire">Hire</span>');
          modalBody.innerHTML = '<div style="white-space:pre-wrap;line-height:1.8;">' + text + '</div>';
        } else {
          modalBody.innerHTML = '<span style="color:#991b1b;">⚠️ ' + (data.error || 'Could not load analysis.') + '</span>';
        }
      })
      .catch(() => {
        modalBody.innerHTML = '<span style="color:#991b1b;">⚠️ Failed to connect. Check your API config or try again.</span>';
      });
  }
</script>

</body>
</html>