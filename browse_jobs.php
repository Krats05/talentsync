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
$where[] = "j.status = ?";
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
    <title>Browse Jobs</title>
    <link rel="stylesheet" href="/talentsync/assets/style.css">
</head>
<body>

<?php
// If your navbar/footer are in /talentsync/includes/
$navbarPath = __DIR__ . "/includes/navbar.php";
if (file_exists($navbarPath)) include $navbarPath;
?>

<main class="footer-container">
    <h1>Browse Jobs</h1>
    <p>Public job postings. No login required.</p>

    <section style="margin: 14px 0;">
        <form method="GET">
            <label for="q">Search</label>
            <input id="q" type="text" name="q" value="<?php echo e($q); ?>" placeholder="job title / publisher / O*NET title" />

            <button type="submit" class="btn btn-black" style="margin-left:10px;">Apply</button>
        </form>
    </section>

    <section style="margin: 18px 0;">
        <h2>Results (<?php echo $totalRows; ?>)</h2>

        <?php if (empty($jobs)): ?>
            <p>No job posts found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Publisher</th>
                        <th>Status</th>
                        <th>O*NET Title</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $j): ?>
                        <tr>
                            <td><?php echo e($j['job_title'] ?: '(Untitled)'); ?></td>
                            <td><?php echo e($j['publisher_name'] ?: 'Unknown'); ?></td>
                            <td><?php echo e($j['status']); ?></td>
                            <td><?php echo e($j['onet_title'] ?: '-'); ?></td>
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
$footerPath = __DIR__ . "/includes/footer.php";
if (file_exists($footerPath)) include $footerPath;
?>

</body>
</html>