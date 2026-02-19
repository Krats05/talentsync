<?php
session_start();


// =========================
// DEV / DEBUG SETTINGS
// =========================

$DEV_SHOW_ERRORS  = true;
$DEV_BYPASS_LOGIN = true;  // set true to skip login during development

if ($DEV_SHOW_ERRORS) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}


// DEV bypass: simulate a logged-in HR user
// - user_id must exist in your DB
// - role must match your users.role ENUM: Admin / HR_Manager / Recruiter

if ($DEV_BYPASS_LOGIN) {
    $_SESSION['user_id'] = 1;
    $_SESSION['role'] = 'HR_Manager';
    $_SESSION['full_name'] = 'Test HR';
}

require_once __DIR__ . "/../config/db.php";

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }


// =========================
// AUTHORIZATION
// =========================

if (!isset($_SESSION['user_id'])) {
    header("Location: /talentsync/login.php");
    exit;
}

$userId = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? null;
$fullName = $_SESSION['full_name'] ?? 'HR';

$allowedRoles = ['Admin', 'HR_Manager', 'Recruiter'];
if (!$role || !in_array($role, $allowedRoles, true)) {
    header("Location: /talentsync/login.php");
    exit;
}


// =========================
// QUERY PARAMS
// =========================

$status = $_GET['status'] ?? 'All';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$validStatuses = ['All', 'Draft', 'Open', 'Closed'];
if (!in_array($status, $validStatuses, true)) $status = 'All';


// =========================
// 1) SUMMARY COUNTS
// =========================

$summary = ['Draft' => 0, 'Open' => 0, 'Closed' => 0, 'Total' => 0];

$stmt = $conn->prepare("
    SELECT status, COUNT(*) AS cnt
    FROM jobs
    WHERE user_id = ?
    GROUP BY status
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $st = $row['status'];
    if (isset($summary[$st])) $summary[$st] = (int)$row['cnt'];
    $summary['Total'] += (int)$row['cnt'];
}
$stmt->close();


// =========================
// 2) BUILD FILTERS
// =========================

$where = ["j.user_id = ?"];
$types = "i";
$params = [$userId];

if (in_array($status, ['Draft','Open','Closed'], true)) {
    $where[] = "j.status = ?";
    $types  .= "s";
    $params[] = $status;
}

if ($q !== '') {
    $like = "%$q%";
    $where[] = "(j.job_title LIKE ? OR od.title LIKE ?)";
    $types  .= "ss";
    $params[] = $like;
    $params[] = $like;
}

$whereSql = "WHERE " . implode(" AND ", $where);

function bindParams(mysqli_stmt $stmt, string $types, array $params) {
    $refs = [];
    foreach ($params as $k => $v) $refs[$k] = &$params[$k];
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}


// =========================
// 3) TOTAL ROWS (pagination)
// =========================

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM jobs j
    LEFT JOIN occupation_data od ON od.onetsoc_code = j.onet_soc_code
    $whereSql
");
bindParams($stmt, $types, $params);
$stmt->execute();
$totalRows = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $limit));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $limit; }


// =========================
// 4) JOBS LIST
// =========================

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
    <title>Dashboard Statistics</title>

    <!-- Use the exact path you requested -->
    <link rel="stylesheet" href="/talentsync/assets/style.css">
</head>
<body>

<?php
$navbarPath = __DIR__ . "/../navbar.php";
if (file_exists($navbarPath)) include $navbarPath;
?>

<main class="footer-container">
    <h1>Dashboard Statistics</h1>
    <p>Welcome, <?php echo e($fullName); ?>.</p>

    <div style="margin: 12px 0;">
        <a class="btn btn-white" href="/talentsync/dashboard.php">Back to Dashboard</a>
    </div>

    <section style="margin: 18px 0;">
        <h2>Summary</h2>
        <ul>
            <li>Draft: <?php echo (int)$summary['Draft']; ?></li>
            <li>Open: <?php echo (int)$summary['Open']; ?></li>
            <li>Closed: <?php echo (int)$summary['Closed']; ?></li>
            <li>Total: <?php echo (int)$summary['Total']; ?></li>
        </ul>
    </section>

    <section style="margin: 18px 0;">
        /<h2>Filter</h2>
        <form method="GET">
            <label for="status">Status</label>
            <select id="status" name="status">
                <?php foreach ($validStatuses as $opt): ?>
                    <option value="<?php echo e($opt); ?>" <?php echo ($status === $opt) ? 'selected' : ''; ?>>
                        <?php echo e($opt); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="q" style="margin-left:10px;">Search</label>
            <input id="q" type="text" name="q" value="<?php echo e($q); ?>" placeholder="job title / O*NET title" />

            <button type="submit" class="btn btn-black" style="margin-left:10px;">Apply</button>
        </form>
    </section>

    <section style="margin: 18px 0;">
        <h2>Jobs (<?php echo $totalRows; ?>)</h2>

        <?php if (empty($jobs)): ?>
            <p>No jobs found.</p>
        <?php else: ?>
            <table>
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

        <div style="margin-top:12px;">
            <span>Page <?php echo $page; ?> / <?php echo $totalPages; ?></span>

            <?php if ($page > 1): ?>
                <a class="btn btn-white" href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $page - 1])); ?>">Prev</a>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <a class="btn btn-white" href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $page + 1])); ?>">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>
</main>

<?php
$footerPath = __DIR__ . "/../footer.php";
if (file_exists($footerPath)) include $footerPath;
?>

</body>
</html>
