<?php
session_start();
require_once __DIR__ . '/includes/csrf.php';
$error = $_GET['error'] ?? '';

// Default to HR_Manager if no role is specified
$role = $_GET['role'] ?? 'HR_Manager';

$allowed_roles = ['HR_Manager', 'Applicant'];
if (!in_array($role, $allowed_roles, true)) {
    $role = 'HR_Manager';
}
$isHR = ($role === 'HR_Manager');
$old = $_GET; // for repopulating fields after error
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up — TalentSync</title>
    <link rel="stylesheet" href="assets/auth.css">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .auth-help { font-size: 12px; color: #64748b; margin-top: 6px; line-height: 1.4; }
        .auth-divider { border-top: 1px solid #e2e8f0; margin: 24px 0 18px; position: relative; }
        .auth-divider span { position: absolute; top: -8px; left: 50%; transform: translateX(-50%); background: #fff; padding: 0 12px; font-size: 11px; font-weight: 700; color: #94a3b8; letter-spacing: 1.5px; text-transform: uppercase; white-space: nowrap; }
        .auth-error-box { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; line-height: 1.5; }
        .auth-info-box { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 12px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; line-height: 1.5; display: flex; gap: 10px; align-items: flex-start; }
        .auth-info-box::before { content: "✓"; font-weight: 800; color: #10b981; font-size: 16px; line-height: 1.3; flex-shrink: 0; }
    </style>
</head>
<body>

<div class="auth-overlay">
    <div class="auth-brand">
        <div class="logo">T</div>
        <span class="brand-text">TalentSync</span>
    </div>
    <div class="auth-modal">
        <h2 class="auth-title">
            <?php echo $isHR ? "HR Manager Sign Up" : "Applicant Sign Up"; ?>
        </h2>

        <?php
        $errorMessages = [
            'missing_company'  => 'Please fill in <strong>Company Name</strong> and <strong>Company Website</strong> — these verify your HR account represents a real company.',
            'invalid_website'  => 'That doesn\'t look like a valid website URL. Use the format <strong>https://example.com</strong> (or your LinkedIn company page URL).',
            'company_too_short'=> 'Company name must be at least 2 characters.',
            'password_mismatch'=> 'Passwords do not match. Please re-enter.',
            'password_short'   => 'Password must be at least 8 characters.',
            'email_exists'     => 'An account with that email already exists. <a href="login.php" style="color:#991b1b; text-decoration:underline; font-weight:600;">Log in instead?</a>',
            'invalid_email'    => 'Please enter a valid email address.',
            'missing_fields'   => 'Please fill in all required fields.',
        ];
        if (isset($errorMessages[$error])): ?>
            <div class="auth-error-box"><?= $errorMessages[$error] ?></div>
        <?php endif; ?>

        <?php if ($isHR): ?>
            <div class="auth-info-box">
                <div><strong>Free email is welcome!</strong> Startups without a company domain can sign up with Gmail, Yahoo, etc. — we just verify legitimacy through company name + website.</div>
            </div>
        <?php endif; ?>

        <form action="api/auth_signup.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="role" value="<?php echo htmlspecialchars($role); ?>">

            <div class="auth-group">
                <label for="signup-name">Full Name *</label>
                <input id="signup-name" type="text" name="full_name" required value="<?php echo htmlspecialchars($old['full_name'] ?? ''); ?>" placeholder="Enter your full name">
            </div>

            <div class="auth-group">
                <label for="signup-email">Email *</label>
                <input id="signup-email" type="email" name="email" required value="<?php echo htmlspecialchars($old['email'] ?? ''); ?>" placeholder="<?php echo $isHR ? 'name@yourcompany.com or name@gmail.com' : 'name@example.com'; ?>">
            </div>

            <div class="auth-group">
                <label for="signup-password">Password *</label>
                <input id="signup-password" type="password" name="password" required minlength="8" placeholder="At least 8 characters">
            </div>

            <div class="auth-group">
                <label for="signup-confirm">Confirm Password *</label>
                <input id="signup-confirm" type="password" name="confirm_password" required minlength="8" placeholder="Re-enter your password">
            </div>

            <?php if ($isHR): ?>
                <div class="auth-divider"><span>Company verification</span></div>

                <div class="auth-group">
                    <label for="signup-company">Company Name *</label>
                    <input id="signup-company" type="text" name="company_name" required minlength="2" value="<?php echo htmlspecialchars($old['company_name'] ?? ''); ?>" placeholder="e.g. Acme Software, Inc.">
                </div>

                <div class="auth-group">
                    <label for="signup-website">Company Website *</label>
                    <input id="signup-website" type="url" name="company_website" required value="<?php echo htmlspecialchars($old['company_website'] ?? ''); ?>" placeholder="https://acme.com or LinkedIn page URL">
                    <p class="auth-help">A company website, LinkedIn company page, or Crunchbase profile all work. Helps us confirm you represent a real company.</p>
                </div>

                <div class="auth-group">
                    <label for="signup-title">Your Job Title</label>
                    <input id="signup-title" type="text" name="job_title" value="<?php echo htmlspecialchars($old['job_title'] ?? ''); ?>" placeholder="e.g. Recruiting Lead, Founder, HR Manager">
                </div>
            <?php endif; ?>

            <button type="submit" class="auth-button">Create Account</button>
        </form>

        <div class="auth-switch">
            <?php if ($isHR): ?>
                <p>Are you an Applicant? <a href="signup.php?role=Applicant">Sign up here</a></p>
            <?php else: ?>
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
