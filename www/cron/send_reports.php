<?php
/**
 * Scheduled Email Reports — Cron-compatible script for automated email reports
 * 
 * Usage (add to crontab):
 *   # Weekly high-risk report every Monday at 8 AM
 *   0 8 * * 1 /path/to/php www/cron/send_reports.php --type=weekly
 *   
 *   # Daily anomaly alerts every day at 7 AM
 *   0 7 * * * /path/to/php www/cron/send_reports.php --type=daily
 *   
 *   # Monthly clinic report on 1st of month at 9 AM
 *   0 9 1 * * /path/to/php www/cron/send_reports.php --type=monthly
 */

// CLI mode detection
$is_cli = (php_sapi_name() === 'cli');
if (!$is_cli) {
    // Allow web access with auth
    session_start();
    if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        die('❌ Access denied. This script must be run via CLI or by admin.');
    }
}

// Parse arguments
$report_type = 'weekly';
if ($is_cli) {
    $opts = getopt('', ['type:']);
    $report_type = $opts['type'] ?? 'weekly';
} else {
    $report_type = $_GET['type'] ?? 'weekly';
}

require_once __DIR__ . '/../config/database.php';

echo "📧 Starting scheduled report: {$report_type}\n";

// ===== EMAIL CONFIGURATION =====
$email_config = [
    'enabled' => false,
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_user' => '',
    'smtp_pass' => '',
    'from_email' => '',
    'from_name' => 'نظام عيادة السكري',
    'recipients' => []
];

// Try to load from settings table
$settings_result = $mysqli->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'email_%'");
if ($settings_result) {
    while ($s = $settings_result->fetch_assoc()) {
        $key = str_replace('email_', '', $s['setting_key']);
        if ($key === 'enabled') $email_config['enabled'] = (bool)$s['setting_value'];
        elseif ($key === 'smtp_host') $email_config['smtp_host'] = $s['setting_value'];
        elseif ($key === 'smtp_port') $email_config['smtp_port'] = (int)$s['setting_value'];
        elseif ($key === 'smtp_user') $email_config['smtp_user'] = $s['setting_value'];
        elseif ($key === 'smtp_pass') $email_config['smtp_pass'] = $s['setting_value'];
        elseif ($key === 'from_email') $email_config['from_email'] = $s['setting_value'];
        elseif ($key === 'recipients') $email_config['recipients'] = explode(',', $s['setting_value']);
    }
}

// ===== Check if email is configured =====
if (!$email_config['enabled'] || empty($email_config['from_email'])) {
    $msg = "⚠️ Email is not configured. Please configure email settings first.\n";
    echo $msg;
    if (!$is_cli) die(nl2br($msg));
    exit(1);
}

// ===== Send email function with SMTP support =====
function send_email($to, $subject, $html_body, $config) {
    // If SMTP is configured, use it. Otherwise fall back to mail().
    if (!empty($config['smtp_host']) && !empty($config['smtp_user'])) {
        return send_email_smtp($to, $subject, $html_body, $config);
    }
    return send_email_mail($to, $subject, $html_body, $config);
}

function send_email_mail($to, $subject, $html_body, $config) {
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$config['from_name']} <{$config['from_email']}>\r\n";
    $headers .= "X-Mailer: Clinic Report System\r\n";
    
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html_body, $headers);
}

function send_email_smtp($to, $subject, $html_body, $config) {
    // SMTP implementation using PHP sockets (no external library required)
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen(
        $config['smtp_host'],
        $config['smtp_port'],
        $errno,
        $errstr,
        15
    );
    
    if (!$socket) {
        echo "❌ SMTP connection failed: {$errstr}\n";
        return false;
    }
    
    $readsmtp = function($socket) {
        $data = '';
        while ($line = fgets($socket, 512)) {
            $data .= $line;
            if (substr($line, 3, 1) === ' ') break; // Last line of SMTP response
        }
        return $data;
    };
    
    $writesmtp = function($socket, $cmd) use ($readsmtp) {
        fwrite($socket, $cmd . "\r\n");
        return $readsmtp($socket);
    };
    
    // SMTP handshake
    $readsmtp($socket); // Read greeting
    $writesmtp($socket, "EHLO clinic-system");
    
    // Start TLS if port 587
    if ($config['smtp_port'] == 587) {
        $writesmtp($socket, "STARTTLS");
        stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        $writesmtp($socket, "EHLO clinic-system");
    }
    
    // Auth
    $writesmtp($socket, "AUTH LOGIN");
    $writesmtp($socket, base64_encode($config['smtp_user']));
    $writesmtp($socket, base64_encode($config['smtp_pass']));
    
    // Send
    $writesmtp($socket, "MAIL FROM:<{$config['from_email']}>");
    $writesmtp($socket, "RCPT TO:<{$to}>");
    $writesmtp($socket, "DATA");
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: {$config['from_name']} <{$config['from_email']}>\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "X-Mailer: Clinic Report System\r\n";
    
    fwrite($socket, $headers . "\r\n" . $html_body . "\r\n.\r\n");
    $readsmtp($socket);
    $writesmtp($socket, "QUIT");
    
    fclose($socket);
    return true;
}

