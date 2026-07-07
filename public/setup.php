<?php
/**
 * SeederLinux Lite - Setup Script
 * Run this script once to initialize the database and create admin user
 *
 * Usage: php setup.php
 * Or access via browser: https://yourserver/setup.php
 */

// Check if running from command line or web
$isCli = php_sapi_name() === 'cli';

function output($message, $isError = false) {
    global $isCli;
    if ($isCli) {
        echo ($isError ? 'ERROR: ' : '') . $message . "\n";
    } else {
        echo '<div style="padding:10px;margin:5px;background:' . ($isError ? '#fee' : '#efe') . ';border-radius:5px;font-family:monospace;">' . htmlspecialchars($message) . '</div>';
    }
}

// Load configuration
if (file_exists(__DIR__ . '/lib/config.php')) {
    require_once __DIR__ . '/lib/config.php';
    require_once __DIR__ . '/lib/db.php';
    require_once __DIR__ . '/lib/functions.php';
} else {
    output('Configuration files not found. Please copy lib/ folder to public/', true);
    exit(1);
}

// Check if already installed
try {
    $existing = Database::fetchOne("SELECT COUNT(*) as count FROM users");
    if ($existing['count'] > 0) {
        output('System already installed. Users exist in database.');
        output('To reinstall, drop all tables first or run: psql -d seederlinux -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"');
        exit(0);
    }
} catch (Exception $e) {
    output('Database not initialized. Please run schema.sql first:');
    output('sudo -u postgres psql -d seederlinux -f install/schema.sql');
    exit(1);
}

// Create admin user
output('Creating admin user...');

$adminUsername = 'admin';
$adminPassword = 'admin123';
$adminHash = password_hash($adminPassword, PASSWORD_BCRYPT, ['cost' => 12]);

try {
    Database::execute(
        "INSERT INTO users (username, password_hash, email, full_name, role) VALUES (?, ?, ?, ?, ?)",
        [$adminUsername, $adminHash, 'admin@seeder.local', 'Administrator', 'admin']
    );

    output('Admin user created successfully!');
    output('Username: admin');
    output('Password: admin123');
    output('');
    output('IMPORTANT: Change the password after first login!');

} catch (Exception $e) {
    output('Failed to create admin user: ' . $e->getMessage(), true);
    exit(1);
}

// Test login
output('');
output('Testing authentication...');
try {
    $user = Database::fetchOne("SELECT * FROM users WHERE username = ?", [$adminUsername]);
    if ($user && password_verify($adminPassword, $user['password_hash'])) {
        output('Authentication test PASSED!');
    } else {
        output('Authentication test FAILED!', true);
    }
} catch (Exception $e) {
    output('Test error: ' . $e->getMessage(), true);
}

output('');
output('Setup complete!');
output('Delete this file after installation for security.');
