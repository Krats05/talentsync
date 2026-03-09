<?php
$role = $_GET['role'] ?? 'HR_Manager';

$allowed_roles = ['HR_Manager', 'job_applicant'];

if (!in_array($role, $allowed_roles, true)) {
    $role = 'HR_Manager';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - TalentSync</title>
    <!-- Main CSS -->
    <link rel="stylesheet" href="assets/auth.css">
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<div class="auth-overlay">

    <div class="auth-brand">
        <div class="logo">T</div>
        <span class="brand-text">TalentSync</span>
    </div>

    <div class="auth-modal">

        <!-- Dynamic Title -->
        <h2 class="auth-title">
            <?php echo ($role === 'job_applicant') 
                ? "Job Applicant Login" 
                : "Hiring Manager Login"; ?>
        </h2>

        <!-- Login Form -->
        <form action="api/auth_login.php" method="POST">

            <!-- Hidden role field -->
            <input type="hidden" name="role" value="<?php echo $role; ?>">

            <div class="auth-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your company email" required>
            </div>

            <div class="auth-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" class="auth-button">
                Login
            </button>

        </form>

        <!-- Switch Role -->
        <div class="auth-switch">
            <?php if ($role === 'hiring') : ?>
                <p>Are you a job applicant?
                    <a href="login.php?role=applicant">Login here</a>
                </p>
            <?php else: ?>
                <p>Are you a hiring manager?
                    <a href="login.php?role=hiring">Login here</a>
                </p>
            <?php endif; ?>
        </div>

    </div>

</div>

</body>
</html>
