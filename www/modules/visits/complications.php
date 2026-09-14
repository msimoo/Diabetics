<?php
/**
 * Complications Form
 */
$page_title = 'المضاعفات | Complications';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$stmt = $mysqli->prepare("SELECT patient_id, full_name, file_number FROM patients WHERE patient_id = ?");
$stmt->bind_param('i', $patient_id); $stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) { echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ مريض غير موجود</div></div></div></div>"; require_once __DIR__ . '/../../includes/footer.php'; exit; }

$stmt = $mysqli->prepare("SELECT * FROM complications WHERE patient_id = ?");
$stmt->bind_param('i', $patient_id); $stmt->execute();
$comp = $stmt->get_result()->fetch_assoc();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_complications'])) {
    $retinopathy = isset($_POST['has_retinopathy']) ? 1 : 0;
    $nephropathy = isset($_POST['has_nephropathy']) ? 1 : 0;
    $neuropathy = isset($_POST['has_neuropathy']) ? 1 : 0;
    $cad = isset($_POST['has_cad']) ? 1 : 0;
    $cva = isset($_POST['has_cva']) ? 1 : 0;
    $pad = isset($_POST['has_pad']) ? 1 : 0;
    $foot = sanitize_input($_POST['diabetic_foot'] ?? '');
    $hypo = sanitize_input($_POST['hypoglycemia_severity'] ?? '');
    $notes = sanitize_input($_POST['notes'] ?? '');

    if ($comp) {
        $stmt = $mysqli->prepare("UPDATE complications SET has_retinopathy=?, has_nephropathy=?, has_neuropathy=?, has_cad=?, has_cva=?, has_pad=?, diabetic_foot=?, hypoglycemia_severity=?, notes=? WHERE patient_id=?");
        $stmt->bind_param('iiiiissssi', $retinopathy, $nephropathy, $neuropathy, $cad, $cva, $pad, $foot, $hypo, $notes, $patient_id);
    } else {
        $stmt = $mysqli->prepare("INSERT INTO complications (patient_id, has_retinopathy, has_nephropathy, has_neuropathy, has_cad, has_cva, has_pad, diabetic_foot, hypoglycemia_severity, notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('iiiiiiisss', $patient_id, $retinopathy, $nephropathy, $neuropathy, $cad, $cva, $pad, $foot, $hypo, $notes);
    }
    if ($stmt->execute()) { $success = '✅ تم حفظ المضاعفات'; $stmt = $mysqli->prepare("SELECT * FROM complications WHERE patient_id = ?"); $stmt->bind_param('i', $patient_id); $stmt->execute(); $comp = $stmt->get_result()->fetch_assoc(); }
    else { $error = '❌ خطأ: ' . $stmt->error; }
}
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header"><h1 class="page-title">⚕️ مضاعفات السكري</h1><p class="page-subtitle"><?php echo escape_output($patient['full_name']); ?> — 📁 <?php echo escape_output($patient['file_number']); ?></p></div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<div class="card">
<form method="post">
    <?php echo csrf_field(); ?>
    <div class="form-section">
        <div class="form-section-title">🩸 المضاعفات الوعائية الدقيقة</div>
        <div class="check-group" style="flex-direction:column;">
            <label class="check-item"><input type="checkbox" name="has_retinopathy" <?php echo ($comp['has_retinopathy'] ?? 0) ? 'checked' : ''; ?>> اعتلال الشبكية (Retinopathy)</label>
            <label class="check-item"><input type="checkbox" name="has_nephropathy" <?php echo ($comp['has_nephropathy'] ?? 0) ? 'checked' : ''; ?>> اعتلال الكلى (Nephropathy)</label>
            <label class="check-item"><input type="checkbox" name="has_neuropathy" <?php echo ($comp['has_neuropathy'] ?? 0) ? 'checked' : ''; ?>> اعتلال الأعصاب (Neuropathy)</label>
        </div>
    </div>
    <div class="form-section">
        <div class="form-section-title">🫀 المضاعفات الوعائية الكبيرة</div>
        <div class="check-group" style="flex-direction:column;">
            <label class="check-item"><input type="checkbox" name="has_cad" <?php echo ($comp['has_cad'] ?? 0) ? 'checked' : ''; ?>> أمراض القلب التاجية (CAD)</label>
            <label class="check-item"><input type="checkbox" name="has_cva" <?php echo ($comp['has_cva'] ?? 0) ? 'checked' : ''; ?>> الأوعية الدماغية (CVA/TIA)</label>
            <label class="check-item"><input type="checkbox" name="has_pad" <?php echo ($comp['has_pad'] ?? 0) ? 'checked' : ''; ?>> الأوعية الطرفية (PAD)</label>
        </div>
    </div>
    <div class="form-section">
        <div class="form-section-title">🦶 مضاعفات القدم</div>
        <div class="form-grid">
            <div class="field"><label>قدم السكري</label>
                <div class="radio-group">
                    <?php foreach (['لا يوجد', 'نعم', 'تاريخ سابق'] as $opt): ?>
                    <label class="radio-item"><input type="radio" name="diabetic_foot" value="<?php echo $opt; ?>" <?php echo ($comp['diabetic_foot'] ?? '') === $opt ? 'checked' : ''; ?>> <?php echo $opt; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="field"><label>نوبات انخفاض السكر (Hypoglycemia)</label>
                <div class="radio-group">
                    <?php foreach (['لا', 'نادرة', 'متكررة'] as $opt): ?>
                    <label class="radio-item"><input type="radio" name="hypoglycemia_severity" value="<?php echo $opt; ?>" <?php echo ($comp['hypoglycemia_severity'] ?? '') === $opt ? 'checked' : ''; ?>> <?php echo $opt; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="field"><label>ملاحظات إضافية</label><textarea name="notes"><?php echo escape_output($comp['notes'] ?? ''); ?></textarea></div>
    <div class="flex justify-between mt-5">
        <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary">🔙 رجوع</a>
        <button type="submit" name="save_complications" class="btn btn-primary">💾 حفظ</button>
    </div>
</form>
</div>
</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div>
