<?php
/**
 * Helper Functions
 */

/**
 * Sanitize user input for storage — strips tags, trims whitespace
 * Note: Prepared statements handle SQL injection. HTML escaping is done on output via escape_output().
 */
function sanitize_input($data): string {
    return strip_tags(trim((string)$data));
}

/**
 * Escape output for safe HTML rendering
 */
function escape_output(string $data): string {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate CSRF token
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verify_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output hidden CSRF field
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

/**
 * Redirect to URL
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Calculate BMI from weight (kg) and height (cm)
 */
function calculate_bmi(float $weight, int $height): float {
    if ($height <= 0) return 0;
    $height_m = $height / 100;
    return round($weight / ($height_m * $height_m), 1);
}

/**
 * Calculate age from date of birth
 */
function calculate_age(string $dob): int {
    $birth = new DateTime($dob);
    $now = new DateTime();
    return $now->diff($birth)->y;
}

/**
 * Format date to Arabic month names
 */
function get_arabic_date(string $date): string {
    $months = [
        'January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس',
        'April' => 'أبريل', 'May' => 'مايو', 'June' => 'يونيو',
        'July' => 'يوليو', 'August' => 'أغسطس', 'September' => 'سبتمبر',
        'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر'
    ];
    $en_date = date('F d, Y', strtotime($date));
    return str_replace(array_keys($months), array_values($months), $en_date);
}

/**
 * Upload file with validation
 */
function upload_file(array $file, string $target_dir, array $allowed_types = ['jpg', 'jpeg', 'png', 'gif']): string {
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types)) {
        throw new Exception('نوع الملف غير مسموح - File type not allowed');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception('حجم الملف كبير جداً - Max 5MB');
    }

    $new_name = uniqid('img_') . '.' . $ext;
    $target = $target_dir . '/' . $new_name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception('فشل رفع الملف - Upload failed');
    }

    return $new_name;
}

/**
 * Generate patient file number (YYYY-XXXX)
 */
function generate_file_number(): string {
    global $mysqli;
    $year = date('Y');
    $stmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM patients WHERE YEAR(created_at) = ?");
    $stmt->bind_param('s', $year);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $next = ($row['cnt'] ?? 0) + 1;
    return $year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

/**
 * Get next visit number for a patient
 */
function get_next_visit_number(int $patient_id): int {
    global $mysqli;
    $stmt = $mysqli->prepare("SELECT COUNT(*) as cnt FROM visits WHERE patient_id = ?");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return ($row['cnt'] ?? 0) + 1;
}

/**
 * Display success/error alert
 */
function alert(string $message, string $type = 'success'): string {
    $icons = ['success' => '✅', 'danger' => '❌', 'warning' => '⚠️', 'info' => 'ℹ️'];
    $icon = $icons[$type] ?? 'ℹ️';
    return "<div class='alert alert-{$type}'>{$icon} {$message}</div>";
}

/**
 * Time ago in Arabic
 */
function time_ago(string $datetime): string {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'منذ لحظات';
    if ($diff < 3600) return 'منذ ' . floor($diff / 60) . ' دقيقة';
    if ($diff < 86400) return 'منذ ' . floor($diff / 3600) . ' ساعة';
    if ($diff < 2592000) return 'منذ ' . floor($diff / 86400) . ' يوم';
    return date('Y-m-d', $timestamp);
}
?>
