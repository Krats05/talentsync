<?php
/**
 * Migration: Add 'skills' column to applications table
 * Run once: http://localhost:8888/talentsync/api/migrate_add_skills.php
 */
require_once __DIR__ . '/../config/db.php';

// Add skills column if it doesn't exist
$result = $conn->query("SHOW COLUMNS FROM applications LIKE 'skills'");
if ($result->num_rows === 0) {
    $conn->query("ALTER TABLE applications ADD COLUMN skills TEXT NULL AFTER cover_letter");
    echo "✅ Added 'skills' column to applications table.\n";
} else {
    echo "ℹ️ 'skills' column already exists.\n";
}

$conn->close();
?>
