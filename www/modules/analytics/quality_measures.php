<?php
/**
 * Quality Measure Dashboard - Track clinical quality against standards
 */
$page_title = '📋 مقاييس الجودة | Quality Measures';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$from_date = $mysqli->real_escape_string($_GET['from_date'] ?? date('Y-m-d', strtotime('-12 months')));
$to_date = $mysqli->real_escape_string($_GET['to_date'] ?? date('Y-m-d'));

// Quality Measure 1: HbA1c Control (< 7%)
$q_hba1c = $mysqli->query("SELECT 
    COUNT(DISTINCT CASE WHEN bs.hba1c_value < 7 THEN v.patient_id END) as numerator,
    COUNT(DISTINCT v.patient_id) as denominator,
    ROUND(COUNT(DISTINCT CASE WHEN bs.hba1c_value < 7 THEN v.patient_id END) / NULLIF(COUNT(DISTINCT v.patient_id), 0) * 100, 1) as rate
FROM visits v JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
WHERE bs.hba1c_value IS NOT NULL AND v.visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc();

// Quality Measure 2: BP Control (< 140/90)
$q_bp = $mysqli->query("SELECT 
    COUNT(DISTINCT CASE WHEN vs.blood_pressure_systolic < 140 AND vs.blood_pressure_diastolic < 90 THEN v.patient_id END) as numerator,
    COUNT(DISTINCT CASE WHEN vs.blood_pressure_systolic IS NOT NULL THEN v.patient_id END) as denominator,
    ROUND(COUNT(DISTINCT CASE WHEN vs.blood_pressure_systolic < 140 AND vs.blood_pressure_diastolic < 90 THEN v.patient_id END) / NULLIF(COUNT(DISTINCT CASE WHEN vs.blood_pressure_systolic IS NOT NULL THEN v.patient_id END), 0) * 100, 1) as rate
FROM visits v JOIN vital_signs vs ON v.visit_id = vs.visit_id
WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc();

// Quality Measure 3: LDL Control (< 100)
$q_ldl = $mysqli->query("SELECT 
    COUNT(DISTINCT CASE WHEN lr.ldl < 100 THEN v.patient_id END) as numerator,
    COUNT(DISTINCT CASE WHEN lr.ldl IS NOT NULL THEN v.patient_id END) as denominator,
    ROUND(COUNT(DISTINCT CASE WHEN lr.ldl < 100 THEN v.patient_id END) / NULLIF(COUNT(DISTINCT CASE WHEN lr.ldl IS NOT NULL THEN v.patient_id END), 0) * 100, 1) as rate
FROM visits v JOIN lab_results lr ON v.visit_id = lr.visit_id
WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc();

// Quality Measure 4: Annual Foot Exam
$q_foot = $mysqli->query("SELECT 
    COUNT(DISTINCT v.patient_id) as denominator,
    COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.patient_id END) as numerator,
    ROUND(COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.patient_id END) / NULLIF(COUNT(DISTINCT v.patient_id), 0) * 100, 1) as rate
FROM visits v LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc();

// Quality Measure 5: Smoking Cessation Counseling
$q_smoking = $mysqli->query("SELECT 
    COUNT(CASE WHEN mh.smoking_status = 'مدخن' THEN 1 END) as smokers,
    COUNT(*) as total,
    ROUND(COUNT(CASE WHEN mh.smoking_status = 'مدخن' THEN 1 END) / NULLIF(COUNT(*), 0) * 100, 1) as rate
FROM medical_history mh")->fetch_assoc();

// Quality Measure 6: Healing Rate
$q_healing = $mysqli->query("SELECT 
    COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN v.patient_id END) as numerator,
    COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.patient_id END) as denominator,
    ROUND(COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 100 THEN v.patient_id END) / NULLIF(COUNT(DISTINCT CASE WHEN fa.assessment_id IS NOT NULL THEN v.patient_id END), 0) * 100, 1) as rate
FROM visits v LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id LEFT JOIN outcomes o ON v.visit_id = o.visit_id
WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc();

// Composite Score
$measures = [
    ['id' => 'hba1c', 'label' => 'التحكم بـ HbA1c', 'target' => 50, 'rate' => (float)($q_hba1c['rate'] ?? 0), 'standard' => 'ADA: <7%'],
    ['id' => 'bp', 'label' => 'التحكم بالضغط', 'target' => 60, 'rate' => (float)($q_bp['rate'] ?? 0), 'standard' => 'ADA: <140/90'],
    ['id' => 'ldl', 'label' => 'التحكم بـ LDL', 'target' => 50, 'rate' => (float)($q_ldl['rate'] ?? 0), 'standard' => 'ADA: <100 mg/dL'],
    ['id' => 'foot', 'label' => 'فحص القدم السنوي', 'target' => 80, 'rate' => (float)($q_foot['rate'] ?? 0), 'standard' => 'ADA: سنوياً'],
    ['id' => 'healing', 'label' => 'معدل الشفاء', 'target' => 70, 'rate' => (float)($q_healing['rate'] ?? 0), 'standard' => 'الهدف: ≥70%'],
];

$composite = round(array_sum(array_column($measures, 'rate')) / count($measures), 1);
?>
<style>
    .q-card { padding: 1.2rem; border-radius: 14px; border: 1px solid var(--border); transition: var(--transition); }
    .q-card:hover { box-shadow: var(--shadow-lg); }
    .q-ring { width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; font-weight: 800; margin: 0 auto; }
    .q-target-bar { height: 4px; border-radius: 2px; background: var(--border); margin-top: 8px; overflow: hidden; }
    .q-target-fill { height: 100%; border-radius: 2px; position: relative; }
    .q-target-marker { position: absolute; top: -6px; width: 2px; height: 16px; background: var(--red); }
    .composite-score { text-align: center; padding: 2rem; border-radius: 16px; background: linear-gradient(135deg, var(--teal), var(--teal-light)); color: #fff; }
    .composite-score .score { font-size: 3rem; font-weight: 900; }
    .score-grade { display: inline-block; padding: 4px 16px; border-radius: 20px; font-weight: 700; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="page-header"><h1 class="page-title">📋 مقاييس الجودة السريرية</h1><p class="page-subtitle">Quality Measures — تتبع معايير ADA وجودة الرعاية</p></div>

<!-- Composite Score -->
<div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr));">
    <div class="composite-score">
        <div class="score"><?php echo $composite; ?>%</div>
        <div>النتيجة المركبة للجودة</div>
        <div class="mt-2 score-grade" style="background:rgba(255,255,255,0.2);">
            <?php echo $composite >= 70 ? '🟢 ممتاز' : ($composite >= 50 ? '🟡 مقبول' : '🔴 يحتاج تحسين'); ?>
        </div>
    </div>
    <?php foreach ($measures as $m): 
        $color = $m['rate'] >= $m['target'] ? '#10b981' : ($m['rate'] >= $m['target'] * 0.7 ? '#f59e0b' : '#ef4444');
        $score = min($m['rate'] / $m['target'] * 100, 100);
    ?>
    <div class="q-card" style="text-align:center;">
        <div class="q-ring" style="border:4px solid <?php echo $color; ?>;color:<?php echo $color; ?>;"><?php echo $m['rate']; ?>%</div>
        <div style="font-weight:700;margin-top:6px;"><?php echo $m['label']; ?></div>
        <div style="font-size:11px;color:var(--text-muted);">الهدف: <?php echo $m['target']; ?>%</div>
        <div style="font-size:10px;color:var(--text-light);"><?php echo $m['standard']; ?></div>
        <div class="q-target-bar">
            <div class="q-target-fill" style="width:<?php echo min($m['rate'], 100); ?>%;background:<?php echo $color; ?>;">
                <div class="q-target-marker" style="right:<?php echo $m['target']; ?>%;"></div>
            </div>
        </div>
        <div style="font-size:10px;margin-top:4px;color:var(--text-muted);">
            <?php echo $score >= 100 ? '✅ هدف محقق' : "🎯 {$score}% من الهدف"; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- QI Smart Recommendations -->
<div class="card mb-4" style="background:linear-gradient(135deg,#f8fafc,#e2e8f0);border:1px solid #cbd5e1;">
    <div class="card-header">
        <div class="card-title">💡 توصيات جودة ذكية</div>
        <span style="font-size:12px;color:var(--text-muted);">QI Smart Recommendations — استناداً لتحليل الفجوات</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:6px;">
        <?php
        $qi_recs_generated = false;
        foreach ($measures as $m):
            $gap = $m['target'] - $m['rate'];
            if ($gap > 0):
                $qi_recs_generated = true;
                $priority = $gap >= 20 ? 'عالية' : ($gap >= 10 ? 'متوسطة' : 'منخفضة');
                $pcolor = $gap >= 20 ? '#ef4444' : ($gap >= 10 ? '#f59e0b' : '#3b82f6');
                $action = '';
                switch ($m['id']) {
                    case 'hba1c': $action = 'تكثيف العلاج الدوائي — مراجعة جرعات الأنسولين، إضافة مثبطات SGLT2/GIP-1، تحسين النظام الغذائي'; break;
                    case 'bp': $action = 'مراجعة الأدوية الخافضة للضغط — إضافة مثبطات ACE أو ARBs، حصر الصوديوم'; break;
                    case 'ldl': $action = 'بدء/تكثيف الستاتينات — استهداف LDL <100، إضافة إيزيتيميب عند الحاجة'; break;
                    case 'foot': $action = 'تفعيل نظام تذكير بمواعيد فحص القدم — تثقيف المريض بأهمية الفحص السنوي'; break;
                    case 'healing': $action = 'تحسين بروتوكول العناية بالجروح — تقييم الحالة الغذائية، مراجعة المضادات الحيوية، تفريغ الضغط'; break;
                }
        ?>
        <div style="display:flex;align-items:flex-start;gap:0.8rem;padding:0.7rem 1rem;background:#fff;border-radius:10px;border-right:4px solid <?php echo $pcolor; ?>;">
            <span style="font-size:1.3rem;"><?php echo $gap >= 20 ? '🔴' : ($gap >= 10 ? '🟡' : '🔵'); ?></span>
            <div style="flex:1;">
                <div style="font-weight:700;font-size:13px;"><?php echo $m['label']; ?> — فجوة <?php echo round($gap, 1); ?>% <span style="font-size:11px;color:<?php echo $pcolor; ?>;">(أولوية <?php echo $priority; ?>)</span></div>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?php echo $action; ?></div>
            </div>
            <span style="font-size:11px;font-weight:600;color:<?php echo $pcolor; ?>;white-space:nowrap;"><?php echo $m['rate']; ?>% ← <?php echo $m['target']; ?>%</span>
        </div>
        <?php endif; endforeach; ?>
        <?php if (!$qi_recs_generated): ?>
        <div style="text-align:center;padding:1.5rem;color:var(--text-muted);">
            🎉 جميع مقاييس الجودة عند الهدف أو تتجاوزه — عمل ممتاز!
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Detailed Table -->
<div class="card">
    <div class="card-header"><div class="card-title">📊 تفاصيل المقاييس</div></div>
    <table>
        <thead><tr><th>المقياس</th><th>النتيجة</th><th>الهدف</th><th>الفجوة</th><th>الحالة</th></tr></thead>
        <tbody>
            <?php foreach ($measures as $m): 
                $gap = round($m['target'] - $m['rate'], 1);
                $gap_display = $gap <= 0 ? '✅' : "⚠️ يحتاج +{$gap}%";
                $status = $gap <= 0 ? '✅ محقق' : ($gap < 15 ? '🟡 قريب' : '🔴 فجوة كبيرة');
            ?>
            <tr>
                <td style="font-weight:600;"><?php echo $m['label']; ?></td>
                <td><strong><?php echo $m['rate']; ?>%</strong></td>
                <td><?php echo $m['target']; ?>%</td>
                <td><?php echo $gap_display; ?></td>
                <td><span class="badge badge-<?php echo $gap <= 0 ? 'success' : ($gap < 15 ? 'warning' : 'danger'); ?>"><?php echo $status; ?></span></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</div>
</div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
