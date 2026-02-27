<?php
/**
 * dashboard.php — HR Dashboard
 * @author Vaishnavi Pushparaj Samani
 * * Database: talentsync_db
 * Tables used: jobs, occupation_data, job_skills, users
 */

session_start();
require_once __DIR__ . '/config/db.php';

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ══════════════════════════════════════════════════════════════════════════════
// TEMPORARY: Bypass login for testing (remove when Lee finishes login.php)
// ══════════════════════════════════════════════════════════════════════════════
//$userId   = 2;  // Hardcoded test user
//$fullName = 'Vaishnavi Test';


// ── Session guard (COMMENTED OUT FOR TESTING) ─────────────────────────────────
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $userId   = (int)$_SESSION['user']['user_id'];
    $fullName = $_SESSION['user']['full_name'] ?? 'HR Manager';
} elseif (isset($_SESSION['user_id'])) {
    $userId   = (int)$_SESSION['user_id'];
    $fullName = $_SESSION['full_name'] ?? 'HR Manager';
} else {
    header('Location: login.php');  // ← This was redirecting you!
    exit;
}


// ── Query parameters ──────────────────────────────────────────────────────────
$status = $_GET['status'] ?? 'All';
$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

// ── Summary counts ────────────────────────────────────────────────────────────
$counts = ['Draft' => 0, 'Open' => 0, 'Closed' => 0, 'Total' => 0];

$stmt = $conn->prepare("SELECT status, COUNT(*) AS cnt FROM jobs WHERE user_id = ? GROUP BY status");
$stmt->bind_param('i', $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['Total'] += (int)$row['cnt'];
}
$stmt->close();

// ── Build WHERE clause ────────────────────────────────────────────────────────
$where  = ['j.user_id = ?'];
$types  = 'i';
$params = [$userId];

if (in_array($status, ['Draft', 'Open', 'Closed'], true)) {
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

// Helper: dynamic bind_param
function bindParams($stmt, $types, $params) {
    $refs = [];
    foreach ($params as $k => $v) $refs[$k] = &$params[$k];
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

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
        COALESCE(js.skills_count, 0) AS skills_count
    FROM jobs j
    LEFT JOIN occupation_data od ON od.onetsoc_code = j.onet_soc_code
    LEFT JOIN (
        SELECT job_id, COUNT(*) AS skills_count
        FROM job_skills
        GROUP BY job_id
    ) js ON js.job_id = j.job_id
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
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 32px 24px 80px; }
        .page-header { margin-bottom: 32px; }
        .page-title { font-size: 28px; font-weight: 800; color: #0f172a; margin: 0 0 6px; }
        .page-subtitle { font-size: 15px; color: #64748b; margin: 0; }

        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .summary-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 22px 24px; box-shadow: 0 1px 4px rgba(0,0,0,.04); }
        .summary-label { font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
        .summary-value { font-size: 34px; font-weight: 800; color: #0f172a; }

        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; box-shadow: 0 1px 4px rgba(0,0,0,.04); margin-bottom: 24px; }
        .card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .card-title { font-size: 17px; font-weight: 700; color: #0f172a; margin: 0; }

        .filters-form { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 16px; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; flex: 1; min-width: 160px; }
        .filter-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .4px; }
        .filter-control { height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; color: #0f172a; background: #f8fafc; outline: none; transition: border-color .2s; }
        .filter-control:focus { border-color: #2563eb; background: #fff; }
        .filter-actions { display: flex; gap: 10px; align-items: flex-end; }

        .btn { display: inline-flex; align-items: center; justify-content: center; height: 40px; padding: 0 18px; border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; transition: background .2s; }
        .btn:hover { background: #f1f5f9; }
        .btn-primary { background: #2563eb; color: #fff; border-color: #2563eb; }
        .btn-primary:hover { background: #1d4ed8; }

        .table-wrap { overflow-x: auto; }
        .jobs-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .jobs-table th { text-align: left; padding: 10px 14px; font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: .4px; border-bottom: 1px solid #e2e8f0; white-space: nowrap; }
        .jobs-table td { padding: 13px 14px; color: #0f172a; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        .jobs-table tr:last-child td { border-bottom: none; }
        .jobs-table tr:hover td { background: #f8fafc; }

        .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .badge-draft  { background: #fef9c3; color: #854d0e; }
        .badge-open   { background: #dcfce7; color: #166534; }
        .badge-closed { background: #fee2e2; color: #991b1b; }

        .action-link { font-size: 13px; color: #2563eb; text-decoration: none; font-weight: 600; cursor: pointer; }
        .action-link:hover { text-decoration: underline; }

        .pagination { display: flex; align-items: center; justify-content: space-between; margin-top: 20px; flex-wrap: wrap; gap: 12px; }
        .pagination-meta { font-size: 14px; color: #64748b; }
        .pagination-actions { display: flex; gap: 8px; }

        .muted { color: #94a3b8; font-size: 14px; padding: 16px 0; }
        .flash { padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }
        .flash-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }

        @media (max-width: 768px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
            .filters-form { flex-direction: column; }
            .filter-item { min-width: 100%; }
        }
    </style>
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

    <section class="card">
        <form method="GET" class="filters-form">
            <div class="filter-item">
                <label class="filter-label">Status</label>
                <select name="status" class="filter-control">
                    <?php foreach (['All', 'Draft', 'Open', 'Closed'] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo ($status === $opt) ? 'selected' : ''; ?>>
                            <?php echo $opt; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-item">
                <label class="filter-label">Search</label>
                <input type="text" name="q" class="filter-control" value="<?php echo e($q); ?>" placeholder="Job title or O*NET…" />
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
                                <td style="color:#64748b;font-size:13px;"><?php echo e($createdAt); ?></td>
                                
                                <td style="display: flex; gap: 10px; align-items: center; border-bottom: none;">
                                    <a href="create_job.php?job_id=<?php echo $j['job_id']; ?>" class="action-link">Edit</a>
                                    <span style="color: #cbd5e1;">|</span>
                                    <form action="api/delete_job.php" method="POST" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this job? This cannot be undone.');">
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