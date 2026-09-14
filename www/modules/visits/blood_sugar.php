<?php
/**
 * Blood Sugar Readings Form
 */
$page_title = 'قراءات سكر الدم | Blood Sugar';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$stmt = $mysqli->prepare("SELECT v.*, p.full_name, p.file_number FROM visits v JOIN patients p ON v.patient_id = p.patient_id WHERE v.visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();
if (!$visit) { echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ زيارة غير موجودة</div></div></div></div>"; require_once __DIR__ . '/../../includes/footer.php'; exit; }

$stmt = $mysqli->prepare("SELECT * FROM blood_sugar_readings WHERE visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$sugar = $stmt->get_result()->fetch_assoc();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_sugar'])) {
    $fpg = $_POST['fpg_value'] ? (float)$_POST['fpg_value'] : null;
    $fpg_date = $_POST['fpg_date'] ?? null;
    $ppg = $_POST['ppg_value'] ? (float)$_POST['ppg_value'] : null;
    $ppg_date = $_POST['ppg_date'] ?? null;
    $hba1c = $_POST['hba1c_value'] ? (float)$_POST['hba1c_value'] : null;
    $hba1c_date = $_POST['hba1c_date'] ?? null;
    $random = $_POST['random_sugar_value'] ? (float)$_POST['random_sugar_value'] : null;
    $random_date = $_POST['random_sugar_date'] ?? null;
    $device = sanitize_input($_POST['lab_device'] ?? '');

    if ($sugar) {
        $stmt = $mysqli->prepare("UPDATE blood_sugar_readings SET fpg_value=?, fpg_date=?, ppg_value=?, ppg_date=?, hba1c_value=?, hba1c_date=?, random_sugar_value=?, random_sugar_date=?, lab_device=? WHERE visit_id=?");
        $stmt->bind_param('dsdsdssssi', $fpg, $fpg_date, $ppg, $ppg_date, $hba1c, $hba1c_date, $random, $random_date, $device, $visit_id);
    } else {
        $stmt = $mysqli->prepare("INSERT INTO blood_sugar_readings (visit_id, fpg_value, fpg_date, ppg_value, ppg_date, hba1c_value, hba1c_date, random_sugar_value, random_sugar_date, lab_device) VALUES (?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('idsdsddsss', $visit_id, $fpg, $fpg_date, $ppg, $ppg_date, $hba1c, $hba1c_date, $random, $random_date, $device);
    }
    if ($stmt->execute()) { $success = '✅ تم حفظ قراءات السكر'; $stmt = $mysqli->prepare("SELECT * FROM blood_sugar_readings WHERE visit_id = ?"); $stmt->bind_param('i', $visit_id); $stmt->execute(); $sugar = $stmt->get_result()->fetch_assoc(); }
    else { $error = '❌ خطأ: ' . $stmt->error; }
}
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header"><h1 class="page-title">🔬 قراءات سكر الدم</h1><p class="page-subtitle"><?php echo escape_output($visit['full_name']); ?> — زيارة #<?php echo $visit['visit_number']; ?></p></div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<div class="card">
<form method="post"><?php echo csrf_field(); ?>
    <table style="width:100%;border-collapse:collapse;">
        <thead><tr style="background:linear-gradient(90deg,var(--teal),var(--teal-light));color:#fff;">
            <th style="padding:10px;text-align:center;">نوع القراءة</th>
            <th style="padding:10px;text-align:center;">القيمة (mg/dL)</th>
            <th style="padding:10px;text-align:center;">التاريخ</th>
        </tr></thead>
        <tbody>
            <tr><td style="padding:8px;font-weight:700;">سكر الصيام (FPG)</td>
                <td style="padding:8px;"><input type="number" name="fpg_value" step="0.1" value="<?php echo $sugar['fpg_value'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;"></td>
                <td style="padding:8px;"><input type="date" name="fpg_date" value="<?php echo $sugar['fpg_date'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;"></td></tr>
            <tr><td style="padding:8px;font-weight:700;">سكر ما بعد الأكل (PPG)</td>
                <td style="padding:8px;"><input type="number" name="ppg_value" step="0.1" value="<?php echo $sugar['ppg_value'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;"></td>
                <td style="padding:8px;"><input type="date" name="ppg_date" value="<?php echo $sugar['ppg_date'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;"></td></tr>
            <tr><td style="padding:8px;font-weight:700;">HbA1c (%) <span id="hba1c_result" style="margin-right:8px;"></span></td>
                <td style="padding:8px;"><input type="number" name="hba1c_value" step="0.1" id="hba1c_value" value="<?php echo $sugar['hba1c_value'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;font-weight:700;font-size:16px;"></td>
                <td style="padding:8px;"><input type="date" name="hba1c_date" value="<?php echo $sugar['hba1c_date'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;"></td></tr>
            <tr><td style="padding:8px;font-weight:700;">سكر عشوائي</td>
                <td style="padding:8px;"><input type="number" name="random_sugar_value" step="0.1" value="<?php echo $sugar['random_sugar_value'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;text-align:center;"></td>
                <td style="padding:8px;"><input type="date" name="random_sugar_date" value="<?php echo $sugar['random_sugar_date'] ?? ''; ?>" style="width:100%;border:1px solid var(--border);border-radius:6px;padding:6px 10px;"></td></tr>
        </tbody>
    </table>
    <div class="field mt-3"><label>المختبر / الجهاز</label><input type="text" name="lab_device" value="<?php echo escape_output($sugar['lab_device'] ?? ''); ?>" placeholder="اسم المختبر أو الجهاز المستخدم"></div>
    <div class="flex justify-between mt-4">
        <a href="<?php echo BASE_URL; ?>/modules/visits/view.php?id=<?php echo $visit_id; ?>" class="btn btn-secondary">🔙 رجوع</a>
        <button type="submit" name="save_sugar" class="btn btn-primary">💾 حفظ</button>
    </div>
</form>
</div>
</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div>
