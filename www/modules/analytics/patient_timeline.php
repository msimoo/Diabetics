<?php
/**
 * Patient Timeline — Longitudinal view of all patient data over time
 */
$page_title = '📋 الجدول الزمني للمريض | Patient Timeline';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$patient_id) { header('Location: ' . BASE_URL . '/modules/patients/index.php'); exit; }

$patient = $mysqli->query("SELECT p.*, mh.diabetes_type, mh.diagnosis_year, mh.duration_years, mh.smoking_status, mh.physical_activity
    FROM patients p LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id WHERE p.patient_id = $patient_id")->fetch_assoc();
if (!$patient) { echo '<div class="app-layout"><div class="main-content"><div class="page-content"><p class="text-center text-muted">المريض غير موجود</p></div></div></div>'; require_once __DIR__ . '/../../includes/footer.php'; exit; }
?>
<style>
    .tl-card { border: 1px solid var(--border); border-radius: 12px; padding: 1rem; margin-bottom: 1rem; transition: var(--transition); }
    .tl-card:hover { box-shadow: var(--shadow-lg); }
    .tl-date { font-size: 11px; color: var(--text-muted); white-space: nowrap; }
    .tl-badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 700; }
    .timeline-connector { position: relative; padding-right: 30px; }
    .timeline-connector::before { content: ''; position: absolute; right: 8px; top: 0; bottom: 0; width: 2px; background: var(--border); }
    .timeline-dot { position: absolute; right: 2px; width: 14px; height: 14px; border-radius: 50%; border: 3px solid var(--teal); background: #fff; top: 20px; }
    .metric-change { font-size: 12px; padding: 2px 8px; border-radius: 4px; }
    .change-up { background: #fee2e2; color: #dc2626; }
    .change-down { background: #d1fae5; color: #059669; }
    .change-stable { background: #fef3c7; color: #d97706; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="page-header flex justify-between items-center flex-wrap gap-3">
    <div>
        <h1 class="page-title">📋 الجدول الزمني</h1>
        <p class="page-subtitle"><?php echo escape_output($patient['full_name']); ?> — 📁 <?php echo escape_output($patient['file_number']); ?></p>
    </div>
    <div class="flex gap-2">
        <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $patient_id; ?>" class="btn btn-sm btn-secondary">👤 ملف المريض</a>
        <button onclick="window.print()" class="btn btn-sm btn-secondary">🖨️ طباعة</button>
    </div>
</div>

<!-- Patient Summary -->
<div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));">
    <div class="stat-card"><div class="stat-value" style="font-size:1.1rem;color:var(--teal);"><?php echo $patient['age'] ?: '—'; ?> سنة</div><div class="stat-label">العمر</div></div>
    <div class="stat-card"><div class="stat-value" style="font-size:1.1rem;color:var(--blue);"><?php echo escape_output($patient['gender']); ?></div><div class="stat-label">الجنس</div></div>
    <div class="stat-card"><div class="stat-value" style="font-size:1.1rem;color:var(--gold);"><?php echo $patient['duration_years'] ? $patient['duration_years'] . ' سنة' : '—'; ?></div><div class="stat-label">مدة السكري</div></div>
    <div class="stat-card"><div class="stat-value" style="font-size:1.1rem;color:var(--orange);"><?php echo escape_output($patient['smoking_status'] ?: '—'); ?></div><div class="stat-label">التدخين</div></div>
    <div class="stat-card"><div class="stat-value" style="font-size:1.1rem;color:var(--purple);"><?php echo escape_output($patient['diabetes_type'] ?: '—'); ?></div><div class="stat-label">النوع</div></div>
</div>

<!-- Charts Row -->
<div class="flex flex-wrap gap-4 mb-4">
    <div class="card" style="flex:2;min-width:350px;">
        <div class="card-header"><div class="card-title">🩸 HbA1c & FPG Trend</div></div>
        <div style="height:220px;"><canvas id="hba1cTimelineChart" width="700" height="220"></canvas></div>
    </div>
    <div class="card" style="flex:1;min-width:250px;">
        <div class="card-header"><div class="card-title">📊 Wagner & Wound Size</div></div>
        <div style="height:220px;"><canvas id="woundTimelineChart" width="400" height="220"></canvas></div>
    </div>
</div>

<!-- Visit Timeline -->
<div class="card">
    <div class="card-header"><div class="card-title">📅 سجل الزيارات</div></div>
    <?php
    $visits = $mysqli->query("SELECT v.*, vs.weight, vs.bmi, vs.blood_pressure_systolic, vs.blood_pressure_diastolic,
        bs.hba1c_value, bs.fpg_value,
        fa.wagner_grade, fa.right_sensation, fa.left_sensation,
        fu.wound_size_cm2, fu.wound_condition, fu.wound_depth, fu.wound_foot,
        o.improvement_percentage, o.healing_date, o.current_amputation,
        t.treatment_type,
        u.full_name as doctor_name
        FROM visits v
        LEFT JOIN vital_signs vs ON v.visit_id = vs.visit_id
        LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
        LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
        LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
        LEFT JOIN outcomes o ON v.visit_id = o.visit_id
        LEFT JOIN treatments t ON v.visit_id = t.visit_id
        LEFT JOIN users u ON v.created_by = u.user_id
        WHERE v.patient_id = $patient_id
        ORDER BY v.visit_date DESC LIMIT 50");
    ?>
    <div class="timeline-connector" style="max-height:600px;overflow-y:auto;">
        <?php while ($v = $visits->fetch_assoc()): ?>
        <div class="tl-card" style="margin-right:20px;position:relative;">
            <div class="timeline-dot" style="background:<?php echo $v['wagner_grade'] >= 3 ? '#dc2626' : ($v['wagner_grade'] >= 2 ? '#f59e0b' : '#10b981'); ?>;"></div>
            <div class="flex justify-between items-start flex-wrap gap-2">
                <div>
                    <div class="flex gap-2 items-center flex-wrap">
                        <span class="tl-date">📅 <?php echo $v['visit_date']; ?></span>
                        <span class="tl-badge" style="background:var(--teal-pale);color:var(--teal);">#<?php echo $v['visit_number']; ?></span>
                        <span class="tl-badge" style="background:var(--bg-input);"><?php echo escape_output($v['visit_reason'] ?: 'متابعة'); ?></span>
                        <?php if ($v['doctor_name']): ?>
                        <span class="tl-badge" style="background:#eff6ff;color:#1d4ed8;font-size:10px;">👨‍⚕️ <?php echo escape_output($v['doctor_name']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($v['improvement_percentage'] !== null): ?>
                <span class="tl-badge" style="background:<?php echo $v['improvement_percentage'] >= 75 ? '#d1fae5;color:#059669' : ($v['improvement_percentage'] >= 50 ? '#fef3c7;color:#d97706' : '#fee2e2;color:#dc2626'); ?>;">
                    تحسن <?php echo $v['improvement_percentage']; ?>%
                </span>
                <?php endif; ?>
            </div>
            <div class="flex flex-wrap gap-3 mt-2" style="font-size:13px;">
                <?php if ($v['hba1c_value']): ?><span>🩸 HbA1c: <strong style="color:<?php echo $v['hba1c_value'] > 7 ? '#dc2626' : '#059669'; ?>;"><?php echo $v['hba1c_value']; ?>%</strong></span><?php endif; ?>
                <?php if ($v['fpg_value']): ?><span>🍬 FPG: <strong><?php echo $v['fpg_value']; ?></strong></span><?php endif; ?>
                <?php if ($v['wagner_grade'] !== null): ?><span>⚠️ Wagner: <strong style="color:<?php echo $v['wagner_grade'] >= 3 ? '#dc2626' : '#f59e0b'; ?>;"><?php echo $v['wagner_grade']; ?></strong></span><?php endif; ?>
                <?php if ($v['weight']): ?><span>⚖️ وزن: <strong><?php echo $v['weight']; ?></strong></span><?php endif; ?>
                <?php if ($v['bmi']): ?><span>📐 BMI: <strong><?php echo $v['bmi']; ?></strong></span><?php endif; ?>
                <?php if ($v['blood_pressure_systolic']): ?><span>💓 BP: <strong><?php echo $v['blood_pressure_systolic']; ?>/<?php echo $v['blood_pressure_diastolic']; ?></strong></span><?php endif; ?>
                <?php if ($v['wound_size_cm2']): ?><span>🩹 جرح: <strong><?php echo $v['wound_size_cm2']; ?> سم²</strong> (<?php echo escape_output($v['wound_foot']); ?>)</span><?php endif; ?>
                <?php if ($v['wound_condition']): ?><span class="tl-badge" style="background:<?php echo in_array($v['wound_condition'], ['متسخة','صديد']) ? '#fee2e2' : '#f0fdf4'; ?>;"><?php echo escape_output($v['wound_condition']); ?></span><?php endif; ?>
                <?php if ($v['treatment_type']): ?><span>💊 <strong><?php echo escape_output($v['treatment_type']); ?></strong></span><?php endif; ?>
                <?php if ($v['current_amputation'] && $v['current_amputation'] !== 'لا'): ?><span class="tl-badge" style="background:#7f1d1d;color:#fff;">🦶 بتر: <?php echo escape_output($v['current_amputation']); ?></span><?php endif; ?>
                <?php if ($v['healing_date']): ?><span class="tl-badge" style="background:#d1fae5;color:#059669;">✅ التئام: <?php echo $v['healing_date']; ?></span><?php endif; ?>
            </div>
            <?php if ($v['chief_complaint'] || $v['doctor_notes']): ?>
            <div class="mt-2" style="font-size:12px;color:var(--text-muted);background:var(--bg-input);padding:6px 10px;border-radius:8px;">
                <?php if ($v['chief_complaint']): ?><div>💬 <strong>الشكوى:</strong> <?php echo escape_output($v['chief_complaint']); ?></div><?php endif; ?>
                <?php if ($v['doctor_notes']): ?><div>📝 <strong>ملاحظات:</strong> <?php echo escape_output($v['doctor_notes']); ?></div><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    </div>
</div>

</div></div></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fetch timeline data
    fetch(BASE_URL + '/api/analytics.php?action=patient_timeline&patient_id=<?php echo $patient_id; ?>')
        .then(r => r.json())
        .then(data => {
            const tl = data.timeline;
            
            // HbA1c + FPG dual-axis chart
            if (tl.hba1c.length > 0) {
                const hba1cPoints = tl.hba1c.filter(d => !d.is_fpg);
                const fpgPoints = tl.hba1c.filter(d => d.is_fpg);
                const labels = [...new Set([...hba1cPoints, ...fpgPoints].map(d => d.date))].sort();
                
                if (labels.length > 0) {
                    const chart = new ClinicChart('hba1cTimelineChart');
                    chart.drawDualAxisChart(
                        labels.map(d => d.slice(5)),
                        labels.map(d => {
                            const match = hba1cPoints.filter(p => p.date === d);
                            return match.length > 0 ? match[0].value : 0;
                        }),
                        labels.map(d => {
                            const match = fpgPoints.filter(p => p.date === d);
                            return match.length > 0 ? match[0].value : 0;
                        }),
                        { leftColor: '#dc2626', rightColor: '#3b82f6', leftLabel: 'HbA1c %', rightLabel: 'FPG' }
                    );
                }
            }

            // Wagner + Wound size dual-axis
            if (tl.wagner.length > 0) {
                const wLabels = [...new Set(tl.wagner.map(d => d.date))].sort();
                const woundData = tl.wound_size || [];
                const chart = new ClinicChart('woundTimelineChart');
                chart.drawDualAxisChart(
                    wLabels.map(d => d.slice(5)),
                    wLabels.map(d => {
                        const match = tl.wagner.filter(p => p.date === d);
                        return match.length > 0 ? match[0].value : 0;
                    }),
                    wLabels.map(d => {
                        const match = woundData.filter(p => p.date === d);
                        return match.length > 0 ? match[0].value : 0;
                    }),
                    { leftColor: '#f59e0b', rightColor: '#ef4444', leftLabel: 'Wagner', rightLabel: 'حجم الجرح' }
                );
            }
        })
        .catch(() => {});
});
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
