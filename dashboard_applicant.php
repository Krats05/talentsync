<?php
session_start();
require_once __DIR__ . '/config/db.php';

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Session guard
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    $userId   = (int)$_SESSION['user']['user_id'];
    $fullName = $_SESSION['user']['full_name'] ?? 'Candidate';
    $role     = $_SESSION['user']['role'] ?? '';
} elseif (isset($_SESSION['user_id'])) {
    $userId   = (int)$_SESSION['user_id'];
    $fullName = $_SESSION['full_name'] ?? 'Candidate';
    $role     = $_SESSION['role'] ?? '';
} else {
    header('Location: login.php');
    exit;
}

// Optional: restrict this page to applicants only
if ($role !== 'Applicant' && $role !== 'applicant') {
    header('Location: dashboard_hr.php'); // Changed from Dashboard.php
// Restrict page to job applicants
if ($role !== 'job_applicant') {
    header("Location: login.php");
    exit;
}

// Search / pagination
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Helper for dynamic bind_param
function bindParams($stmt, $types, $params) {
    if ($types === '') return;
    $refs = [];
    foreach ($params as $k => $v) {
        $refs[$k] = &$params[$k];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// Summary counts
$counts = [
    'Total' => 0,
    'Pending' => 0,
    'Reviewed' => 0,
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
    $status = $row['status'] ?? 'Pending';
    $counts['Total'] += (int)$row['cnt'];

    if (isset($counts[$status])) {
        $counts[$status] = (int)$row['cnt'];
    }
}
$stmt->close();

// Build search condition
$where = ["a.user_id = ?"];
$types = "i";
$params = [$userId];

if ($q !== '') {
    $like = "%$q%";
    $where[] = "(j.job_title LIKE ? OR u.full_name LIKE ? OR od.title LIKE ?)";
    $types .= "sss";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = "WHERE " . implode(" AND ", $where);

// Total rows
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

$totalPages = max(1, (int)ceil($totalRows / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// Fetch applications
$stmt = $conn->prepare("
    SELECT
        a.application_id,
        a.status AS application_status,
        a.applied_at,
        j.job_id,
        j.job_title,
        j.status AS job_status,
        u.full_name AS company_name,
        od.title AS onet_title
    FROM applications a
    LEFT JOIN jobs j ON a.job_id = j.job_id
    LEFT JOIN users u ON j.user_id = u.user_id
    LEFT JOIN occupation_data od ON od.onetsoc_code = j.onet_soc_code
    $whereSql
    ORDER BY a.applied_at DESC
    LIMIT ? OFFSET ?
");

$typesList = $types . "ii";
$paramsList = array_merge($params, [$limit, $offset]);

bindParams($stmt, $typesList, $paramsList);
$stmt->execute();
$r = $stmt->get_result();

$applications = [];
while ($row = $r->fetch_assoc()) {
    $applications[] = $row;
}
$stmt->close();

$baseQuery = ['q' => $q];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Dashboard - TalentSync</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 32px 24px 80px;
        }

        .page-header {
            margin-bottom: 28px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .page-subtitle {
            color: #64748b;
            font-size: 15px;
        }

        .top-actions {
            display: flex;
            gap: 12px;
            margin: 18px 0 28px;
            flex-wrap: wrap;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
        }

        .summary-label {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .summary-value {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
        }

        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 4px rgba(0,0,0,.04);
            margin-bottom: 24px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 18px;
            color: #0f172a;
        }

        .filters-form {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 220px;
            flex: 1;
        }

        .filter-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }

        .filter-control {
            height: 40px;
            padding: 0 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 14px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 40px;
            padding: 0 18px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #0f172a;
            cursor: pointer;
        }

        .btn-primary {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        .btn:hover {
            background: #f1f5f9;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .table-wrap {
            overflow-x: auto;
        }

        .app-table {
            width: 100%;
            border-collapse: collapse;
        }

        .app-table th {
            text-align: left;
            padding: 10px 14px;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
        }

        .app-table td {
            padding: 13px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #0f172a;
        }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-reviewed {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-default {
            background: #e2e8f0;
            color: #334155;
        }

        .muted {
            color: #94a3b8;
            font-size: 14px;
        }

        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .pagination-actions {
            display: flex;
            gap: 8px;
        }

        @media (max-width: 768px) {
            .summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<main class="container">
    <header class="page-header">
        <h1 class="page-title">Applicant Dashboard</h1>
        <p class="page-subtitle">Welcome back, <?php echo e($fullName); ?>.</p>
    </header>

    <div class="top-actions">
        <a href="browse_jobs.php" class="btn btn-primary">Browse Jobs</a>
    </div>

    <section class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">Total Applications</div>
            <div class="summary-value"><?php echo $counts['Total']; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Pending</div>
            <div class="summary-value" style="color:#92400e;"><?php echo $counts['Pending']; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Reviewed</div>
            <div class="summary-value" style="color:#1d4ed8;"><?php echo $counts['Reviewed']; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Rejected</div>
            <div class="summary-value" style="color:#991b1b;"><?php echo $counts['Rejected']; ?></div>
        </div>
    </section>

    <section class="card">
        <h2 class="card-title">My Applications</h2>

        <form method="GET" class="filters-form">
            <div class="filter-item">
                <label class="filter-label">Search</label>
                <input
                    type="text"
                    name="q"
                    class="filter-control"
                    value="<?php echo e($q); ?>"
                    placeholder="Search by job title, company, O*NET title"
                >
            </div>

            <div>
                <button type="submit" class="btn">Apply</button>
                <a href="job_applicant_dashboard.php" class="btn">Reset</a>
            </div>
        </form>
    </section>

    <section class="card">
        <?php if (empty($applications)): ?>
            <p class="muted">You have not applied for any jobs yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="app-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Company / Publisher</th>
                            <th>O*NET Title</th>
                            <th>Application Status</th>
                            <th>Job Status</th>
                            <th>Applied At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $a): 
                            $statusClass = match($a['application_status']) {
                                'Pending' => 'badge-pending',
                                'Reviewed' => 'badge-reviewed',
                                'Rejected' => 'badge-rejected',
                                default => 'badge-default'
                            };
                            $appliedAt = $a['applied_at'] ? date('M j, Y', strtotime($a['applied_at'])) : '—';
                        ?>
                            <tr>
                                <td><?php echo e($a['job_title'] ?: '(Untitled)'); ?></td>
                                <td><?php echo e($a['company_name'] ?: 'Unknown'); ?></td>
                                <td><?php echo e($a['onet_title'] ?: '—'); ?></td>
                                <td>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo e($a['application_status'] ?: 'Pending'); ?>
                                    </span>
                                </td>
                                <td><?php echo e($a['job_status'] ?: '—'); ?></td>
                                <td><?php echo e($appliedAt); ?></td>
                                <td>
                                    <a href="job_detail.php?id=<?php echo (int)$a['job_id']; ?>" class="btn">
                                        View Job
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <nav class="pagination">
                <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
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