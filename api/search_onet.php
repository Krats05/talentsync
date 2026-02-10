<?php
header('Content-Type: application/json');
require_once '../config/db.php';

$term = isset($_GET['term']) ? $_GET['term'] : '';

if ($term) {
    // FIX: Changed 'onet_soc_code' to 'onetsoc_code' (No underscore)
    $sql = "SELECT onetsoc_code, title 
            FROM occupation_data 
            WHERE title LIKE ? 
            LIMIT 10";
            
    $stmt = $conn->prepare($sql);
    
    // Add wildcards for partial matching
    $searchTerm = "%" . $term . "%";
    $stmt->bind_param("s", $searchTerm);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    
    echo json_encode($jobs);
} else {
    echo json_encode([]);
}
?>