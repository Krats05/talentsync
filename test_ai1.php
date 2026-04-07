<?php
require_once __DIR__ . '/config/ai.php';
$result = call_claude('What is TalentSync in one sentence?', 'You are an HR tech assistant.');
echo $result['success'] ? $result['message'] : 'Error: ' . $result['error'];