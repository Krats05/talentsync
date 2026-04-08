<?php
// api/chatbox.php — AI Job Creation Agent (powered by Claude)
// Accepts POST with JSON body: { "message": "...", "onet_soc_code": "" }
// Returns JSON response

session_start();
require_once '../config/db.php';
require_once '../config/ai.php';   // Shared Claude wrapper — provides CLAUDE_API_KEY, CLAUDE_MODEL

header('Content-Type: application/json');
set_time_limit(60);


function claude_request(string $userMessage, string $systemPrompt = '', int $maxTokens = 1024): array {
    $body = [
        'model'      => CLAUDE_MODEL,
        'max_tokens' => $maxTokens,
        'messages'   => [['role' => 'user', 'content' => $userMessage]],
    ];
    if ($systemPrompt !== '') {
        $body['system'] = $systemPrompt;
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST            => true,
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_TIMEOUT         => 60,
        CURLOPT_HTTPHEADER      => [
            'Content-Type: application/json',
            'x-api-key: '          . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS      => json_encode($body),
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return ['success' => false, 'message' => '', 'error' => 'cURL error: ' . $curlError];
    }

    $data = json_decode($response, true);
    if ($httpCode !== 200) {
        $msg = $data['error']['message'] ?? "HTTP $httpCode";
        return ['success' => false, 'message' => '', 'error' => $msg];
    }

    $text = '';
    foreach ($data['content'] ?? [] as $block) {
        if ($block['type'] === 'text') $text .= $block['text'];
    }
    return ['success' => true, 'message' => $text, 'error' => null];
}

// ── Session guard ────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}
$user_id = (int)$_SESSION['user_id'];

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['HR_Manager', 'Admin', 'Recruiter'], true)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}
require_once __DIR__ . '/../includes/csrf.php';
csrf_validate();

// ── Parse JSON body ──────────────────────────────────────────────────────────
$input       = json_decode(file_get_contents('php://input'), true) ?? [];
$userMessage = trim($input['message']       ?? '');
$forcedCode  = trim($input['onet_soc_code'] ?? '');  // set when HR picks from clarify list

if (empty($userMessage) && empty($forcedCode)) {
    echo json_encode(['status' => 'error', 'message' => 'Empty request']);
    exit;
}

// ── Step 1: Extract standard job title from vague natural-language input ─────
// Uses a cheap call (max_tokens=20) to normalise any language/phrasing.
function extractJobTitle(string $userMessage): string {
    $system = 'You are an HR assistant. Determine if the user wants to hire someone or create a job posting. This includes: directly naming a role ("I need a Project Manager"), describing a task they want someone to do ("I want someone to create a video game", "I need someone to write code."), or any hiring-related request in any language. If it is a hiring request, extract the single most likely standard English job title and reply with ONLY that title (e.g. "Game Developer", "Software Developer"). Only reply with exactly NOT_A_JOB_REQUEST if the message is clearly unrelated to hiring (e.g. greetings like "hi", questions about you, completely unrelated topics).';

    $result = claude_request($userMessage, $system, 20);

    if (!$result['success']) {
        return $userMessage;
    }
    return trim($result['message']);
}

// ── Step 2: Search O*NET occupation_data ─────────────────────────────────────
function searchOnet(mysqli $conn, string $searchTerm): array {
    $like = '%' . $searchTerm . '%';
    $stmt = $conn->prepare(
        'SELECT onetsoc_code, title FROM occupation_data WHERE title LIKE ? LIMIT 5'
    );
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    return $rows;
}

// ── Step 3: Fetch O*NET skills for a SOC code (mirrors get_skills.php) ───────
function fetchSkills(mysqli $conn, string $socCode): array {
    $skills = ['tech' => [], 'general' => []];

    // Technology skills — hot & in-demand
    $stmtTech = $conn->prepare(
        "SELECT example AS element_name
         FROM technology_skills
         WHERE onetsoc_code = ? AND hot_technology = 'Y' AND in_demand = 'Y'
         GROUP BY example LIMIT 15"
    );
    if ($stmtTech) {
        $stmtTech->bind_param('s', $socCode);
        $stmtTech->execute();
        $res = $stmtTech->get_result();
        while ($row = $res->fetch_assoc()) {
            $skills['tech'][] = $row['element_name'];
        }
        $stmtTech->close();
    }

    // General skills — ordered by O*NET importance weight
    $stmtGen = $conn->prepare(
        "SELECT c.element_name
         FROM skills s
         JOIN content_model_reference c ON s.element_id = c.element_id
         WHERE s.onetsoc_code = ? AND s.scale_id = 'IM'
         ORDER BY s.data_value DESC LIMIT 15"
    );
    if ($stmtGen) {
        $stmtGen->bind_param('s', $socCode);
        $stmtGen->execute();
        $res = $stmtGen->get_result();
        while ($row = $res->fetch_assoc()) {
            $skills['general'][] = $row['element_name'];
        }
        $stmtGen->close();
    }

    return $skills;
}

