<?php
/**
 * Visit Creation - Main Form
 */
$page_title = 'زيارة جديدة | New Visit';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$selected_patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$error = ''; $success = ''; $new_visit_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_visit'])) {
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $visit_date = $_POST['visit_date'] ?? date('Y-m-d');
    $visit_reason = sanitize_input($_POST['visit_reason'] ?? '');
    $chief_complaint = sanitize_input($_POST['chief_complaint'] ?? '');
    $doctor_notes = sanitize_input($_POST['doctor_notes'] ?? '');
    $treatment_plan = sanitize_input($_POST['treatment_plan'] ?? '');
    $next_review = $_POST['next_review_date'] ?? null;

    if (!$patient_id) { $error = '❌ يرجى اختيار المريض'; }
    else {
        $visit_number = get_next_visit_number($patient_id);
        $stmt = $mysqli->prepare("INSERT INTO visits (patient_id, visit_number, visit_date, visit_reason, chief_complaint, doctor_notes, treatment_plan, next_review_date, created_by) VALUES (?,?,?,?,?,?,?,?,?)");
        $user_id = $_SESSION['user_id'];
        $stmt->bind_param('iissssssi', $patient_id, $visit_number, $visit_date, $visit_reason, $chief_complaint, $doctor_notes, $treatment_plan, $next_review, $user_id);
        if ($stmt->execute()) {
            $new_visit_id = $stmt->insert_id;
            $success = '✅ تم إنشاء الزيارة بنجاح';
            echo "<script>window.location.href='view.php?id=$new_visit_id';</script>";
        } else { $error = '❌ خطأ: ' . $stmt->error; }
    }
}

// Fetch patients list for dropdown
$patients = $mysqli->query("SELECT patient_id, full_name, file_number FROM patients WHERE is_active = 1 ORDER BY full_name");
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header"><h1 class="page-title">🩺 زيارة جديدة</h1><p class="page-subtitle">New Patient Visit</p></div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<div class="card">
<form method="post">
    <?php echo csrf_field(); ?>
    <div class="form-section">
        <div class="form-section-title">👤 المريض</div>
        <div class="field">
            <label>اختر المريض <span class="required">*</span></label>
            <select name="patient_id" required>
                <option value="">-- اختر المريض --</option>
                <?php while ($p = $patients->fetch_assoc()): ?>
                <option value="<?php echo $p['patient_id']; ?>" <?php echo $p['patient_id'] === $selected_patient_id ? 'selected' : ''; ?>>
                    <?php echo escape_output($p['full_name']); ?> — 📁 <?php echo escape_output($p['file_number']); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>
    <div class="form-section">
        <div class="form-section-title">📅 تفاصيل الزيارة</div>
        <div class="form-grid">
            <div class="field"><label>تاريخ الزيارة <span class="required">*</span></label><input type="date" name="visit_date" value="<?php echo date('Y-m-d'); ?>" required></div>
            <div class="field"><label>سبب الزيارة</label>
                <select name="visit_reason">
                    <option value="">-- اختر --</option>
                    <option value="متابعة">متابعة</option>
                    <option value="شكوى محددة">شكوى محددة</option>
                    <option value="طوارئ">طوارئ</option>
                    <option value="مراجعة">مراجعة</option>
                </select>
            </div>
            <div class="field col-span-2"><label>الشكوى الرئيسية</label><textarea name="chief_complaint" placeholder="وصف الشكوى..."></textarea></div>
            <div class="field col-span-2"><label>ملاحظات الطبيب</label><textarea name="doctor_notes" placeholder="ملاحظات الفحص السريري..."></textarea></div>
            <div class="field col-span-2"><label>الخطة العلاجية</label><textarea name="treatment_plan" placeholder="خطة العلاج والتوصيات..."></textarea></div>
            <div class="field"><label>تاريخ المراجعة القادمة</label><input type="date" name="next_review_date"></div>
        </div>
    </div>
    <div class="flex justify-between mt-5">
        <a href="<?php echo BASE_URL; ?>/modules/dashboard.php" class="btn btn-secondary">🔙 رجوع</a>
        <button type="submit" name="create_visit" class="btn btn-primary">🩺 إنشاء الزيارة</button>
    </div>
</form>
</div>
</div><?php require_once __DIR__ . '/../../includes/footer.php'; ?></div>
