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
    http_response_code(400);
    exit('Missing fields');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit('Invalid email');
}

// allowed roles
$allowed_roles = ['HR_Manager', 'job_applicant'];

if (!in_array($role, $allowed_roles, true)) {
    http_response_code(400);
    exit('Invalid role');
}

// check duplicate email
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result && $result->num_rows > 0) {
    http_response_code(409);
    exit('Email already exists');
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    INSERT INTO users (full_name, email, password_hash, role)
    VALUES (?, ?, ?, ?)
");
$stmt->bind_param("ssss", $full_name, $email, $password_hash, $role);

if (!$stmt->execute()) {
    http_response_code(500);
    exit('Signup failed');
}

$_SESSION["user_id"] = $conn->insert_id;
$_SESSION["full_name"] = $full_name;
$_SESSION["email"] = $email;
$_SESSION["role"] = $role;

// redirect based on role
if ($role === 'job_applicant') {
    header("Location: ../job_applicant_dashboard.php");
    exit;
} else {
    header("Location: ../dashboard.php");
    exit;
}