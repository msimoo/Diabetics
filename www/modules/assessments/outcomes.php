<?php
/**
 * Outcomes & Follow-up Form — النتائج والمتابعة
 * Upgraded with wound tracking, complications, infection, hospitalization
 */
$page_title = 'النتائج والمتابعة | Outcomes';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$stmt = $mysqli->prepare("SELECT v.*, p.full_name, p.file_number FROM visits v JOIN patients p ON v.patient_id = p.patient_id WHERE v.visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();
if (!$visit) { echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ زيارة غير موجودة</div></div></div></div>"; require_once __DIR__ . '/../../includes/footer.php'; exit; }

$stmt = $mysqli->prepare("SELECT * FROM outcomes WHERE visit_id = ?");
$stmt->bind_param('i', $visit_id); $stmt->execute();
$o = $stmt->get_result()->fetch_assoc();

// Also get wound data for context
$stmt_w = $mysqli->prepare("SELECT * FROM foot_ulcers WHERE visit_id = ?");
$stmt_w->bind_param('i', $visit_id); $stmt_w->execute();
$wound_data = $stmt_w->get_result()->fetch_assoc();

$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_outcomes'])) {
    $improvement = $_POST['improvement_percentage'] !== '' ? (int)$_POST['improvement_percentage'] : null;
    $improvement_date = $_POST['improvement_date'] ?? null;
    $healing_date = $_POST['healing_date'] ?? null;
    $prev_amputation = sanitize_input($_POST['previous_amputation'] ?? '');
    $prev_amputation_date = $_POST['previous_amputation_date'] ?? null;
    $prev_amputation_location = sanitize_input($_POST['previous_amputation_location'] ?? '');
    $curr_amputation = sanitize_input($_POST['current_amputation'] ?? '');
    $curr_amputation_date = $_POST['current_amputation_date'] ?? null;
    $referral = sanitize_input($_POST['referral_to'] ?? '');
    $referral_reason = sanitize_input($_POST['referral_reason'] ?? '');
    $deceased = isset($_POST['is_deceased']) ? 1 : 0;
    $death_date = $_POST['death_date'] ?? null;
    $death_cause = sanitize_input($_POST['death_cause'] ?? '');
    $death_detail = sanitize_input($_POST['death_cause_detail'] ?? '');
    $wound_complications = sanitize_input($_POST['wound_complications'] ?? '');
    $infection_status = sanitize_input($_POST['infection_status'] ?? '');
    $hosp_required = isset($_POST['hospitalization_required']) ? 1 : 0;
    $hosp_dates = sanitize_input($_POST['hospitalization_dates'] ?? '');

    if ($o) {
        $stmt = $mysqli->prepare("UPDATE outcomes SET improvement_percentage=?, improvement_date=?, healing_date=?, previous_amputation=?, previous_amputation_date=?, previous_amputation_location=?, current_amputation=?, current_amputation_date=?, referral_to=?, referral_reason=?, is_deceased=?, death_date=?, death_cause=?, death_cause_detail=?, wound_complications=?, infection_status=?, hospitalization_required=?, hospitalization_dates=? WHERE visit_id=?");
        $stmt->bind_param('isssssssssisssssisi', $improvement, $improvement_date, $healing_date, $prev_amputation, $prev_amputation_date, $prev_amputation_location, $curr_amputation, $curr_amputation_date, $referral, $referral_reason, $deceased, $death_date, $death_cause, $death_detail, $wound_complications, $infection_status, $hosp_required, $hosp_dates, $visit_id);
    } else {
        $stmt = $mysqli->prepare("INSERT INTO outcomes (visit_id, improvement_percentage, improvement_date, healing_date, previous_amputation, previous_amputation_date, previous_amputation_location, current_amputation, current_amputation_date, referral_to, referral_reason, is_deceased, death_date, death_cause, death_cause_detail, wound_complications, infection_status, hospitalization_required, hospitalization_dates) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
        $stmt->bind_param('isssssssssissssssis', $visit_id, $improvement, $improvement_date, $healing_date, $prev_amputation, $prev_amputation_date, $prev_amputation_location, $curr_amputation, $curr_amputation_date, $referral, $referral_reason, $deceased, $death_date, $death_cause, $death_detail, $wound_complications, $infection_status, $hosp_required, $hosp_dates);
    }
    if ($stmt->execute()) { $success = '✅ تم حفظ النتائج والمتابعة'; $stmt = $mysqli->prepare("SELECT * FROM outcomes WHERE visit_id = ?"); $stmt->bind_param('i', $visit_id); $stmt->execute(); $o = $stmt->get_result()->fetch_assoc(); }
    else { $error = '❌ خطأ: ' . $stmt->error; }
}

$improvement_options = [0, 10, 25, 50, 60, 75, 90, 100];
$amputation_options = ['لا', 'فوق الركبة', 'تحت الركبة', 'فوق الكاحل', 'تحت الكاحل', 'بتر رايس', 'إصبع'];
$infection_options = ['لا توجد', 'التهاب موضعي', 'التهاب مع صديد', 'التهاب عظم', 'تسمم دموي', 'غرغرينا'];
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header"><h1 class="page-title">📈 النتائج والمتابعة التفصيلية</h1><p class="page-subtitle"><?php echo escape_output($visit['full_name']); ?> — زيارة #<?php echo $visit['visit_number']; ?> (<?php echo $visit['visit_date']; ?>)</p></div>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="card">
<form method="post"><?php echo csrf_field(); ?>

<!-- Section 1: Progress Tracking -->
<div class="form-section">
<div class="form-section-title" style="background:linear-gradient(90deg,#0a7e6e,#13a896);">📊 تتبع تحسن القرحة</div>
<?php if ($wound_data): ?>
<div style="background:var(--teal-pale);padding:10px 14px;border-radius:8px;margin-bottom:14px;font-size:13px;">
    <strong>الجرح المسجل:</strong> <?php echo $wound_data['initial_cause'] ?? '—'; ?> |
    <strong>الحجم:</strong> <?php echo $wound_data['wound_size_cm2'] ? $wound_data['wound_size_cm2'] . ' سم²' : '—'; ?> |
    <strong>القدم:</strong> <?php echo $wound_data['wound_foot'] ?? '—'; ?> |
    <strong>العمق:</strong> <?php echo $wound_data['wound_depth'] ?? '—'; ?>
</div>
<?php endif; ?>
<div class="field mb-3">
    <label>نسبة التحسن <span class="required">*</span></label>
    <div class="check-group">
    <?php foreach ($improvement_options as $pct): ?>
    <label class="radio-item" style="padding:4px 8px;background:<?php echo ($o['improvement_percentage'] ?? '') === (string)$pct ? 'var(--teal-pale)' : 'var(--bg-input)'; ?>;border-radius:6px;">
        <input type="radio" name="improvement_percentage" value="<?php echo $pct; ?>" <?php echo ($o['improvement_percentage'] ?? '') === (string)$pct ? 'checked' : ''; ?>>
        <?php echo $pct; ?>%
    </label>
    <?php endforeach; ?>
    </div>
</div>
<div class="form-grid">
<div class="field"><label>تاريخ التحسن</label><input type="date" name="improvement_date" value="<?php echo $o['improvement_date'] ?? ''; ?>"></div>
<div class="field"><label>تاريخ الشفاء الكامل</label><input type="date" name="healing_date" value="<?php echo $o['healing_date'] ?? ''; ?>"></div>
</div>
</div>

<!-- Section 2: Wound Complications & Infection -->
<div class="form-section">
<div class="form-section-title" style="background:linear-gradient(90deg,#ef4444,#dc2626);">⚠️ مضاعفات الجرح والالتهابات</div>
<div class="form-grid">
<div class="field"><label>حالة الالتهاب</label>
<select name="infection_status">
    <option value="">-- اختر --</option>
    <?php foreach ($infection_options as $inf): ?>
    <option value="<?php echo $inf; ?>" <?php echo ($o['infection_status'] ?? '') === $inf ? 'selected' : ''; ?>><?php echo $inf; ?></option>
    <?php endforeach; ?>
</select></div>
<div class="field"><label>مضاعفات الجرح</label>
<textarea name="wound_complications" rows="2" placeholder="مضاعفات مثل: نزيف، تأخر التئام، ورم دموي، تفاقم التقرح..."><?php echo escape_output($o['wound_complications'] ?? ''); ?></textarea></div>
</div>
<div class="form-grid mt-3">
<div class="check-item"><input type="checkbox" name="hospitalization_required" value="1" <?php echo ($o['hospitalization_required'] ?? 0) ? 'checked' : ''; ?>> <strong>تطلب تنويم في المستشفى</strong></div>
<div class="field"><label>تواريخ التنويم</label><input type="text" name="hospitalization_dates" value="<?php echo escape_output($o['hospitalization_dates'] ?? ''); ?>" placeholder="مثال: ١/٦-٥/٦/٢٠٢٦"></div>
</div>
</div>

<!-- Section 3: Amputation -->
<div class="form-section">
<div class="form-section-title" style="background:linear-gradient(90deg,#6b21a8,#9333ea);">🦿 البتر</div>
<div class="form-grid">
<div>
<div class="field"><label>بتر سابق</label><select name="previous_amputation">
    <option value="">-- اختر --</option>
    <?php foreach ($amputation_options as $a): ?><option value="<?php echo $a; ?>" <?php echo ($o['previous_amputation'] ?? '') === $a ? 'selected' : ''; ?>><?php echo $a; ?></option><?php endforeach; ?>
</select></div>
<div class="field mt-2"><label>تاريخ البتر السابق</label><input type="date" name="previous_amputation_date" value="<?php echo $o['previous_amputation_date'] ?? ''; ?>"></div>
<div class="field mt-2"><label>موقع البتر السابق</label><input type="text" name="previous_amputation_location" value="<?php echo escape_output($o['previous_amputation_location'] ?? ''); ?>" placeholder="مثال: إصبع القدم الأيسر"></div>
</div>
<div>
<div class="field"><label>بتر حالي</label><select name="current_amputation">
    <option value="">-- اختر --</option>
    <?php foreach ($amputation_options as $a): ?><option value="<?php echo $a; ?>" <?php echo ($o['current_amputation'] ?? '') === $a ? 'selected' : ''; ?>><?php echo $a; ?></option><?php endforeach; ?>
</select></div>
<div class="field mt-2"><label>تاريخ البتر الحالي</label><input type="date" name="current_amputation_date" value="<?php echo $o['current_amputation_date'] ?? ''; ?>"></div>
</div>
</div>
</div>

<!-- Section 4: Referral -->
<div class="form-section">
<div class="form-section-title" style="background:linear-gradient(90deg,#0ea5e9,#0284c7);">🏥 التحويل</div>
<div class="form-grid">
<div class="field"><label>التحويل إلى</label><input type="text" name="referral_to" value="<?php echo escape_output($o['referral_to'] ?? ''); ?>" placeholder="اسم الجهة (مستشفى، عيادة أخرى...)"></div>
<div class="field"><label>سبب التحويل</label><textarea name="referral_reason" rows="2"><?php echo escape_output($o['referral_reason'] ?? ''); ?></textarea></div>
</div>
</div>

<!-- Section 5: Deceased -->
<div class="form-section">
<div class="form-section-title" style="background:linear-gradient(90deg,#4b5563,#374151);">⚠️ الوفاة</div>
<div class="field"><label class="check-item"><input type="checkbox" name="is_deceased" id="is_deceased" <?php echo ($o['is_deceased'] ?? 0) ? 'checked' : ''; ?>> توفي المريض</label></div>
<div id="deathFields" style="display:<?php echo ($o['is_deceased'] ?? 0) ? 'block' : 'none'; ?>;">
<div class="form-grid mt-3">
<div class="field"><label>تاريخ الوفاة</label><input type="date" name="death_date" value="<?php echo $o['death_date'] ?? ''; ?>"></div>
<div class="field"><label>سبب الوفاة</label><select name="death_cause">
    <option value="">-- اختر --</option>
    <option value="مضاعفات قدم السكري" <?php echo ($o['death_cause'] ?? '') === 'مضاعفات قدم السكري' ? 'selected' : ''; ?>>مضاعفات قدم السكري</option>
    <option value="مضاعفات قلبية وعائية" <?php echo ($o['death_cause'] ?? '') === 'مضاعفات قلبية وعائية' ? 'selected' : ''; ?>>مضاعفات قلبية وعائية</option>
    <option value="فشل كلوي" <?php echo ($o['death_cause'] ?? '') === 'فشل كلوي' ? 'selected' : ''; ?>>فشل كلوي</option>
    <option value="سبب آخر" <?php echo ($o['death_cause'] ?? '') === 'سبب آخر' ? 'selected' : ''; ?>>سبب آخر</option>
</select></div>
</div>
<div class="field mt-2"><label>تفاصيل سبب الوفاة</label><textarea name="death_cause_detail" rows="2"><?php echo escape_output($o['death_cause_detail'] ?? ''); ?></textarea></div>
</div>
</div>

<div class="flex justify-between mt-5" style="gap:12px;flex-wrap:wrap;">
    <a href="<?php echo BASE_URL; ?>/modules/visits/view.php?id=<?php echo $visit_id; ?>" class="btn btn-secondary">🔙 رجوع للزيارة</a>
    <button type="submit" name="save_outcomes" class="btn btn-primary" style="padding:10px 32px;">💾 حفظ النتائج والمتابعة</button>
</div>

</form></div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const deceasedCheck = document.getElementById('is_deceased');
    if (deceasedCheck) {
        deceasedCheck.addEventListener('change', function() {
            document.getElementById('deathFields').style.display = this.checked ? 'block' : 'none';
        });
    }
});
</script>
</div>
