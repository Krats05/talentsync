<?php
/**
 * Simple file-based rate limiter
 * Tracks attempts per IP address using temp files.
 *
 * Usage:
 *   require_once __DIR__ . '/rate_limit.php';
 *   rate_limit_check('login', 5, 900); // 5 attempts per 15 minutes
 */

function rate_limit_check(string $action, int $maxAttempts = 5, int $windowSeconds = 900): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = md5($action . '_' . $ip);
    $file = sys_get_temp_dir() . '/talentsync_rl_' . $key;

    $attempts = [];

    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            // Keep only attempts within the time window
            $cutoff = time() - $windowSeconds;
            $attempts = array_filter($data, function ($ts) use ($cutoff) {
                return $ts > $cutoff;
            });
        }
    }

    if (count($attempts) >= $maxAttempts) {
        http_response_code(429);
        exit('Too many attempts. Please try again in ' . ceil($windowSeconds / 60) . ' minutes.');
    }

    // Record this attempt
    $attempts[] = time();
    file_put_contents($file, json_encode(array_values($attempts)), LOCK_EX);
}

function rate_limit_clear(string $action): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = md5($action . '_' . $ip);
    $file = sys_get_temp_dir() . '/talentsync_rl_' . $key;
    if (file_exists($file)) {
        unlink($file);
    }
}
