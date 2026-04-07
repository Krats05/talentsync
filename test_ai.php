<?php
require_once 'config/ai.php';
$result = call_claude('Say hello', 'You are a friendly assistant.');
echo $result['success'] ? $result['message'] : 'Error: ' . $result['error'];
