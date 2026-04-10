<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$application_id = (int)($_POST['application_id'] ?? 0);
$job_id = (int)($_POST['job_id'] ?? 0);

if ($application_id <= 0) {
    header("Location: ../job_applications.php?job_id=$job_id");
    exit;
}

// summary sil → tekrar yazabil
$stmt = $conn->prepare("UPDATE applications SET feedback_summary = NULL WHERE application_id = ?");
$stmt->bind_param("i", $application_id);
$stmt->execute();

header("Location: ../job_applications.php?job_id=$job_id");
exit;