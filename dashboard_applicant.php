<?php
/**
 * dashboard_applicant.php — Applicant Dashboard
 * @author Vaishnavi Pushparaj Samani
 * 
 * Displays job applications for logged-in applicants with status tracking,
 * search functionality, and pagination.
 * 
 * Database: talentsync_db
 * Tables: applications, jobs, users, occupation_data
 */

session_start();
require_once __DIR__ . '/config/db.php';

function e($s) { 
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); 
}

// ── Session guard ─────────────────────────────────────────────────────────────
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $userId   = (int)$_SESSION['user']['user_id'];
    $fullName = $_SESSION['user']['full_name'] ?? 'Candidate';
    $role     = $_SESSION['user']['role'] ?? '';
} elseif (isset($_SESSION['user_id'])) {
    $userId   = (int)$_SESSION['user_id'];
    $fullName = $_SESSION['full_name'] ?? 'Candidate';
    $role     = $_SESSION['role'] ?? '';
} else {
    // User not logged in - redirect to login page
    header('Location: login.php');
    exit;
}

// Optional: Restrict to applicants only (uncomment if needed)
// if ($role !== 'Applicant' && $role !== 'applicant') {
//     header('Location: dashboard.php');
//     exit;
// }

// ── Query parameters ──────────────────────────────────────────────────────────
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// ── Helper function for dynamic bind_param ───────────────────────────────────
function bindParams($stmt, $types, $params) {
    if ($types === '') return;
    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// ── Summary counts ────────────────────────────────────────────────────────────
$counts = [
    'Total' => 0,
    'Interviewing' => 0,
    'Offer' => 0,
    'Rejected' => 0
];

$stmt = $conn->prepare("
    SELECT status, COUNT(*) AS cnt
    FROM applications
    WHERE user_id = ?
    GROUP BY status
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $status = $row['status'] ?? '';
    $counts['Total'] += (int)$row['cnt'];
    
    // Map database statuses to dashboard categories
    if (stripos($status, 'interview') !== false) {
        $counts['Interviewing'] += (int)$row['cnt'];
    } elseif (stripos($status, 'offer') !== false || stripos($status, 'accept') !== false) {
        $counts['Offer'] += (int)$row['cnt'];
    } elseif (stripos($status, 'reject') !== false || stripos($status, 'declin') !== false) {
        $counts['Rejected'] += (int)$row['cnt'];
    }
}
$stmt->close();

// ── Build search query ────────────────────────────────────────────────────────
$where = ["a.user_id = ?"];
$types = "i";
$params = [$userId];

if ($q !== '') {
    $like = "%$q%";
    $where[] = "(j.job_title LIKE ? OR u.full_name LIKE ? OR od.title LIKE ?)";
    $types .= "sss";
    array_push($params, $like, $like, $like);
}

$whereSql = "WHERE " . implode(" AND ", $where);

// ── Get total count for pagination ───────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM applications a
    LEFT JOIN jobs j ON a.job_id = j.job_id
    LEFT JOIN users u ON j.user_id = u.user_id
    LEFT JOIN occupation_data od ON od.onetsoc_code = j.onet_soc_code
    $whereSql
");
bindParams($stmt, $types, $params);
$stmt->execute();
$totalRows = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = max(1, ceil($totalRows / $limit));

// ── Fetch applications ────────────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT
        a.application_id,
        a.status,
        a.applied_at,
        j.job_id,
        j.job_title,
        j.status AS job_status,
        u.full_name AS company,
        od.title AS onet
    FROM applications a
    LEFT JOIN jobs j ON a.job_id = j.job_id
    LEFT JOIN users u ON j.user_id = u.user_id
    LEFT JOIN occupation_data od ON od.onetsoc_code = j.onet_soc_code
    $whereSql
    ORDER BY a.applied_at DESC
    LIMIT ? OFFSET ?
");

bindParams($stmt, $types . 'ii', array_merge($params, [$limit, $offset]));
$stmt->execute();
$apps = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Dashboard - TalentSync</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .container { max-width: 1200px; margin: 0 auto; padding: 32px 24px; }
        .page-title { font-size: 28px; font-weight: 800; color: #0f172a; margin-bottom: 6px; }
        .page-subtitle { color: #64748b; font-size: 15px; margin-bottom: 28px; }
        
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
        .summary-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 22px 24px; }
        .summary-label { font-size: 13px; font-weight: 600; color: #64748b; text-transform: uppercase; margin-bottom: 8px; }
        .summary-value { font-size: 32px; font-weight: 800; }
        
        .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .card-title { font-size: 18px; font-weight: 700; margin: 0 0 18px; color: #0f172a; }
        
        .filters-form { display: flex; gap: 14px; flex-wrap: wrap; align-items: end; }
        .filter-item { display: flex; flex-direction: column; gap: 6px; min-width: 220px; flex: 1; }
        .filter-label { font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; }
        .filter-control { height: 40px; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; }
        
        .btn { display: inline-flex; align-items: center; justify-content: center; height: 40px; padding: 0 18px; 
               border-radius: 10px; font-size: 14px; font-weight: 600; text-decoration: none; 
               border: 1px solid #cbd5e1; background: #f8fafc; color: #0f172a; cursor: pointer; }
        .btn-primary { background: #2563eb; color: #fff; border-color: #2563eb; }
        .btn:hover { background: #f1f5f9; }
        .btn-primary:hover { background: #1d4ed8; }
        
        .app-table { width: 100%; border-collapse: collapse; }
        .app-table th { text-align: left; padding: 10px 14px; font-size: 12px; font-weight: 700; 
                        color: #64748b; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; }
        .app-table td { padding: 13px 14px; border-bottom: 1px solid #f1f5f9; color: #0f172a; }
        
        /* Badge colors per specification */
        .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .badge-interviewing { background: #fef9c3; color: #854d0e; }  /* Yellow */
        .badge-applied { background: #dbeafe; color: #1e40af; }       /* Blue */
        .badge-offer { background: #dcfce7; color: #166534; }         /* Green */
        .badge-rejected { background: #fee2e2; color: #991b1b; }      /* Red */
        
        .muted { color: #94a3b8; font-size: 14px; }
        .pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
        
        @media (max-width: 768px) {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<main class="container">
    <h1 class="page-title">Applicant Dashboard</h1>
    <p class="page-subtitle">Welcome back, <?= e($fullName) ?>.</p>

    <div style="margin: 18px 0 28px;">
        <a href="browse_jobs.php" class="btn btn-primary">Browse Jobs</a>
    </div>

    <!-- Summary Cards -->
    <section class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">Interviewing</div>
            <div class="summary-value" style="color: #854d0e;"><?= $counts['Interviewing'] ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Total Application</div>
            <div class="summary-value" style="color: #1e40af;"><?= $counts['Total'] ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Offer</div>
            <div class="summary-value" style="color: #166534;"><?= $counts['Offer'] ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Rejected</div>
            <div class="summary-value" style="color: #991b1b;"><?= $counts['Rejected'] ?></div>
        </div>
    </section>

    <!-- Search Filter -->
    <section class="card">
        <h2 class="card-title">My Applications</h2>
        <form method="GET" class="filters-form">
            <div class="filter-item">
                <label class="filter-label">Search</label>
                <input type="text" name="q" class="filter-control" 
                       value="<?= e($q) ?>" placeholder="Job title, company, O*NET">
            </div>
            <div>
                <button type="submit" class="btn">Apply</button>
                <a href="dashboard_applicant.php" class="btn">Reset</a>
            </div>
        </form>
    </section>

    <!-- Applications Table -->
    <section class="card">
        <?php if (empty($apps)): ?>
            <p class="muted">No applications yet. <a href="browse_jobs.php" style="color: #2563eb;">Browse jobs →</a></p>
        <?php else: ?>
            <table class="app-table">
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Company</th>
                        <th>O*NET</th>
                        <th>Status</th>
                        <th>Job Status</th>
                        <th>Applied</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($apps as $a):
                        // Determine badge class based on status
                        $s = strtolower($a['status'] ?? 'pending');
                        if (strpos($s, 'interview') !== false) {
                            $badge = 'interviewing';
                        } elseif (strpos($s, 'offer') !== false || strpos($s, 'accept') !== false) {
                            $badge = 'offer';
                        } elseif (strpos($s, 'reject') !== false || strpos($s, 'declin') !== false) {
                            $badge = 'rejected';
                        } else {
                            $badge = 'applied';
                        }
                    ?>
                        <tr>
                            <td style="font-weight: 600;"><?= e($a['job_title'] ?: '(Untitled)') ?></td>
                            <td><?= e($a['company'] ?: 'Unknown') ?></td>
                            <td style="color: #475569;"><?= e($a['onet'] ?: '—') ?></td>
                            <td>
                                <span class="badge badge-<?= $badge ?>">
                                    <?= e($a['status'] ?: 'Pending') ?>
                                </span>
                            </td>
                            <td><?= e($a['job_status'] ?: '—') ?></td>
                            <td style="color: #64748b; font-size: 13px;">
                                <?= $a['applied_at'] ? date('M j, Y', strtotime($a['applied_at'])) : '—' ?>
                            </td>
                            <td>
                                <a href="job_detail.php?id=<?= (int)$a['job_id'] ?>" 
                                   style="font-size: 13px; color: #2563eb; font-weight: 600;">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <nav class="pagination">
                <span style="font-size: 14px; color: #64748b;">
                    Page <?= $page ?> of <?= $totalPages ?>
                </span>
                <div style="display: flex; gap: 8px;">
                    <?php if ($page > 1): ?>
                        <a class="btn" href="?q=<?= urlencode($q) ?>&page=<?= $page - 1 ?>">← Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn btn-primary" href="?q=<?= urlencode($q) ?>&page=<?= $page + 1 ?>">Next →</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</main>

<?php include 'includes/footer.php'; ?>

</body>
</html>