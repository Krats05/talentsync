<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
csrf_validate();

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400); 
    exit('Missing fields');
}

$stmt = $conn->prepare("SELECT user_id, full_name, password_hash, role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401); 
    exit('Invalid email or password');
}

$user = $result->fetch_assoc();

if (!password_verify($password, $user['password_hash'])) {
    http_response_code(401); 
    exit('Invalid email or password');
}

$_SESSION["user_id"] = $user['user_id'];
$_SESSION["full_name"] = $user['full_name'];
$_SESSION["email"] = $email;
$_SESSION["role"] = $user['role'];

// Redirect to original page if provided, otherwise go to dashboard
$redirect = trim($_POST['redirect'] ?? '');
if ($redirect !== '') {
    // Only allow relative redirects (prevent open redirect attacks)
    $redirect = ltrim($redirect, '/');
    if (!preg_match('/^https?:\/\//i', $redirect)) {
        header("Location: ../" . $redirect);
        exit;
    }
}

if ($user['role'] === 'Applicant') {
    header("Location: ../dashboard_applicant.php");
} else {
    header("Location: ../dashboard_hr.php");
}
exit;