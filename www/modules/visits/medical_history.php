<?php
/**
 * Medical History Form
 */
$page_title = 'التاريخ الطبي | Medical History';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/helpers_lookup.php';

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

$stmt = $mysqli->prepare("SELECT patient_id, full_name, file_number FROM patients WHERE patient_id = ?");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$patient = $stmt->get_result()->fetch_assoc();
if (!$patient) { echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ مريض غير موجود</div></div></div></div>"; require_once __DIR__ . '/../../includes/footer.php'; exit; }

$stmt = $mysqli->prepare("SELECT * FROM medical_history WHERE patient_id = ?");
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$history = $stmt->get_result()->fetch_assoc();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_history'])) {
    $diabetes_type = sanitize_input($_POST['diabetes_type'] ?? '');
    $diagnosis_year = $_POST['diagnosis_year'] ?? null;
    $duration = $_POST['duration_years'] ?? null;
    $diagnosis_method = isset($_POST['diagnosis_method']) ? implode(',', $_POST['diagnosis_method']) : '';
    $companion = sanitize_input($_POST['companion_name'] ?? '');
    $family_history = isset($_POST['family_history']) ? implode(',', $_POST['family_history']) : '';
    $smoking = sanitize_input($_POST['smoking_status'] ?? '');
    $activity = sanitize_input($_POST['physical_activity'] ?? '');
    $hypertension = isset($_POST['has_hypertension']) ? 1 : 0;
    $kidney = isset($_POST['has_kidney_disease']) ? 1 : 0;
    $retinopathy = isset($_POST['has_eye_retinopathy']) ? 1 : 0;
    $cataract = isset($_POST['has_eye_cataract']) ? 1 : 0;
    $glaucoma = isset($_POST['has_eye_glaucoma']) ? 1 : 0;
    $other = sanitize_input($_POST['other_chronic_diseases'] ?? '');

    if ($history) {
        $stmt = $mysqli->prepare("UPDATE medical_history SET diabetes_type=?, diagnosis_year=?, duration_years=?, diagnosis_method=?, companion_name=?, family_history=?, smoking_status=?, physical_activity=?, has_hypertension=?, has_kidney_disease=?, has_eye_retinopathy=?, has_eye_cataract=?, has_eye_glaucoma=?, other_chronic_diseases=? WHERE patient_id=?");
        $stmt->bind_param('ssisssssiiiiiisi', $diabetes_type, $diagnosis_year, $duration, $diagnosis_method, $companion, $family_history, $smoking, $activity, $hypertension, $kidney, $retinopathy, $cataract, $glaucoma, $other, $patient_id);
    } else {
        $stmt = $mysqli->prepare("INSERT INTO medical_history (patient_id, diabetes_type, diagnosis_year, duration_years, diagnosis_method, companion_name, family_history, smoking_status, physical_activity, has_hypertension, has_kidney_disease, has_eye_retinopathy, has_eye_cataract, has_eye_glaucoma, other_chronic_diseases) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('isssssssiiiiiss', $patient_id, $diabetes_type, $diagnosis_year, $duration, $diagnosis_method, $companion, $family_history, $smoking, $activity, $hypertension, $kidney, $retinopathy, $cataract, $glaucoma, $other);
    }
    if ($stmt->execute()) { $success = '✅ تم حفظ التاريخ الطبي'; $stmt = $mysqli->prepare("SELECT * FROM medical_history WHERE patient_id = ?"); $stmt->bind_param('i', $patient_id); $stmt->execute(); $history = $stmt->get_result()->fetch_assoc(); }
    else { $error = '❌ خطأ: ' . $stmt->error; }
}
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header"><h1 class="page-title">🩺 التاريخ الطبي</h1><p class="page-subtitle"><?php echo escape_output($patient['full_name']); ?> — 📁 <?php echo escape_output($patient['file_number']); ?></p></div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<div class="card">
<form method="post">
    <?php echo csrf_field(); ?>
    <div class="form-section">
        <div class="form-section-title">📋 تاريخ مرض السكري</div>
        <div class="form-grid form-grid-3">
            <div class="field"><label>نوع السكري <span class="required">*</span></label>
                <select name="diabetes_type">
                    <option value="">-- اختر --</option>
                    <?php echo get_diabetes_types_options($mysqli, $history['diabetes_type'] ?? ''); ?>
                </select>
            </div>
            <div class="field"><label>سنة التشخيص</label><input type="number" name="diagnosis_year" min="1950" max="2030" value="<?php echo $history['diagnosis_year'] ?? ''; ?>"></div>
            <div class="field"><label>مدة الإصابة (سنوات)</label><input type="number" name="duration_years" value="<?php echo $history['duration_years'] ?? ''; ?>"></div>
        </div>
    </div>
    <div class="form-section">
        <div class="form-section-title">🔬 طريقة التشخيص</div>
        <div class="check-group">
            <?php $methods = ['أعراض كلاسيكية', 'FPG ≥ 126', 'HbA1c ≥ 6.5%', 'OGTT', 'مصادفة أثناء فحص آخر'];
            $selected_methods = explode(',', $history['diagnosis_method'] ?? ''); foreach ($methods as $m): ?>
            <label class="check-item"><input type="checkbox" name="diagnosis_method[]" value="<?php echo $m; ?>" <?php echo in_array($m, $selected_methods) ? 'checked' : ''; ?>> <?php echo $m; ?></label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="form-section">
        <div class="form-section-title">👥 التاريخ العائلي ونمط الحياة</div>
        <div class="form-grid">
            <div>
                <label style="font-weight:800;color:var(--teal);font-size:13px;">التاريخ العائلي</label>
                <div class="check-group" style="flex-direction:column;">
                    <?php $fam = ['أب/أم', 'أخ/أخت', 'أجداد', 'لا يوجد'];
                    $selected_fam = explode(',', $history['family_history'] ?? ''); foreach ($fam as $f): ?>
                    <label class="check-item"><input type="checkbox" name="family_history[]" value="<?php echo $f; ?>" <?php echo in_array($f, $selected_fam) ? 'checked' : ''; ?>> <?php echo $f; ?></label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <div class="field mb-3"><label>التدخين</label>
                    <select name="smoking_status">
                        <option value="">-- اختر --</option>
                        <?php foreach (['لا', 'مدخن', 'سابق'] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo ($history['smoking_status'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field"><label>النشاط البدني</label>
                    <select name="physical_activity">
                        <option value="">-- اختر --</option>
                        <?php foreach (['لا يوجد', 'خفيف', 'معتدل', 'منتظم'] as $opt): ?>
                        <option value="<?php echo $opt; ?>" <?php echo ($history['physical_activity'] ?? '') === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="form-section">
        <div class="form-section-title">🏥 الأمراض المزمنة المرافقة</div>
        <div class="check-group">
            <?php $chronic = [
                'has_hypertension' => 'ارتفاع ضغط الدم',
                'has_kidney_disease' => 'أمراض الكلى',
                'has_eye_retinopathy' => 'اعتلال الشبكية',
                'has_eye_cataract' => 'المياه البيضاء (Cataract)',
                'has_eye_glaucoma' => 'المياه الزرقاء (Glaucoma)'
            ]; foreach ($chronic as $field => $label): ?>
            <label class="check-item"><input type="checkbox" name="<?php echo $field; ?>" <?php echo ($history[$field] ?? 0) ? 'checked' : ''; ?>> <?php echo $label; ?></label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="field mt-4"><label>أمراض مزمنة أخرى</label><textarea name="other_chronic_diseases" placeholder="اذكر أي أمراض أخرى..."><?php echo escape_output($history['other_chronic_diseases'] ?? ''); ?></textarea></div>
    <div class="field mt-3"><label>مرافق المريض/ولي الأمر</label><input type="text" name="companion_name" value="<?php echo escape_output($history['companion_name'] ?? ''); ?>" placeholder="الاسم وصلة القرابة"></div>
    <div class="flex justify-between mt-5">
        <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary">🔙 رجوع</a>
        <button type="submit" name="save_history" class="btn btn-primary">💾 حفظ</button>
    </div>
</form>
</div>
</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div>
