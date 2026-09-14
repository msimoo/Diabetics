<?php
/**
 * System Settings Page
 */
$page_title = '⚙️ الإعدادات | Settings';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Only admin can access settings
require_role('admin');

// Ensure settings table exists
$mysqli->query("CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Ensure audit_log table exists
$mysqli->query("CREATE TABLE IF NOT EXISTS audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function log_audit($mysqli, $user_id, $action, $entity_type = null, $entity_id = null, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $mysqli->prepare("INSERT INTO audit_log (user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ississ', $user_id, $action, $entity_type, $entity_id, $details, $ip);
    $stmt->execute();
}

function get_setting($mysqli, $key, $default = '') {
    $stmt = $mysqli->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r ? $r['setting_value'] : $default;
}

function update_setting($mysqli, $key, $value) {
    $stmt = $mysqli->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param('sss', $key, $value, $value);
    $stmt->execute();
}

$message = '';
$message_type = '';
$active_tab = $_GET['tab'] ?? 'general';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $message = '❌ طلب غير مصرح به';
        $message_type = 'danger';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save_settings') {
            update_setting($mysqli, 'site_name_ar', sanitize_input($_POST['site_name_ar'] ?? SITE_NAME));
            update_setting($mysqli, 'site_name_en', sanitize_input($_POST['site_name_en'] ?? SITE_NAME_EN));
            update_setting($mysqli, 'clinic_address', sanitize_input($_POST['clinic_address'] ?? ''));
            update_setting($mysqli, 'clinic_phone', sanitize_input($_POST['clinic_phone'] ?? ''));
            update_setting($mysqli, 'clinic_email', sanitize_input($_POST['clinic_email'] ?? ''));
            update_setting($mysqli, 'session_timeout', (int)($_POST['session_timeout'] ?? 1800));
            update_setting($mysqli, 'items_per_page', (int)($_POST['items_per_page'] ?? 20));
            update_setting($mysqli, 'enable_notifications', $_POST['enable_notifications'] ?? '1');
            update_setting($mysqli, 'default_appointment_duration', (int)($_POST['default_appointment_duration'] ?? 30));
            log_audit($mysqli, $_SESSION['user_id'], 'update_settings', 'system', null, 'Updated system settings');
            $message = '✅ تم حفظ الإعدادات بنجاح';
            $message_type = 'success';
        } elseif ($action === 'backup_db') {
            // Create DB backup
            $backup_dir = __DIR__ . '/../../backups';
            if (!is_dir($backup_dir)) mkdir($backup_dir, 0755, true);
            
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $filepath = $backup_dir . '/' . $filename;
            
            // Use mysqldump via PHP
            $DB_HOST = 'localhost';
            $DB_USER = 'root';
            $DB_PASS = '';
            $DB_NAME = 'clinic_diabetes';
            
            $cmd = "mysqldump --host=$DB_HOST --user=$DB_USER --password=$DB_PASS --routines --single-transaction $DB_NAME > " . escapeshellarg($filepath) . " 2>&1";
            $output = shell_exec($cmd);
            
            if (file_exists($filepath) && filesize($filepath) > 0) {
                log_audit($mysqli, $_SESSION['user_id'], 'backup_database', 'system', null, "Created backup: $filename");
                $message = "✅ تم إنشاء نسخة احتياطية: $filename (" . round(filesize($filepath) / 1024) . " KB)";
                $message_type = 'success';
            } else {
                $message = '❌ فشل إنشاء النسخة الاحتياطية: ' . ($output ?: 'خطأ غير معروف');
                $message_type = 'danger';
            }
        } elseif ($action === 'clear_logs') {
            $before = $mysqli->query("SELECT COUNT(*) as cnt FROM audit_log")->fetch_assoc()['cnt'];
            $mysqli->query("DELETE FROM audit_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
            $after = $mysqli->query("SELECT COUNT(*) as cnt FROM audit_log")->fetch_assoc()['cnt'];
            log_audit($mysqli, $_SESSION['user_id'], 'clear_audit_logs', 'system', null, "Cleared logs older than 90 days ($before → $after)");
            $message = "✅ تم تنظيف السجلات (" . ($before - $after) . " سجل قديم)";
            $message_type = 'success';
        }
    }
}

// Load current settings
$site_name_ar = get_setting($mysqli, 'site_name_ar', 'مركز سري للغدد الصماء والسكري');
$site_name_en = get_setting($mysqli, 'site_name_en', 'Sari Endocrinology & Diabetes Center');
$clinic_address = get_setting($mysqli, 'clinic_address', '');
$clinic_phone = get_setting($mysqli, 'clinic_phone', '');
$clinic_email = get_setting($mysqli, 'clinic_email', '');
$session_timeout = get_setting($mysqli, 'session_timeout', '1800');
$items_per_page = get_setting($mysqli, 'items_per_page', '20');
$enable_notifications = get_setting($mysqli, 'enable_notifications', '1');
$default_appointment_duration = get_setting($mysqli, 'default_appointment_duration', '30');

