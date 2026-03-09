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
        <h2 class="auth-title">Login</h2>

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
            <p>Don't have an account? <a href="signup.php">Sign Up</a></p>
        </div>
    </div>
</div>
</body>
</html>
