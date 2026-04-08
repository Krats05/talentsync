<?php
/**
 * Shared helper functions used across TalentSync
 */

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
