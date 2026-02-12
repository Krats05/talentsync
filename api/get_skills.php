<?php
// api/get_skills.php
require_once '../config/db.php';

header('Content-Type: application/json');

// Get the O*NET SOC code from the URL
$soc_code = isset($_GET['soc_code']) ? $_GET['soc_code'] : '';

if (empty($soc_code)) {
    echo json_encode(["error" => "No SOC code provided"]);
    exit;
}

// Query the O*NET skills table. 
// (Assuming standard O*NET schema where 'element_name' holds the skill name)
$sql = "SELECT element_name FROM skills WHERE onetsoc_code = ? LIMIT 20";
$stmt = $conn->prepare($sql);
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