<?php
/**
 * dashboard.php — HR Dashboard
 * @author Vaishnavi Pushparaj Samani
 * Database: talentsync_db
 * Tables used: jobs, occupation_data, job_skills, users, meeting_notes
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$userId   = (int)$_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'HR Manager';

$status = $_GET['status'] ?? 'All';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// Summary counts
$counts = ['Draft'=>0,'Open'=>0,'Closed'=>0,'Total'=>0];
$stmt = $conn->prepare("SELECT status, COUNT(*) AS cnt FROM jobs WHERE user_id = ? AND deleted_at IS NULL GROUP BY status");
$stmt->bind_param('i',$userId); $stmt->execute();
$res = $stmt->get_result();
while ($row=$res->fetch_assoc()) { $counts[$row['status']]=(int)$row['cnt']; $counts['Total']+=(int)$row['cnt']; }
$stmt->close();

// Pipeline Analytics
$analytics = ['total_apps'=>0,'pending'=>0,'interviewing'=>0,'offered'=>0,'rejected'=>0];
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total_apps,
        SUM(CASE WHEN a.status='Pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN a.status='Interviewing' THEN 1 ELSE 0 END) AS interviewing,
        SUM(CASE WHEN a.status='Offered' THEN 1 ELSE 0 END) AS offered,
        SUM(CASE WHEN a.status='Rejected' THEN 1 ELSE 0 END) AS rejected
    FROM applications a JOIN jobs j ON a.job_id=j.job_id
    WHERE j.user_id=? AND j.deleted_at IS NULL AND a.deleted_at IS NULL
");
$stmt->bind_param('i',$userId); $stmt->execute();
$aRow=$stmt->get_result()->fetch_assoc();
if ($aRow) { $analytics=array_map('intval',$aRow); }
$stmt->close();

$totalApps = max(1,$analytics['total_apps']);
$conversionRates = [
    'screen_rate'    => round(($analytics['interviewing']+$analytics['offered'])/$totalApps*100,1),
    'interview_rate' => round($analytics['interviewing']/$totalApps*100,1),
    'offer_rate'     => round($analytics['offered']/$totalApps*100,1),
    'rejection_rate' => round($analytics['rejected']/$totalApps*100,1),
];

// WHERE clause
$where=['j.user_id=?','j.deleted_at IS NULL']; $types='i'; $params=[$userId];
if (in_array($status,JOB_STATUSES,true)) { $where[]='j.status=?'; $types.='s'; $params[]=$status; }
if ($q!=='') { $like="%$q%"; $where[]='(j.job_title LIKE ? OR od.title LIKE ?)'; $types.='ss'; $params[]=$like; $params[]=$like; }
$whereSql='WHERE '.implode(' AND ',$where);

// Pagination
$stmt=$conn->prepare("SELECT COUNT(*) AS total FROM jobs j LEFT JOIN occupation_data od ON od.onetsoc_code=j.onet_soc_code $whereSql");
bindParams($stmt,$types,$params); $stmt->execute();
$totalRows=(int)$stmt->get_result()->fetch_assoc()['total']; $stmt->close();
$totalPages=max(1,(int)ceil($totalRows/$limit));

// Fetch jobs
$stmt=$conn->prepare("
    SELECT j.job_id, j.job_title, j.status, j.created_at, j.onet_soc_code,
           od.title AS onet_title,
           COALESCE(js.skills_count,0) AS skills_count,
           COALESCE(ap.apps_count,0) AS apps_count
    FROM jobs j
    LEFT JOIN occupation_data od ON od.onetsoc_code=j.onet_soc_code
    LEFT JOIN (SELECT job_id,COUNT(*) AS skills_count FROM job_skills GROUP BY job_id) js ON js.job_id=j.job_id
    LEFT JOIN (SELECT job_id,COUNT(*) AS apps_count FROM applications GROUP BY job_id) ap ON ap.job_id=j.job_id
    $whereSql ORDER BY j.created_at DESC LIMIT ? OFFSET ?
");
bindParams($stmt,$types.'ii',array_merge($params,[$limit,$offset])); $stmt->execute();
$r=$stmt->get_result(); $jobs=[];
while ($row=$r->fetch_assoc()) $jobs[]=$row;
$stmt->close();

// All saved notes
$allNotes=[];
$an=$conn->prepare("
    SELECT mn.note_id, mn.job_id, mn.title, mn.candidate_name,
           mn.user_notes, mn.ai_summary, mn.created_at, j.job_title
    FROM meeting_notes mn
    LEFT JOIN jobs j ON j.job_id=mn.job_id
    WHERE mn.user_id=?
    ORDER BY mn.created_at DESC
");
$an->bind_param("i",$userId); $an->execute();
$anr=$an->get_result();
while ($row=$anr->fetch_assoc()) $allNotes[]=$row;
$an->close();

$baseQuery=['status'=>$status,'q'=>$q];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>HR Dashboard – TalentSync</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/dashboard_hr.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<main class="container">
    <header class="page-header">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 class="page-title">HR Dashboard</h1>
                <p class="page-subtitle">Welcome back, <?php echo e($fullName); ?>.</p>
            </div>
            <button onclick="openAllNotesModal()" class="btn"
                    style="display:inline-flex;align-items:center;gap:8px;font-size:13px;height:36px;padding:0 16px;">
                📝 Saved Notes
                <?php if (!empty($allNotes)): ?>
                    <span style="background:#2563eb;color:#fff;border-radius:99px;font-size:11px;padding:1px 8px;font-weight:700;" id="dash-notes-count">
                        <?= count($allNotes) ?>
                    </span>
                <?php endif; ?>
            </button>
        </div>
    </header>

    <?php if (isset($_GET['error'])): ?>
        <div class="flash flash-error" style="background:#fee2e2;color:#991b1b;border:1px solid #fecaca;padding:14px 20px;border-radius:12px;margin-bottom:16px;font-size:14px;">
            <?php $err=e($_GET['error']); if($err==='DeleteFailed') echo 'Failed to delete job.'; elseif($err==='Unauthorized') echo 'Permission denied.'; else echo 'An error occurred.'; ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['success'])): ?>
        <div class="flash flash-success">
            <?php if($_GET['success']==='JobSaved') echo '✓ Job saved.'; elseif($_GET['success']==='JobDeleted') echo '✓ Job deleted.'; else echo '✓ Done.'; ?>
        </div>
    <?php endif; ?>

    <section class="summary-grid">
        <?php foreach ([['Draft Jobs','Draft','#854d0e'],['Open Jobs','Open','#166534'],['Closed Jobs','Closed','#991b1b'],['Total Jobs','Total','#1e40af']] as [$lbl,$key,$col]): ?>
            <div class="summary-card">
                <div class="summary-label"><?= e($lbl) ?></div>
                <div class="summary-value" style="color:<?= $col ?>"><?= $counts[$key] ?></div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="card" id="ai-insights-section">
        <div class="card-header">
            <h2 class="card-title" style="display:flex;align-items:center;gap:8px;"><span style="font-size:20px;">🤖</span> Hiring Pipeline Analytics</h2>
            <button onclick="loadInsights()" class="btn" id="refresh-insights-btn" style="font-size:13px;height:34px;padding:0 14px;">↻ Refresh AI Insights</button>
        </div>

        <?php if ($analytics['total_apps']>0): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;padding:8px 0;">
            <?php foreach ([
                ['Total Applications',$analytics['total_apps'],'100','#3b82f6'],
                ['Screening Rate',$analytics['interviewing']+$analytics['offered'],$conversionRates['screen_rate'],'#8b5cf6'],
                ['Interview Rate',$analytics['interviewing'],$conversionRates['interview_rate'],'#eab308'],
                ['Offer Rate',$analytics['offered'],$conversionRates['offer_rate'],'#22c55e'],
                ['Rejection Rate',$analytics['rejected'],$conversionRates['rejection_rate'],'#ef4444'],
            ] as [$lbl,$cnt,$pct,$col]): ?>
                <div style="text-align:center;">
                    <div style="font-size:24px;font-weight:700;color:<?=$col?>"><?=$cnt?></div>
                    <div style="font-size:13px;color:#64748b;margin:4px 0;"><?=$lbl?></div>
                    <div style="width:100%;height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;">
                        <div style="height:100%;width:<?=$pct?>%;background:<?=$col?>;border-radius:4px;"></div>
                    </div>
                    <div style="font-size:12px;font-weight:600;color:<?=$col?>;margin-top:4px;"><?=$pct?>%</div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0 0;">
        <div id="insights-toolbar" onclick="toggleInsights()" style="display:flex;align-items:center;justify-content:space-between;padding:14px 0;cursor:pointer;user-select:none;">
            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:16px;">🤖</span>
                <span style="font-size:14px;font-weight:700;color:#0f172a;">AI Insights</span>
                <span style="font-size:11px;color:#94a3b8;background:#f1f5f9;padding:2px 8px;border-radius:999px;">Powered by Claude</span>
            </div>
            <span id="insights-arrow" style="font-size:18px;color:#94a3b8;transition:transform 0.3s;">▼</span>
        </div>
        <div id="insights-panel" style="max-height:0;overflow:hidden;transition:max-height 0.4s ease;">
            <div id="insights-content" style="font-size:14px;line-height:1.7;color:#334155;padding-bottom:8px;">
                <div style="display:flex;align-items:center;gap:10px;color:#94a3b8;padding:12px 0;">
                    <div style="width:20px;height:20px;border:3px solid #e2e8f0;border-top:3px solid #2563eb;border-radius:50%;animation:spin 1s linear infinite;"></div>
                    Analyzing your hiring pipeline...
                </div>
            </div>
        </div>
    </section>

    <section class="card">
        <form method="GET" class="filters-form">
            <div class="filter-item">
                <label class="filter-label" for="filter-status">Status</label>
                <select id="filter-status" name="status" class="filter-control">
                    <?php foreach (JOB_FILTER_STATUSES as $opt): ?>
                        <option value="<?= $opt ?>" <?= ($status===$opt)?'selected':'' ?>><?= $opt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-item">
                <label class="filter-label" for="filter-search">Search</label>
                <input id="filter-search" type="text" name="q" class="filter-control" value="<?= e($q) ?>" placeholder="Job title or O*NET…"/>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn">Apply</button>
                <a href="?" class="btn">Reset</a>
                <a href="create_job.php" class="btn btn-primary">+ Create Job</a>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-header"><h2 class="card-title">All Jobs (<?= $totalRows ?>)</h2></div>
        <?php if (empty($jobs)): ?>
            <p class="muted">No jobs found. <a href="create_job.php" class="action-link">Create your first job →</a></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="jobs-table">
                    <thead><tr><th>#</th><th>Job Title</th><th>O*NET Occupation</th><th>Status</th><th>Skills</th><th>Apps</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($jobs as $j):
                            $badgeClass = match($j['status']) { 'Open'=>'badge-open','Closed'=>'badge-closed',default=>'badge-draft' };
                            $createdAt  = $j['created_at'] ? date('M j, Y',strtotime($j['created_at'])) : '—';
                        ?>
                        <tr>
                            <td style="color:#94a3b8;font-size:12px;">#<?= $j['job_id'] ?></td>
                            <td style="font-weight:600;"><?= e($j['job_title']?:'(Untitled)') ?></td>
                            <td style="color:#475569;"><?= e($j['onet_title']?:($j['onet_soc_code']?:'—')) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= e($j['status']) ?></span></td>
                            <td><?= $j['skills_count'] ?></td>
                            <td>
                                <?php if ($j['apps_count']>0): ?>
                                    <a href="job_applications.php?job_id=<?= $j['job_id'] ?>" class="action-link" style="display:inline-flex;align-items:center;gap:4px;">
                                        <span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:700;"><?= $j['apps_count'] ?></span> View
                                    </a>
                                <?php else: ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                            </td>
                            <td style="color:#64748b;font-size:13px;"><?= e($createdAt) ?></td>
                            <td style="display:flex;gap:10px;align-items:center;border-bottom:none;">
                                <a href="create_job.php?job_id=<?= $j['job_id'] ?>" class="action-link">Edit</a>
                                <span style="color:#cbd5e1;">|</span>
                                <form action="api/delete_job.php" method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to delete this job and its applications?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="job_id" value="<?= $j['job_id'] ?>">
                                    <button type="submit" class="action-link" style="background:none;border:none;padding:0;color:#ef4444;font-family:inherit;">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <nav class="pagination">
                <span class="pagination-meta">Page <?= $page ?> of <?= $totalPages ?></span>
                <div class="pagination-actions">
                    <?php if ($page>1): ?><a class="btn" href="?<?= http_build_query(array_merge($baseQuery,['page'=>$page-1])) ?>">← Prev</a><?php endif; ?>
                    <?php if ($page<$totalPages): ?><a class="btn btn-primary" href="?<?= http_build_query(array_merge($baseQuery,['page'=>$page+1])) ?>">Next →</a><?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</main>

<!-- All Notes Modal -->
<div id="allNotesModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:2000;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;padding:28px 32px;max-width:700px;width:94%;max-height:85vh;overflow-y:auto;position:relative;box-shadow:0 8px 32px rgba(0,0,0,0.2);">
    <button onclick="closeAllNotesModal()" style="position:absolute;top:14px;right:18px;background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;">✕</button>
    <div style="font-size:17px;font-weight:700;color:#1e293b;margin-bottom:4px;">📝 Saved Notes</div>
    <div style="font-size:12px;color:#94a3b8;margin-bottom:20px;">All your meeting notes across all jobs</div>

    <div id="all-notes-list">
    <?php if (empty($allNotes)): ?>
      <p style="font-size:13px;color:#94a3b8;">No meeting notes saved yet.</p>
    <?php else: ?>
      <?php foreach ($allNotes as $an): ?>
        <div id="dash-note-row-<?= (int)$an['note_id'] ?>"
             style="display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:8px;background:#fff;">
          <div style="width:32px;height:32px;border-radius:7px;background:#e0e7ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:15px;">📄</div>
          <div style="flex:1;min-width:0;cursor:pointer;" onclick="openDashNoteDetail(<?= (int)$an['note_id'] ?>)" onmouseover="this.parentElement.style.background='#f8fafc'" onmouseout="this.parentElement.style.background='#fff'">
            <div style="font-size:13px;font-weight:600;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= e($an['title'] ?: 'Untitled Meeting') ?>
            </div>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px;">
              <?= $an['created_at'] ? e(date('M j, Y · H:i',strtotime($an['created_at']))) : '-' ?>
              <?php if ($an['candidate_name']): ?>
                &middot; <span style="color:#475569;"><?= e($an['candidate_name']) ?></span>
              <?php endif; ?>
              <?php if ($an['job_title']): ?>
                &middot; <a href="job_applications.php?job_id=<?= (int)$an['job_id'] ?>" style="color:#2563eb;text-decoration:none;" onclick="event.stopPropagation();"><?= e($an['job_title']) ?></a>
              <?php endif; ?>
            </div>
          </div>
          <div style="font-size:14px;color:#94a3b8;cursor:pointer;" onclick="openDashNoteDetail(<?= (int)$an['note_id'] ?>)">›</div>
          <button onclick="deleteDashNote(<?= (int)$an['note_id'] ?>)" title="Sil"
                  style="background:none;border:none;cursor:pointer;color:#ef4444;font-size:14px;padding:4px 6px;border-radius:6px;opacity:0.6;flex-shrink:0;"
                  onmouseover="this.style.opacity='1';this.style.background='#fee2e2'" onmouseout="this.style.opacity='0.6';this.style.background='none'">🗑</button>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
    </div>
  </div>
</div>

<!-- Note Detail Modal (dashboard) -->
<div id="dashNoteModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:2100;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:16px;padding:28px 32px;max-width:620px;width:94%;max-height:82vh;overflow-y:auto;position:relative;box-shadow:0 8px 32px rgba(0,0,0,0.2);">
    <button onclick="closeDashNoteModal()" style="position:absolute;top:14px;right:18px;background:none;border:none;font-size:20px;cursor:pointer;color:#64748b;">✕</button>
    <div id="dashNoteTitle" style="font-size:17px;font-weight:700;color:#1e293b;margin-bottom:6px;"></div>
    <div id="dashNoteMeta"  style="font-size:12px;color:#94a3b8;margin-bottom:18px;"></div>
    <div id="dashNoteBody"></div>
  </div>
</div>

<?php include 'includes/footer.php'; ?>

<style>
    @keyframes spin { to { transform:rotate(360deg); } }
    #insights-content ul { margin:8px 0; padding-left:20px; }
    #insights-content li { margin-bottom:6px; }
    #insights-content strong { color:#0f172a; }
    .insights-error { color:#991b1b; background:#fee2e2; padding:12px 16px; border-radius:10px; font-size:13px; }
    #insights-toolbar:hover { background:#f8fafc; border-radius:8px; margin:0 -8px; padding:14px 8px; }
    .insight-card { border-left:4px solid #cbd5e1; background:#f8fafc; border-radius:0 10px 10px 0; padding:14px 18px; margin-bottom:12px; }
    .insight-header { font-weight:700; font-size:14px; color:#0f172a; margin-bottom:4px; }
    .insight-body { font-size:13px; line-height:1.6; color:#475569; }
    .insight-success { border-left-color:#22c55e; background:#f0fdf4; } .insight-success .insight-header { color:#166534; }
    .insight-warning { border-left-color:#f59e0b; background:#fffbeb; } .insight-warning .insight-header { color:#92400e; }
    .insight-danger  { border-left-color:#ef4444; background:#fef2f2; } .insight-danger .insight-header  { color:#991b1b; }
    .insight-info    { border-left-color:#3b82f6; background:#eff6ff; } .insight-info .insight-header    { color:#1e40af; }
    .insight-neutral { border-left-color:#94a3b8; background:#f8fafc; } .insight-neutral .insight-header { color:#475569; }
</style>

<script>
// AI Insights
let insightsOpen=false, insightsLoaded=false;
function toggleInsights(){
    const panel=document.getElementById('insights-panel'), arrow=document.getElementById('insights-arrow');
    insightsOpen=!insightsOpen;
    if(insightsOpen){ panel.style.maxHeight=panel.scrollHeight+2000+'px'; arrow.style.transform='rotate(180deg)'; if(!insightsLoaded) loadInsights(); }
    else { panel.style.maxHeight='0'; arrow.style.transform='rotate(0deg)'; }
}
function loadInsights(){
    const container=document.getElementById('insights-content'), btn=document.getElementById('refresh-insights-btn');
    container.innerHTML='<div style="display:flex;align-items:center;gap:10px;color:#94a3b8;padding:12px 0;"><div style="width:20px;height:20px;border:3px solid #e2e8f0;border-top:3px solid #2563eb;border-radius:50%;animation:spin 1s linear infinite;"></div>Analyzing...</div>';
    btn.disabled=true; btn.style.opacity='0.5';
    fetch('api/hr_insights.php')
        .then(r=>{ if(!r.ok) throw new Error(); return r.json(); })
        .then(data=>{ if(data.success){ container.innerHTML=data.message; insightsLoaded=true; } else { const e=document.createElement('span'); e.textContent=data.error||'Unknown'; container.innerHTML='<div class="insights-error">Unable to load: '+e.innerHTML+'</div>'; } const p=document.getElementById('insights-panel'); if(insightsOpen) p.style.maxHeight=p.scrollHeight+'px'; })
        .catch(()=>{ container.innerHTML='<div class="insights-error">Network error.</div>'; })
        .finally(()=>{ btn.disabled=false; btn.style.opacity='1'; });
}

// Note data
const ALL_NOTE_DATA = <?php
  $js=[];
  foreach ($allNotes as $an) {
      $js[(int)$an['note_id']]=[
          'title'          => $an['title']?:'Untitled Meeting',
          'date'           => $an['created_at'] ? date('M j, Y · H:i',strtotime($an['created_at'])) : '-',
          'job_title'      => $an['job_title']??'',
          'candidate_name' => $an['candidate_name']??'',
          'user_notes'     => $an['user_notes']??'',
          'ai_summary'     => $an['ai_summary']??'',
      ];
  }
  echo json_encode($js, JSON_HEX_TAG|JSON_HEX_AMP);
?>;

function openAllNotesModal()  { document.getElementById('allNotesModal').style.display='flex'; }
function closeAllNotesModal() { document.getElementById('allNotesModal').style.display='none'; }
function closeDashNoteModal() { document.getElementById('dashNoteModal').style.display='none'; }

function esc(t){ const d=document.createElement('div'); d.textContent=t; return d.innerHTML; }

function openDashNoteDetail(noteId){
    const n=ALL_NOTE_DATA[noteId]; if(!n) return;
    document.getElementById('dashNoteTitle').textContent=n.title;
    let meta=n.date;
    if(n.candidate_name) meta+=' · '+n.candidate_name;
    if(n.job_title) meta+=' · '+n.job_title;
    document.getElementById('dashNoteMeta').textContent=meta;
    let html='';
    if(n.user_notes?.trim()) html+=`<div style="margin-bottom:16px;"><div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Your Notes</div><div style="font-size:13px;color:#374151;line-height:1.7;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;white-space:pre-wrap;">${esc(n.user_notes)}</div></div>`;
    if(n.ai_summary?.trim()) html+=`<div style="margin-bottom:16px;"><div style="font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">AI Summary</div><div style="font-size:13px;color:#374151;line-height:1.7;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px;white-space:normal;">${n.ai_summary}</div></div>`;
    if(!html) html='<p style="color:#94a3b8;font-size:13px;">No content available.</p>';
    document.getElementById('dashNoteBody').innerHTML=html;
    document.getElementById('dashNoteModal').style.display='flex';
}

async function deleteDashNote(noteId){
    if(!confirm('Are you sure you want to delete this note?')) return;
    try {
        const fd=new FormData(); fd.append('note_id',noteId);
        const res=await fetch('api/delete_note.php',{method:'POST',body:fd});
        const data=await res.json();
        if(data.success){
            const row=document.getElementById('dash-note-row-'+noteId);
            if(row) row.remove();
            delete ALL_NOTE_DATA[noteId];
            const badge=document.getElementById('dash-notes-count');
            if(badge){ const n=parseInt(badge.textContent||0)-1; badge.textContent=n; if(n<=0) badge.style.display='none'; }
        } else { alert(data.error||'Delete failed. Please try again'); }
    } catch(e){ alert('An error occurred. Please try again.'); }
}

document.getElementById('allNotesModal').addEventListener('click',function(e){ if(e.target===this) closeAllNotesModal(); });
document.getElementById('dashNoteModal').addEventListener('click',function(e){ if(e.target===this) closeDashNoteModal(); });
document.addEventListener('keydown',function(e){ if(e.key==='Escape'){ closeAllNotesModal(); closeDashNoteModal(); } });
</script>
</body>
</html>