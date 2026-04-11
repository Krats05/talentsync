<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

$sql = "
CREATE TABLE IF NOT EXISTS meeting_notes (
    note_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) DEFAULT 'Untitled Meeting',
    raw_transcript TEXT,
    user_notes TEXT,
    ai_summary TEXT,
    duration_seconds INT UNSIGNED DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
)";

if ($conn->query($sql)) {
    echo "meeting_notes table ready";
} else {
    echo "Error: " . $conn->error;
}