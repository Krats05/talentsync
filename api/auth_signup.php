<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Method not allowed"]);
    exit;
}

$full_name = trim($_POST['full_name'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($full_name === '' || $email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Missing fields"]);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Invalid email"]);
    exit;
}

$check = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$check->bind_param("s", $email);
$check->execute();
$result = $check->get_result();

if ($result && $result->num_rows > 0) {
    http_response_code(409);
    echo json_encode(["ok" => false, "error" => "Email already exists"]);
    exit;
}

$password_hash = password_hash($password, PASSWORD_DEFAULT);
$role = "HR_Manager";

$stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $full_name, $email, $password_hash, $role);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Signup failed"]);
    exit;
}

$_SESSION["user_id"] = $conn->insert_id;
$_SESSION["full_name"] = $full_name;
$_SESSION["email"] = $email;
$_SESSION["role"] = $role;

header("Location: ../dashboard.php");
exit;