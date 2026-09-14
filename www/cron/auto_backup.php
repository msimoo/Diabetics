<?php
/**
 * Automated Database Backup Script
 * Run this via Windows Task Scheduler or cron job for daily backups.
 * 
 * Windows Task Scheduler setup:
 *   1. Open Task Scheduler → Create Basic Task
 *   2. Trigger: Daily at a specific time (e.g., 2:00 AM)
 *   3. Action: Start a program → "C:\xampp\php\php.exe"
 *   4. Arguments: "C:\path\to\clinic\www\cron\auto_backup.php"
 * 
 * Linux/Mac cron setup:
 *   0 2 * * * /usr/bin/php /path/to/clinic/www/cron/auto_backup.php
 * 
 * Run manually: php www/cron/auto_backup.php
 */

// Prevent direct web access
if (php_sapi_name() !== 'cli' && !isset($_SERVER['REQUEST_METHOD'])) {
    die('This script can only be run from the command line or Task Scheduler.');
}

// Show output in CLI, silent in web
$is_cli = php_sapi_name() === 'cli';

function log_msg($msg) {
    global $is_cli;
    if ($is_cli) echo date('Y-m-d H:i:s') . " - $msg\n";
}

log_msg("Starting automated database backup...");

// Configuration
$config_file = __DIR__ . '/../config/database.php';
$backup_dir = __DIR__ . '/../backups';

if (!file_exists($config_file)) {
    log_msg("❌ Config file not found: $config_file");
    exit(1);
}

// Parse database config directly from file
$db_config = file_get_contents($config_file);
preg_match('/\$DB_HOST\s*=\s*[\'"]([^\'"]+)[\'"]/', $db_config, $host_m);
preg_match('/\$DB_USER\s*=\s*[\'"]([^\'"]+)[\'"]/', $db_config, $user_m);
preg_match('/\$DB_PASS\s*=\s*[\'"]([^\'"]+)[\'"]/', $db_config, $pass_m);
preg_match('/\$DB_NAME\s*=\s*[\'"]([^\'"]+)[\'"]/', $db_config, $name_m);

$DB_HOST = $host_m[1] ?? 'localhost';
$DB_USER = $user_m[1] ?? 'root';
$DB_PASS = $pass_m[1] ?? '';
$DB_NAME = $name_m[1] ?? 'clinic_diabetes';

// Create backups directory
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
    log_msg("✅ Created backups directory: $backup_dir");
}

// Generate filename with timestamp
$timestamp = date('Y-m-d_H-i-s');
$filename = "backup_{$DB_NAME}_{$timestamp}.sql";
$filepath = "$backup_dir/$filename";

// Find mysqldump
$mysqldump = '';
$possible_paths = [
    'C:\\xampp\\mysql\\bin\\mysqldump.exe',
    'C:\\wamp64\\bin\\mysql\\mysql*\\bin\\mysqldump.exe',
    'C:\\laragon\\bin\\mysql\\mysql*\\bin\\mysqldump.exe',
    '/usr/bin/mysqldump',
    '/usr/local/bin/mysqldump',
    'mysqldump',
];

foreach ($possible_paths as $path) {
    // Handle wildcards for Windows
    if (strpos($path, '*') !== false) {
        $globbed = glob($path);
        if (!empty($globbed)) {
            $mysqldump = $globbed[0];
            break;
        }
    } else {
        // Check if file exists on Windows, or is available in PATH
        if (file_exists($path) || $path === 'mysqldump') {
            $mysqldump = $path;
            break;
        }
    }
}

// If no specific path found, try PATH
if (empty($mysqldump)) {
    // Check if mysqldump is in PATH
    $output = [];
    exec('where mysqldump 2>nul', $output, $return_var);
    if ($return_var === 0 && !empty($output)) {
        $mysqldump = $output[0];
    } else {
        exec('which mysqldump 2>/dev/null', $output, $return_var);
        if ($return_var === 0 && !empty($output)) {
            $mysqldump = $output[0];
        }
    }
}

if (empty($mysqldump)) {
    log_msg("❌ mysqldump not found. Please install MySQL client tools.");
    log_msg("   Backup file NOT created.");

    // Try to log the failure
    try {
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        if (!$conn->connect_error) {
            $stmt = $conn->prepare("INSERT INTO backup_log (filename, file_size_kb, backup_type, status, error_message) VALUES (?, 0, 'تلقائي', 'فشل', ?)");
            $err = "mysqldump not found";
            $stmt->bind_param('ss', $filename, $err);
            $stmt->execute();
            $conn->close();
        }
    } catch (Exception $e) {
        // Silently fail
    }

    exit(1);
}

// Escape the password for command line
$escaped_pass = escapeshellarg($DB_PASS);
$escaped_user = escapeshellarg($DB_USER);
$escaped_host = escapeshellarg($DB_HOST);
$escaped_db = escapeshellarg($DB_NAME);
$escaped_file = escapeshellarg($filepath);

// Build mysqldump command
$command = "\"$mysqldump\" --host=$escaped_host --user=$escaped_user --password=$escaped_pass --single-transaction --routines --triggers --add-drop-table $escaped_db > $escaped_file 2>&1";

log_msg("Running: mysqldump --host=$escaped_host --user=$escaped_user ...");

$output = [];
$return_var = 0;
exec($command, $output, $return_var);

if ($return_var !== 0) {
    $error = implode("\n", $output);
    log_msg("❌ Backup failed: $error");

    // Log failure to database
    try {
        $conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
        if (!$conn->connect_error) {
            $stmt = $conn->prepare("INSERT INTO backup_log (filename, file_size_kb, backup_type, status, error_message) VALUES (?, 0, 'تلقائي', 'فشل', ?)");
            $err_short = substr($error, 0, 200);
            $stmt->bind_param('ss', $filename, $err_short);
            $stmt->execute();
            $conn->close();
        }
    } catch (Exception $e) {
        log_msg("❌ Could not log to database: " . $e->getMessage());
    }

    exit(1);
}

// Check file size
$file_size = filesize($filepath);
$file_size_kb = round($file_size / 1024);

// Compress old backups (keep last 30)
$backups = glob($backup_dir . '/backup_*.sql');
if (count($backups) > 30) {
    usort($backups, function($a, $b) {
        return filemtime($a) - filemtime($b);
    });
    $to_delete = array_slice($backups, 0, count($backups) - 30);
    foreach ($to_delete as $old) {
        unlink($old);
        log_msg("🗑️ Removed old backup: " . basename($old));
    }
}

log_msg("✅ Backup created successfully: $filename ($file_size_kb KB)");

// Log success to database
try {
    $conn = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("INSERT INTO backup_log (filename, file_size_kb, backup_type, status) VALUES (?, ?, 'تلقائي', 'ناجح')");
        $stmt->bind_param('si', $filename, $file_size_kb);
        $stmt->execute();
        $conn->close();
        log_msg("✅ Backup logged to database.");
    }
} catch (Exception $e) {
    log_msg("⚠️ Could not log to database: " . $e->getMessage());
}

log_msg("✅ Automated backup completed successfully.");
echo "\n";
