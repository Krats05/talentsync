<?php
require_once __DIR__ . '/config/ai.php';
$result = call_claude('how is todays weather in dc?', 'You are a weather assistant.');
echo $result['success'] ? $result['message'] : 'Error: ' . $result['error'];