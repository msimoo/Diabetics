<?php
/**
 * Lab Results Form
 */
$page_title = 'الفحوصات المخبرية | Lab Results';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$stmt = $mysqli->prepare("SELECT v.*, p.full_name, p.file_number FROM visits v JOIN patients p ON v.patient_id = p.patient_id WHERE v.visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();
if (!$visit) { echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ زيارة غير موجودة</div></div></div></div>"; require_once __DIR__ . '/../../includes/footer.php'; exit; }

$stmt = $mysqli->prepare("SELECT * FROM lab_results WHERE visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$labs = $stmt->get_result()->fetch_assoc();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_labs'])) {
    $cholesterol = $_POST['total_cholesterol'] ? (float)$_POST['total_cholesterol'] : null;
    $ldl = $_POST['ldl'] ? (float)$_POST['ldl'] : null;
    $hdl = $_POST['hdl'] ? (float)$_POST['hdl'] : null;
    $trig = $_POST['triglycerides'] ? (float)$_POST['triglycerides'] : null;
    $creat = $_POST['creatinine'] ? (float)$_POST['creatinine'] : null;
    $egfr = $_POST['egfr'] ? (int)$_POST['egfr'] : null;
    $micro = $_POST['microalbumin'] ? (float)$_POST['microalbumin'] : null;
    $tsh = $_POST['tsh'] ? (float)$_POST['tsh'] : null;
    $test_date = $_POST['test_date'] ?? null;
    $other = sanitize_input($_POST['other_labs'] ?? '');

    if ($labs) {
        $stmt = $mysqli->prepare("UPDATE lab_results SET total_cholesterol=?, ldl=?, hdl=?, triglycerides=?, creatinine=?, egfr=?, microalbumin=?, tsh=?, test_date=?, other_labs=? WHERE visit_id=?");
        $stmt->bind_param('dddddiiss si', $cholesterol, $ldl, $hdl, $trig, $creat, $egfr, $micro, $tsh, $test_date, $other, $visit_id);
    } else {
        $stmt = $mysqli->prepare("INSERT INTO lab_results (visit_id, total_cholesterol, ldl, hdl, triglycerides, creatinine, egfr, microalbumin, tsh, test_date, other_labs) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('idddddiiiss', $visit_id, $cholesterol, $ldl, $hdl, $trig, $creat, $egfr, $micro, $tsh, $test_date, $other);
    }
    if ($stmt->execute()) { $success = '✅ تم حفظ الفحوصات المخبرية'; $stmt = $mysqli->prepare("SELECT * FROM lab_results WHERE visit_id = ?"); $stmt->bind_param('i', $visit_id); $stmt->execute(); $labs = $stmt->get_result()->fetch_assoc(); }
    else { $error = '❌ خطأ: ' . $stmt->error; }
}

$normal_ranges = [
    'total_cholesterol' => ['label' => 'الكوليسترول الكلي', 'unit' => 'mg/dL', 'min' => 0, 'max' => 200, 'step' => '1'],
    'ldl' => ['label' => 'LDL', 'unit' => 'mg/dL', 'min' => 0, 'max' => 100, 'step' => '1'],
    'hdl' => ['label' => 'HDL', 'unit' => 'mg/dL', 'min' => 40, 'max' => 100, 'step' => '1'],
    'triglycerides' => ['label' => 'الدهون الثلاثية (TG)', 'unit' => 'mg/dL', 'min' => 0, 'max' => 150, 'step' => '1'],
    'creatinine' => ['label' => 'وظائف الكلى (Creatinine)', 'unit' => 'mg/dL', 'min' => 0.6, 'max' => 1.2, 'step' => '0.01'],
    'egfr' => ['label' => 'eGFR', 'unit' => 'mL/min/1.73m²', 'min' => 90, 'max' => 999, 'step' => '1'],
    'microalbumin' => ['label' => 'بروتين البول (Microalbumin)', 'unit' => 'mg/g Cr', 'min' => 0, 'max' => 30, 'step' => '0.1'],
    'tsh' => ['label' => 'وظائف الغدة الدرقية (TSH)', 'unit' => 'mIU/L', 'min' => 0.4, 'max' => 4.0, 'step' => '0.01'],
];
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header"><h1 class="page-title">🧪 الفحوصات المخبرية</h1><p class="page-subtitle"><?php echo escape_output($visit['full_name']); ?> — زيارة #<?php echo $visit['visit_number']; ?></p></div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<div class="card"><form method="post"><?php echo csrf_field(); ?>
<table style="width:100%;border-collapse:collapse;">
<thead><tr style="background:var(--teal-pale);">
<th style="padding:10px;border-bottom:2px solid var(--teal-mid);">الفحص</th>
<th style="padding:10px;border-bottom:2px solid var(--teal-mid);">القيمة</th>
<th style="padding:10px;border-bottom:2px solid var(--teal-mid);">الوحدة</th>
<th style="padding:10px;border-bottom:2px solid var(--teal-mid);">النطاق الطبيعي</th>
</tr></thead>
<tbody>
<?php foreach ($normal_ranges as $field => $info): ?>
<tr><td style="padding:8px;font-weight:700;"><?php echo $info['label']; ?></td>
<td style="padding:8px;"><input type="number" name="<?php echo $field; ?>" step="<?php echo $info['step']; ?>" value="<?php echo $labs[$field] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;" class="lab-input" data-field="<?php echo $field; ?>" data-min="<?php echo $info['min']; ?>" data-max="<?php echo $info['max']; ?>"></td>
<td style="padding:8px;"><?php echo $info['unit']; ?></td>
<td style="padding:8px;font-size:12px;color:var(--gray);"><?php echo $info['min']; ?> – <?php echo $info['max']; ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<div class="field mt-3"><label>تاريخ الفحص</label><input type="date" name="test_date" value="<?php echo $labs['test_date'] ?? date('Y-m-d'); ?>"></div>
<div class="field mt-3"><label>فحوصات أخرى</label><textarea name="other_labs"><?php echo escape_output($labs['other_labs'] ?? ''); ?></textarea></div>
<div class="flex justify-between mt-5">
<a href="<?php echo BASE_URL; ?>/modules/visits/view.php?id=<?php echo $visit_id; ?>" class="btn btn-secondary">🔙 رجوع</a>
<button type="submit" name="save_labs" class="btn btn-primary">💾 حفظ</button>
</div>
</form></div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.lab-input').forEach(input => {
        input.addEventListener('input', function() {
            const val = parseFloat(this.value);
            const min = parseFloat(this.dataset.min);
            const max = parseFloat(this.dataset.max);
            if (val && (val < min || val > max)) {
                this.style.borderColor = '#ef4444';
                this.style.background = '#fef2f2';
            } else {
                this.style.borderColor = 'var(--border)';
                this.style.background = '';
            }
        });
    });
});
</script>
</div>
