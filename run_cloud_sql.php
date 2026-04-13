<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

$columns = ['user_notes', 'ai_summary', 'duration_seconds'];

echo "<pre>";

foreach ($columns as $col) {
    $sql = "SHOW COLUMNS FROM meeting_notes LIKE '$col'";
    $res = $conn->query($sql);

    if (!$res) {
        echo "$col => ERROR: " . $conn->error . "\n";
        continue;
    }

    if ($res->num_rows > 0) {
        echo "$col => EXISTS\n";
    } else {
        echo "$col => MISSING\n";
    }
}

echo "</pre>";

$conn->close();
?>