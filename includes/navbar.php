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
    <!-- If users are Login -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <div class="nav-right">
            <a href="api/logout.php" class="Mbtn Mbtn-black">Log Out</a>
        </div>
    <!-- If users are not Login -->
    <?php else: ?>
        <div class="nav-right">
            <a href="login.php?role=hiring" class="Mbtn Mbtn-white">Login</a>
            <a href="signup.php?role=hiring" class="Mbtn Mbtn-black">Sign Up</a>
        </div>
    <?php endif; ?>
</header>