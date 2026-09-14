<?php
/**
 * Patient Auto-Instructions View — Task 6
 * Shows personalized instructions generated for a patient
 */
$page_title = '📋 تعليمات المريض | Patient Instructions';
require_once __DIR__ . '/../../includes/auth_check.php';
require_role(['super_admin', 'admin', 'doctor', 'medical_assistant', 'nurse']);
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/generate.php';

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

$stmt = $mysqli->prepare("SELECT patient_id, full_name, file_number FROM patients WHERE patient_id = ?");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();

if (!$patient) {
    echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ مريض غير موجود</div></div></div></div>";
    require_once __DIR__ . '/../../includes/footer.php'; exit;
}

$instructions = generate_patient_instructions($mysqli, $patient_id);
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="page-title">📋 التعليمات المخصصة</h1>
        <p class="page-subtitle"><?php echo escape_output($patient['full_name']); ?> — 📁 <?php echo escape_output($patient['file_number']); ?></p>
    </div>
    <div class="flex gap-2">
        <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary">🔙 ملف المريض</a>
        <button onclick="window.print()" class="btn btn-primary">🖨️ طباعة</button>
    </div>
</div>

<div class="card no-print mb-4">
    <div class="card-header"><div class="card-title">🤖 كيف تعمل التعليمات التلقائية</div></div>
    <p style="font-size:13px;color:var(--text-muted);">
        يتم اختيار هذه التعليمات تلقائياً بناءً على بيانات المريض المسجلة في النظام: 
        نوع السكري، قراءات السكر التراكمي (HbA1c)، درجة تقييم القدم (Wagner)، وجود جروح، وحالة التدخين.
        يمكنك تخصيص هذه التعليمات من <a href="<?php echo BASE_URL; ?>/modules/auto_instructions/index.php">إدارة التعليمات التلقائية</a>.
    </p>
</div>

<div class="card" style="padding:24px;">
    <div class="page-header" style="text-align:center;border-bottom:2px solid var(--teal);padding-bottom:20px;margin-bottom:24px;">
        <h2 style="font-family:'Cairo',sans-serif;font-size:22px;color:var(--teal);">🏥 مركز سري للغدد الصماء والسكري</h2>
        <h3 style="font-size:16px;margin-top:8px;">📋 تعليمات وإرشادات للمريض</h3>
        <div style="display:flex;justify-content:center;gap:20px;margin-top:12px;font-size:14px;color:var(--text-muted);">
            <span>👤 <?php echo escape_output($patient['full_name']); ?></span>
            <span>📁 <?php echo escape_output($patient['file_number']); ?></span>
            <span>📅 <?php echo date('Y-m-d'); ?></span>
        </div>
    </div>

    <?php if (empty($instructions)): ?>
    <div style="text-align:center;padding:40px;">
        <div style="font-size:48px;margin-bottom:16px;">📋</div>
        <h3 style="color:var(--text-muted);">لا توجد تعليمات متطابقة</h3>
        <p style="color:#94a3b8;margin-top:8px;">
            لم يتم العثور على تعليمات تطابق حالة المريض الحالية.
            أضف تعليمات جديدة من <a href="<?php echo BASE_URL; ?>/modules/auto_instructions/index.php">إدارة التعليمات التلقائية</a>.
        </p>
    </div>
    <?php else: ?>
    <div style="max-width:700px;margin:0 auto;">
        <?php echo render_instructions_html($instructions); ?>
    </div>
    <?php endif; ?>
    
    <div style="text-align:center;margin-top:32px;padding-top:16px;border-top:1px solid var(--border);font-size:12px;color:var(--text-muted);">
        <p>تم إنشاء هذه التعليمات تلقائياً — يرجى مراجعة الطبيب للتأكد من ملاءمتها لحالة المريض</p>
        <p style="margin-top:4px;">© <?php echo date('Y'); ?> Sari Endocrinology & Diabetes Center</p>
    </div>
</div>

</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Print specific CSS
    const style = document.createElement('style');
    style.textContent = `@media print { .sidebar, .navbar, .no-print { display: none !important; } .main-content { margin-right: 0 !important; } }`;
    document.head.appendChild(style);
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
