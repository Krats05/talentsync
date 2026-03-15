<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TalentSync Homepage</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/homepage.css?v=2">
</head>
<body>

    <!-- Top Navbar -->
    <?php require_once 'includes/navbar.php'; ?>


    <!-- Product Description -->
    <section class="main-description">
        <div class="MainDes-left">
            <h1>
            Practical Recruitment System<br />
            For IT Companies
            </h1>

            <p>
            Centralize candidate data and standardize hiring process. Reduce time-to-hire, 
            eliminate administrative delays, and support HR managers and hiring teams in 
            making accurate hiring decisions.
            </p>

            <div style="margin-top: 18px; display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                <a href="browse_jobs.php" class="browse-btn">Browse Jobs</a>
            </div>
        </div>

        <div class="MainDes-right">
            <img src="assets/img/homepage_img.png" alt="homepage_img">
        </div>
    </section>
    

    <!-- Footer -->
    <?php require_once 'includes/footer.php'; ?>
</body>
</html>