<?php
/**
 * Smart Cohort Builder — Build custom patient cohorts, compare side-by-side
 */
$page_title = '🏗️ منشئ المجموعات الذكي | Cohort Builder';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Get distinct values for filters
$cities = $mysqli->query("SELECT DISTINCT city FROM patients WHERE city IS NOT NULL AND city != '' AND is_active = 1 ORDER BY city");
$genders = $mysqli->query("SELECT DISTINCT gender FROM patients WHERE is_active = 1");
$treatments = $mysqli->query("SELECT DISTINCT treatment_type FROM treatments WHERE treatment_type IS NOT NULL AND treatment_type != '' ORDER BY treatment_type");

// Build cohort query
$where = [];
$params = [];

if (!empty($_GET['gender'])) {
    $g = $mysqli->real_escape_string($_GET['gender']);
    $where[] = "p.gender = '$g'";
}
if (!empty($_GET['city'])) {
    $c = $mysqli->real_escape_string($_GET['city']);
    $where[] = "p.city = '$c'";
}
if (!empty($_GET['age_min'])) {
    $am = (int)$_GET['age_min'];
    $where[] = "p.age >= $am";
}
if (!empty($_GET['age_max'])) {
    $ax = (int)$_GET['age_max'];
    $where[] = "p.age <= $ax";
}
if (!empty($_GET['wagner_min'])) {
    $wm = (int)$_GET['wagner_min'];
    $where[] = "fa.wagner_grade >= $wm";
}
if (!empty($_GET['healed'])) {
    $h = $_GET['healed'];
    if ($h === 'healed') $where[] = "o.improvement_percentage >= 100";
    elseif ($h === 'not_healed') $where[] = "(o.improvement_percentage IS NULL OR o.improvement_percentage < 100)";
}
if (!empty($_GET['smoking'])) {
    $s = $mysqli->real_escape_string($_GET['smoking']);
    $where[] = "mh.smoking_status = '$s'";
}
if (!empty($_GET['hba1c_min'])) {
    $hm = (float)$_GET['hba1c_min'];
    $where[] = "bs.hba1c_value >= $hm";
}
if (!empty($_GET['has_amputation']) && $_GET['has_amputation'] === 'yes') {
    $where[] = "o.current_amputation IS NOT NULL AND o.current_amputation != 'لا'";
}

$where_clause = !empty($where) ? 'AND ' . implode(' AND ', $where) : '';
$query = "SELECT p.patient_id, p.full_name, p.file_number, p.age, p.gender, p.city,
    fa.wagner_grade, bs.hba1c_value, o.improvement_percentage, o.current_amputation,
    mh.smoking_status,
    (SELECT COUNT(*) FROM visits v2 WHERE v2.patient_id = p.patient_id) as visit_count
    FROM patients p
    LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE p.is_active = 1 $where_clause
    GROUP BY p.patient_id
    ORDER BY p.full_name ASC LIMIT 500";

$cohort = $mysqli->query($query);
$cohort_count = $cohort ? $cohort->num_rows : 0;
$cohort_rows = [];
if ($cohort) while ($row = $cohort->fetch_assoc()) $cohort_rows[] = $row;

