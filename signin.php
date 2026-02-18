<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>TalentSync Homepage</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<div class="auth-container">

    <h2>Create Account</h2>

    <form action="signup.php" method="POST">

        <div class="form-group">
            <label>First Name</label>
            <input type="text" name="first_name" placeholder="John" required>
        </div>

        <div class="form-group">
            <label>Last Name</label>
            <input type="text" name="last_name" placeholder="Doe" required>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" placeholder="manager@company.com" required>
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Create a password" required>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="Confirm your password" required>
        </div>

        <button type="submit" class="btn btn-black">Sign Up</button>

    </form>

    <p>
        Already have an account? log in here 
        <a href="login.php">Login</a>
    </p>

</div>

</body>
</html>
