<?php
session_start();
require_once __DIR__ . "/config/db.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

$job_id = max(0, (int)($_GET['id'] ?? 0));

if ($job_id <= 0) {
    die("Invalid job ID.");
}

$user_name = $_SESSION['full_name'] ?? '';
$user_email = $_SESSION['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Job - TalentSync</title>
    <link rel="stylesheet" href="/talentsync/assets/style.css">
    <link rel="stylesheet" href="/talentsync/assets/auth.css">
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main style="max-width: 700px; margin: 40px auto; padding: 0 20px;">
    <a href="job_detail.php?id=<?php echo (int)$job_id; ?>">← Back to Job</a>

    <h2 style="margin-top: 20px;">Apply for Job</h2>

    <form action="api/submit_application.php" method="POST" style="margin-top: 20px;">
        <input type="hidden" name="job_id" value="<?php echo (int)$job_id; ?>">

        <div style="margin-bottom: 16px;">
            <label for="name">Name</label><br>
            <input
                id="name"
                type="text"
                name="name"
                value="<?php echo e($user_name); ?>"
                required
                style="width: 100%; padding: 10px;"
            >
        </div>

        <div style="margin-bottom: 16px;">
            <label for="email">Email</label><br>
            <input
                id="email"
                type="email"
                name="email"
                value="<?php echo e($user_email); ?>"
                required
                style="width: 100%; padding: 10px;"
            >
        </div>

        <button type="submit" class="Mbtn Mbtn-black">Submit Application</button>
    </form>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>