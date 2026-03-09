<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method not allowed');
}

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
  http_response_code(400);
  exit('Missing email/password');
}

$stmt = $conn->prepare("SELECT user_id, full_name, email, password_hash, role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;

if (!$user || !password_verify($password, $user["password_hash"])) {
  http_response_code(401);
  exit('Invalid credentials');
}

$_SESSION["user_id"] = (int)$user["user_id"];
$_SESSION["full_name"] = $user["full_name"];
$_SESSION["email"] = $user["email"];
$_SESSION["role"] = $user["role"];

if ($user["role"] === 'job_applicant') {
  header("Location: ../job_applicant_dashboard.php");
  exit;
} elseif ($user["role"] === 'HR_Manager') {
  header("Location: ../Dashboard_HR.php");
  exit;
} else {
  header("Location: ../index.php");
  exit;
}
