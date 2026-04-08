<?php
/**
 * dashboard.php — HR Dashboard
 * @author Vaishnavi Pushparaj Samani
 * * Database: talentsync_db
 * Tables used: jobs, occupation_data, job_skills, users
 */

session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/helpers.php';

// ══════════════════════════════════════════════════════════════════════════════
// TEMPORARY: Bypass login for testing (remove when Lee finishes login.php)
// ══════════════════════════════════════════════════════════════════════════════
//$userId   = 2;  // Hardcoded test user
//$fullName = 'Vaishnavi Test';


// ── Session guard ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$userId   = (int)$_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'HR Manager';


// ── Query parameters ──────────────────────────────────────────────────────────
$status = $_GET['status'] ?? 'All';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// ── Summary counts ────────────────────────────────────────────────────────────
$counts = ['Draft' => 0, 'Open' => 0, 'Closed' => 0, 'Total' => 0];

$stmt = $conn->prepare("SELECT status, COUNT(*) AS cnt FROM jobs WHERE user_id = ? AND deleted_at IS NULL GROUP BY status");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['Total'] += (int)$row['cnt'];
}
$stmt->close();

// ── Pipeline Analytics ──────────────────────────────────────────────────────
// Calculates conversion rates through the hiring funnel using conditional aggregation
$analytics = ['total_apps' => 0, 'pending' => 0, 'interviewing' => 0, 'offered' => 0, 'rejected' => 0];
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_apps,
        SUM(CASE WHEN a.status = 'Pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN a.status = 'Interviewing' THEN 1 ELSE 0 END) AS interviewing,
        SUM(CASE WHEN a.status = 'Offered' THEN 1 ELSE 0 END) AS offered,
        SUM(CASE WHEN a.status = 'Rejected' THEN 1 ELSE 0 END) AS rejected
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    WHERE j.user_id = ? AND j.deleted_at IS NULL AND a.deleted_at IS NULL
");
$stmt->bind_param('i', $userId);
$stmt->execute();
$aRow = $stmt->get_result()->fetch_assoc();
if ($aRow) {
    $analytics['total_apps']    = (int)$aRow['total_apps'];
    $analytics['pending']       = (int)$aRow['pending'];
    $analytics['interviewing']  = (int)$aRow['interviewing'];
    $analytics['offered']       = (int)$aRow['offered'];
    $analytics['rejected']      = (int)$aRow['rejected'];
}
$stmt->close();

// Conversion rates (percentage of total that advanced to each stage)
$totalApps = max(1, $analytics['total_apps']); // avoid div by zero
$conversionRates = [
    'screen_rate'    => round(($analytics['interviewing'] + $analytics['offered']) / $totalApps * 100, 1),
    'interview_rate' => round($analytics['interviewing'] / $totalApps * 100, 1),
    'offer_rate'     => round($analytics['offered'] / $totalApps * 100, 1),
    'rejection_rate' => round($analytics['rejected'] / $totalApps * 100, 1),
];

// ── Build WHERE clause ────────────────────────────────────────────────────────
$where  = ['j.user_id = ?', 'j.deleted_at IS NULL'];
$types  = 'i';
$params = [$userId];

if (in_array($status, JOB_STATUSES, true)) {
    $where[]  = 'j.status = ?';
    $types   .= 's';
    $params[] = $status;
}

if ($q !== '') {
    $like     = "%$q%";
    $where[]  = '(j.job_title LIKE ? OR od.title LIKE ?)';
    $types   .= 'ss';
    $params[] = $like;
    $params[] = $like;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);


// ── Pagination total ──────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM jobs j
    LEFT JOIN occupation_data od ON od.onetsoc_code = j.onet_soc_code
    $whereSql
");
bindParams($stmt, $types, $params);
$stmt->execute();
$totalRows  = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();
$totalPages = max(1, (int)ceil($totalRows / $limit));

// ── Fetch jobs ────────────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT
        j.job_id,
        j.job_title,
        j.status,
        j.created_at,
        j.onet_soc_code,
        od.title AS onet_title,
        COALESCE(js.skills_count, 0) AS skills_count,
        COALESCE(ap.apps_count, 0) AS apps_count
    FROM jobs j
    LEFT JOIN occupation_data od ON od.onetsoc_code = j.onet_soc_code
    LEFT JOIN (
        SELECT job_id, COUNT(*) AS skills_count
        FROM job_skills
        GROUP BY job_id
    ) js ON js.job_id = j.job_id
    LEFT JOIN (
        SELECT job_id, COUNT(*) AS apps_count
        FROM applications
        GROUP BY job_id
    ) ap ON ap.job_id = j.job_id
    $whereSql
    ORDER BY j.created_at DESC
    LIMIT ? OFFSET ?
");

$typesList  = $types . 'ii';
$paramsList = array_merge($params, [$limit, $offset]);
bindParams($stmt, $typesList, $paramsList);
$stmt->execute();
$r    = $stmt->get_result();
$jobs = [];
while ($row = $r->fetch_assoc()) $jobs[] = $row;
$stmt->close();

$baseQuery = ['status' => $status, 'q' => $q];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>HR Dashboard – TalentSync</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/dashboard_hr.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<main class="container">
    <header class="page-header">
        <h1 class="page-title">HR Dashboard</h1>
        <p class="page-subtitle">Welcome back, <?php echo e($fullName); ?>.</p>
    </header>

<?php if (isset($_GET['success'])): ?>
        <div class="flash flash-success">
            <?php 
                if ($_GET['success'] === 'JobSaved') echo '✓ Job saved successfully.';
                elseif ($_GET['success'] === 'JobDeleted') echo '✓ Job deleted successfully.';
                else echo '✓ Action completed successfully.'; 
            ?>
        </div>
    <?php endif; ?>

    <section class="summary-grid">
        <?php
        $cards = [
            ['label' => 'Draft Jobs',  'key' => 'Draft',  'color' => '#854d0e'],
            ['label' => 'Open Jobs',   'key' => 'Open',   'color' => '#166534'],
            ['label' => 'Closed Jobs', 'key' => 'Closed', 'color' => '#991b1b'],
            ['label' => 'Total Jobs',  'key' => 'Total',  'color' => '#1e40af'],
        ];
        foreach ($cards as $c): ?>
            <div class="summary-card">
                <div class="summary-label"><?php echo e($c['label']); ?></div>
                <div class="summary-value" style="color:<?php echo $c['color']; ?>">
                    <?php echo $counts[$c['key']]; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <!-- ══════════════════════════════════════════════════════════════════════
         Hiring Pipeline Analytics + AI Hiring Insights (Merged)
         Pipeline funnel metrics by Vaishnavi Pushparaj Samani
         AI Insights integration by @author Kratika Patidar
         Calls api/hr_insights.php which pulls pipeline data from the database
         and sends it to Claude API for analysis. Displays actionable insights
         with a refresh button for on-demand updates.
         ══════════════════════════════════════════════════════════════════════ -->
    <section class="card" id="ai-insights-section">
        <div class="card-header">
            <h2 class="card-title" style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:20px;">🤖</span> Hiring Pipeline Analytics
            </h2>
            <!-- Refresh button for AI insights — @author Kratika Patidar -->
            <button onclick="loadInsights()" class="btn" id="refresh-insights-btn" style="font-size:13px; height:34px; padding:0 14px;">
                ↻ Refresh AI Insights
            </button>
        </div>

        <!-- Pipeline funnel metrics (static) — @author Vaishnavi -->
        <?php if ($analytics['total_apps'] > 0): ?>
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:16px; padding:8px 0;">
            <?php
            $funnelStages = [
                ['label' => 'Total Applications', 'count' => $analytics['total_apps'], 'pct' => '100', 'color' => '#3b82f6'],
                ['label' => 'Screening Rate', 'count' => $analytics['interviewing'] + $analytics['offered'], 'pct' => $conversionRates['screen_rate'], 'color' => '#8b5cf6'],
                ['label' => 'Interview Rate', 'count' => $analytics['interviewing'], 'pct' => $conversionRates['interview_rate'], 'color' => '#eab308'],
                ['label' => 'Offer Rate',     'count' => $analytics['offered'],      'pct' => $conversionRates['offer_rate'],     'color' => '#22c55e'],
                ['label' => 'Rejection Rate', 'count' => $analytics['rejected'],     'pct' => $conversionRates['rejection_rate'], 'color' => '#ef4444'],
            ];
            foreach ($funnelStages as $stage):
            ?>
                <div style="text-align:center;">
                    <div style="font-size:24px;font-weight:700;color:<?= $stage['color'] ?>;"><?= $stage['count'] ?></div>
                    <div style="font-size:13px;color:#64748b;margin:4px 0;"><?= $stage['label'] ?></div>
                    <div style="width:100%;height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;">
                        <div style="height:100%;width:<?= $stage['pct'] ?>%;background:<?= $stage['color'] ?>;border-radius:4px;"></div>
                    </div>
                    <div style="font-size:12px;font-weight:600;color:<?= $stage['color'] ?>;margin-top:4px;"><?= $stage['pct'] ?>%</div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Divider between pipeline metrics and AI insights — @author Kratika Patidar -->
        <hr style="border:none; border-top:1px solid #e2e8f0; margin:20px 0 0;">

        <!-- AI Insights collapsible toolbar — @author Kratika Patidar
             Clicking the toolbar expands/collapses the AI insights panel.
             Insights are loaded on first expand to save API calls. -->
        <div id="insights-toolbar" onclick="toggleInsights()" style="display:flex; align-items:center; justify-content:space-between; padding:14px 0; cursor:pointer; user-select:none;">
            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:16px;">🤖</span>
                <span style="font-size:14px; font-weight:700; color:#0f172a;">AI Insights</span>
                <span style="font-size:11px; color:#94a3b8; background:#f1f5f9; padding:2px 8px; border-radius:999px;">Powered by Claude</span>
            </div>
            <span id="insights-arrow" style="font-size:18px; color:#94a3b8; transition:transform 0.3s;">▼</span>
        </div>

        <!-- Collapsible insights content — @author Kratika Patidar -->
        <div id="insights-panel" style="max-height:0; overflow:hidden; transition:max-height 0.4s ease;">
            <div id="insights-content" style="font-size:14px; line-height:1.7; color:#334155; padding-bottom:8px;">
                <div style="display:flex; align-items:center; gap:10px; color:#94a3b8; padding:12px 0;">
                    <div class="insights-spinner" style="width:20px; height:20px; border:3px solid #e2e8f0; border-top:3px solid #2563eb; border-radius:50%; animation:spin 1s linear infinite;"></div>
                    Analyzing your hiring pipeline...
                </div>
            </div>
        </div>
    </section>

    <!-- Styles for AI Hiring Insights card — @author Kratika Patidar -->
    <style>
        @keyframes spin { to { transform: rotate(360deg); } }
        #insights-content ul { margin: 8px 0; padding-left: 20px; }
        #insights-content li { margin-bottom: 6px; }
        #insights-content strong { color: #0f172a; }
        .insights-error { color: #991b1b; background: #fee2e2; padding: 12px 16px; border-radius: 10px; font-size: 13px; }
        #insights-toolbar:hover { background: #f8fafc; border-radius: 8px; margin: 0 -8px; padding: 14px 8px; }

        /* Color-coded insight cards — @author Kratika Patidar */
        .insight-card {
            border-left: 4px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 0 10px 10px 0;
            padding: 14px 18px;
            margin-bottom: 12px;
        }
        .insight-header {
            font-weight: 700;
            font-size: 14px;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .insight-body {
            font-size: 13px;
            line-height: 1.6;
            color: #475569;
        }
        /* Green — positive trends */
        .insight-success { border-left-color: #22c55e; background: #f0fdf4; }
        .insight-success .insight-header { color: #166534; }
        /* Amber — needs attention */
        .insight-warning { border-left-color: #f59e0b; background: #fffbeb; }
        .insight-warning .insight-header { color: #92400e; }
        /* Red — critical / red flags */
        .insight-danger { border-left-color: #ef4444; background: #fef2f2; }
        .insight-danger .insight-header { color: #991b1b; }
        /* Blue — informational */
        .insight-info { border-left-color: #3b82f6; background: #eff6ff; }
        .insight-info .insight-header { color: #1e40af; }
        /* Gray — general tips */
        .insight-neutral { border-left-color: #94a3b8; background: #f8fafc; }
        .insight-neutral .insight-header { color: #475569; }
    </style>

    <!-- JavaScript for AI Hiring Insights — @author Kratika Patidar
         toggleInsights(): Expands/collapses the insights panel. Loads data on first open.
         loadInsights(): Fetches insights from api/hr_insights.php (Claude API)
         and renders the HTML response in the insights panel. -->
    <script>
    let insightsOpen = false;
    let insightsLoaded = false;

    // Toggle the insights panel open/closed — @author Kratika Patidar
    function toggleInsights() {
        const panel = document.getElementById('insights-panel');
        const arrow = document.getElementById('insights-arrow');
        insightsOpen = !insightsOpen;

        if (insightsOpen) {
            // Expand panel
            panel.style.maxHeight = panel.scrollHeight + 2000 + 'px';
            arrow.style.transform = 'rotate(180deg)';

            // Load insights on first open (saves API calls)
            if (!insightsLoaded) {
                loadInsights();
            }
        } else {
            // Collapse panel
            panel.style.maxHeight = '0';
            arrow.style.transform = 'rotate(0deg)';
        }
    }

    function loadInsights() {
        const container = document.getElementById('insights-content');
        const btn = document.getElementById('refresh-insights-btn');

        // Show loading spinner while Claude analyzes the data
        container.innerHTML = '<div style="display:flex;align-items:center;gap:10px;color:#94a3b8;padding:12px 0;">' +
            '<div style="width:20px;height:20px;border:3px solid #e2e8f0;border-top:3px solid #2563eb;border-radius:50%;animation:spin 1s linear infinite;"></div>' +
            'Analyzing your hiring pipeline...</div>';
        btn.disabled = true;
        btn.style.opacity = '0.5';

        // Call the HR Insights API endpoint
        fetch('api/hr_insights.php')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    container.innerHTML = data.message;
                    insightsLoaded = true;
                } else {
                    var errText = document.createElement('span');
                    errText.textContent = data.error || 'Unknown error';
                    container.innerHTML = '<div class="insights-error">Unable to load insights: ' +
                        errText.innerHTML + '</div>';
                }
                // Update panel height after content loads
                const panel = document.getElementById('insights-panel');
                if (insightsOpen) {
                    panel.style.maxHeight = panel.scrollHeight + 'px';
                }
            })
            .catch(err => {
                container.innerHTML = '<div class="insights-error">Network error. Please try again.</div>';
            })
            .finally(() => {
                btn.disabled = false;
                btn.style.opacity = '1';
            });
    }
    </script>

    <section class="card">
        <form method="GET" class="filters-form">
            <div class="filter-item">
                <label class="filter-label" for="filter-status">Status</label>
                <select id="filter-status" name="status" class="filter-control">
                    <?php foreach (JOB_FILTER_STATUSES as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo ($status === $opt) ? 'selected' : ''; ?>>
                            <?php echo $opt; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-item">
                <label class="filter-label" for="filter-search">Search</label>
                <input id="filter-search" type="text" name="q" class="filter-control" value="<?php echo e($q); ?>" placeholder="Job title or O*NET…" />
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn">Apply</button>
                <a href="?" class="btn">Reset</a>
                <a href="create_job.php" class="btn btn-primary">+ Create Job</a>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="card-header">
            <h2 class="card-title">All Jobs (<?php echo $totalRows; ?>)</h2>
        </div>

        <?php if (empty($jobs)): ?>
            <p class="muted">No jobs found. <a href="create_job.php" class="action-link">Create your first job →</a></p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="jobs-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Job Title</th>
                            <th>O*NET Occupation</th>
                            <th>Status</th>
                            <th>Skills</th>
                            <th>Apps</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $j):
                            $badgeClass = match($j['status']) {
                                'Open' => 'badge-open', 'Closed' => 'badge-closed', default => 'badge-draft'
                            };
                            $createdAt = $j['created_at'] ? date('M j, Y', strtotime($j['created_at'])) : '—';
                        ?>
                            <tr>
                                <td style="color:#94a3b8;font-size:12px;">#<?php echo $j['job_id']; ?></td>
                                <td style="font-weight:600;"><?php echo e($j['job_title'] ?: '(Untitled)'); ?></td>
                                <td style="color:#475569;"><?php echo e($j['onet_title'] ?: ($j['onet_soc_code'] ?: '—')); ?></td>
                                <td><span class="badge <?php echo $badgeClass; ?>"><?php echo e($j['status']); ?></span></td>
                                <td><?php echo $j['skills_count']; ?></td>
                                <td>
                                    <?php if ($j['apps_count'] > 0): ?>
                                        <a href="job_applications.php?job_id=<?php echo $j['job_id']; ?>" class="action-link" style="display:inline-flex;align-items:center;gap:4px;">
                                            <span style="background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:700;"><?php echo $j['apps_count']; ?></span>
                                            View
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#cbd5e1;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color:#64748b;font-size:13px;"><?php echo e($createdAt); ?></td>
                                
                                <td style="display: flex; gap: 10px; align-items: center; border-bottom: none;">
                                    <a href="create_job.php?job_id=<?php echo $j['job_id']; ?>" class="action-link">Edit</a>
                                    <span style="color: #cbd5e1;">|</span>
                                    <form action="api/delete_job.php" method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this job? This cannot be undone.');">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="job_id" value="<?php echo $j['job_id']; ?>">
                                        <button type="submit" class="action-link" style="background: none; border: none; padding: 0; color: #ef4444; font-family: inherit;">Delete</button>
                                    </form>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <nav class="pagination">
                <span class="pagination-meta">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                <div class="pagination-actions">
                    <?php if ($page > 1): ?>
                        <a class="btn" href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $page - 1])); ?>">← Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-primary" href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $page + 1])); ?>">Next →</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>

</body>
</html>
