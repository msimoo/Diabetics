<?php
/**
 * Notification & Alert System
 * In-app notifications for high-risk patients, missed visits, and clinical alerts
 */
$page_title = 'الإشعارات والتنبيهات | Notifications';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Ensure notifications table exists
$mysqli->query("CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('critical', 'warning', 'info', 'success') NOT NULL DEFAULT 'info',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_dismissed TINYINT(1) NOT NULL DEFAULT 0,
    related_url VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Generate notifications from current data
function generate_notifications($mysqli) {
    // Only generate if no recent notifications (last 6 hours)
    $recent = $mysqli->query("SELECT COUNT(*) as c FROM notifications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 HOUR)")->fetch_assoc()['c'];
    if ($recent > 0) return;

    // Critical: High Wagner + no recent visit
    $critical = $mysqli->query("SELECT p.patient_id, p.full_name, p.file_number, fa.wagner_grade,
        (SELECT MAX(visit_date) FROM visits v WHERE v.patient_id = p.patient_id) as last_visit
        FROM patients p
        JOIN visits v ON p.patient_id = v.patient_id
        JOIN foot_assessments fa ON v.visit_id = fa.visit_id
        WHERE p.is_active = 1 AND fa.wagner_grade >= 3
        GROUP BY p.patient_id");
    while ($row = $critical->fetch_assoc()) {
        $days_since = $row['last_visit'] ? floor((time() - strtotime($row['last_visit'])) / 86400) : 0;
        $msg = "مريض {$row['full_name']} (ملف {$row['file_number']}) — درجة Wagner {$row['wagner_grade']}. ";
        $msg .= $days_since > 30 ? "آخر زيارة منذ {$days_since} يوم — مطلوب تدخل فوري!" : "يحتاج متابعة عاجلة.";
        $stmt = $mysqli->prepare("INSERT IGNORE INTO notifications (patient_id, title, message, type, related_url) VALUES (?, ?, ?, 'critical', ?)");
        $title = "🆘 حالة حرجة - Wagner {$row['wagner_grade']}";
        $url = BASE_URL . "/modules/patients/view.php?id={$row['patient_id']}";
        $stmt->bind_param('isss', $row['patient_id'], $title, $msg, $url);
        $stmt->execute();
    }

    // Warning: No visit in 60+ days
    $no_visit = $mysqli->query("SELECT p.patient_id, p.full_name, p.file_number, p.phone_primary,
        DATEDIFF(CURDATE(), (SELECT MAX(visit_date) FROM visits v WHERE v.patient_id = p.patient_id)) as gap_days
        FROM patients p WHERE p.is_active = 1 AND (SELECT MAX(visit_date) FROM visits v WHERE v.patient_id = p.patient_id) IS NOT NULL
        HAVING gap_days > 60");
    while ($row = $no_visit->fetch_assoc()) {
        $msg = "مريض {$row['full_name']} لم يزر العيادة منذ {$row['gap_days']} يوم. ";
        $msg .= "رقم الهاتف: {$row['phone_primary']}";
        $stmt = $mysqli->prepare("INSERT IGNORE INTO notifications (patient_id, title, message, type, related_url) VALUES (?, ?, ?, 'warning', ?)");
        $title = "⚠️ انقطاع عن المتابعة - {$row['gap_days']} يوم";
        $url = BASE_URL . "/modules/patients/view.php?id={$row['patient_id']}";
        $stmt->bind_param('isss', $row['patient_id'], $title, $msg, $url);
        $stmt->execute();
    }

    // Info: HbA1c > 9%
    $high_hba1c = $mysqli->query("SELECT DISTINCT p.patient_id, p.full_name, p.file_number, bs.hba1c_value
        FROM patients p JOIN visits v ON p.patient_id = v.patient_id
        JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
        WHERE p.is_active = 1 AND bs.hba1c_value > 9
        ORDER BY bs.hba1c_value DESC LIMIT 20");
    while ($row = $high_hba1c->fetch_assoc()) {
        $msg = "مريض {$row['full_name']} — سكر تراكمي {$row['hba1c_value']}%. ";
        $msg .= "يحتاج تعديل خطة العلاج وتحكم أفضل في السكر.";
        $stmt = $mysqli->prepare("INSERT IGNORE INTO notifications (patient_id, title, message, type, related_url) VALUES (?, ?, ?, 'info', ?)");
        $title = "📈 HbA1c مرتفع - {$row['hba1c_value']}%";
        $url = BASE_URL . "/modules/patients/view.php?id={$row['patient_id']}";
        $stmt->bind_param('isss', $row['patient_id'], $title, $msg, $url);
        $stmt->execute();
    }

    // Success: Recently healed patients
    $healed = $mysqli->query("SELECT DISTINCT p.patient_id, p.full_name, p.file_number, o.healing_date
        FROM patients p JOIN visits v ON p.patient_id = v.patient_id
        JOIN outcomes o ON v.visit_id = o.visit_id
        WHERE p.is_active = 1 AND o.improvement_percentage = 100
        AND o.healing_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        LIMIT 20");
    while ($row = $healed->fetch_assoc()) {
        $msg = "مريض {$row['full_name']} — تم الشفاء التام! تاريخ الشفاء: {$row['healing_date']}";
        $stmt = $mysqli->prepare("INSERT IGNORE INTO notifications (patient_id, title, message, type, related_url) VALUES (?, ?, ?, 'success', ?)");
        $title = "✅ تم الشفاء - {$row['full_name']}";
        $url = BASE_URL . "/modules/patients/view.php?id={$row['patient_id']}";
        $stmt->bind_param('isss', $row['patient_id'], $title, $msg, $url);
        $stmt->execute();
    }
}

// Generate notifications if needed
// Also fix: $no_visit uses file_name instead of file_number
generate_notifications($mysqli);

// Handle dismiss action
if (isset($_GET['dismiss'])) {
    $id = (int)$_GET['dismiss'];
    $mysqli->query("UPDATE notifications SET is_dismissed = 1 WHERE notification_id = $id");
    redirect(BASE_URL . '/modules/analytics/notifications.php');
}
if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $mysqli->query("UPDATE notifications SET is_read = 1 WHERE notification_id = $id");
    redirect(BASE_URL . '/modules/analytics/notifications.php');
}
if (isset($_GET['mark_all_read'])) {
    $mysqli->query("UPDATE notifications SET is_read = 1 WHERE is_read = 0");
    redirect(BASE_URL . '/modules/analytics/notifications.php');
}

// Filter
$type_filter = $_GET['type'] ?? 'all';
$where = "WHERE n.is_dismissed = 0";
if ($type_filter !== 'all') {
    $where .= " AND n.type = '" . $mysqli->real_escape_string($type_filter) . "'";
}

// Get notifications
$notifications = $mysqli->query("SELECT n.*, p.full_name, p.file_number 
    FROM notifications n 
    LEFT JOIN patients p ON n.patient_id = p.patient_id
    $where
    ORDER BY n.created_at DESC LIMIT 100");

$unread_count = $mysqli->query("SELECT COUNT(*) as c FROM notifications WHERE is_read = 0 AND is_dismissed = 0")->fetch_assoc()['c'];

// Counts by type
$counts = [
    'critical' => $mysqli->query("SELECT COUNT(*) as c FROM notifications WHERE type='critical' AND is_dismissed=0")->fetch_assoc()['c'],
    'warning' => $mysqli->query("SELECT COUNT(*) as c FROM notifications WHERE type='warning' AND is_dismissed=0")->fetch_assoc()['c'],
    'info' => $mysqli->query("SELECT COUNT(*) as c FROM notifications WHERE type='info' AND is_dismissed=0")->fetch_assoc()['c'],
    'success' => $mysqli->query("SELECT COUNT(*) as c FROM notifications WHERE type='success' AND is_dismissed=0")->fetch_assoc()['c'],
];
?>
<style>
    .notif-critical { border-right: 4px solid #dc2626; background: #fef2f2; }
    .notif-warning { border-right: 4px solid #f59e0b; background: #fffbeb; }
    .notif-info { border-right: 4px solid #3b82f6; background: #eff6ff; }
    .notif-success { border-right: 4px solid #10b981; background: #ecfdf5; }
    .notif-item {
        padding: 1rem 1.2rem; border-radius: 10px; margin-bottom: 0.8rem;
        transition: all 0.3s; cursor: pointer;
    }
    .notif-item:hover { transform: translateX(-4px); }
    .notif-item.unread { font-weight: 600; }
    .notif-item .notif-time { font-size: 11px; color: #94a3b8; }
    .notif-item .notif-title { font-size: 14px; margin-bottom: 0.3rem; }
    .notif-item .notif-msg { font-size: 13px; color: #555; line-height: 1.6; }
    .badge-critical { background: #dc2626; color: white; }
    .badge-warning { background: #f59e0b; color: white; }
    .badge-info { background: #3b82f6; color: white; }
    .badge-success { background: #10b981; color: white; }
    .unread-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-left: 6px; }
</style>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="page-header flex flex-wrap gap-3 items-center justify-between">
    <div>
        <h1 class="page-title">🔔 الإشعارات والتنبيهات</h1>
        <p class="page-subtitle">Notification Center — <?php echo $unread_count; ?> غير مقروء</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="?mark_all_read=1" class="btn btn-sm btn-secondary">✔️ تعيين الكل مقروء</a>
        <a href="?type=all" class="btn btn-sm <?php echo $type_filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">الكل (<?php echo array_sum($counts); ?>)</a>
        <a href="?type=critical" class="btn btn-sm <?php echo $type_filter === 'critical' ? 'btn-primary' : 'btn-secondary'; ?>" style="color:#dc2626;">🔴 حرج (<?php echo $counts['critical']; ?>)</a>
        <a href="?type=warning" class="btn btn-sm <?php echo $type_filter === 'warning' ? 'btn-primary' : 'btn-secondary'; ?>" style="color:#f59e0b;">🟠 إنذار (<?php echo $counts['warning']; ?>)</a>
        <a href="?type=info" class="btn btn-sm <?php echo $type_filter === 'info' ? 'btn-primary' : 'btn-secondary'; ?>">ℹ️ معلومات (<?php echo $counts['info']; ?>)</a>
        <a href="?type=success" class="btn btn-sm <?php echo $type_filter === 'success' ? 'btn-primary' : 'btn-secondary'; ?>" style="color:#10b981;">✅ نجاح (<?php echo $counts['success']; ?>)</a>
    </div>
</div>

<div class="card">
    <div class="card-header"><div class="card-title">📋 قائمة الإشعارات</div></div>
    <div style="padding:0.5rem 1.5rem 1.5rem;">
        <?php if ($notifications->num_rows === 0): ?>
        <div style="text-align:center;padding:40px 20px;color:#94a3b8;">
            <div style="font-size:3rem;margin-bottom:0.5rem;">🔔</div>
            <h3>لا توجد إشعارات</h3>
            <p>لا توجد إشعارات جديدة في الوقت الحالي. سيتم إنشاء الإشعارات تلقائياً عند توفر بيانات جديدة.</p>
        </div>
        <?php else: ?>
            <?php while ($n = $notifications->fetch_assoc()): ?>
            <div class="notif-item notif-<?php echo $n['type']; ?> <?php echo !$n['is_read'] ? 'unread' : ''; ?>"
                 onclick="window.location.href='<?php echo $n['related_url'] ?: '#'; ?>'">
                <div class="flex justify-between items-start">
                    <div class="notif-title">
                        <?php if (!$n['is_read']): ?><span class="unread-dot" style="background:<?php 
                            echo $n['type'] === 'critical' ? '#dc2626' : ($n['type'] === 'warning' ? '#f59e0b' : ($n['type'] === 'success' ? '#10b981' : '#3b82f6')); ?>;"></span><?php endif; ?>
                        <?php echo escape_output($n['title']); ?>
                    </div>
                    <div class="flex gap-1 no-print">
                        <?php if (!$n['is_read']): ?>
                        <a href="?mark_read=<?php echo $n['notification_id']; ?>" class="btn btn-sm btn-secondary" onclick="event.stopPropagation();" title="تعيين مقروء">✔️</a>
                        <?php endif; ?>
                        <a href="?dismiss=<?php echo $n['notification_id']; ?>" class="btn btn-sm btn-secondary" onclick="event.stopPropagation();" title="تجاهل">✕</a>
                    </div>
                </div>
                <div class="notif-msg"><?php echo escape_output($n['message']); ?></div>
                <div class="flex justify-between items-center mt-1">
                    <span class="notif-time">🕐 <?php echo time_ago($n['created_at']); ?></span>
                    <?php if ($n['full_name']): ?>
                    <span style="font-size:11px;color:var(--teal);">👤 <?php echo escape_output($n['full_name']); ?> (📁 <?php echo escape_output($n['file_number']); ?>)</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        <?php endif; ?>
    </div>
</div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div></div>