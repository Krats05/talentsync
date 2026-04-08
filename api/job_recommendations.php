<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/ai.php';
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);
    exit;
}

$role = $_SESSION['role'] ?? '';
if ($role !== 'Applicant' && $role !== 'applicant') {
    echo json_encode([
        'success' => false,
        'message' => 'Only applicants can access this feature.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$role_type  = trim($input['role_type'] ?? '');
$experience = trim($input['experience'] ?? '');
$skills     = trim($input['skills'] ?? '');
$location   = trim($input['location'] ?? '');
$salary     = trim($input['salary'] ?? '');
$industries = trim($input['industries'] ?? '');

if ($role_type === '' || $experience === '' || $skills === '' || $location === '' || $salary === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Please complete all required fields.'
    ]);
    exit;
}

$sql = "
    SELECT 
        j.job_id,
        j.job_title,
        j.description,
        j.location,
        j.salary_range,
        u.full_name AS company,
        GROUP_CONCAT(js.skill_name SEPARATOR ', ') AS required_skills
    FROM jobs j
    JOIN users u ON j.user_id = u.user_id
    LEFT JOIN job_skills js ON j.job_id = js.job_id
    WHERE j.status = 'Open'
    GROUP BY j.job_id, j.job_title, j.description, j.location, j.salary_range, u.full_name
";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Database query failed.'
    ]);
    exit;
}

$job_list = [];
while ($row = $result->fetch_assoc()) {
    $job_list[] = $row;
}

if (empty($job_list)) {
    echo json_encode([
        'success' => false,
        'message' => 'No open jobs available right now.'
    ]);
    exit;
}

$prompt = "
I am a job applicant. Here is my profile:

Role type: {$role_type}
Experience level: {$experience}
Top skills: {$skills}
Preferred work location: {$location}
Expected salary range: {$salary}
Preferred industries: {$industries}

Here are all available jobs:
" . json_encode($job_list, JSON_PRETTY_PRINT) . "

Please rank the top 5 most relevant jobs for me.

Return ONLY valid JSON in this exact format:
{
  \"recommendations\": [
    {
      \"job_id\": 1,
      \"job_title\": \"...\",
      \"company\": \"...\",
      \"reason\": \"...\"
    }
  ]
}
";

$system_prompt = "You are a career advisor. Match jobs based on skills, experience level, preferences, work location, and salary expectations. Return only valid JSON.";

try {
    $ai_response = call_claude($prompt, $system_prompt);

    // If call_claude already returns an array, use it directly.
    // If it returns a string, decode it.
    if (is_array($ai_response)) {
        $decoded = $ai_response;
    } else {
        $decoded = json_decode($ai_response, true);
    }

    if (!is_array($decoded) || !isset($decoded['recommendations'])) {
        echo json_encode([
            'success' => false,
            'message' => 'AI response format was invalid.',
            'raw_response' => $ai_response
        ]);
        exit;
    }

    $_SESSION['ai_job_recommendations'] = $decoded['recommendations'];
    $_SESSION['ai_questionnaire'] = [
        'role_type' => $role_type,
        'experience' => $experience,
        'skills' => $skills,
        'location' => $location,
        'salary' => $salary,
        'industries' => $industries
    ];

    echo json_encode([
        'success' => true,
        'message' => 'Recommendations generated successfully.'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'AI recommendation failed: ' . $e->getMessage()
    ]);
}