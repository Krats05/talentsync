<?php
/**
 * Demo auto-login — logs the visitor in as the seeded demo HR account.
 * Used by the "Try Live Demo" button on the homepage.
 *
 * Demo account: test.hr@gmail.com — owns all 10 seeded demo jobs and ~37
 * applications for a rich, populated dashboard.
 */
session_start();
require_once __DIR__ . '/../config/db.php';

$DEMO_EMAIL = 'test.hr@gmail.com';

$stmt = $conn->prepare("SELECT user_id, full_name, role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $DEMO_EMAIL);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$user || $user['role'] !== 'HR_Manager') {
    header("Location: ../login.php?error=DemoUnavailable");
    exit;
}

// Set up demo session
session_regenerate_id(true);
$_SESSION['user_id']   = (int)$user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];
$_SESSION['is_demo']   = true;

header("Location: ../dashboard_hr.php?demo=1");
exit;
