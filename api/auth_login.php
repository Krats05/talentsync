<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(["ok"=>false,"error"=>"Method not allowed"]);
  exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
  http_response_code(400);
  echo json_encode(["ok"=>false,"error"=>"Missing email/password"]);
  exit;
}

$stmt = $conn->prepare("SELECT user_id, full_name, email, password_hash, role FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;

if (!$user || !password_verify($password, $user["password_hash"])) {
  http_response_code(401);
  echo json_encode(["ok"=>false,"error"=>"Invalid credentials"]);
  exit;
}

$_SESSION["user_id"] = (int)$user["user_id"];
$_SESSION["full_name"] = $user["full_name"];
$_SESSION["email"] = $user["email"];
$_SESSION["role"] = $user["role"];

header("Location: ../dashboard.php");
exit;


