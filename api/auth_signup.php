<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/rate_limit.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
csrf_validate();
rate_limit_check('signup', 5, 900); // 5 attempts per 15 minutes

// ─── Pull form fields ─────────────────────────────────────────────────────
$full_name        = trim($_POST['full_name'] ?? '');
$email            = strtolower(trim($_POST['email'] ?? ''));
$password         = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$role             = trim($_POST['role'] ?? '');

// HR-only fields
$company_name    = trim($_POST['company_name'] ?? '');
$company_website = trim($_POST['company_website'] ?? '');
$job_title       = trim($_POST['job_title'] ?? '');

// Helper for redirect with error preserving form values
function redirectError($code, $role, $vals = []) {
    $params = ['role' => $role, 'error' => $code] + $vals;
    header("Location: /talentsync/signup.php?" . http_build_query($params));
    exit;
}

// ─── Validation ───────────────────────────────────────────────────────────
$allowed_roles = ['HR_Manager', 'Applicant'];
if (!in_array($role, $allowed_roles, true)) {
    http_response_code(400); exit('Invalid role');
}

$preserve = ['full_name' => $full_name, 'email' => $email];
if ($role === 'HR_Manager') {
    $preserve['company_name']    = $company_name;
    $preserve['company_website'] = $company_website;
    $preserve['job_title']       = $job_title;
}

if ($full_name === '' || $email === '' || $password === '') {
    redirectError('missing_fields', $role, $preserve);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectError('invalid_email', $role, $preserve);
}

if (strlen($password) < 8) {
    redirectError('password_short', $role, $preserve);
}

if ($confirm_password !== '' && $password !== $confirm_password) {
    redirectError('password_mismatch', $role, $preserve);
}

// ─── HR-specific company verification (replaces old domain blocklist) ─────
if ($role === 'HR_Manager') {
    if ($company_name === '' || $company_website === '') {
        redirectError('missing_company', $role, $preserve);
    }
    if (mb_strlen($company_name) < 2) {
        redirectError('company_too_short', $role, $preserve);
    }

    // Normalise website URL — accept naked domains by prepending https://
    if (!preg_match('#^https?://#i', $company_website)) {
        $company_website = 'https://' . $company_website;
    }
    if (!filter_var($company_website, FILTER_VALIDATE_URL)) {
        redirectError('invalid_website', $role, $preserve);
    }
    // Cap lengths to fit DB columns
    $company_name    = mb_substr($company_name, 0, 150);
    $company_website = mb_substr($company_website, 0, 255);
    $job_title       = mb_substr($job_title, 0, 100);
}

// ─── Email uniqueness check ───────────────────────────────────────────────
$check = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$check->bind_param("s", $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    $check->close();
    redirectError('email_exists', $role, $preserve);
}
$check->close();

// ─── Insert ───────────────────────────────────────────────────────────────
$password_hash = password_hash($password, PASSWORD_DEFAULT);

if ($role === 'HR_Manager') {
    $stmt = $conn->prepare("
        INSERT INTO users (full_name, email, password_hash, role, company_name, company_website, job_title)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssss", $full_name, $email, $password_hash, $role, $company_name, $company_website, $job_title);
} else {
    $stmt = $conn->prepare("
        INSERT INTO users (full_name, email, password_hash, role)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("ssss", $full_name, $email, $password_hash, $role);
}

if (!$stmt->execute()) {
    http_response_code(500); exit('Signup failed');
}

// ─── Set session & redirect ───────────────────────────────────────────────
$_SESSION["user_id"]   = $conn->insert_id;
$_SESSION["full_name"] = $full_name;
$_SESSION["email"]     = $email;
$_SESSION["role"]      = $role;

if ($role === 'Applicant') {
    header("Location: ../dashboard_applicant.php");
} else {
    header("Location: ../dashboard_hr.php");
}
exit;
