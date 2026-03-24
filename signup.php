<?php
// Default to HR_Manager if no role is specified
$role = $_GET['role'] ?? 'HR_Manager';

// Enforce strict allowed roles matching your database ENUM
$allowed_roles = ['HR_Manager', 'Applicant'];

if (!in_array($role, $allowed_roles, true)) {
    $role = 'HR_Manager';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - TalentSync: O*NET-Integrated Recruitment System</title>
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
            <?php echo ($role === 'Applicant') ? "Applicant Sign Up" : "HR Manager Sign Up"; ?>
        </h2>

       <form action="api/auth_signup.php" method="POST">
            <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">

            <div class="auth-group">
                <label>Full Name</label>
                <input type="text" name="full_name" required placeholder="Enter your full name">
            </div>
            <div class="auth-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="Enter your email">
            </div>
            <div class="auth-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Create a password">
            </div>
            <div class="auth-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required placeholder="Confirm your password">
            </div>
            <button type="submit" class="auth-button">Sign Up</button>
        </form>

        <div class="auth-switch">
            <?php if ($role === 'HR_Manager') : ?>
                <p>Are you an Applicant? <a href="signup.php?role=Applicant">Sign up here</a></p>
            <?php else : ?>
                <p>Are you an HR Manager? <a href="signup.php?role=HR_Manager">Sign up here</a></p>
            <?php endif; ?>
        </div>

        <div class="auth-switch">
            <p>Already have an account? <a href="login.php?role=<?php echo htmlspecialchars($role); ?>">Login</a></p>
        </div>
    </div>
</div>
</body>
</html>