// ── Step 4: Generate job description with Claude ─────────────────────────────
function generateDescription(string $onetTitle, string $socCode, string $jobTitle, array $skills): string {
    $prompt = <<<PROMPT
Based on the O*NET occupational data below, write a compelling job posting description.

OCCUPATION: {$onetTitle} ({$socCode})
JOB TITLE REQUESTED: {$jobTitle}

Write the following sections in plain text (no # markdown headers):

Role Overview
(2-3 sentences describing the position and its impact on the team)

Key Responsibilities
(5-7 bullet points starting with action verbs)

What We Offer
(2-3 generic benefit bullet points)

Rules:
- Total length: 200-300 words
- Professional yet engaging tone
- Do NOT list required skills or qualifications — these are shown separately
- Do NOT include salary ranges or company-specific details
- Plain text only, no markdown formatting
PROMPT;

    $system = 'You are a professional HR writer who creates clear, compelling job postings based on O*NET occupational data.';
    $result = claude_request($prompt, $system, 1024);

    if (!$result['success']) {
        throw new RuntimeException($result['error']);
    }
    return $result['message'];
}

// ── Step 5: Save job as Draft (mirrors save_job.php INSERT path) ─────────────
function saveJobAsDraft(mysqli $conn, int $userId, string $jobTitle, string $socCode, string $description, array $skills): int {
    $status = 'Draft';
    $stmt = $conn->prepare(
        'INSERT INTO jobs (user_id, job_title, onet_soc_code, description, status) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->bind_param('issss', $userId, $jobTitle, $socCode, $description, $status);

    if (!$stmt->execute()) {
        error_log("chatbox.php: DB error saving job: " . $conn->error);
        throw new RuntimeException('Failed to save job. Please try again.');
    }
    $jobId = $conn->insert_id;
    $stmt->close();

    // Insert tech skills
    if (!empty($skills['tech'])) {
        $stmtTech = $conn->prepare(
            "INSERT INTO job_skills (job_id, skill_name, skill_type, source) VALUES (?, ?, 'tech', 'ONET_Default')"
        );
        foreach ($skills['tech'] as $skill) {
            $stmtTech->bind_param('is', $jobId, $skill);
            $stmtTech->execute();
        }
        $stmtTech->close();
    }

    // Insert general skills
    if (!empty($skills['general'])) {
        $stmtGen = $conn->prepare(
            "INSERT INTO job_skills (job_id, skill_name, skill_type, source) VALUES (?, ?, 'general', 'ONET_Default')"
        );
        foreach ($skills['general'] as $skill) {
            $stmtGen->bind_param('is', $jobId, $skill);
            $stmtGen->execute();
        }
        $stmtGen->close();
    }

    return $jobId;
}

// ════════════════════════════════════════════════════════════════════════════
// MAIN FLOW
// ════════════════════════════════════════════════════════════════════════════
try {
    // ── Case A: HR already picked an O*NET code from the clarify list ────────
    if (!empty($forcedCode)) {
        $stmt = $conn->prepare(
            'SELECT onetsoc_code, title FROM occupation_data WHERE onetsoc_code = ? LIMIT 1'
        );
        $stmt->bind_param('s', $forcedCode);
        $stmt->execute();
        $occ = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$occ) {
            echo json_encode(['status' => 'no_match']);
            exit;
        }

        $jobTitle  = $occ['title'];
        $socCode   = $occ['onetsoc_code'];
        $onetTitle = $occ['title'];

    } else {
        // ── Case B: Natural language input — extract title then search O*NET ─
        $jobTitle = extractJobTitle($userMessage);

        if ($jobTitle === 'NOT_A_JOB_REQUEST') {
            echo json_encode([
                'status'  => 'not_job',
                'message' => "I'm an AI Job Assistant — I can help you create job postings! Try describing a role you'd like to hire for, e.g. \"I want a Software Developer\" or \"I need a Project Manager\".",
            ]);
            exit;
        }

        $matches  = searchOnet($conn, $jobTitle);

        if (count($matches) === 0) {
            // Broader fallback: search with first word only
            $firstWord = explode(' ', $jobTitle)[0];
            $matches   = searchOnet($conn, $firstWord);
        }

        if (count($matches) === 0) {
            echo json_encode(['status' => 'no_match', 'searched_for' => $jobTitle]);
            exit;
        }

        if (count($matches) > 1) {
            $matchList = array_map(
                fn($m) => ['code' => $m['onetsoc_code'], 'title' => $m['title']],
                $matches
            );
            echo json_encode([
                'status'    => 'clarify',
                'job_title' => $jobTitle,
                'matches'   => $matchList,
            ]);
            exit;
        }

        // Exactly 1 match — proceed automatically
        $socCode   = $matches[0]['onetsoc_code'];
        $onetTitle = $matches[0]['title'];
    }

    // ── Fetch O*NET skills ────────────────────────────────────────────────────
    $skills = fetchSkills($conn, $socCode);

    // ── Generate job description via Claude ───────────────────────────────────
    $description = generateDescription($onetTitle, $socCode, $jobTitle, $skills);

    // ── Save to database as Draft ─────────────────────────────────────────────
    $jobId = saveJobAsDraft($conn, $user_id, $jobTitle, $socCode, $description, $skills);

    echo json_encode([
        'status'     => 'success',
        'job_id'     => $jobId,
        'job_title'  => $jobTitle,
        'onet_title' => $onetTitle,
        'draft_url'  => 'create_job.php?job_id=' . $jobId,
    ]);

} catch (RuntimeException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
