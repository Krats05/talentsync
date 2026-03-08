<?php
include 'includes/db.php';
include 'includes/navbar.php';

$job_id = $_GET['id'];

$sql = "SELECT * FROM jobs WHERE id = $job_id";
$result = mysqli_query($conn, $sql);
$job = mysqli_fetch_assoc($result);
?>

<h2><?php echo $job['title']; ?></h2>
<p><?php echo $job['description']; ?></p>
<p>Location: <?php echo $job['location']; ?></p>

<a href="apply_job.php?id=<?php echo (int)$job['job_id']; ?>" class="btn btn-black">
    Apply Now
</a>