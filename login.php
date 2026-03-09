<?php
$role = $_GET['role'] ?? 'HR_Manager';

$allowed_roles = ['HR_Manager', 'Applicant'];
if (!in_array($role, $allowed_roles, true)) {
    $role = 'HR_Manager';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - TalentSync: O*NET-Integrated Recruitment System</title>
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
        <h2 class="auth-title">
            <?php echo ($role === 'Applicant') ? "Applicant Login" : "HR Manager Login"; ?>
        </h2>

       <form action="api/auth_login.php" method="POST">
            <div class="auth-group">
                <label>Email</label>
                <input type="email" name="email" required>
            </div>
            <div class="auth-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit" class="auth-button">Login</button>
        </form>

        <div class="auth-switch">
            <?php if ($role === 'HR_Manager') : ?>
                <p>Are you an Applicant? <a href="login.php?role=Applicant">Login here</a></p>
            <?php else : ?>
                <p>Are you an HR Manager? <a href="login.php?role=HR_Manager">Login here</a></p>
            <?php endif; ?>
        </div>

        <div class="auth-switch">
            <p>Don't have an account? <a href="signup.php?role=<?php echo htmlspecialchars($role); ?>">Sign Up</a></p>
        </div>
    </div>
</div>
</body>
</html>