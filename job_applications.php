<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();

require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/csrf.php";
require_once __DIR__ . "/includes/helpers.php";

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$role   = $_SESSION['role'] ?? null;

$allowedRoles = ['HR_Manager', 'Admin', 'Recruiter'];
if (!$userId || !$role || !in_array($role, $allowedRoles, true)) {
    header("Location: /talentsync/login.php");
    exit;
}

$jobId        = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 0;
$filterStatus = $_GET['status'] ?? 'All';
$validFilterStatuses = APP_FILTER_STATUSES;
if (!in_array($filterStatus, $validFilterStatuses, true)) $filterStatus = 'All';

$success   = $_GET['success'] ?? '';
$pageError = null;

if ($jobId <= 0) {
    http_response_code(400);
    $pageError = "Missing or invalid job_id.";
}

const BG_YELLOW = '#fef9c3';
const FG_YELLOW = '#854d0e';
const BG_GREEN  = '#dcfce7';
const FG_GREEN  = '#166534';
const BG_RED    = '#fee2e2';
const FG_RED    = '#991b1b';

function badge_for_db_status(string $dbStatus): array {
    switch ($dbStatus) {
        case 'Pending':      return ['label' => 'Pending',      'bg' => '#e5e7eb', 'fg' => '#111827'];
        case 'Interviewing': return ['label' => 'Interviewing', 'bg' => BG_YELLOW, 'fg' => FG_YELLOW];
        case 'Offered':      return ['label' => 'Offered',      'bg' => BG_GREEN,  'fg' => FG_GREEN];
        case 'Rejected':     return ['label' => 'Rejected',     'bg' => BG_RED,    'fg' => FG_RED];
        default:             return ['label' => $dbStatus,      'bg' => '#e5e7eb', 'fg' => '#111827'];
    }
}

