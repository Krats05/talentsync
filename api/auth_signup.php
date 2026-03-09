<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

$full_name = trim($_POST['full_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? '');

if ($full_name === '' || $email === '' || $password === '' || $role === '') {
    http_response_code(400); exit('Missing fields');
}

// Check against your exact Database rules (Capital A)
$allowed_roles = ['HR_Manager', 'Applicant'];
if (!in_array($role, $allowed_roles, true)) {
    http_response_code(400); exit('Invalid role');
}

$check = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    http_response_code(409); exit('Email already exists');
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $full_name, $email, $password_hash, $role);

if (!$stmt->execute()) {
    http_response_code(500); exit('Signup failed');
}

$_SESSION["user_id"] = $conn->insert_id;
$_SESSION["full_name"] = $full_name;
$_SESSION["email"] = $email;
$_SESSION["role"] = $role;

// Redirect perfectly based on Capital A
if ($role === 'Applicant') {
    header("Location: ../dashboard_applicant.php");
} else {
    header("Location: ../dashboard_hr.php");
}
exit;