// ===== HTML escape helper =====
function e_html($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// ===== GENERATE REPORT CONTENT =====

switch ($report_type) {
    case 'daily':
        // Daily anomaly alerts
        echo "📊 Generating daily anomaly report...\n";
        
        $anomalies = [];
        
        // HbA1c spikes
        $spikes = $mysqli->query("SELECT v.patient_id, p.full_name, p.file_number, bs1.hba1c_value as older, bs2.hba1c_value as recent
            FROM blood_sugar_readings bs1 JOIN visits v1 ON bs1.visit_id = v1.visit_id
            JOIN blood_sugar_readings bs2 ON bs2.reading_id > bs1.reading_id AND bs2.visit_id IN (SELECT visit_id FROM visits WHERE patient_id = v1.patient_id)
            JOIN visits v2 ON bs2.visit_id = v2.visit_id JOIN patients p ON v1.patient_id = p.patient_id
            WHERE bs2.hba1c_value - bs1.hba1c_value > 1.5 AND DATEDIFF(v2.visit_date, v1.visit_date) <= 120
            GROUP BY v1.patient_id ORDER BY (bs2.hba1c_value - bs1.hba1c_value) DESC LIMIT 10");
        while ($s = $spikes->fetch_assoc()) $anomalies[] = $s;
        
        $subject = "⚠️ تنبيهات يومية - حالات شذوذ سريرية | Daily Anomaly Alerts";
        $html = "<h2>⚠️ تنبيهات الحالات الشاذة</h2><p>" . date('Y-m-d H:i') . "</p><hr>";
        
        if (empty($anomalies)) {
            $html .= "<p style='color:#059669;'>✅ لا توجد حالات شذوذ جديدة.</p>";
        } else {
            $html .= "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;width:100%;'>";
            $html .= "<tr style='background:#dc2626;color:#fff;'><th>المريض</th><th>نوع الشذوذ</th><th>التفاصيل</th></tr>";
            foreach ($anomalies as $a) {
                $name_esc = e_html($a['full_name']);
                $older_esc = e_html($a['older']);
                $recent_esc = e_html($a['recent']);
                $html .= "<tr><td>{$name_esc}</td><td>ارتفاع HbA1c</td><td>من {$older_esc}% إلى {$recent_esc}%</td></tr>";
            }
            $html .= "</table>";
        }
        
        $recipients = $email_config['recipients'];
        break;
        
    case 'weekly':
        // Weekly high-risk patient summary
        echo "📊 Generating weekly high-risk report...\n";
        
        $high_risk = [];
        $result = $mysqli->query("SELECT DISTINCT v.patient_id, p.full_name, p.file_number, p.age, p.gender, p.city,
            fa.wagner_grade, bs.hba1c_value, mh.smoking_status,
            DATEDIFF(CURDATE(), MAX(v.visit_date)) as days_since_last,
            o.improvement_percentage, o.current_amputation
            FROM visits v
            JOIN patients p ON v.patient_id = p.patient_id
            LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
            LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
            LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
            LEFT JOIN outcomes o ON v.visit_id = o.visit_id
            WHERE p.is_active = 1
            GROUP BY v.patient_id
            HAVING (fa.wagner_grade >= 3 OR bs.hba1c_value > 9 OR days_since_last > 60)
            ORDER BY fa.wagner_grade DESC, days_since_last DESC LIMIT 20");
        while ($r = $result->fetch_assoc()) $high_risk[] = $r;
        
        // Clinic stats
        $total = (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1")->fetch_assoc()['c'];
        $new_week = (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['c'];
        $visits_week = (int)$mysqli->query("SELECT COUNT(*) as c FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)")->fetch_assoc()['c'];
        $wagner3 = (int)$mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN foot_assessments fa ON v.visit_id = fa.visit_id WHERE fa.wagner_grade >= 3")->fetch_assoc()['c'];
        $lost = (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1 AND patient_id NOT IN (SELECT DISTINCT patient_id FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY))")->fetch_assoc()['c'];
        
        $subject = "📊 التقرير الأسبوعي - مرضى الخطورة العالية | Weekly High-Risk Report";
        $html = "<h2>📊 التقرير الأسبوعي للعيادة</h2>";
        $html .= "<p>الفترة: " . date('Y-m-d', strtotime('-7 days')) . " — " . date('Y-m-d') . "</p><hr>";
        
        $html .= "<div style='display:flex;gap:10px;flex-wrap:wrap;margin:10px 0;'>";
        $html .= "<div style='padding:10px;border:1px solid #e2e8f0;border-radius:6px;text-align:center;min-width:80px;'><strong style='font-size:18px;color:#0a7e6e;'>{$total}</strong><br><small>إجمالي المرضى</small></div>";
        $html .= "<div style='padding:10px;border:1px solid #e2e8f0;border-radius:6px;text-align:center;min-width:80px;'><strong style='font-size:18px;color:#3b82f6;'>{$visits_week}</strong><br><small>زيارات الأسبوع</small></div>";
        $html .= "<div style='padding:10px;border:1px solid #e2e8f0;border-radius:6px;text-align:center;min-width:80px;'><strong style='font-size:18px;color:#dc2626;'>{$wagner3}</strong><br><small>Wagner 3+</small></div>";
        $html .= "<div style='padding:10px;border:1px solid #e2e8f0;border-radius:6px;text-align:center;min-width:80px;'><strong style='font-size:18px;color:#f59e0b;'>{$lost}</strong><br><small>منقطعون</small></div>";
        $html .= "</div><hr>";
        
        if (empty($high_risk)) {
            $html .= "<p style='color:#059669;'>✅ لا توجد حالات عالية الخطورة هذا الأسبوع.</p>";
        } else {
            $html .= "<h3>🔴 مرضى الخطورة العالية</h3>";
            $html .= "<table border='1' cellpadding='6' cellspacing='0' style='border-collapse:collapse;width:100%;'>";
            $html .= "<tr style='background:#dc2626;color:#fff;'><th>المريض</th><th>الملف</th><th>Wagner</th><th>HbA1c</th><th>آخر زيارة</th><th>الحالة</th></tr>";
            foreach ($high_risk as $r) {
                $name_esc    = e_html($r['full_name']);
                $file_esc    = e_html($r['file_number']);
                $status = '';
                if ($r['wagner_grade'] >= 3) $status .= '⚠️ Wagner 3+ ';
                if ($r['hba1c_value'] > 9) $status .= '📈 HbA1c >9% ';
                if ($r['days_since_last'] > 60) $status .= '🚪 منقطع ';
                if ($r['current_amputation'] && $r['current_amputation'] !== 'لا') $status .= '🦶 بتر ';
                $html .= "<tr><td style='font-weight:bold;'>{$name_esc}</td><td>{$file_esc}</td><td style='color:#dc2626;font-weight:bold;'>" . e_html($r['wagner_grade']) . "</td><td>" . e_html($r['hba1c_value']) . "%</td><td>" . (int)$r['days_since_last'] . " يوم</td><td>{$status}</td></tr>";
            }
            $html .= "</table>";
        }
        
        // Insights
        $html .= "<hr><h3>💡 رؤى وتحليلات</h3><ul>";
        if ($wagner3 > 5) $html .= "<li>🔴 عدد كبير من حالات Wagner 3+ ({$wagner3}) — تخصيص عيادة قدم أسبوعية.</li>";
        if ($lost > 20) $html .= "<li>🚪 {$lost} مريض منقطع — حملة استدعاء عبر الهاتف.</li>";
        if ($new_week > 5) $html .= "<li>👥 {$new_week} مرضى جدد هذا الأسبوع — ترحيب وتوعية أولية.</li>";
        $html .= "</ul>";
        
        $recipients = $email_config['recipients'];
        break;
        
    case 'monthly':
        echo "📊 Generating monthly clinic report...\n";
        
        // Aggregate monthly stats
        $stats = [];
        $stats['total_patients'] = (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE is_active = 1")->fetch_assoc()['c'];
        $stats['new_month'] = (int)$mysqli->query("SELECT COUNT(*) as c FROM patients WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['c'];
        $stats['visits_month'] = (int)$mysqli->query("SELECT COUNT(*) as c FROM visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['c'];
        $stats['avg_hba1c'] = $mysqli->query("SELECT ROUND(AVG(bs.hba1c_value), 1) as avg FROM blood_sugar_readings bs JOIN visits v ON bs.visit_id = v.visit_id WHERE v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['avg'];
        $stats['healed'] = (int)$mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c FROM visits v JOIN outcomes o ON v.visit_id = o.visit_id WHERE o.improvement_percentage >= 100 AND v.visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['c'];
        $stats['amputations'] = (int)$mysqli->query("SELECT COUNT(*) as c FROM outcomes WHERE current_amputation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND current_amputation IS NOT NULL AND current_amputation != 'لا'")->fetch_assoc()['c'];
        
        $subject = "📈 التقرير الشهري للعيادة | Monthly Clinic Report — " . date('F Y');
        $html = "<h2>📈 التقرير الشهري للعيادة</h2>";
        $html .= "<p>الشهر: " . date('F Y') . "</p><hr>";
        
        $html .= "<div style='display:flex;gap:10px;flex-wrap:wrap;margin:10px 0;'>";
        $html .= "<div style='padding:15px;border:1px solid #e2e8f0;border-radius:8px;text-align:center;min-width:100px;background:#f0fdf4;'><strong style='font-size:22px;color:#0a7e6e;'>{$stats['total_patients']}</strong><br>إجمالي المرضى</div>";
        $html .= "<div style='padding:15px;border:1px solid #e2e8f0;border-radius:8px;text-align:center;min-width:100px;background:#eff6ff;'><strong style='font-size:22px;color:#3b82f6;'>{$stats['visits_month']}</strong><br>زيارات الشهر</div>";
        $html .= "<div style='padding:15px;border:1px solid #e2e8f0;border-radius:8px;text-align:center;min-width:100px;background:#fff7ed;'><strong style='font-size:22px;color:#db2777;'>{$stats['avg_hba1c']}%</strong><br>متوسط HbA1c</div>";
        $html .= "<div style='padding:15px;border:1px solid #e2e8f0;border-radius:8px;text-align:center;min-width:100px;background:#d1fae5;'><strong style='font-size:22px;color:#059669;'>{$stats['healed']}</strong><br>تم شفاؤهم</div>";
        $html .= "<div style='padding:15px;border:1px solid #e2e8f0;border-radius:8px;text-align:center;min-width:100px;background:#fee2e2;'><strong style='font-size:22px;color:#dc2626;'>{$stats['amputations']}</strong><br>حالات بتر</div>";
        $html .= "</div><hr>";
        
        $html .= "<h3>💡 ملخص الشهر</h3><ul>";
        $html .= "<li>إجمالي المرضى النشطين: {$stats['total_patients']}</li>";
        $html .= "<li>مرضى جدد هذا الشهر: {$stats['new_month']}</li>";
        $html .= "<li>إجمالي الزيارات: {$stats['visits_month']}</li>";
        $html .= "<li>متوسط HbA1c: {$stats['avg_hba1c']}%</li>";
        $html .= "<li>حالات الشفاء التام: {$stats['healed']}</li>";
        if ($stats['amputations'] > 0) $html .= "<li style='color:#dc2626;'>حالات البتر: {$stats['amputations']} ⚠️</li>";
        $html .= "</ul>";
        
        $html .= "<hr><p style='color:#64748b;font-size:11px;'>تم الإنشاء تلقائياً بواسطة نظام عيادة السكري | " . date('Y-m-d H:i:s') . "</p>";
        
        $recipients = $email_config['recipients'];
        break;
        
    default:
        echo "❌ Unknown report type: {$report_type}\n";
        exit(1);
}

// ===== SEND EMAILS =====
if (empty($recipients)) {
    echo "❌ No email recipients configured.\n";
    exit(1);
}

$sent = 0;
$failed = 0;
foreach ($recipients as $email) {
    $email = trim($email);
    if (empty($email)) continue;
    
    if (send_email($email, $subject, $html, $email_config)) {
        echo "✅ Sent to: {$email}\n";
        $sent++;
    } else {
        echo "❌ Failed to send to: {$email}\n";
        $failed++;
    }
}

echo "\n📬 Report complete: {$sent} sent, {$failed} failed\n";
?>