<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$fullName = $_SESSION['full_name'] ?? 'Candidate';
$role     = $_SESSION['role'] ?? '';

if ($role !== 'Applicant' && $role !== 'applicant') {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Job Recommendation Questionnaire</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php include 'includes/navbar.php'; ?>

<main class="container" style="max-width: 800px; margin: 40px auto;">
    <section class="card">
        <h1 class="page-title">AI Job Recommendation Questionnaire</h1>
        <p class="page-subtitle">
            Tell us about your preferences and we will recommend the best open jobs for you.
        </p>

        <form id="recommendationForm">
            <div style="margin-bottom: 20px;">
                <label for="role_type"><strong>What type of role are you looking for?</strong></label><br>
                <select id="role_type" name="role_type" class="filter-control" style="width: 100%; margin-top: 8px;" required>
                    <option value="">Select role type</option>
                    <option value="Software">Software</option>
                    <option value="Data">Data</option>
                    <option value="Marketing">Marketing</option>
                    <option value="Design">Design</option>
                    <option value="Business Analyst">Business Analyst</option>
                    <option value="Product">Product</option>
                    <option value="Operations">Operations</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="experience"><strong>What is your experience level?</strong></label><br>
                <select id="experience" name="experience" class="filter-control" style="width: 100%; margin-top: 8px;" required>
                    <option value="">Select experience level</option>
                    <option value="Entry">Entry</option>
                    <option value="Mid">Mid</option>
                    <option value="Senior">Senior</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="skills"><strong>List your top skills</strong></label><br>
                <textarea id="skills" name="skills" rows="4" class="filter-control" style="width: 100%; margin-top: 8px;" placeholder="e.g. Python, SQL, Excel, Tableau" required></textarea>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="location"><strong>Preferred work location?</strong></label><br>
                <select id="location" name="location" class="filter-control" style="width: 100%; margin-top: 8px;" required>
                    <option value="">Select location preference</option>
                    <option value="Remote">Remote</option>
                    <option value="On-site">On-site</option>
                    <option value="Hybrid">Hybrid</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="salary"><strong>Expected salary range?</strong></label><br>
                <select id="salary" name="salary" class="filter-control" style="width: 100%; margin-top: 8px;" required>
                    <option value="">Select salary range</option>
                    <option value="$40,000 - $60,000">$40,000 - $60,000</option>
                    <option value="$60,001 - $80,000">$60,001 - $80,000</option>
                    <option value="$80,001 - $100,000">$80,001 - $100,000</option>
                    <option value="$100,001+">$100,001+</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label for="industries"><strong>Any industries you prefer? (optional)</strong></label><br>
                <textarea id="industries" name="industries" rows="3" class="filter-control" style="width: 100%; margin-top: 8px;" placeholder="e.g. healthcare, retail, education, finance"></textarea>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary">Generate Recommendations</button>
                <a href="dashboard_applicant.php" class="btn">Back to Dashboard</a>
            </div>
        </form>

        <div id="statusMessage" style="margin-top: 20px;"></div>
    </section>
</main>

<script>
document.getElementById('recommendationForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const statusDiv = document.getElementById('statusMessage');
    statusDiv.innerHTML = '<p>Generating recommendations...</p>';

    const payload = {
        role_type: document.getElementById('role_type').value,
        experience: document.getElementById('experience').value,
        skills: document.getElementById('skills').value,
        location: document.getElementById('location').value,
        salary: document.getElementById('salary').value,
        industries: document.getElementById('industries').value
    };

    try {
        const response = await fetch('api/job_recommendations.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || ''
            },
            body: JSON.stringify(payload)
        });

        const raw = await response.text();
        console.log('RAW API RESPONSE:', raw);

        let data;
        try {
            data = JSON.parse(raw);
        } catch (parseError) {
            statusDiv.innerHTML = '<p style="color:red;">API did not return valid JSON. Check console.</p>';
            console.log(raw);
            return;
        }

        if (!response.ok) {
            throw new Error('Server error');
        }

        if (data.success) {
            window.location.href = 'dashboard_applicant.php?ai_recs=1';
        } else {
            statusDiv.innerHTML = '<p style="color:red;">' + (data.message || 'Failed to generate recommendations.') + '</p>';
            console.log('API ERROR DATA:', data);
        }
    } catch (error) {
        console.error('FETCH ERROR:', error);
        statusDiv.innerHTML = '<p style="color:red;">Something went wrong. Please try again.</p>';
    }
});
</script>
  
<?php include 'includes/footer.php'; ?>

</body>
</html>