// Fetch audit logs
$page = (int)($_GET['log_page'] ?? 1);
$per_page = 30;
$offset = ($page - 1) * $per_page;
$total_logs = $mysqli->query("SELECT COUNT(*) as cnt FROM audit_log")->fetch_assoc()['cnt'];
$total_pages = ceil($total_logs / $per_page);
$logs = $mysqli->query("
    SELECT al.*, u.full_name 
    FROM audit_log al 
    LEFT JOIN users u ON al.user_id = u.user_id 
    ORDER BY al.created_at DESC 
    LIMIT $per_page OFFSET $offset
");

// Get backup files
$backup_dir = __DIR__ . '/../../backups';
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $f) {
        if (pathinfo($f, PATHINFO_EXTENSION) === 'sql') {
            $backups[] = [
                'name' => $f,
                'size' => filesize($backup_dir . '/' . $f),
                'date' => date('Y-m-d H:i:s', filemtime($backup_dir . '/' . $f))
            ];
        }
    }
}

// System info
$db_size = $mysqli->query("SELECT ROUND(SUM(data_length + index_length) / 1024) as size_kb FROM information_schema.tables WHERE table_schema = 'clinic_diabetes'")->fetch_assoc()['size_kb'] ?? 0;
$table_count = $mysqli->query("SELECT COUNT(*) as cnt FROM information_schema.tables WHERE table_schema = 'clinic_diabetes'")->fetch_assoc()['cnt'] ?? 0;
$patient_count = $mysqli->query("SELECT COUNT(*) as cnt FROM patients")->fetch_assoc()['cnt'];
$visit_count = $mysqli->query("SELECT COUNT(*) as cnt FROM visits")->fetch_assoc()['cnt'];
$user_count = $mysqli->query("SELECT COUNT(*) as cnt FROM users WHERE is_active = 1")->fetch_assoc()['cnt'];
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>"><?php echo $message; ?></div>
            <?php endif; ?>

            <div class="page-header">
                <h1 class="page-title">⚙️ الإعدادات</h1>
                <p class="page-subtitle">إعدادات النظام وإدارة النسخ الاحتياطية وسجلات التدقيق</p>
            </div>

            <!-- Tabs -->
            <div class="flex gap-2 mb-4 flex-wrap">
                <a href="?tab=general" class="btn <?php echo $active_tab === 'general' ? 'btn-primary' : 'btn-secondary'; ?>">⚙️ عام</a>
                <a href="?tab=backup" class="btn <?php echo $active_tab === 'backup' ? 'btn-primary' : 'btn-secondary'; ?>">💾 نسخ احتياطي</a>
                <a href="?tab=logs" class="btn <?php echo $active_tab === 'logs' ? 'btn-primary' : 'btn-secondary'; ?>">📋 سجل التدقيق</a>
                <a href="?tab=system" class="btn <?php echo $active_tab === 'system' ? 'btn-primary' : 'btn-secondary'; ?>">🖥️ معلومات النظام</a>
            </div>

            <!-- General Settings -->
            <?php if ($active_tab === 'general'): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title">⚙️ الإعدادات العامة</div>
                </div>
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="save_settings">

                    <div class="form-grid">
                        <div class="field">
                            <label>اسم المركز (عربي)</label>
                            <input type="text" name="site_name_ar" value="<?php echo escape_output($site_name_ar); ?>">
                        </div>
                        <div class="field">
                            <label>اسم المركز (إنجليزي)</label>
                            <input type="text" name="site_name_en" value="<?php echo escape_output($site_name_en); ?>">
                        </div>
                        <div class="field">
                            <label>العنوان</label>
                            <input type="text" name="clinic_address" value="<?php echo escape_output($clinic_address); ?>" placeholder="عنوان المركز">
                        </div>
                        <div class="field">
                            <label>رقم الهاتف</label>
                            <input type="text" name="clinic_phone" value="<?php echo escape_output($clinic_phone); ?>" placeholder="رقم الهاتف">
                        </div>
                        <div class="field">
                            <label>البريد الإلكتروني</label>
                            <input type="email" name="clinic_email" value="<?php echo escape_output($clinic_email); ?>" placeholder="البريد الإلكتروني">
                        </div>
                        <div class="field">
                            <label>مدة صلاحية الجلسة (ثواني)</label>
                            <input type="number" name="session_timeout" value="<?php echo $session_timeout; ?>" min="300" max="86400">
                        </div>
                        <div class="field">
                            <label>عدد العناصر لكل صفحة</label>
                            <input type="number" name="items_per_page" value="<?php echo $items_per_page; ?>" min="10" max="100">
                        </div>
                        <div class="field">
                            <label>مدة الموعد الافتراضية (دقائق)</label>
                            <input type="number" name="default_appointment_duration" value="<?php echo $default_appointment_duration; ?>" min="5" max="120">
                        </div>
                        <div class="field">
                            <label>تفعيل الإشعارات</label>
                            <select name="enable_notifications">
                                <option value="1" <?php echo $enable_notifications === '1' ? 'selected' : ''; ?>>نعم</option>
                                <option value="0" <?php echo $enable_notifications === '0' ? 'selected' : ''; ?>>لا</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-4">💾 حفظ الإعدادات</button>
                </form>
            </div>

            <!-- Backup Tab -->
            <?php elseif ($active_tab === 'backup'): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <div class="card-title">💾 النسخ الاحتياطي</div>
                </div>
                <form method="post" class="mb-4">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="backup_db">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('إنشاء نسخة احتياطية الآن؟')">💾 إنشاء نسخة احتياطية</button>
                </form>

                <h3 class="card-title" style="margin-bottom:12px;">النسخ السابقة</h3>
                <?php if (empty($backups)): ?>
                    <p class="text-muted">لا توجد نسخ احتياطية بعد</p>
                <?php else: ?>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>اسم الملف</th>
                                    <th>الحجم</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $b): ?>
                                <tr>
                                    <td><?php echo $b['name']; ?></td>
                                    <td><?php echo round($b['size'] / 1024, 1); ?> KB</td>
                                    <td><?php echo $b['date']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Audit Logs Tab -->
            <?php elseif ($active_tab === 'logs'): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📋 سجل التدقيق (<?php echo $total_logs; ?>)</div>
                    <form method="post" onsubmit="return confirm('حذف السجلات الأقدم من 90 يوماً؟')">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="clear_logs">
                        <button type="submit" class="btn btn-sm btn-danger">🧹 تنظيف</button>
                    </form>
                </div>
                <?php if ($logs->num_rows === 0): ?>
                    <p class="text-muted text-center" style="padding:20px;">لا توجد سجلات</p>
                <?php else: ?>
                    <div class="table-container">
                        <table style="font-size:13px;">
                            <thead>
                                <tr>
                                    <th>التاريخ</th>
                                    <th>المستخدم</th>
                                    <th>الإجراء</th>
                                    <th>النوع</th>
                                    <th>التفاصيل</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($log = $logs->fetch_assoc()): ?>
                                <tr>
                                    <td style="white-space:nowrap;"><?php echo $log['created_at']; ?></td>
                                    <td><?php echo escape_output($log['full_name'] ?: '—'); ?></td>
                                    <td><span class="badge badge-info"><?php echo escape_output($log['action']); ?></span></td>
                                    <td><?php echo $log['entity_type'] ?: '—'; ?></td>
                                    <td style="max-width:250px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo escape_output($log['details'] ?: '—'); ?></td>
                                    <td style="font-size:11px;"><?php echo $log['ip_address'] ?: '—'; ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($total_pages > 1): ?>
                    <div class="flex gap-2 mt-3 justify-center">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?tab=logs&log_page=<?php echo $i; ?>" class="btn btn-sm <?php echo $i === $page ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- System Info Tab -->
            <?php elseif ($active_tab === 'system'): ?>
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🖥️ معلومات النظام</div>
                </div>
                <div class="form-grid">
                    <div class="field">
                        <label>إصدار PHP</label>
                        <input type="text" value="<?php echo phpversion(); ?>" readonly>
                    </div>
                    <div class="field">
                        <label>قاعدة البيانات</label>
                        <input type="text" value="MySQL <?php echo $mysqli->server_info; ?>" readonly>
                    </div>
                    <div class="field">
                        <label>حجم قاعدة البيانات</label>
                        <input type="text" value="<?php echo $db_size; ?> KB" readonly>
                    </div>
                    <div class="field">
                        <label>عدد الجداول</label>
                        <input type="text" value="<?php echo $table_count; ?>" readonly>
                    </div>
                    <div class="field">
                        <label>إجمالي المرضى</label>
                        <input type="text" value="<?php echo $patient_count; ?>" readonly>
                    </div>
                    <div class="field">
                        <label>إجمالي الزيارات</label>
                        <input type="text" value="<?php echo $visit_count; ?>" readonly>
                    </div>
                    <div class="field">
                        <label>المستخدمين النشطين</label>
                        <input type="text" value="<?php echo $user_count; ?>" readonly>
                    </div>
                    <div class="field">
                        <label>نظام التشغيل</label>
                        <input type="text" value="<?php echo PHP_OS; ?>" readonly>
                    </div>
                    <div class="field">
                        <label>مسار uploads</label>
                        <input type="text" value="<?php echo UPLOAD_PATH; ?>" readonly>
                    </div>
                    <div class="field">
                        <label>إصدار النظام</label>
                        <input type="text" value="2.0.0" readonly>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
