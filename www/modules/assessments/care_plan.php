<?php
/**
 * Care Plan Form — خطة العناية المتكاملة
 * Upgraded with wound care, medications, follow-up scheduling, patient education
 */
$page_title = 'خطة العناية | Care Plan';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$stmt = $mysqli->prepare("SELECT v.*, p.full_name, p.file_number FROM visits v JOIN patients p ON v.patient_id = p.patient_id WHERE v.visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();
if (!$visit) { echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ زيارة غير موجودة</div></div></div></div>"; require_once __DIR__ . '/../../includes/footer.php'; exit; }

$stmt = $mysqli->prepare("SELECT * FROM care_plan WHERE visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$cp = $stmt->get_result()->fetch_assoc();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_care'])) {
    $care_type = isset($_POST['care_type']) ? implode(',', $_POST['care_type']) : '';
    $dressing = sanitize_input($_POST['dressing_frequency'] ?? '');
    $offload = isset($_POST['offloading_needed']) ? 1 : 0;
    $offload_type = sanitize_input($_POST['offloading_type'] ?? '');
    $diet = sanitize_input($_POST['dietary_plan'] ?? '');
    $emergency = sanitize_input($_POST['emergency_instructions'] ?? '');
    $wound_care = sanitize_input($_POST['wound_care_instructions'] ?? '');
    $med_instr = sanitize_input($_POST['medication_instructions'] ?? '');
    $follow_up = sanitize_input($_POST['follow_up_frequency'] ?? '');
    $education = sanitize_input($_POST['patient_education_notes'] ?? '');

    if ($cp) {
        $stmt = $mysqli->prepare("UPDATE care_plan SET care_type=?, dressing_frequency=?, offloading_needed=?, offloading_type=?, dietary_plan=?, emergency_instructions=?, wound_care_instructions=?, medication_instructions=?, follow_up_frequency=?, patient_education_notes=? WHERE visit_id=?");
        $stmt->bind_param('ssisssssssi', $care_type, $dressing, $offload, $offload_type, $diet, $emergency, $wound_care, $med_instr, $follow_up, $education, $visit_id);
    } else {
        $stmt = $mysqli->prepare("INSERT INTO care_plan (visit_id, care_type, dressing_frequency, offloading_needed, offloading_type, dietary_plan, emergency_instructions, wound_care_instructions, medication_instructions, follow_up_frequency, patient_education_notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('ississsssss', $visit_id, $care_type, $dressing, $offload, $offload_type, $diet, $emergency, $wound_care, $med_instr, $follow_up, $education);
    }
    if ($stmt->execute()) { $success = '✅ تم حفظ خطة العناية'; $stmt = $mysqli->prepare("SELECT * FROM care_plan WHERE visit_id = ?"); $stmt->bind_param('i', $visit_id); $stmt->execute(); $cp = $stmt->get_result()->fetch_assoc(); }
    else { $error = '❌ خطأ: ' . $stmt->error; }
}

$care_types_full = [
    'تقليم أظافر', 'نظافة قدم', 'ترطيب قدم',
    'تنظيف الجرح', 'تغيير غيار', 'تطهير الجرح',
    'إزالة نسيج ميت', 'أخذ مزرعة', 'تصحيح حذاء طبي',
    'جبيرة قدم', 'تمارين علاجية', 'تدليك القدم'
];

$dressing_options = ['يومياً', 'يوم بعد يوم', 'مرتين بالأسبوع', 'مرة بالأسبوع', 'حسب الحاجة'];
$follow_up_options = ['أسبوعياً', 'كل أسبوعين', 'شهرياً', 'كل 3 شهور', 'كل 6 شهور', 'سنوياً'];
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header"><h1 class="page-title">🩹 خطة العناية المتكاملة</h1><p class="page-subtitle"><?php echo escape_output($visit['full_name']); ?> — زيارة #<?php echo $visit['visit_number']; ?> (<?php echo $visit['visit_date']; ?>)</p></div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="card">
<form method="post"><?php echo csrf_field(); ?>

<!-- Section 1: العناية الأساسية -->
<div class="form-section">
<div class="form-section-title" style="background:linear-gradient(90deg,#0a7e6e,#13a896);">🧴 أنواع العناية المقدمة</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:6px;margin-bottom:10px;">
    <?php $selected_care = explode(',', $cp['care_type'] ?? ''); ?>
    <?php foreach ($care_types_full as $ct): ?>
    <label class="check-item" style="padding:6px 10px;background:var(--bg-input);border-radius:8px;border:1px solid var(--border);cursor:pointer;transition:var(--transition);font-size:13px;">
        <input type="checkbox" name="care_type[]" value="<?php echo $ct; ?>" <?php echo in_array($ct, $selected_care) ? 'checked' : ''; ?>>
        <?php echo $ct; ?>
    </label>
    <?php endforeach; ?>
</div>
</div>

<!-- Section 2: الغيار وتخفيف الضغط -->
<div class="form-section">
<div class="form-section-title" style="background:linear-gradient(90deg,#0ea5e9,#0284c7);">🩹 الغيار وتخفيف الضغط</div>
<div class="form-grid">
<div class="field"><label>عدد مرات الغيار</label><select name="dressing_frequency">
    <option value="">-- اختر --</option>
    <?php foreach ($dressing_options as $f): ?>
    <option value="<?php echo $f; ?>" <?php echo ($cp['dressing_frequency'] ?? '') === $f ? 'selected' : ''; ?>><?php echo $f; ?></option>
    <?php endforeach; ?>
</select></div>
<div class="field"><label>تخفيف الضغط (Off-loading)</label>
<div class="check-item" style="margin-top:8px;">
    <input type="checkbox" name="offloading_needed" value="1" <?php echo ($cp['offloading_needed'] ?? 0) ? 'checked' : ''; ?>>
    <span>مطلوب</span>
</div>
<input type="text" name="offloading_type" placeholder="نوع تخفيف الضغط (حذاء طبي، عكازات...)" value="<?php echo escape_output($cp['offloading_type'] ?? ''); ?>" style="margin-top:8px;">
</div>
</div>
</div>

<!-- Section 3: العناية بالجروح -->
<div class="form-section">
<div class="form-section-title" style="background:linear-gradient(90deg,#8b5cf6,#7c3aed);">🩺 تعليمات العناية بالجروح</div>
<div class="field"><textarea name="wound_care_instructions" rows="4" placeholder="تعليمات تنظيف الجرح، نوع المحلول المستخدم، كيفية تغيير الغيار..."><?php echo escape_output($cp['wound_care_instructions'] ?? ''); ?></textarea></div>
</div>

<!-- Section 4: الأدوية -->
<div class="form-section">
<div class="form-section-title" style="background:linear-gradient(90deg,#10b981,#059669);">💊 التعليمات الدوائية</div>
<div class="field"><textarea name="medication_instructions" rows="4" placeholder="المضادات الحيوية، مسكنات الألم، الأدوية الموضعية، مواعيد الجرعات..."><?php echo escape_output($cp['medication_instructions'] ?? ''); ?></textarea></div>
</div>

<!-- Section 5: النظام الغذائي -->
<div class="form-section">
<div class="form-section-title" style="background:linear-gradient(90deg,#f59e0b,#d97706);">🥗 الخطة الغذائية</div>
<div class="field"><textarea name="dietary_plan" rows="3" placeholder="النظام الغذائي الموصى به، الأطعمة المسموحة والممنوعة، وجبات مقترحة..."><?php echo escape_output($cp['dietary_plan'] ?? ''); ?></textarea></div>
</div>

<!-- Section 6: المتابعة -->
<div class="form-section">
<div class="form-section-title" style="background:linear-gradient(90deg,#f97316,#ea580c);">📅 جدولة المتابعة</div>
<div class="form-grid">
<div class="field"><label>تكرار المتابعة</label><select name="follow_up_frequency">
    <option value="">-- اختر --</option>
    <?php foreach ($follow_up_options as $fu): ?>
    <option value="<?php echo $fu; ?>" <?php echo ($cp['follow_up_frequency'] ?? '') === $fu ? 'selected' : ''; ?>><?php echo $fu; ?></option>
    <?php endforeach; ?>
</select></div>
<div class="field"><label>تعليمات الطوارئ</label><textarea name="emergency_instructions" rows="2" placeholder="ما يجب فعله في حالات الطوارئ"><?php echo escape_output($cp['emergency_instructions'] ?? ''); ?></textarea></div>
</div>
</div>

<!-- Section 7: التوعية -->
<div class="form-section">
<div class="form-section-title" style="background:linear-gradient(90deg,#06b6d4,#0891b2);">📚 توعية وتعليم المريض</div>
<div class="field"><textarea name="patient_education_notes" rows="4" placeholder="نقاط التوعية المقدمة للمريض: العناية بالقدم، علامات الخطر، أهمية متابعة السكر، كيفية فحص القدم يومياً..."><?php echo escape_output($cp['patient_education_notes'] ?? ''); ?></textarea></div>
</div>

<div class="flex justify-between mt-5" style="gap:12px;flex-wrap:wrap;">
    <a href="<?php echo BASE_URL; ?>/modules/visits/view.php?id=<?php echo $visit_id; ?>" class="btn btn-secondary">🔙 رجوع للزيارة</a>
    <div style="display:flex;gap:8px;">
        <button type="submit" name="save_care" class="btn btn-primary" style="padding:10px 32px;">💾 حفظ خطة العناية</button>
    </div>
</div>

</form></div>

</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div>
