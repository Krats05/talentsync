<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/ai.php';

header('Content-Type: application/json');

/* REQUEST CHECK */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "success" => false,
        "error" => "Invalid request"
    ]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "success" => false,
        "error" => "Unauthorized"
    ]);
    exit;
}

/* INPUT */
$transcript = trim($_POST['raw_transcript'] ?? '');
$userNotes  = trim($_POST['user_notes'] ?? '');
$title      = trim($_POST['title'] ?? 'Untitled');

if ($transcript === '' && $userNotes === '') {
    echo json_encode([
        "success" => false,
        "error" => "No content"
    ]);
    exit;
}

/* API KEY */
$apiKey = defined('CLAUDE_API_KEY') ? CLAUDE_API_KEY : '';

if (!$apiKey) {
    echo json_encode([
        "success" => false,
        "error" => "API key missing"
    ]);
    exit;
}

/* SAFE TEXT FOR PROMPT */
$cleanTranscript = substr($transcript, 0, 12000);
$cleanUserNotes  = substr($userNotes, 0, 6000);
$cleanTitle      = substr($title, 0, 200);

/* PROMPT */
$prompt = <<<PROMPT
You are helping format meeting notes.

Meeting title:
{$cleanTitle}

Transcript:
{$cleanTranscript}

User Notes:
{$cleanUserNotes}

Task:
Create:
1. A clean readable dialogue section from the transcript.
2. A short structured summary section.

Important rules:
- Return ONLY valid HTML.
- Do NOT include markdown.
- Do NOT include triple backticks.
- Keep dialogue as short readable lines.
- Keep summary concise with bullet points.
- Use EXACTLY this structure:

<h3>Dialogue</h3>
<p><strong>Speaker:</strong> sentence...</p>
<p><strong>Speaker:</strong> sentence...</p>

<h3>Summary</h3>
<ul>
  <li>Point 1</li>
  <li>Point 2</li>
  <li>Point 3</li>
</ul>

If speaker names are unclear, use labels like Speaker 1, Speaker 2, Candidate, Coach, Interviewer, etc. based on context.
PROMPT;

/* PAYLOAD */
$payload = [
    "model" => "claude-sonnet-4-5",
    "max_tokens" => 900,
    "messages" => [
        [
            "role" => "user",
            "content" => [
                [
                    "type" => "text",
                    "text" => $prompt
                ]
            ]
        ]
    ]
];

/* CURL */
$ch = curl_init("https://api.anthropic.com/v1/messages");

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "x-api-key: " . $apiKey,
    "anthropic-version: 2023-06-01"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

/* LOCAL FIX - remove these on production if possible */
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

curl_close($ch);

/* CURL ERROR */
if ($response === false || $curlError) {
    echo json_encode([
        "success" => false,
        "error" => "cURL Error: " . $curlError
    ]);
    exit;
}

/* PARSE JSON */
$data = json_decode($response, true);

if (!is_array($data)) {
    echo json_encode([
        "success" => false,
        "error" => "Invalid response",
        "raw" => $response
    ]);
    exit;
}

/* API ERROR */
if ($httpCode !== 200) {
    echo json_encode([
        "success" => false,
        "error" => $data['error']['message'] ?? "API error",
        "raw" => $data
    ]);
    exit;
}

/* EXTRACT TEXT */
$output = '';

if (!empty($data['content']) && is_array($data['content'])) {
    foreach ($data['content'] as $block) {
        if (($block['type'] ?? '') === 'text') {
            $output .= $block['text'] ?? '';
        }
    }
}

$output = trim($output);

/* EMPTY CHECK */
if ($output === '') {
    echo json_encode([
        "success" => false,
        "error" => "Empty AI response"
    ]);
    exit;
}

/* CLEAN COMMON MODEL FORMATTING ISSUES */
$output = preg_replace('/^```html\s*/i', '', $output);
$output = preg_replace('/^```\s*/i', '', $output);
$output = preg_replace('/\s*```$/i', '', $output);
$output = trim($output);

/* SPLIT DIALOGUE + SUMMARY */
$dialogueHtml = '';
$summaryHtmlOnly = '';

if (preg_match('/<h3>\s*Dialogue\s*<\/h3>(.*?)(?=<h3>\s*Summary\s*<\/h3>)/is', $output, $matches)) {
    $dialogueHtml = trim($matches[1]);
}

if (preg_match('/<h3>\s*Summary\s*<\/h3>(.*)$/is', $output, $matches)) {
    $summaryHtmlOnly = trim($matches[1]);
}

/* FALLBACKS */
if ($dialogueHtml === '' && $summaryHtmlOnly === '') {
    $summaryHtmlOnly = $output;
}

/* OPTIONAL EXTRA CLEANUP */
$dialogueHtml = trim($dialogueHtml);
$summaryHtmlOnly = trim($summaryHtmlOnly);

/* SUCCESS */
echo json_encode([
    "success" => true,
    "summary_html" => $output,
    "dialogue_html" => $dialogueHtml,
    "summary_html_only" => $summaryHtmlOnly
]);
exit;