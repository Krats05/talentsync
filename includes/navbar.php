<!-- Top Navbar -->
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<header class="navbar">

    <a href="index.php" class="nav-left" style="text-decoration: none; color: inherit; cursor: pointer;">
        <div class="logo">T</div>
        <span class="brand">TalentSync</span>
    </a>
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="nav-right">
            <!-- Dashboard button -->
            <?php if ($_SESSION["role"] == "Applicant"): ?>
                <a href="dashboard_applicant.php" class="Mbtn Mbtn-white">Dashboard</a>
            <?php else: ?>
                <a href="dashboard_hr.php" class="Mbtn Mbtn-white">Dashboard</a>
            <?php endif; ?>
            <!-- log out button -->
            <a href="api/logout.php" class="Mbtn Mbtn-black">Log Out</a>
        </div>
    <?php else: ?>
        <div class="nav-right">
            <a href="login.php" class="Mbtn Mbtn-white">Login</a>
            <a href="signup.php" class="Mbtn Mbtn-black">Sign Up</a>
        </div>
    <?php endif; ?>
</header>