// Summary stats for cohort
$avg_age = $cohort_count > 0 ? round(array_sum(array_column($cohort_rows, 'age')) / $cohort_count, 1) : 0;
$avg_hba1c_cohort = 0;
$hba1c_values = array_filter(array_column($cohort_rows, 'hba1c_value'));
$avg_hba1c_cohort = count($hba1c_values) > 0 ? round(array_sum($hba1c_values) / count($hba1c_values), 1) : 0;
$healed_count = count(array_filter($cohort_rows, fn($r) => ($r['improvement_percentage'] ?? 0) >= 100));
$wagner3_count = count(array_filter($cohort_rows, fn($r) => ($r['wagner_grade'] ?? 0) >= 3));
$smokers = count(array_filter($cohort_rows, fn($r) => $r['smoking_status'] === 'مدخن'));
$amputations = count(array_filter($cohort_rows, fn($r) => $r['current_amputation'] && $r['current_amputation'] !== 'لا'));
?>
<style>
    .cf-card { padding: 1rem; border-radius: 12px; border: 1px solid var(--border); transition: var(--transition); }
    .cf-card:hover { box-shadow: var(--shadow-lg); }
    .filter-group { background: var(--bg-input); padding: 0.75rem; border-radius: 10px; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header">
    <h1 class="page-title">🏗️ منشئ المجموعات الذكي</h1>
    <p class="page-subtitle">Smart Cohort Builder — أنشئ مجموعات مرضى مخصصة وقارن</p>
</div>

<div class="flex flex-wrap gap-4 mb-4">
    <!-- Filters -->
    <div class="card" style="flex:1;min-width:280px;">
        <div class="card-header"><div class="card-title">🔍 الفلاتر</div></div>
        <form method="get" class="flex flex-col gap-3">
            <div class="flex gap-2">
                <div class="field" style="flex:1;"><label>الجنس</label>
                    <select name="gender"><option value="">الكل</option><option value="ذكر" <?php echo $_GET['gender'] === 'ذكر' ? 'selected' : ''; ?>>ذكر</option><option value="أنثى" <?php echo $_GET['gender'] === 'أنثى' ? 'selected' : ''; ?>>أنثى</option></select>
                </div>
                <div class="field" style="flex:1;"><label>المدينة</label>
                    <select name="city"><option value="">الكل</option><?php while ($c = $cities->fetch_assoc()): ?><option value="<?php echo $c['city']; ?>" <?php echo ($_GET['city'] ?? '') === $c['city'] ? 'selected' : ''; ?>><?php echo $c['city']; ?></option><?php endwhile; ?></select>
                </div>
            </div>
            <div class="flex gap-2">
                <div class="field" style="flex:1;"><label>العمر من</label><input type="number" name="age_min" value="<?php echo $_GET['age_min'] ?? ''; ?>" placeholder="0"></div>
                <div class="field" style="flex:1;"><label>العمر إلى</label><input type="number" name="age_max" value="<?php echo $_GET['age_max'] ?? ''; ?>" placeholder="100"></div>
            </div>
            <div class="flex gap-2">
                <div class="field" style="flex:1;"><label>Wagner ≥</label><input type="number" name="wagner_min" value="<?php echo $_GET['wagner_min'] ?? ''; ?>" placeholder="0" min="0" max="5"></div>
                <div class="field" style="flex:1;"><label>HbA1c ≥</label><input type="number" step="0.1" name="hba1c_min" value="<?php echo $_GET['hba1c_min'] ?? ''; ?>" placeholder="0"></div>
            </div>
            <div class="flex gap-2">
                <div class="field" style="flex:1;"><label>نتيجة الشفاء</label>
                    <select name="healed"><option value="">الكل</option><option value="healed" <?php echo ($_GET['healed'] ?? '') === 'healed' ? 'selected' : ''; ?>>تم الشفاء</option><option value="not_healed" <?php echo ($_GET['healed'] ?? '') === 'not_healed' ? 'selected' : ''; ?>>لم يشفَ</option></select>
                </div>
                <div class="field" style="flex:1;"><label>التدخين</label>
                    <select name="smoking"><option value="">الكل</option><option value="مدخن" <?php echo ($_GET['smoking'] ?? '') === 'مدخن' ? 'selected' : ''; ?>>مدخن</option><option value="لا" <?php echo ($_GET['smoking'] ?? '') === 'لا' ? 'selected' : ''; ?>>غير مدخن</option></select>
                </div>
            </div>
            <div class="field"><label>بتر</label>
                <select name="has_amputation"><option value="">الكل</option><option value="yes" <?php echo ($_GET['has_amputation'] ?? '') === 'yes' ? 'selected' : ''; ?>>نعم</option></select>
            </div>
            <button type="submit" class="btn btn-primary">🔍 تطبيق الفلاتر</button>
            <a href="?" class="btn btn-sm btn-secondary">🔄 إعادة تعيين</a>
        </form>
    </div>

    <!-- Results -->
    <div style="flex:2;min-width:400px;">
        <?php if (isset($_GET['gender']) || isset($_GET['city']) || isset($_GET['age_min'])): ?>
        <!-- Cohort Summary -->
        <div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(100px,1fr));">
            <div class="stat-card"><div class="stat-value" style="font-size:1.2rem;color:var(--teal);"><?php echo $cohort_count; ?></div><div class="stat-label">عدد المرضى</div></div>
            <div class="stat-card"><div class="stat-value" style="font-size:1.2rem;color:var(--blue);"><?php echo $avg_age; ?></div><div class="stat-label">متوسط العمر</div></div>
            <div class="stat-card"><div class="stat-value" style="font-size:1.2rem;color:var(--red);"><?php echo $avg_hba1c_cohort; ?>%</div><div class="stat-label">متوسط HbA1c</div></div>
            <div class="stat-card"><div class="stat-value" style="font-size:1.2rem;color:var(--green);"><?php echo $healed_count; ?></div><div class="stat-label">تم شفاؤهم</div></div>
            <div class="stat-card"><div class="stat-value" style="font-size:1.2rem;color:var(--orange);"><?php echo $wagner3_count; ?></div><div class="stat-label">Wagner 3+</div></div>
            <div class="stat-card"><div class="stat-value" style="font-size:1.2rem;color:var(--purple);"><?php echo $smokers; ?></div><div class="stat-label">مدخنون</div></div>
            <div class="stat-card"><div class="stat-value" style="font-size:1.2rem;color:#7f1d1d;"><?php echo $amputations; ?></div><div class="stat-label">بتر</div></div>
        </div>

        <!-- Cohort Table -->
        <div class="card">
            <div class="card-header"><div class="card-title">📊 أفراد المجموعة</div></div>
            <div class="table-container" style="max-height:400px;overflow-y:auto;">
                <table>
                    <thead><tr><th>المريض</th><th>العمر</th><th>الجنس</th><th>المدينة</th><th>Wagner</th><th>HbA1c</th><th>تحسن</th><th>مدخن</th><th>زيارات</th></tr></thead>
                    <tbody>
                        <?php foreach ($cohort_rows as $r): ?>
                        <tr>
                            <td><a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $r['patient_id']; ?>" style="font-weight:600;"><?php echo escape_output($r['full_name']); ?></a></td>
                            <td><?php echo $r['age'] ?: '—'; ?></td>
                            <td><?php echo $r['gender']; ?></td>
                            <td><?php echo escape_output($r['city'] ?: '—'); ?></td>
                            <td><span class="badge badge-<?php echo ($r['wagner_grade'] ?? 0) >= 3 ? 'danger' : 'info'; ?>"><?php echo $r['wagner_grade'] ?? '—'; ?></span></td>
                            <td><?php echo $r['hba1c_value'] ?: '—'; ?></td>
                            <td><?php echo $r['improvement_percentage'] !== null ? $r['improvement_percentage'] . '%' : '—'; ?></td>
                            <td><?php echo $r['smoking_status'] === 'مدخن' ? '🚬' : '—'; ?></td>
                            <td><?php echo $r['visit_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($cohort_rows)): ?><tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:2rem;">لا توجد نتائج تطابق الفلاتر</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="card"><p class="text-muted text-center" style="padding:3rem;">اختر فلاتر من اليسار وابدأ البحث</p></div>
        <?php endif; ?>
    </div>
</div>

</div></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
