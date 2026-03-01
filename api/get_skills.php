<?php
// api/get_skills.php
require_once '../config/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

$soc_code = isset($_GET['soc_code']) ? $_GET['soc_code'] : '';

if (empty($soc_code)) {
    echo json_encode(["error" => "No SOC code provided"]);
    exit;
}

// Prepare our final two-part response array
$response = [
    "tech_skills" => [],
    "general_skills" => []
];

// 1. QUERY FOR TECHNOLOGY SKILLS (Strict "Hot & In Demand" filter)
$sql_tech = "SELECT example AS element_name 
             FROM technology_skills 
             WHERE onetsoc_code = ? AND hot_technology = 'Y' AND in_demand = 'Y'
             GROUP BY example LIMIT 15";
$stmt_tech = $conn->prepare($sql_tech);

if ($stmt_tech) {
    $stmt_tech->bind_param("s", $soc_code);
    $stmt_tech->execute();
    $res_tech = $stmt_tech->get_result();
    while ($row = $res_tech->fetch_assoc()) {
        $response['tech_skills'][] = $row['element_name'];
    }
    $stmt_tech->close();
}

// 2. QUERY FOR GENERAL SKILLS
$sql_gen = "SELECT c.element_name 
            FROM skills s
            JOIN content_model_reference c ON s.element_id = c.element_id
            WHERE s.onetsoc_code = ? AND s.scale_id = 'IM'
            ORDER BY s.data_value DESC LIMIT 15";
$stmt_gen = $conn->prepare($sql_gen);

if ($stmt_gen) {
    $stmt_gen->bind_param("s", $soc_code);
    $stmt_gen->execute();
    $res_gen = $stmt_gen->get_result();
    while ($row = $res_gen->fetch_assoc()) {
        $response['general_skills'][] = $row['element_name'];
    }
    $stmt_gen->close();
}

// Send both arrays back to the frontend
echo json_encode($response);
$conn->close();
?>