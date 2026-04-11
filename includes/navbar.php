<!-- Top Navbar -->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/csrf.php';
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
        <nav class="nav-center" aria-label="Main navigation">
            <?php if ($isApplicant): ?>
                <a href="index.php" class="nav-link <?= $_currentPage === 'index.php' ? 'nav-active' : '' ?>">Home</a>
                <a href="browse_jobs.php" class="nav-link <?= ($_currentPage === 'browse_jobs.php' || $_currentPage === 'job_detail.php') ? 'nav-active' : '' ?>">Browse Jobs</a>
                <a href="dashboard_applicant.php" class="nav-link <?= $_currentPage === 'dashboard_applicant.php' ? 'nav-active' : '' ?>">My Applications</a>
            <?php else: ?>
                <a href="index.php" class="nav-link <?= $_currentPage === 'index.php' ? 'nav-active' : '' ?>">Home</a>
                <a href="dashboard_hr.php" class="nav-link <?= ($_currentPage === 'dashboard_hr.php' || $_currentPage === 'job_applications.php') ? 'nav-active' : '' ?>">Dashboard</a>
                <a href="create_job.php" class="nav-link <?= $_currentPage === 'create_job.php' ? 'nav-active' : '' ?>">Create Job</a>
                <a href="notepad.php" class="nav-link <?= $_currentPage === 'notepad.php' ? 'nav-active' : '' ?>">Take Notes</a>
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
        <nav class="nav-center" aria-label="Main navigation">
            <a href="index.php" class="nav-link <?= $_currentPage === 'index.php' ? 'nav-active' : '' ?>">Home</a>
            <a href="browse_jobs.php" class="nav-link <?= $_currentPage === 'browse_jobs.php' ? 'nav-active' : '' ?>">Browse Jobs</a>
        </nav>
        <div class="nav-right">
            <a href="login.php" class="Mbtn Mbtn-white">Login</a>
            <a href="signup.php" class="Mbtn Mbtn-black">Sign Up</a>
        </div>
    <?php endif; ?>
</header>

<script>
var CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
</script>