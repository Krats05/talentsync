<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        <h2 class="auth-title">Login</h2>

        <form action="api/auth_login.php" method="POST">
            <?php
                $redirect = $_GET['redirect'] ?? '';
                if ($redirect !== ''):
            ?>
                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <div class="auth-group">
                <label>Email</label>
                <input type="email" name="email" required placeholder="Enter your email">
            </div>
            <div class="auth-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Enter your password">
            </div>
            <button type="submit" class="auth-button">Login</button>
        </form>

        <div class="auth-switch">
            <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
        </div>
    </div>
</div>
</body>
</html>
