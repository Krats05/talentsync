<?php

session_start();

require_once __DIR__ . "/config/db.php";

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Query params
$q = trim($_GET['q'] ?? '');
$status = 'Open'; // default to Open for public browsing
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build filters
$where = [];
$types = '';
$params = [];

$where[] = "j.status = ?";
$where[] = "j.deleted_at IS NULL";
$types .= "s";
$params[] = "Open";

if ($q !== '') {
    $like = "%$q%";
    $where[] = "(j.job_title LIKE ? OR u.full_name LIKE ? OR od.title LIKE ?)";
    $types .= "sss";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = $where ? ("WHERE " . implode(" AND ", $where)) : "";

// bind_param helper
function bindParams(mysqli_stmt $stmt, string $types, array $params) {
    if ($types === "") return;
    $refs = [];
    foreach ($params as $k => $v) $refs[$k] = &$params[$k];
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

// Total rows
$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM jobs j
    LEFT JOIN users u ON u.user_id = j.user_id
    LEFT JOIN occupation_data od ON od.onetsoc_code = j.onet_soc_code
    $whereSql
");
bindParams($stmt, $types, $params);
$stmt->execute();
$totalRows = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = max(1, (int)ceil($totalRows / $limit));
if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $limit; }

// Job list
$stmt = $conn->prepare("
    SELECT
        j.job_id,
        j.job_title,
        j.status,
        j.created_at,
        u.full_name AS publisher_name,
        od.title AS onet_title
    FROM jobs j
    LEFT JOIN users u ON u.user_id = j.user_id
    LEFT JOIN occupation_data od ON od.onetsoc_code = j.onet_soc_code
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

$baseQuery = ['q' => $q, 'status' => $status];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Browse Jobs - TalentSync</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/browse_jobs.css">
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="container">
    <header class="page-header">
        <h1 class="page-title">Browse Jobs</h1>
        <p class="page-subtitle"><?php echo $totalRows; ?> open position<?php echo $totalRows !== 1 ? 's' : ''; ?> available</p>
    </header>

    <section class="card" style="margin-bottom: 24px;">
        <form method="GET" class="filters-form">
            <div class="filter-item">
                <label class="filter-label">Search</label>
                <input type="text" name="q" class="filter-control" value="<?php echo e($q); ?>" placeholder="Job title, company, or O*NET title..." />
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Search</button>
                <a href="browse_jobs.php" class="btn">Reset</a>
            </div>
        </form>
    </section>

    <?php if (empty($jobs)): ?>
        <section class="card">
            <p class="muted" style="text-align: center; padding: 40px 0;">No open jobs found. Try a different search.</p>
        </section>
    <?php else: ?>
        <div class="jobs-grid">
            <?php foreach ($jobs as $j):
                $createdAt = $j['created_at'] ? date('M j, Y', strtotime($j['created_at'])) : '';
            ?>
                <a href="job_detail.php?id=<?php echo (int)$j['job_id']; ?>" class="job-card">
                    <div class="job-card-top">
                        <span class="badge badge-open"><?php echo e($j['status']); ?></span>
                        <span class="job-date"><?php echo e($createdAt); ?></span>
                    </div>
                    <h3 class="job-card-title"><?php echo e($j['job_title'] ?: '(Untitled)'); ?></h3>
                    <p class="job-card-company"><?php echo e($j['publisher_name'] ?: 'Unknown'); ?></p>
                    <?php if ($j['onet_title']): ?>
                        <span class="job-card-tag"><?php echo e($j['onet_title']); ?></span>
                    <?php endif; ?>
                    <span class="job-card-arrow">View Details &rarr;</span>
                </a>
            <?php endforeach; ?>
        </div>

        <nav class="pagination" style="margin-top: 24px;">
            <span class="pagination-meta">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
            <div class="pagination-actions">
                <?php if ($page > 1): ?>
                    <a class="btn" href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $page - 1])); ?>">&larr; Prev</a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a class="btn btn-primary" href="?<?php echo http_build_query(array_merge($baseQuery, ['page' => $page + 1])); ?>">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </nav>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>