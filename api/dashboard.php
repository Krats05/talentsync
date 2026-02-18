<?php
//These are used to display error for debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

//Debug lines end



session_start();
require_once __DIR__ . "/../config/db.php";

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Session guard
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? null;        // expected: hr / candidate / admin
$fullName = $_SESSION['full_name'] ?? 'HR';

// Authorization (dashboard is HR-facing)
$allowedRoles = ['hr', 'admin'];
if (!$role || !in_array($role, $allowedRoles, true)) {
    header("Location: ../login.php");
    exit;
}

// DB connection guard
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("DB connection not found. Check config/db.php for \$conn (mysqli).");
}

// Query params
$status = $_GET['status'] ?? 'All';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Summary counts
$counts = ['Draft' => 0, 'Open' => 0, 'Closed' => 0, 'Total' => 0];

$stmt = $conn->prepare("SELECT status, COUNT(*) AS cnt FROM jobs WHERE user_id = ? GROUP BY status");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $counts[$row['status']] = (int)$row['cnt'];
    $counts['Total'] += (int)$row['cnt'];
}
$stmt->close();

// Dynamic filters (prepared)
$where = ["j.user_id = ?"];
$types = "i";
$params = [$userId];

if (in_array($status, ['Draft','Open','Closed'], true)) {
    $where[] = "j.status = ?";
    $types .= "s";
    $params[] = $status;
}

if ($q !== '') {
    $like = "%$q%";
    $where[] = "(j.job_title LIKE ? OR od.title LIKE ?)";
    $types .= "ss";
    $params[] = $like;
    $params[] = $like;
}

$whereSql = "WHERE " . implode(" AND ", $where);

// bind_param helper (supports dynamic param lists)
function bindParams(mysqli_stmt $stmt, string $types, array $params) {
    $refs = [];
    foreach ($params as $k => $v) $refs[$k] = &$params[$k];
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// Pagination total
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM jobs j
    LEFT JOIN occupation_data od ON od.onetsoc_code = j.onet_soc_code
    $whereSql");

bindParams($stmt, $types, $params);
$stmt->execute();
$totalRows = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $limit));

// Job list (includes O*NET title + skill count)
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
    LIMIT ? OFFSET ?");

$typesList = $types . "ii";
$paramsList = array_merge($params, [$limit, $offset]);
bindParams($stmt, $typesList, $paramsList);

$stmt->execute();
$r = $stmt->get_result();
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
    <title>HR Dashboard</title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>

<?php
$navbarPath = __DIR__ . "/../includes/navbar.php";
if (file_exists($navbarPath)) include $navbarPath;
?>

<main class="container">
    <header class="page-header">
        <h1 class="page-title">HR Dashboard</h1>
        <p class="page-subtitle">Welcome, <?php echo e($fullName); ?>.</p>
    </header>

    <section class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">Active Drafts</div>
            <div class="summary-value"><?php echo $counts['Draft']; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Open Jobs</div>
            <div class="summary-value"><?php echo $counts['Open']; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Closed Jobs</div>
            <div class="summary-value"><?php echo $counts['Closed']; ?></div>
        </div>
        <div class="summary-card">
            <div class="summary-label">Total</div>
            <div class="summary-value"><?php echo $counts['Total']; ?></div>
        </div>
    </section>

    <section class="card filters-card">
        <form method="GET" class="filters-form">
            <div class="filter-item">
                <label class="filter-label" for="status">Status</label>
                <select id="status" name="status" class="filter-control">
                    <?php foreach (['All','Draft','Open','Closed'] as $opt): ?>
                        <option value="<?php echo e($opt); ?>" <?php echo ($status === $opt) ? 'selected' : ''; ?>>
                            <?php echo e($opt); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-item">
                <label class="filter-label" for="q">Search</label>
                <input id="q" type="text" name="q" class="filter-control" value="<?php echo e($q); ?>" placeholder="job title / O*NET title" />
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn">Apply</button>
                <a href="../create_job.php" class="btn btn-primary">+ Create Job</a>
            </div>
        </form>
    </section>

    <section class="card table-card">
        <div class="card-header">
            <h2 class="card-title">All Created Jobs</h2>
        </div>

        <?php if (empty($jobs)): ?>
            <p class="muted">No jobs found.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="jobs-table">
                    <thead>
                        <tr>
                            <th>Job ID</th>
                            <th>Job Title</th>
                            <th>O*NET Title</th>
                            <th>Status</th>
                            <th>#Skills</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $j): ?>
                            <tr>
                                <td><?php echo (int)$j['job_id']; ?></td>
                                <td><?php echo e($j['job_title'] ?: '(Untitled)'); ?></td>
                                <td><?php echo e($j['onet_title'] ?: ($j['onet_soc_code'] ?: '-')); ?></td>
                                <td><?php echo e($j['status']); ?></td>
                                <td><?php echo (int)$j['skills_count']; ?></td>
                                <td><?php echo e($j['created_at'] ?? '-'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <nav class="pagination">
                <span class="pagination-meta">Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>
                <div class="pagination-actions">
                    <?php if ($page > 1): ?>
                        <a class="btn" href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $page - 1])); ?>">Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a class="btn" href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $page + 1])); ?>">Next</a>
                    <?php endif; ?>
                </div>
            </nav>
        <?php endif; ?>
    </section>
</main>

<?php
$footerPath = __DIR__ . "/../includes/footer.php";
if (file_exists($footerPath)) include $footerPath;
?>

</body>
</html>
