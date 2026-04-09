<?php
// api/ai_score.php — AI-Enhanced Candidate Scoring
session_start();
require_once __DIR__ . '/../config/ai.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/skill_match.php';

// Auth check
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
$role   = $_SESSION['role'] ?? null;
$allowedRoles = ['HR_Manager', 'Admin', 'Recruiter'];

if (!$userId || !$role || !in_array($role, $allowedRoles, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$application_id = isset($_GET['application_id']) ? (int)$_GET['application_id'] : 0;
if ($application_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid application_id']);
    exit;
}

// Fetch application + job data, verify job belongs to this HR user
$stmt = $conn->prepare("
    SELECT
        a.skills          AS applicant_skills,
        a.cover_letter,
        a.full_name,
        a.job_id,
        j.job_title,
        j.description,
        GROUP_CONCAT(js.skill_name SEPARATOR ', ') AS required_skills
    FROM applications a
    JOIN jobs j ON a.job_id = j.job_id
    LEFT JOIN job_skills js ON j.job_id = js.job_id
    WHERE a.application_id = ?
      AND j.user_id = ?
      AND a.deleted_at IS NULL
      AND j.deleted_at IS NULL
    GROUP BY a.application_id
");
$stmt->bind_param('ii', $application_id, $userId);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Application not found or access denied']);
    exit;
}

// Run existing algorithm to get match context
$jobId = (int)$data['job_id'];
$score = calculate_match_score($jobId, $data['applicant_skills'] ?? '', $conn);

if ($score['applicant_count'] > 0) {
    $pct         = round($score['composite'] * 100);
    $matchedList = implode(', ', $score['matched']) ?: 'None';
    $missingList = implode(', ', $score['missing']) ?: 'None';
    $algoLine    = "Algorithm match score: {$pct}%\n"
                 . "Matched skills: {$matchedList}\n"
                 . "Missing skills: {$missingList}\n";
} else {
    $algoLine = "Algorithm score: N/A (no skills provided by applicant)\n";
}

// Build Claude prompt
$jobTitle        = $data['job_title']        ?? 'Unknown Role';
$requiredSkills  = $data['required_skills']  ?? 'Not specified';
$applicantSkills = $data['applicant_skills'] ?? 'Not provided';
$coverLetter     = trim($data['cover_letter'] ?? '');
$coverLetter     = ($coverLetter !== '') ? $coverLetter : 'No cover letter provided.';

$prompt = "Analyze this candidate applying for the role of \"{$jobTitle}\":\n\n"
    . $algoLine
    . "Required skills for the job: {$requiredSkills}\n"
    . "Applicant's listed skills: {$applicantSkills}\n"
    . "Cover letter:\n{$coverLetter}\n\n"
    . "Please provide your analysis in this exact format:\n\n"
    . "1) Qualitative Assessment\n"
    . "[2 sentences — overall impression of this candidate]\n\n"
    . "2) Transferable Skills\n"
    . "[Skills or experience from the cover letter the algorithm may have missed]\n\n"
    . "3) Key Skill Gaps\n"
    . "[What important skills are missing]\n\n"
    . "4) Recommendation: [Strong Hire / Hire / Maybe / No Hire]\n"
    . "[One sentence reason]";

$systemPrompt = "You are an experienced HR scoring analyst. Evaluate candidates fairly and objectively. Be specific and concise. Use plain text only, no markdown.";

$result = call_claude($prompt, $systemPrompt);

echo json_encode($result);