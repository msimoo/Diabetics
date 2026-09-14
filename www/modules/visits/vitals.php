<?php
/**
 * Vital Signs Form
 */
$page_title = 'العلامات الحيوية | Vital Signs';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$stmt = $mysqli->prepare("SELECT v.*, p.full_name, p.file_number FROM visits v JOIN patients p ON v.patient_id = p.patient_id WHERE v.visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();
if (!$visit) { echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ زيارة غير موجودة</div></div></div></div>"; require_once __DIR__ . '/../../includes/footer.php'; exit; }

$stmt = $mysqli->prepare("SELECT * FROM vital_signs WHERE visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$vitals = $stmt->get_result()->fetch_assoc();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vitals'])) {
    $weight = $_POST['weight'] ? (float)$_POST['weight'] : null;
    $height = $_POST['height'] ? (int)$_POST['height'] : null;
    $bmi = ($weight && $height) ? calculate_bmi($weight, $height) : null;
    $bp_text = sanitize_input($_POST['blood_pressure_text'] ?? '');
    $bp_sys = $_POST['bp_systolic'] ? (int)$_POST['bp_systolic'] : null;
    $bp_dia = $_POST['bp_diastolic'] ? (int)$_POST['bp_diastolic'] : null;
    $waist = $_POST['waist_circumference'] ? (float)$_POST['waist_circumference'] : null;
    $temp = $_POST['temperature'] ? (float)$_POST['temperature'] : null;
    $hr = $_POST['heart_rate'] ? (int)$_POST['heart_rate'] : null;
    $o2 = $_POST['oxygen_saturation'] ? (float)$_POST['oxygen_saturation'] : null;
    $notes = sanitize_input($_POST['notes'] ?? '');

    if ($vitals) {
        $stmt = $mysqli->prepare("UPDATE vital_signs SET weight=?, height=?, bmi=?, blood_pressure_systolic=?, blood_pressure_diastolic=?, blood_pressure_text=?, waist_circumference=?, temperature=?, heart_rate=?, oxygen_saturation=?, notes=? WHERE visit_id=?");
        $stmt->bind_param('dddddsddddsi', $weight, $height, $bmi, $bp_sys, $bp_dia, $bp_text, $waist, $temp, $hr, $o2, $notes, $visit_id);
    } else {
        $stmt = $mysqli->prepare("INSERT INTO vital_signs (visit_id, weight, height, bmi, blood_pressure_systolic, blood_pressure_diastolic, blood_pressure_text, waist_circumference, temperature, heart_rate, oxygen_saturation, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('idddddsdddds', $visit_id, $weight, $height, $bmi, $bp_sys, $bp_dia, $bp_text, $waist, $temp, $hr, $o2, $notes);
    }
    if ($stmt->execute()) { $success = '✅ تم حفظ العلامات الحيوية'; $stmt = $mysqli->prepare("SELECT * FROM vital_signs WHERE visit_id = ?"); $stmt->bind_param('i', $visit_id); $stmt->execute(); $vitals = $stmt->get_result()->fetch_assoc(); }
    else { $error = '❌ خطأ: ' . $stmt->error; }
}
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header"><h1 class="page-title">💉 العلامات الحيوية</h1><p class="page-subtitle"><?php echo escape_output($visit['full_name']); ?> — زيارة #<?php echo $visit['visit_number']; ?></p></div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<div class="card">
<form method="post"><?php echo csrf_field(); ?>
    <table class="vitals-table" style="width:100%;border-collapse:collapse;">
        <thead><tr style="background:var(--teal-pale);">
            <th style="padding:10px;text-align:center;border-bottom:2px solid var(--teal-mid);">القياس</th>
            <th style="padding:10px;text-align:center;border-bottom:2px solid var(--teal-mid);">القيمة</th>
            <th style="padding:10px;text-align:center;border-bottom:2px solid var(--teal-mid);">الوحدة</th>
            <th style="padding:10px;text-align:center;border-bottom:2px solid var(--teal-mid);">ملاحظات</th>
        </tr></thead>
        <tbody>
            <tr><td style="padding:8px;font-weight:700;">الوزن</td><td style="padding:8px;"><input type="number" name="weight" step="0.1" id="weight" value="<?php echo $vitals['weight'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;"></td><td style="padding:8px;">كغم</td><td style="padding:8px;"><input type="text" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;"></td></tr>
            <tr><td style="padding:8px;font-weight:700;">الطول</td><td style="padding:8px;"><input type="number" name="height" id="height" value="<?php echo $vitals['height'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;"></td><td style="padding:8px;">سم</td><td style="padding:8px;"><input type="text" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;"></td></tr>
            <tr><td style="padding:8px;font-weight:700;">مؤشر كتلة الجسم (BMI)</td><td style="padding:8px;"><input type="text" name="bmi" id="bmi" value="<?php echo $vitals['bmi'] ?? ''; ?>" readonly style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;background:#f0f0f0;font-weight:700;"></td><td style="padding:8px;">kg/m²</td><td style="padding:8px;font-size:12px;color:var(--gray);">يُحسب تلقائياً</td></tr>
            <tr><td style="padding:8px;font-weight:700;">ضغط الدم</td><td style="padding:8px;">
                <div style="display:flex;gap:4px;align-items:center;">
                    <input type="text" name="blood_pressure_text" id="blood_pressure_text" value="<?php echo $vitals['blood_pressure_text'] ?? ''; ?>" placeholder="120/80" style="flex:1;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;">
                    <input type="hidden" name="bp_systolic" id="blood_pressure_systolic" value="<?php echo $vitals['blood_pressure_systolic'] ?? ''; ?>">
                    <input type="hidden" name="bp_diastolic" id="blood_pressure_diastolic" value="<?php echo $vitals['blood_pressure_diastolic'] ?? ''; ?>">
                </div>
            </td><td style="padding:8px;">mmHg</td><td style="padding:8px;"><input type="text" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;"></td></tr>
            <tr><td style="padding:8px;font-weight:700;">محيط الخصر</td><td style="padding:8px;"><input type="number" name="waist_circumference" step="0.1" value="<?php echo $vitals['waist_circumference'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;"></td><td style="padding:8px;">سم</td><td style="padding:8px;"><input type="text" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;"></td></tr>
            <tr><td style="padding:8px;font-weight:700;">درجة الحرارة</td><td style="padding:8px;"><input type="number" name="temperature" step="0.1" value="<?php echo $vitals['temperature'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;"></td><td style="padding:8px;">°م</td><td style="padding:8px;"><input type="text" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;"></td></tr>
            <tr><td style="padding:8px;font-weight:700;">معدل ضربات القلب</td><td style="padding:8px;"><input type="number" name="heart_rate" value="<?php echo $vitals['heart_rate'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;"></td><td style="padding:8px;">ن/د</td><td style="padding:8px;"><input type="text" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;"></td></tr>
            <tr><td style="padding:8px;font-weight:700;">تشبع الأكسجين</td><td style="padding:8px;"><input type="number" name="oxygen_saturation" step="0.1" min="0" max="100" value="<?php echo $vitals['oxygen_saturation'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;"></td><td style="padding:8px;">%</td><td style="padding:8px;"><input type="text" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;"></td></tr>
        </tbody>
    </table>
    <div class="field mt-3"><label>ملاحظات</label><textarea name="notes"><?php echo escape_output($vitals['notes'] ?? ''); ?></textarea></div>
    <div class="flex justify-between mt-4">
        <a href="<?php echo BASE_URL; ?>/modules/visits/view.php?id=<?php echo $visit_id; ?>" class="btn btn-secondary">🔙 رجوع</a>
        <button type="submit" name="save_vitals" class="btn btn-primary">💾 حفظ</button>
    </div>
</form>
</div>
</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div>
