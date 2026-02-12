<?php
// api/search_onet.php
require_once '../config/db.php';

// Tell the browser we are sending back JSON data
header('Content-Type: application/json');

// Get the search query from the URL (e.g., search_onet.php?q=software)
$search_query = isset($_GET['q']) ? $_GET['q'] : '';

if (empty($search_query)) {
    echo json_encode([]);
    exit;
}

// Search the O*NET occupation_data table using a wildcard (%)
$sql = "SELECT onetsoc_code, title FROM occupation_data WHERE title LIKE ? LIMIT 10";
$stmt = $conn->prepare($sql);

// Add wildcards to the search term
$search_term = "%" . $search_query . "%";
$stmt->bind_param("s", $search_term);

$stmt->execute();
$result = $stmt->get_result();

$jobs = [];
while ($row = $result->fetch_assoc()) {
    $jobs[] = [
        'code' => $row['onetsoc_code'],
        'title' => $row['title']
    ];
}

// Output the results as JSON for the frontend
echo json_encode($jobs);

$stmt->close();
$conn->close();
?>