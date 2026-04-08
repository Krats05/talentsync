<?php
session_start();
require_once __DIR__ . "/config/db.php";
require_once __DIR__ . "/includes/csrf.php";

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

$user_name  = trim($_SESSION['full_name'] ?? '');
$user_email = trim($_SESSION['email'] ?? '');
$user_phone = trim($_SESSION['phone'] ?? '');


$name_parts = preg_split('/\s+/', $user_name, 2);
$first_name = $name_parts[0] ?? '';
$last_name  = $name_parts[1] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Job - TalentSync</title>
    <link rel="stylesheet" href="/talentsync/assets/style.css">
    <link rel="stylesheet" href="/talentsync/assets/auth.css">

    <style>
        .apply-page {
            max-width: 900px;
            margin: 40px auto 60px;
            padding: 0 20px;
        }

        .apply-card {
            background: #fff;
            border: 1px solid #dcdcdc;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
        }

        .apply-back {
            display: inline-block;
            margin-bottom: 20px;
            color: #5b2ca0;
            font-size: 16px;
            text-decoration: none;
        }

        .apply-back:hover {
            text-decoration: underline;
        }

        .apply-title {
            font-size: 42px;
            font-weight: 700;
            color: #0a7c66;
            margin: 0 0 8px;
        }

        .apply-note {
            font-size: 18px;
            color: #333;
            margin-bottom: 28px;
        }

        .required-star {
            color: #d93025;
        }

        .apply-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #222;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 16px 18px;
            font-size: 16px;
            border: 1.5px solid #cfcfcf;
            border-radius: 16px;
            background: #fff;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #0a7c66;
            box-shadow: 0 0 0 3px rgba(10, 124, 102, 0.12);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 140px;
        }

        .apply-submit-btn {
            align-self: flex-start;
            background: #081a3a;
            color: #fff;
            border: none;
            border-radius: 16px;
            padding: 16px 28px;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.15s ease, opacity 0.15s ease;
        }

        .apply-submit-btn:hover {
            opacity: 0.95;
            transform: translateY(-1px);
        }

        @media (max-width: 768px) {
            .apply-title {
                font-size: 32px;
            }

            .apply-card {
                padding: 24px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .apply-submit-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<?php include __DIR__ . '/includes/navbar.php'; ?>

<main class="apply-page">
    <div class="apply-card">
        <a class="apply-back" href="job_detail.php?id=<?php echo (int)$job_id; ?>">← Back to Job</a>

        <h1 class="apply-title">Apply for this job</h1>

        <?php if (isset($_GET['error'])): ?>
            <div style="background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; padding: 14px 20px; border-radius: 12px; margin-bottom: 20px; font-size: 14px;">
                <?php
                    $err = $_GET['error'];
                    if ($err === 'MissingFields') echo 'Please fill in all required fields.';
                    elseif ($err === 'AlreadyApplied') echo 'You have already applied for this job.';
                    elseif ($err === 'SubmitFailed') echo 'Something went wrong. Please try again.';
                    else echo 'An error occurred.';
                ?>
            </div>
        <?php endif; ?>

        <p class="apply-note"><span class="required-star">*</span> indicates a required field</p>

        <form action="api/submit_application.php" method="POST" class="apply-form">
            <?= csrf_field() ?>
            <input type="hidden" name="job_id" value="<?php echo (int)$job_id; ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name <span class="required-star">*</span></label>
                    <input
                        id="first_name"
                        type="text"
                        name="first_name"
                        value="<?php echo e($first_name); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name <span class="required-star">*</span></label>
                    <input
                        id="last_name"
                        type="text"
                        name="last_name"
                        value="<?php echo e($last_name); ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email <span class="required-star">*</span></label>
                <input
                    id="email"
                    type="email"
                    name="email"
                    value="<?php echo e($user_email); ?>"
                    required
                >
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input
                        id="phone"
                        type="text"
                        name="phone"
                        value="<?php echo e($user_phone); ?>"
                        placeholder="Enter your phone number"
                    >
                </div>

                <div class="form-group">
                    <label for="city">Location (City) <span class="required-star">*</span></label>
                    <input
                        id="city"
                        type="text"
                        name="city"
                        placeholder="Enter your city"
                        required
                    >
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="linkedin_url">LinkedIn URL</label>
                    <input
                        id="linkedin_url"
                        type="url"
                        name="linkedin_url"
                        placeholder="https://linkedin.com/in/yourname"
                    >
                </div>

                <div class="form-group">
                    <label for="portfolio_url">Portfolio / GitHub</label>
                    <input
                        id="portfolio_url"
                        type="url"
                        name="portfolio_url"
                        placeholder="https://github.com/yourname"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="skills">Skills</label>
                <textarea
                    id="skills"
                    name="skills"
                    rows="4"
                    placeholder="Enter skills separated by commas, e.g. SQL, Python, Tableau, Project Management"
                ></textarea>
            </div>

            <div class="form-group">
                <label for="cover_letter">Why are you a good fit for this role?</label>
                <textarea
                    id="cover_letter"
                    name="cover_letter"
                    placeholder="Tell us about your background, experience, and why you're interested in this role."
                ></textarea>
            </div>

            <button type="submit" class="apply-submit-btn">Submit Application</button>
        </form>
    </div>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>

</body>
</html>