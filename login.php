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
    <title>Login - TalentSync</title>

    <!-- Main CSS -->
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<div class="auth-overlay">

    <div class="auth-modal">

        <!-- Dynamic Title -->
        <h2 class="auth-title">
            <?php echo ($role === 'applicant') 
                ? "Job Applicant Login" 
                : "Hiring Manager Login"; ?>
        </h2>

        <!-- Login Form -->
        <form action="login.php?role=<?php echo $role; ?>" method="POST">

            <!-- Hidden role field -->
            <input type="hidden" name="role" value="<?php echo $role; ?>">

            <div class="auth-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Enter your email" required>
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
            <?php else : ?>
                <p>Are you a hiring manager?
                    <a href="login.php?role=hiring">Login here</a>
                </p>
            <?php endif; ?>
        </div>

    </div>

</div>

</body>
</html>
