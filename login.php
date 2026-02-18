<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>TalentSync Homepage</title>
</head>

<body>

<div class="auth-container">

    <h2>Login</h2>

    <form action="login.php" method="POST">

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="manager@company.com" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter your password" required>
        </div>

        <button type="submit" class="btn btn-black">Login</button>

    </form>

    <p>
        Don't have an account?
        <a href="signup.php">Sign Up</a>
    </p>

</div>

</body>
</html>
