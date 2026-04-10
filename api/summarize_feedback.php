<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../config/db.php';

// POST kontrol
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../dashboard_hr.php");
    exit;
}

// login kontrol
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$application_id = isset($_POST['application_id']) ? (int)$_POST['application_id'] : 0;
$job_id = isset($_POST['job_id']) ? (int)$_POST['job_id'] : 0;
$feedback = trim($_POST['feedback'] ?? '');

if ($application_id <= 0 || $job_id <= 0 || $feedback === '') {
    header("Location: ../job_applications.php?job_id=$job_id&error=invalid");
    exit;
}

/*
|--------------------------------------------------------------------------
| API KEY
|--------------------------------------------------------------------------
| Test için direkt buraya koyabilirsin.
| Push etmeden önce kaldır.
*/
require_once __DIR__ . '/../config/ai.php';
$apiKey = CLAUDE_API_KEY;

if (!$apiKey) {
    header("Location: ../job_applications.php?job_id=$job_id&error=missing_api_key");
    exit;
}

/*
|--------------------------------------------------------------------------
| Claude prompt
|--------------------------------------------------------------------------
*/
$prompt = "Summarize the following interview feedback for an HR dashboard.\n" .
          "Rules:\n" .
          "- Write 2 to 3 concise professional sentences.\n" .
          "- Keep the meaning.\n" .
          "- Remove repetition.\n" .
          "- Do not copy the feedback word-for-word unless necessary.\n\n" .
          "Interview feedback:\n" . $feedback;

/*
|--------------------------------------------------------------------------
| Claude API payload
|--------------------------------------------------------------------------
| Güncel docs'ta model isimleri Claude Sonnet 4.6 / Opus 4.6 gibi geçiyor.
| Burada son sürüm model adı kullanıldı. Hesabında erişim yoksa altta fallback var.
*/
$modelsToTry = [
    "claude-sonnet-4-6",
    "claude-sonnet-4-5"
];

$summary = '';
$lastError = '';

foreach ($modelsToTry as $modelName) {
    $payload = [
        "model" => $modelName,
        "max_tokens" => 220,
        "messages" => [
            [
                "role" => "user",
                "content" => $prompt
            ]
        ]
    ];

    $ch = curl_init("https://api.anthropic.com/v1/messages");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "x-api-key: " . $apiKey,
        "anthropic-version: 2023-06-01"
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $curlError) {
    header("Location: ../job_applications.php?job_id=$job_id&error=api_failed");
    exit;
}
    

    $data = json_decode($response, true);

    if (!is_array($data)) {
        $lastError = "invalid_json";
        continue;
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $lastError = $data['error']['message'] ?? ('http_' . $httpCode);
        continue;
    }

    $textParts = [];

    if (!empty($data['content']) && is_array($data['content'])) {
        foreach ($data['content'] as $block) {
            if (($block['type'] ?? '') === 'text' && !empty($block['text'])) {
                $textParts[] = $block['text'];
            }
        }
    }

    $summary = trim(implode("\n", $textParts));

    if ($summary !== '') {
        break;
    }

    $lastError = "empty_summary";
}

if ($summary === '') {
    echo "<pre>";
    echo "HTTP Code: " . $httpCode . "\n\n";
    print_r($data);
    exit;
}

// Database’e kaydet
$stmt = $conn->prepare("
    UPDATE applications
    SET feedback_summary = ?
    WHERE application_id = ?
");
$stmt->bind_param("si", $summary, $application_id);
$stmt->execute();
$stmt->close();

// geri dön
header("Location: ../job_applications.php?job_id=$job_id&success=saved");
exit;