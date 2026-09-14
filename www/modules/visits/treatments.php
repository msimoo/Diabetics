<?php
/**
 * Current Treatment Form
 */
$page_title = 'العلاج الحالي | Treatment';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$stmt = $mysqli->prepare("SELECT v.*, p.full_name, p.file_number FROM visits v JOIN patients p ON v.patient_id = p.patient_id WHERE v.visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();
if (!$visit) { echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ زيارة غير موجودة</div></div></div></div>"; require_once __DIR__ . '/../../includes/footer.php'; exit; }

$stmt = $mysqli->prepare("SELECT * FROM treatments WHERE visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$t = $stmt->get_result()->fetch_assoc();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_treatment'])) {
    $treatment_type = isset($_POST['treatment_type']) ? implode(',', $_POST['treatment_type']) : '';
    $oral_meds = sanitize_input($_POST['oral_meds_details'] ?? '');
    $insulin = sanitize_input($_POST['insulin_details'] ?? '');
    $other = sanitize_input($_POST['other_meds'] ?? '');
    $ab_oral = sanitize_input($_POST['antibiotics_oral'] ?? '');
    $ab_iv = sanitize_input($_POST['antibiotics_iv'] ?? '');
    $ab_duration = sanitize_input($_POST['antibiotics_duration'] ?? '');
    $ointments = sanitize_input($_POST['topical_ointments'] ?? '');

    if ($t) {
        $stmt = $mysqli->prepare("UPDATE treatments SET treatment_type=?, oral_meds_details=?, insulin_details=?, other_meds=?, antibiotics_oral=?, antibiotics_iv=?, antibiotics_duration=?, topical_ointments=? WHERE visit_id=?");
        $stmt->bind_param('ssssssssi', $treatment_type, $oral_meds, $insulin, $other, $ab_oral, $ab_iv, $ab_duration, $ointments, $visit_id);
    } else {
        $stmt = $mysqli->prepare("INSERT INTO treatments (visit_id, treatment_type, oral_meds_details, insulin_details, other_meds, antibiotics_oral, antibiotics_iv, antibiotics_duration, topical_ointments) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('issssssss', $visit_id, $treatment_type, $oral_meds, $insulin, $other, $ab_oral, $ab_iv, $ab_duration, $ointments);
    }
    if ($stmt->execute()) { $success = '✅ تم حفظ العلاج'; $stmt = $mysqli->prepare("SELECT * FROM treatments WHERE visit_id = ?"); $stmt->bind_param('i', $visit_id); $stmt->execute(); $t = $stmt->get_result()->fetch_assoc(); }
    else { $error = '❌ خطأ: ' . $stmt->error; }
}

$treatment_types = ['حمية غذائية فقط', 'أدوية فموية (OADs)', 'أنسولين', 'أنسولين + أدوية فموية', 'مضخة أنسولين (Pump)', 'GLP-1 RA'];
$selected_types = explode(',', $t['treatment_type'] ?? '');
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header"><h1 class="page-title">💊 العلاج الحالي</h1><p class="page-subtitle"><?php echo escape_output($visit['full_name']); ?> — زيارة #<?php echo $visit['visit_number']; ?></p></div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<div class="card"><form method="post"><?php echo csrf_field(); ?>
<div class="form-section"><div class="form-section-title">💊 نوع العلاج المستخدم</div>
<div class="check-group"><?php foreach ($treatment_types as $tt): ?>
<label class="check-item"><input type="checkbox" name="treatment_type[]" value="<?php echo $tt; ?>" <?php echo in_array($tt, $selected_types) ? 'checked' : ''; ?>> <?php echo $tt; ?></label>
<?php endforeach; ?></div></div>
<div class="form-grid">
<div class="field"><label>الأدوية الفموية (الأسماء والجرعات)</label><textarea name="oral_meds_details"><?php echo escape_output($t['oral_meds_details'] ?? ''); ?></textarea></div>
<div class="field"><label>الأنسولين (النوع والجرعة)</label><textarea name="insulin_details"><?php echo escape_output($t['insulin_details'] ?? ''); ?></textarea></div>
</div>
<div class="field mt-3"><label>أدوية أخرى (ضغط، شحوم، قلب...)</label><textarea name="other_meds"><?php echo escape_output($t['other_meds'] ?? ''); ?></textarea></div>
<hr class="divider" style="border:none;border-top:1px dashed var(--teal-mid);margin:16px 0;">
<div class="form-section"><div class="form-section-title">🦠 المضادات الحيوية</div></div>
<div class="form-grid">
<div class="field"><label>مضادات حيوية بالفم</label><textarea name="antibiotics_oral"><?php echo escape_output($t['antibiotics_oral'] ?? ''); ?></textarea></div>
<div class="field"><label>مضادات حيوية بالوريد</label><textarea name="antibiotics_iv"><?php echo escape_output($t['antibiotics_iv'] ?? ''); ?></textarea></div>
</div>
<div class="form-grid mt-3">
<div class="field"><label>مدة المضاد الحيوي</label><input type="text" name="antibiotics_duration" value="<?php echo escape_output($t['antibiotics_duration'] ?? ''); ?>" placeholder="مثال: 7 أيام"></div>
<div class="field"><label>المراهم الموضعية</label><textarea name="topical_ointments"><?php echo escape_output($t['topical_ointments'] ?? ''); ?></textarea></div>
</div>
<div class="flex justify-between mt-5">
<a href="<?php echo BASE_URL; ?>/modules/visits/view.php?id=<?php echo $visit_id; ?>" class="btn btn-secondary">🔙 رجوع</a>
<button type="submit" name="save_treatment" class="btn btn-primary">💾 حفظ</button>
</div>
</form></div>
</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div>