// Ownership check
$job = null;
if (!$pageError) {
    $stmt = $conn->prepare("SELECT job_id, job_title, status, created_at FROM jobs WHERE job_id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("ii", $jobId, $userId);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$job) { http_response_code(403); $pageError = "Access denied."; }
}

// Applications
$applications = [];
if (!$pageError) {
    if ($filterStatus === 'All') {
        $stmt = $conn->prepare("SELECT application_id, job_id, user_id, full_name, email, phone, cover_letter, skills, status, applied_at, updated_at FROM applications WHERE job_id = ? AND deleted_at IS NULL ORDER BY applied_at DESC");
        $stmt->bind_param("i", $jobId);
    } else {
        $stmt = $conn->prepare("SELECT application_id, job_id, user_id, full_name, email, phone, cover_letter, skills, status, applied_at, updated_at FROM applications WHERE job_id = ? AND status = ? AND deleted_at IS NULL ORDER BY applied_at DESC");
        $stmt->bind_param("is", $jobId, $filterStatus);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $applications[] = $row;
    $stmt->close();
}

// Skill Match
require_once __DIR__ . '/api/skill_match.php';
$matchScores = [];
if (!$pageError && !empty($applications)) {
    foreach ($applications as $a) {
        $matchScores[$a['application_id']] = calculate_match_score($jobId, $a['skills'] ?? '', $conn);
    }
}

// Job skills
$jobSkills = [];
if (!$pageError) {
    $sk = $conn->prepare("SELECT skill_name, skill_type FROM job_skills WHERE job_id = ? ORDER BY skill_type, skill_name");
    $sk->bind_param("i", $jobId);
    $sk->execute();
    $skr = $sk->get_result();
    while ($s = $skr->fetch_assoc()) $jobSkills[] = $s;
    $sk->close();
}

// Saved notes for this job
$savedNotes = [];
if (!$pageError) {
    $sn = $conn->prepare("SELECT note_id, title, candidate_name, user_notes, ai_summary, created_at FROM meeting_notes WHERE job_id = ? AND user_id = ? ORDER BY created_at DESC");
    $sn->bind_param("ii", $jobId, $userId);
    $sn->execute();
    $snr = $sn->get_result();
    while ($row = $snr->fetch_assoc()) $savedNotes[] = $row;
    $sn->close();
}

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
    .app-status-cell { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .status-select { height:34px; padding:0 8px; border:1px solid #d1d5db; border-radius:8px; font-size:13px; cursor:pointer; outline:none; }
    .status-select:focus { border-color:#2563eb; }
    .cover-cell { max-width:220px; font-size:13px; color:#475569; line-height:1.4; }
    .match-cell { text-align:center; min-width:90px; }
    .match-badge { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:999px; font-size:13px; font-weight:700; }
    .match-high { background:#dcfce7; color:#166534; }
    .match-mid  { background:#fef9c3; color:#854d0e; }
    .match-low  { background:#fee2e2; color:#991b1b; }
    .match-none { background:#f1f5f9; color:#94a3b8; }
    .match-details { font-size:11px; color:#64748b; margin-top:2px; }
    .match-bar { width:100%; height:6px; background:#e5e7eb; border-radius:3px; margin-top:4px; overflow:hidden; }
    .match-bar-fill { height:100%; border-radius:3px; transition:width 0.3s; }
    .job-info-bar { display:flex; gap:24px; flex-wrap:wrap; align-items:center; margin-bottom:8px; }
    .job-info-item { font-size:13px; color:#64748b; }
    .job-info-item strong { color:#374151; }

    /* AI Modal */
    .ai-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1000; align-items:center; justify-content:center; }
    .ai-modal-overlay.open { display:flex; }
    .ai-modal { background:#fff; border-radius:16px; padding:28px 32px; max-width:580px; width:92%; box-shadow:0 8px 32px rgba(0,0,0,0.2); position:relative; max-height:80vh; overflow-y:auto; }
    .ai-modal-close { position:absolute; top:14px; right:18px; background:none; border:none; font-size:20px; cursor:pointer; color:#64748b; }
    .ai-modal-title { font-size:16px; font-weight:700; margin-bottom:16px; color:#1e293b; }
    .ai-modal-body  { font-size:14px; color:#374151; line-height:1.8; white-space:pre-wrap; }
    .ai-btn { display:inline-flex; align-items:center; gap:4px; margin-top:6px; padding:4px 11px; border-radius:8px; border:1px solid #2563eb; background:#eff6ff; color:#2563eb; font-size:12px; font-weight:600; cursor:pointer; }
    .ai-btn:hover { background:#dbeafe; }
    .rec-strong { color:#166534; font-weight:700; }
    .rec-hire   { color:#1d4ed8; font-weight:700; }
    .rec-maybe  { color:#854d0e; font-weight:700; }
    .rec-no     { color:#991b1b; font-weight:700; }

    /* Saved Notes */
    .notes-divider { border:none; border-top:1px solid #e5e7eb; margin:24px 0 16px; }
    .notes-section-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
    .notes-section-title { font-size:15px; font-weight:600; color:#1e293b; display:flex; align-items:center; gap:8px; }
    .notes-count-badge { font-size:11px; background:#f1f5f9; color:#64748b; border:1px solid #e2e8f0; border-radius:99px; padding:2px 9px; font-weight:500; }
    .add-note-btn { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:600; color:#2563eb; border:1px solid #2563eb; background:#eff6ff; border-radius:8px; padding:6px 13px; text-decoration:none; }
    .add-note-btn:hover { background:#dbeafe; }

    .note-row { display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid #e5e7eb; border-radius:10px; margin-bottom:8px; background:#fff; transition:background 0.15s; }
    .note-row:hover { background:#f8fafc; }
    .note-icon-wrap { width:32px; height:32px; border-radius:7px; background:#e0e7ff; display:flex; align-items:center; justify-content:center; flex-shrink:0; font-size:15px; }
    .note-info { flex:1; min-width:0; cursor:pointer; }
    .note-topic { font-size:13px; font-weight:600; color:#1e293b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .note-meta-row { font-size:11px; color:#94a3b8; margin-top:2px; }
    .note-chevron { font-size:14px; color:#94a3b8; cursor:pointer; }
    .note-delete-btn { background:none; border:none; cursor:pointer; color:#ef4444; font-size:14px; padding:4px 6px; border-radius:6px; flex-shrink:0; opacity:0.6; }
    .note-delete-btn:hover { background:#fee2e2; opacity:1; }
    .no-notes-msg { font-size:13px; color:#94a3b8; padding:10px 0; }

    /* Note Detail Modal */
    .note-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1100; align-items:center; justify-content:center; }
    .note-modal-overlay.open { display:flex; }
    .note-modal { background:#fff; border-radius:16px; padding:28px 32px; max-width:620px; width:94%; box-shadow:0 8px 32px rgba(0,0,0,0.2); position:relative; max-height:82vh; overflow-y:auto; }
    .note-modal-close { position:absolute; top:14px; right:18px; background:none; border:none; font-size:20px; cursor:pointer; color:#64748b; }
    .note-modal-title { font-size:17px; font-weight:700; color:#1e293b; margin-bottom:4px; }
    .note-modal-meta  { font-size:12px; color:#94a3b8; margin-bottom:18px; }
    .note-modal-section { margin-bottom:16px; }
    .note-modal-label { font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:0.05em; margin-bottom:6px; }
    .note-modal-content { font-size:13px; color:#374151; line-height:1.7; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px; white-space:pre-wrap; }
  </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="container">
  <header class="page-header">
    <a href="dashboard_hr.php" style="font-size:14px;color:#64748b;text-decoration:none;">&larr; Back to Dashboard</a>
    <h1 class="page-title" style="margin-top:12px;">
      <?php echo e($pageError ? 'Applications' : ($job['job_title'] ?: 'Untitled Job')); ?>
    </h1>
  </header>

  <?php if ($pageError): ?>
    <section class="card"><p class="muted"><?php echo e($pageError); ?></p></section>
  <?php else: ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="flash flash-error" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 20px;border-radius:12px;margin-bottom:16px;font-size:14px;">
        <?php
          $err = e($_GET['error']);
          if ($err === 'InvalidStatus')       echo 'Invalid status value.';
          elseif ($err === 'Unauthorized')    echo 'You do not have permission to update this application.';
          elseif ($err === 'missing_api_key') echo 'Claude API key is missing.';
          elseif ($err === 'api_failed')      echo 'Claude summary request failed.';
          elseif ($err === 'invalid')         echo 'Missing application, job, or feedback.';
          else echo 'An error occurred.';
        ?>
      </div>
    <?php endif; ?>

    <?php if ($success === 'StatusUpdated'): ?>
      <div class="flash flash-success">Status updated successfully.</div>
    <?php endif; ?>

    <section class="card" style="margin-bottom:16px;">
      <div class="job-info-bar">
        <span class="job-info-item"><strong>Job ID:</strong> #<?php echo (int)$job['job_id']; ?></span>
        <span class="job-info-item"><strong>Status:</strong> <?php echo e($job['status']); ?></span>
        <span class="job-info-item"><strong>Created:</strong> <?php echo $job['created_at'] ? date('M j, Y', strtotime($job['created_at'])) : '-'; ?></span>
        <span class="job-info-item"><strong>Applications:</strong> <?php echo count($applications); ?></span>
      </div>
      <form method="GET" class="filters-form">
        <input type="hidden" name="job_id" value="<?php echo (int)$jobId; ?>">
        <div class="filter-item" style="min-width:140px;flex:0;">
          <label class="filter-label" for="filter-app-status">Filter by Status</label>
          <select id="filter-app-status" name="status" class="filter-control">
            <option value="All"          <?php echo ($filterStatus==='All')          ?'selected':''; ?>>All</option>
            <option value="Pending"      <?php echo ($filterStatus==='Pending')      ?'selected':''; ?>>Pending</option>
            <option value="Interviewing" <?php echo ($filterStatus==='Interviewing') ?'selected':''; ?>>Interviewing</option>
            <option value="Offered"      <?php echo ($filterStatus==='Offered')      ?'selected':''; ?>>Offered</option>
            <option value="Rejected"     <?php echo ($filterStatus==='Rejected')     ?'selected':''; ?>>Rejected</option>
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
                  $dbStatus    = (string)$a['status'];
                  $badge       = badge_for_db_status($dbStatus);
                  $appliedDate = $a['applied_at'] ? date('M j, Y', strtotime($a['applied_at'])) : '-';
                  $cl          = trim((string)($a['cover_letter'] ?? ''));
                  $clShort     = ($cl !== '' && strlen($cl) > 80) ? substr($cl, 0, 80) . '...' : $cl;
                ?>
                <tr>
                  <td style="font-weight:600;"><?php echo e($a['full_name']); ?></td>
                  <td style="color:#475569;"><?php echo e($a['email']); ?></td>
                  <td class="match-cell">
                    <?php
                      $score = $matchScores[$a['application_id']] ?? null;
                      if ($score && ($score['applicant_count'] > 0)):
                        $pct      = round($score['composite'] * 100);
                        $cls      = $pct >= 70 ? 'match-high' : ($pct >= 40 ? 'match-mid' : 'match-low');
                        $barColor = $pct >= 70 ? '#22c55e'    : ($pct >= 40 ? '#eab308'   : '#ef4444');
                    ?>
                      <span class="match-badge <?= $cls ?>"><?= $pct ?>%</span>
                      <div class="match-bar"><div class="match-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div></div>
                      <div class="match-details"><?= count($score['matched']) ?>/<?= $score['job_count'] ?> skills</div>
                    <?php else: ?>
                      <span class="match-badge match-none">N/A</span>
                      <div class="match-details">No skills provided</div>
                    <?php endif; ?>
                    <button class="ai-btn" data-appid="<?= (int)$a['application_id'] ?>" data-name="<?= e($a['full_name']) ?>" onclick="openAiModal(this)">🤖 AI Analysis</button>
                  </td>
                  <td>
                    <div class="app-status-cell">
                      <span class="badge" style="background:<?php echo e($badge['bg']); ?>;color:<?php echo e($badge['fg']); ?>;"><?php echo e($badge['label']); ?></span>
                      <form method="POST" action="api/update_application_status.php" style="margin:0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="application_id" value="<?php echo (int)$a['application_id']; ?>">
                        <input type="hidden" name="job_id"         value="<?php echo (int)$jobId; ?>">
                        <input type="hidden" name="return_status"  value="<?php echo e($filterStatus); ?>">
                        <select name="status" class="status-select" data-autosubmit="1" aria-label="Update application status">
                          <?php foreach ($dbStatusOptions as $opt): ?>
                            <option value="<?php echo e($opt); ?>" <?php echo ($dbStatus===$opt)?'selected':''; ?>><?php echo e($opt); ?></option>
                          <?php endforeach; ?>
                        </select>
                      </form>
                    </div>
                  </td>
                  <td style="color:#64748b;font-size:13px;"><?php echo e($appliedDate); ?></td>
                  <td class="cover-cell">
                    <?php if ($cl === ''): ?>
                      <span style="color:#94a3b8;">-</span>
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

      <!-- ══ SAVED NOTES ══ -->
      <hr class="notes-divider">
      <div class="notes-section-header">
        <div class="notes-section-title">
          📝 Saved Notes
          <span class="notes-count-badge"><?= count($savedNotes) ?></span>
        </div>
        <a class="add-note-btn" href="meeting_notes.php?job_id=<?= (int)$jobId ?>">+ Add Note</a>
      </div>

      <?php if (empty($savedNotes)): ?>
        <p class="no-notes-msg">No meeting notes saved for this job yet.</p>
      <?php else: ?>
        <?php foreach ($savedNotes as $sn): ?>
          <div class="note-row" id="note-row-<?= (int)$sn['note_id'] ?>">
            <div class="note-icon-wrap">📄</div>
            <div class="note-info" onclick="openNoteModal(<?= (int)$sn['note_id'] ?>)">
              <div class="note-topic"><?= e($sn['title'] ?: 'Untitled Meeting') ?></div>
              <div class="note-meta-row">
                <?= $sn['created_at'] ? e(date('M j, Y · H:i', strtotime($sn['created_at']))) : '-' ?>
                <?php if (!empty($sn['candidate_name'])): ?>
                  &middot; <span style="color:#475569;"><?= e($sn['candidate_name']) ?></span>
                <?php endif; ?>
              </div>
            </div>
            <div class="note-chevron" onclick="openNoteModal(<?= (int)$sn['note_id'] ?>)">›</div>
            <button class="note-delete-btn" onclick="deleteNote(<?= (int)$sn['note_id'] ?>)" title="Delete note">🗑</button>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    </section>
  <?php endif; ?>
</main>

<!-- AI Analysis Modal -->
<div class="ai-modal-overlay" id="aiModal">
  <div class="ai-modal">
    <button class="ai-modal-close" id="aiModalClose">✕</button>
    <div class="ai-modal-title" id="aiModalTitle">AI Analysis</div>
    <div class="ai-modal-body"  id="aiModalBody">Analyzing…</div>
  </div>
</div>

<!-- Note Detail Modal -->
<div class="note-modal-overlay" id="noteModal">
  <div class="note-modal">
    <button class="note-modal-close" onclick="closeNoteModal()">✕</button>
    <div class="note-modal-title" id="noteModalTitle"></div>
    <div class="note-modal-meta"  id="noteModalMeta"></div>
    <div id="noteModalBody"></div>
  </div>
</div>

<script>
const NOTE_DATA = <?php
  $js = [];
  foreach ($savedNotes as $sn) {
      $js[(int)$sn['note_id']] = [
          'title'          => $sn['title'] ?: 'Untitled Meeting',
          'date'           => $sn['created_at'] ? date('M j, Y · H:i', strtotime($sn['created_at'])) : '-',
          'candidate_name' => $sn['candidate_name'] ?? '',
          'user_notes'     => $sn['user_notes']  ?? '',
          'ai_summary'     => $sn['ai_summary']  ?? '',
      ];
  }
  echo json_encode($js, JSON_HEX_TAG | JSON_HEX_AMP);
?>;
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<script>
  // Status auto-submit
  document.querySelectorAll('select[data-autosubmit="1"]').forEach(sel => {
    sel.addEventListener('change', () => sel.closest('form')?.submit());
  });

  function escapeHtml(t) { const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

  // AI Modal
  const aiModal = document.getElementById('aiModal');
  document.getElementById('aiModalClose').addEventListener('click', () => aiModal.classList.remove('open'));
  aiModal.addEventListener('click', e => { if (e.target===aiModal) aiModal.classList.remove('open'); });

  function openAiModal(btn) {
    const appId=btn.dataset.appid, name=btn.dataset.name;
    document.getElementById('aiModalTitle').textContent = '🤖 AI Analysis — '+name;
    document.getElementById('aiModalBody').innerHTML = '<em style="color:#64748b;">Analyzing candidate…</em>';
    aiModal.classList.add('open');

    fetch('api/ai_score.php?application_id='+encodeURIComponent(appId))
      .then(r => { if(!r.ok) throw new Error(); return r.json(); })
      .then(data => {
        if (data.success) {
          let t = data.message||'';
          t = t.replace(/Strong Hire/g,'<span class="rec-strong">Strong Hire</span>');
          t = t.replace(/No Hire/g,    '<span class="rec-no">No Hire</span>');
          t = t.replace(/\bMaybe\b/g,  '<span class="rec-maybe">Maybe</span>');
          t = t.replace(/\bHire\b/g,   '<span class="rec-hire">Hire</span>');
          document.getElementById('aiModalBody').innerHTML = '<div style="white-space:pre-wrap;line-height:1.8;">'+t+'</div>';
        } else {
          document.getElementById('aiModalBody').innerHTML = '<span style="color:#991b1b;">⚠️ '+(data.error||'Could not load analysis.')+'</span>';
        }
      })
      .catch(() => { document.getElementById('aiModalBody').innerHTML = '<span style="color:#991b1b;">⚠️ Failed to connect.</span>'; });
  }

  // Note Detail Modal
  const noteModal = document.getElementById('noteModal');
  function openNoteModal(id) {
    const n = NOTE_DATA[id]; if (!n) return;
    document.getElementById('noteModalTitle').textContent = n.title;
    document.getElementById('noteModalMeta').textContent  = n.date + (n.candidate_name ? ' · '+n.candidate_name : '');
    let html = '';
    if (n.user_notes?.trim()) html += `<div class="note-modal-section"><div class="note-modal-label">Your Notes</div><div class="note-modal-content">${escapeHtml(n.user_notes)}</div></div>`;
    if (n.ai_summary?.trim()) html += `<div class="note-modal-section"><div class="note-modal-label">AI Summary</div><div class="note-modal-content" style="white-space:normal;">${n.ai_summary}</div></div>`;
    if (!html) html = '<p style="color:#94a3b8;font-size:13px;">No content available.</p>';
    document.getElementById('noteModalBody').innerHTML = html;
    noteModal.classList.add('open');
  }
  function closeNoteModal() { noteModal.classList.remove('open'); }
  noteModal.addEventListener('click', e => { if (e.target===noteModal) closeNoteModal(); });

  // Delete note
  async function deleteNote(noteId) {
    if (!confirm('Are you sure you want to delete this note?')) return;
    try {
      const fd = new FormData();
      fd.append('note_id', noteId);
      const res  = await fetch('api/delete_note.php', { method:'POST', body:fd });
      const data = await res.json();
      if (data.success) {
        const row = document.getElementById('note-row-'+noteId);
        if (row) row.remove();
        delete NOTE_DATA[noteId];
        // Update count badge
        const badge = document.querySelector('.notes-count-badge');
        if (badge) badge.textContent = parseInt(badge.textContent||0) - 1;
      } else {
        alert(data.error || 'Delete failed. Please try again.');
      }
    } catch(e) {
      alert('An error occurred. Please try again.');
    }
  }

  document.addEventListener('keydown', e => {
    if (e.key==='Escape') { closeNoteModal(); aiModal.classList.remove('open'); }
  });
</script>
</body>
</html>