<?php
include 'includes/navbar.php';

$job_id = $_GET['id'];
?>

<h2>Apply for Job</h2>

<form action="submit_application.php" method="POST" enctype="multipart/form-data">

<input type="hidden" name="job_id" value="<?php echo $job_id; ?>">

<label>Name</label>
<input type="text" name="name" required>

<label>Email</label>
<input type="email" name="email" required>

<label>Upload Resume</label>
<input type="file" name="resume" required>

<button type="submit">Submit Application</button>

</form>