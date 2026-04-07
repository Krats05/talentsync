<?php
/**
 * config/ai.php — Shared Claude API Configuration
 *
 * Usage: require_once __DIR__ . '/../config/ai.php';
 *        $response = call_claude("Your prompt here");
 *        $response = call_claude("Your prompt here", "You are a helpful assistant.");
 *
 * All AI features share this single file. Do NOT create separate API configs.
 */

// ── API Key ──────────────────────────────────────────────────────────────────
// Get your key from: https://console.anthropic.com/settings/keys
define('CLAUDE_API_KEY', 'your-api-key-here');

// ── Model Configuration ─────────────────────────────────────────────────────
define('CLAUDE_MODEL', 'claude-haiku-4-5');    // Fast & cheapest ($1/M input, $5/M output)
define('CLAUDE_MAX_TOKENS', 4096);              // Max response length

/**
 * Send a prompt to Claude API and get a text response.
 *
 * @param string $userMessage  The user's message/prompt
 * @param string $systemPrompt Optional system prompt to set context/role
 * @param int    $maxTokens    Optional override for max response tokens
 * @return array ['success' => bool, 'message' => string, 'error' => string|null]
 *
 * Example:
 *   $result = call_claude("Summarize this feedback: ...", "You are an HR assistant.");
 *   if ($result['success']) {
 *       echo $result['message'];
 *   } else {
 *       echo "Error: " . $result['error'];
 *   }
 */
function call_claude(string $userMessage, string $systemPrompt = '', int $maxTokens = 0): array {
    if (CLAUDE_API_KEY === 'your-api-key-here') {
        return ['success' => false, 'message' => '', 'error' => 'API key not configured. Update config/ai.php'];
    }

    $maxTokens = $maxTokens > 0 ? $maxTokens : CLAUDE_MAX_TOKENS;

    $body = [
        'model'      => CLAUDE_MODEL,
        'max_tokens' => $maxTokens,
        'messages'   => [
            ['role' => 'user', 'content' => $userMessage]
        ],
    ];

    if ($systemPrompt !== '') {
        $body['system'] = $systemPrompt;
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Network error
    if ($response === false) {
        return ['success' => false, 'message' => '', 'error' => 'Network error: ' . $curlError];
    }

    $data = json_decode($response, true);

    // API error
    if ($httpCode !== 200) {
        $errMsg = $data['error']['message'] ?? "HTTP $httpCode";
        return ['success' => false, 'message' => '', 'error' => $errMsg];
    }

    // Extract text from response
    $text = '';
    foreach ($data['content'] ?? [] as $block) {
        if ($block['type'] === 'text') {
            $text .= $block['text'];
        }
    }

    return ['success' => true, 'message' => $text, 'error' => null];
}

/**
 * Send a multi-turn conversation to Claude API.
 * Useful for chatbox features where context matters.
 *
 * @param array  $messages     Array of ['role' => 'user'|'assistant', 'content' => '...']
 * @param string $systemPrompt Optional system prompt
 * @param int    $maxTokens    Optional override for max tokens
 * @return array ['success' => bool, 'message' => string, 'error' => string|null]
 *
 * Example:
 *   $messages = [
 *       ['role' => 'user', 'content' => 'I need a job posting for a Software Engineer'],
 *       ['role' => 'assistant', 'content' => 'Sure! What skills are required?'],
 *       ['role' => 'user', 'content' => 'Python, AWS, and Docker'],
 *   ];
 *   $result = call_claude_chat($messages, "You are a job posting assistant.");
 */
function call_claude_chat(array $messages, string $systemPrompt = '', int $maxTokens = 0): array {
    if (CLAUDE_API_KEY === 'your-api-key-here') {
        return ['success' => false, 'message' => '', 'error' => 'API key not configured. Update config/ai.php'];
    }

    $maxTokens = $maxTokens > 0 ? $maxTokens : CLAUDE_MAX_TOKENS;

    $body = [
        'model'      => CLAUDE_MODEL,
        'max_tokens' => $maxTokens,
        'messages'   => $messages,
    ];

    if ($systemPrompt !== '') {
        $body['system'] = $systemPrompt;
    }

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . CLAUDE_API_KEY,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_POSTFIELDS     => json_encode($body),
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'message' => '', 'error' => 'Network error: ' . $curlError];
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $errMsg = $data['error']['message'] ?? "HTTP $httpCode";
        return ['success' => false, 'message' => '', 'error' => $errMsg];
    }

    $text = '';
    foreach ($data['content'] ?? [] as $block) {
        if ($block['type'] === 'text') {
            $text .= $block['text'];
        }
    }

    return ['success' => true, 'message' => $text, 'error' => null];
}
