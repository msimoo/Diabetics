<?php
/**
 * Email Settings — Configure SMTP and scheduled report recipients
 */
$page_title = '📧 إعدادات البريد الإلكتروني | Email Settings';
require_once __DIR__ . '/../../includes/auth_check.php';
if ($_SESSION['role'] !== 'admin') { header('Location: ' . BASE_URL . '/modules/dashboard.php'); exit; }
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Ensure settings table exists
$mysqli->query("CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Save settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'email_enabled' => isset($_POST['email_enabled']) ? 1 : 0,
        'email_smtp_host' => $mysqli->real_escape_string($_POST['email_smtp_host'] ?? ''),
        'email_smtp_port' => (int)($_POST['email_smtp_port'] ?? 587),
        'email_smtp_user' => $mysqli->real_escape_string($_POST['email_smtp_user'] ?? ''),
        'email_smtp_pass' => $mysqli->real_escape_string($_POST['email_smtp_pass'] ?? ''),
        'email_from_email' => $mysqli->real_escape_string($_POST['email_from_email'] ?? ''),
        'email_recipients' => $mysqli->real_escape_string($_POST['email_recipients'] ?? ''),
    ];
    
    foreach ($settings as $key => $value) {
        $stmt = $mysqli->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->bind_param('sss', $key, $value, $value);
        $stmt->execute();
    }
    $success = '✅ تم حفظ الإعدادات بنجاح';
}

// Load current settings
$current = [];
$result = $mysqli->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'email_%'");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $current[$row['setting_key']] = $row['setting_value'];
    }
}
?>
<style>
    .settings-section { background: #fff; border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border); margin-bottom: 1rem; }
    .settings-section h3 { margin: 0 0 1rem 0; color: var(--teal); font-size: 1rem; }
    .field-group { display: flex; gap: 1rem; flex-wrap: wrap; }
    .field-group .field { flex: 1; min-width: 200px; }
    .test-btn { padding: 6px 16px; border-radius: 8px; border: 1px solid var(--teal); color: var(--teal); background: var(--teal-pale); cursor: pointer; font-size: 12px; }
    .test-btn:hover { background: var(--teal); color: #fff; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header">
    <h1 class="page-title">📧 إعدادات البريد الإلكتروني</h1>
    <p class="page-subtitle">Email Settings — SMTP والتقارير المجدولة</p>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<form method="post">
    <!-- SMTP Settings -->
    <div class="settings-section">
        <h3>🔌 إعدادات SMTP</h3>
        <div class="field mb-2">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="email_enabled" value="1" <?php echo !empty($current['email_enabled']) ? 'checked' : ''; ?>>
                <span style="font-weight:600;">تفعيل إرسال البريد الإلكتروني</span>
            </label>
        </div>
        <div class="field-group">
            <div class="field"><label>خادم SMTP</label><input type="text" name="email_smtp_host" value="<?php echo $current['email_smtp_host'] ?? ''; ?>" placeholder="smtp.gmail.com"></div>
            <div class="field"><label>المنفذ</label><input type="number" name="email_smtp_port" value="<?php echo $current['email_smtp_port'] ?? 587; ?>" placeholder="587"></div>
        </div>
        <div class="field-group">
            <div class="field"><label>اسم المستخدم</label><input type="text" name="email_smtp_user" value="<?php echo $current['email_smtp_user'] ?? ''; ?>" placeholder="user@gmail.com"></div>
            <div class="field"><label>كلمة المرور</label><input type="password" name="email_smtp_pass" value="<?php echo $current['email_smtp_pass'] ?? ''; ?>" placeholder="App Password"></div>
        </div>
        <div class="field"><label>بريد المرسل</label><input type="email" name="email_from_email" value="<?php echo $current['email_from_email'] ?? ''; ?>" placeholder="clinic@example.com"></div>
    </div>

    <!-- Recipients -->
    <div class="settings-section">
        <h3>👥 المستلمون</h3>
        <div class="field"><label>عناوين البريد الإلكتروني (افصل بينها بفاصلة)</label>
            <textarea name="email_recipients" rows="3" placeholder="doctor1@example.com, doctor2@example.com"><?php echo $current['email_recipients'] ?? ''; ?></textarea>
        </div>
    </div>

    <!-- Scheduled Reports Info -->
    <div class="settings-section">
        <h3>📅 التقارير المجدولة</h3>
        <p style="font-size:13px;color:var(--text-muted);">لتفعيل التقارير المجدولة، أضف الأوامر التالية إلى Crontab (لينكس) أو Task Scheduler (ويندوز):</p>
        <pre style="background:#1e293b;color:#e2e8f0;padding:12px;border-radius:8px;font-size:12px;direction:ltr;text-align:left;overflow-x:auto;">
# التقرير الأسبوعي — كل إثنين 8 صباحاً
0 8 * * 1 <?php echo PHP_BINARY; ?> <?php echo __DIR__; ?>/../../cron/send_reports.php --type=weekly

# التنبيهات اليومية — كل يوم 7 صباحاً
0 7 * * * <?php echo PHP_BINARY; ?> <?php echo __DIR__; ?>/../../cron/send_reports.php --type=daily

# التقرير الشهري — الأول من كل شهر 9 صباحاً
0 9 1 * * <?php echo PHP_BINARY; ?> <?php echo __DIR__; ?>/../../cron/send_reports.php --type=monthly
        </pre>
        <p style="font-size:12px;color:var(--text-muted);">ملاحظة: PHP's mail() يتطلب إعدادات sendmail أو SMTP في php.ini</p>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="btn btn-primary">💾 حفظ الإعدادات</button>
        <a href="<?php echo BASE_URL; ?>/cron/send_reports.php?type=weekly" class="btn btn-secondary" onclick="return confirm('اختبار إرسال التقرير الأسبوعي الآن؟')">🧪 اختبار الإرسال</a>
    </div>
</form>

</div></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
