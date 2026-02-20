<?php
// api/get_skills.php
require_once '../config/db.php';

// 1. Turn on error reporting so it doesn't fail silently
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// Get the O*NET SOC code from the URL
$soc_code = isset($_GET['soc_code']) ? $_GET['soc_code'] : '';

if (empty($soc_code)) {
    echo json_encode(["error" => "No SOC code provided"]);
    exit;
}

// Query the O*NET skills table. 
$sql = "SELECT element_name FROM skills WHERE onetsoc_code = ? LIMIT 20";
$stmt = $conn->prepare($sql);

// 2. CRITICAL FIX: Check if the SQL query actually worked before proceeding
if (!$stmt) {
    // If it fails, send the exact database error back to the browser
    echo json_encode(["error" => "SQL Error: " . $conn->error]);
    exit;
}

$stmt->bind_param("s", $soc_code);

$stmt->execute();
$result = $stmt->get_result();

$skills = [];
while ($row = $result->fetch_assoc()) {
    $skills[] = $row['element_name'];
}

echo json_encode($skills);

$stmt->close();
$conn->close();
?>