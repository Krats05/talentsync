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

// UPDATE: We must JOIN the skills table with the content_model_reference table
// to get the actual text names of the skills. We also filter by scale_id = 'IM' (Importance)
// so we get the most relevant skills first.
$sql = "
    SELECT c.element_name 
    FROM skills s
    JOIN content_model_reference c ON s.element_id = c.element_id
    WHERE s.onetsoc_code = ? AND s.scale_id = 'IM'
    ORDER BY s.data_value DESC
    LIMIT 20
";

$stmt = $conn->prepare($sql);

// Add error handling just in case the query fails
if (!$stmt) {
    echo json_encode(["error" => "Database Query Failed: " . $conn->error]);
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