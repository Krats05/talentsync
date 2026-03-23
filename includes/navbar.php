<!-- Top Navbar -->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_currentPage = basename($_SERVER['PHP_SELF']);
?>

<header class="navbar">

    <a href="index.php" class="nav-left" style="text-decoration: none; color: inherit; cursor: pointer;">
        <div class="logo">T</div>
        <span class="brand">TalentSync</span>
    </a>

    <?php if (isset($_SESSION['user_id'])): ?>
        <?php
            $isApplicant = ($_SESSION['role'] ?? '') === 'Applicant';
            $navName = htmlspecialchars($_SESSION['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
            $navInitial = strtoupper(substr($navName, 0, 1));
            $navRole = $isApplicant ? 'Applicant' : 'HR Manager';
        ?>
        <nav class="nav-center">
            <?php if ($isApplicant): ?>
                <a href="browse_jobs.php" class="nav-link <?= $_currentPage === 'browse_jobs.php' ? 'nav-active' : '' ?>">Browse Jobs</a>
                <a href="dashboard_applicant.php" class="nav-link <?= $_currentPage === 'dashboard_applicant.php' ? 'nav-active' : '' ?>">My Applications</a>
            <?php else: ?>
                <a href="dashboard_hr.php" class="nav-link <?= $_currentPage === 'dashboard_hr.php' ? 'nav-active' : '' ?>">Dashboard</a>
                <a href="create_job.php" class="nav-link <?= $_currentPage === 'create_job.php' ? 'nav-active' : '' ?>">Create Job</a>
            <?php endif; ?>
        </nav>
        <div class="nav-right">
            <div class="nav-user">
                <div class="nav-avatar"><?= $navInitial ?></div>
                <div class="nav-user-info">
                    <span class="nav-user-name"><?= $navName ?></span>
                    <span class="nav-user-role"><?= $navRole ?></span>
                </div>
            </div>
            <a href="api/logout.php" class="Mbtn Mbtn-black">Log Out</a>
        </div>
    <?php else: ?>
        <div class="nav-right">
            <a href="login.php" class="Mbtn Mbtn-white">Login</a>
            <a href="signup.php" class="Mbtn Mbtn-black">Sign Up</a>
        </div>
    <?php endif; ?>
</header>