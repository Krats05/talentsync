<?php
// Get role from URL (default = hiring)
$role = $_GET['role'] ?? 'hiring';

// Normalize role
if ($role !== 'applicant') {
    $role = 'hiring';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - TalentSync</title>

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
            <?php echo ($role === 'applicant')
                ? "Job Applicant Sign Up"
                : "Hiring Manager Sign Up"; ?>
        </h2>

        <!-- Signup Form -->
       <form action="api/auth_signup.php" method="POST">

            <!-- Hidden role field -->
            <input type="hidden" name="role" value="<?php echo $role; ?>">

         <div class="auth-group">
    <label>Full Name</label>
    <input type="text" name="full_name" placeholder="John Doe" required>
</div>

            <div class="auth-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required>
            </div>

            <div class="auth-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Create a password" required>
            </div>

            <div class="auth-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Confirm your password" required>
            </div>

            <button type="submit" class="auth-button">
                Sign Up
            </button>

        </form>

        <!-- Switch Role -->
        <div class="auth-switch">
            <?php if ($role === 'hiring') : ?>
                <p>Are you a job applicant?
                    <a href="signup.php?role=applicant">Sign up here</a>
                </p>
            <?php else : ?>
                <p>Are you a hiring manager?
                    <a href="signup.php?role=hiring">Sign up here</a>
                </p>
            <?php endif; ?>
        </div>

        <!-- Switch to Login -->
        <div class="auth-switch">
            <p>Already have an account?
                <a href="login.php?role=<?php echo $role; ?>">Login</a>
            </p>
        </div>

    </div>

</div>

</body>
</html>
