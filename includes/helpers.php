<?php
/**
 * Shared helper functions used across TalentSync
 */

// Application status constants
const APP_STATUSES = ['Pending', 'Interviewing', 'Offered', 'Rejected'];
const JOB_STATUSES = ['Draft', 'Open', 'Closed'];
const JOB_FILTER_STATUSES = ['All', 'Draft', 'Open', 'Closed'];
const APP_FILTER_STATUSES = ['All', 'Pending', 'Interviewing', 'Offered', 'Rejected'];

// HTML escape shorthand
function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// Dynamic bind_param helper for prepared statements
function bindParams(mysqli_stmt $stmt, string $types, array $params) {
    if ($types === '') return;
    $refs = [];
    foreach ($params as $k => $v) $refs[$k] = &$params[$k];
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}
