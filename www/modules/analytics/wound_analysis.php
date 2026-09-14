<?php
/**
 * Foot & Wound Detailed Analysis — تحليل تفصيلي لحالات القدم والجروح
 * Comprehensive analysis dashboard for foot cases, wounds, assessments, awareness, and education
 */
$page_title = 'تحليل الجروح والقدم | Wound Analysis';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Date filters
$from_date = $mysqli->real_escape_string($_GET['from_date'] ?? date('Y-m-d', strtotime('-12 months')));
$to_date = $mysqli->real_escape_string($_GET['to_date'] ?? date('Y-m-d'));

// ===== 1. WOUND STATISTICS =====

// Total wound cases in period
$total_wound_cases = $mysqli->query("
    SELECT COUNT(DISTINCT v.patient_id) as cnt 
    FROM visits v JOIN foot_ulcers fu ON v.visit_id = fu.visit_id 
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

// Total foot assessments in period
$total_assessments = $mysqli->query("
    SELECT COUNT(*) as cnt FROM foot_assessments fa 
    JOIN visits v ON fa.visit_id = v.visit_id 
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

// Wound cause distribution
$cause_dist = [];
$result = $mysqli->query("SELECT fu.initial_cause, COUNT(*) as cnt 
    FROM foot_ulcers fu JOIN visits v ON fu.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND fu.initial_cause IS NOT NULL AND fu.initial_cause != ''
    GROUP BY fu.initial_cause ORDER BY cnt DESC");
while ($row = $result->fetch_assoc()) { $cause_dist[] = $row; }

// Wound foot distribution (right vs left)
$foot_dist = $mysqli->query("SELECT wound_foot, COUNT(*) as cnt 
    FROM foot_ulcers fu JOIN visits v ON fu.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND wound_foot IS NOT NULL
    GROUP BY wound_foot")->fetch_all(MYSQLI_ASSOC);

// Wound depth distribution
$depth_dist = [];
$result = $mysqli->query("SELECT fu.wound_depth, COUNT(*) as cnt 
    FROM foot_ulcers fu JOIN visits v ON fu.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND fu.wound_depth IS NOT NULL AND fu.wound_depth != ''
    GROUP BY fu.wound_depth ORDER BY cnt DESC");
while ($row = $result->fetch_assoc()) { $depth_dist[] = $row; }

// Wound condition distribution
$condition_dist = [];
$result = $mysqli->query("SELECT fu.wound_condition, COUNT(*) as cnt 
    FROM foot_ulcers fu JOIN visits v ON fu.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND fu.wound_condition IS NOT NULL AND fu.wound_condition != ''
    GROUP BY fu.wound_condition ORDER BY cnt DESC");
while ($row = $result->fetch_assoc()) { $condition_dist[] = $row; }

// Average wound size
$avg_wound_size = $mysqli->query("
    SELECT AVG(fu.wound_size_cm2) as avg_size 
    FROM foot_ulcers fu JOIN visits v ON fu.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND fu.wound_size_cm2 IS NOT NULL
")->fetch_assoc()['avg_size'];

// ===== 2. WAGNER GRADE ANALYSIS =====

$wagner_data = [];
$result = $mysqli->query("SELECT fa.wagner_grade, COUNT(*) as cnt 
    FROM foot_assessments fa JOIN visits v ON fa.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND fa.wagner_grade IS NOT NULL
    GROUP BY fa.wagner_grade ORDER BY fa.wagner_grade");
while ($row = $result->fetch_assoc()) { $wagner_data[] = $row; }

// Wagner >= 3 count (high risk)
$high_risk_count = 0;
foreach ($wagner_data as $w) { if ($w['wagner_grade'] >= 3) $high_risk_count += $w['cnt']; }

// ===== 3. HEALING / OUTCOME STATISTICS =====

// Healing outcomes
$healed_count = $mysqli->query("SELECT COUNT(*) as cnt FROM outcomes o 
    JOIN visits v ON o.visit_id = v.visit_id
    WHERE o.improvement_percentage = 100 AND v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

$improved_count = $mysqli->query("SELECT COUNT(*) as cnt FROM outcomes o 
    JOIN visits v ON o.visit_id = v.visit_id
    WHERE o.improvement_percentage >= 50 AND o.improvement_percentage < 100 
    AND v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

$no_improvement_count = $mysqli->query("SELECT COUNT(*) as cnt FROM outcomes o 
    JOIN visits v ON o.visit_id = v.visit_id
    WHERE (o.improvement_percentage < 50 OR o.improvement_percentage IS NULL) 
    AND v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

// Amputation count
$amputation_count = $mysqli->query("SELECT COUNT(*) as cnt FROM outcomes 
    WHERE current_amputation IS NOT NULL AND current_amputation != 'لا'
    AND current_amputation_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

// Average healing time (days) for healed cases
$avg_healing_days = $mysqli->query("
    SELECT AVG(DATEDIFF(o.healing_date, v.visit_date)) as avg_days
    FROM outcomes o JOIN visits v ON o.visit_id = v.visit_id
    WHERE o.healing_date IS NOT NULL AND o.improvement_percentage = 100
    AND v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['avg_days'];

// Referral count
$referral_count = $mysqli->query("SELECT COUNT(*) as cnt FROM outcomes 
    WHERE referral_to IS NOT NULL AND referral_to != ''
    AND outcome_id IN (SELECT outcome_id FROM outcomes o JOIN visits v ON o.visit_id = v.visit_id WHERE v.visit_date BETWEEN '$from_date' AND '$to_date')
")->fetch_assoc()['cnt'];

// ===== 4. SENSATION & PULSE ANALYSIS =====

$sensation_data = [];
$result = $mysqli->query("
    SELECT 'يمنى' as foot, right_sensation as sensation, COUNT(*) as cnt 
    FROM foot_assessments fa JOIN visits v ON fa.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND fa.right_sensation IS NOT NULL
    GROUP BY right_sensation
    UNION ALL
    SELECT 'يسرى' as foot, left_sensation as sensation, COUNT(*) as cnt 
    FROM foot_assessments fa JOIN visits v ON fa.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND fa.left_sensation IS NOT NULL
    GROUP BY left_sensation
");
while ($row = $result->fetch_assoc()) { $sensation_data[] = $row; }

$pulse_data = [];
$result = $mysqli->query("
    SELECT 'يمنى' as foot, right_pulse as pulse, COUNT(*) as cnt 
    FROM foot_assessments fa JOIN visits v ON fa.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND fa.right_pulse IS NOT NULL
    GROUP BY right_pulse
    UNION ALL
    SELECT 'يسرى' as foot, left_pulse as pulse, COUNT(*) as cnt 
    FROM foot_assessments fa JOIN visits v ON fa.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND fa.left_pulse IS NOT NULL
    GROUP BY left_pulse
");
while ($row = $result->fetch_assoc()) { $pulse_data[] = $row; }

// ===== 5. INFECTION & COMPLICATION STATS =====

$infection_dist = [];
$result = $mysqli->query("SELECT o.infection_status, COUNT(*) as cnt 
    FROM outcomes o JOIN visits v ON o.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND o.infection_status IS NOT NULL AND o.infection_status != ''
    GROUP BY o.infection_status ORDER BY cnt DESC");
while ($row = $result->fetch_assoc()) { $infection_dist[] = $row; }

$hosp_count = $mysqli->query("SELECT COUNT(*) as cnt FROM outcomes o 
    JOIN visits v ON o.visit_id = v.visit_id
    WHERE o.hospitalization_required = 1 AND v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

// ===== 6. DEFORMITIES ANALYSIS =====
$deformities_data = [];
$result = $mysqli->query("
    SELECT 'يمنى' as foot, right_deformities as deformities 
    FROM foot_assessments fa JOIN visits v ON fa.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND fa.right_deformities IS NOT NULL AND fa.right_deformities != ''
    UNION ALL
    SELECT 'يسرى' as foot, left_deformities as deformities 
    FROM foot_assessments fa JOIN visits v ON fa.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' AND fa.left_deformities IS NOT NULL AND fa.left_deformities != ''
");
$deformity_counts = [];
while ($row = $result->fetch_assoc()) {
    $items = explode(',', $row['deformities']);
    foreach ($items as $item) {
        $item = trim($item);
        if ($item) {
            $deformity_counts[$item] = ($deformity_counts[$item] ?? 0) + 1;
        }
    }
}
arsort($deformity_counts);

// ===== 7. AWARENESS & EDUCATION METRICS =====
// Count of education sessions from care_plan
$education_count = $mysqli->query("
    SELECT COUNT(*) as cnt FROM care_plan cp 
    JOIN visits v ON cp.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date' 
    AND (cp.patient_education_notes IS NOT NULL AND cp.patient_education_notes != '')
")->fetch_assoc()['cnt'];

$care_plan_count = $mysqli->query("SELECT COUNT(*) as cnt FROM care_plan cp 
    JOIN visits v ON cp.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

$education_percent = $care_plan_count > 0 ? round(($education_count / $care_plan_count) * 100, 1) : 0;

// Monthly wound cases trend
$monthly_wounds = [];
$result = $mysqli->query("SELECT DATE_FORMAT(v.visit_date, '%Y-%m') as month, COUNT(DISTINCT fu.ulcer_id) as cnt 
    FROM foot_ulcers fu JOIN visits v ON fu.visit_id = v.visit_id
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY DATE_FORMAT(v.visit_date, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) { $monthly_wounds[] = $row; }

// Monthly healing trend
$monthly_healings = [];
$result = $mysqli->query("SELECT DATE_FORMAT(o.healing_date, '%Y-%m') as month, COUNT(*) as cnt 
    FROM outcomes o JOIN visits v ON o.visit_id = v.visit_id
    WHERE o.healing_date BETWEEN '$from_date' AND '$to_date' AND o.improvement_percentage = 100
    GROUP BY DATE_FORMAT(o.healing_date, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) { $monthly_healings[] = $row; }

// Foot care guide view count (from education module)
$awareness_topics = [
    ['topic' => 'العناية بالقدم لمرضى السكري', 'icon' => '🦶', 'link' => 'foot_care_guide.php'],
    ['topic' => 'النظام الغذائي المناسب', 'icon' => '🥗', 'link' => 'diet_plan.php'],
    ['topic' => 'أساسيات مرض السكري', 'icon' => '📖', 'link' => 'diabetes_basics.php'],
    ['topic' => 'التمارين الرياضية', 'icon' => '🏃', 'link' => 'exercise_guide.php'],
    ['topic' => 'العناية المنزلية', 'icon' => '🏠', 'link' => 'home_care.php'],
    ['topic' => 'دليل الأدوية', 'icon' => '💊', 'link' => 'medication_guide.php'],
    ['topic' => 'حالات الطوارئ', 'icon' => '🚨', 'link' => 'emergency.php'],
];
?>
<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<!-- Header -->
<div class="page-header flex flex-wrap gap-3 items-center justify-between">
    <div>
        <h1 class="page-title">🦶 تحليل حالات القدم والجروح</h1>
        <p class="page-subtitle">Foot & Wound Analysis — تحليل شامل لحالات القدم، الجروح، التقييم، التوعية، والتعليم</p>
    </div>
    <form method="get" class="flex flex-wrap gap-2 items-center">
        <label style="font-size:12px;color:var(--gray);">من</label>
        <input type="date" name="from_date" value="<?php echo $from_date; ?>" style="padding:5px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:'Tajawal',sans-serif;">
        <label style="font-size:12px;color:var(--gray);">إلى</label>
        <input type="date" name="to_date" value="<?php echo $to_date; ?>" style="padding:5px 8px;border:1px solid var(--border);border-radius:6px;font-size:13px;font-family:'Tajawal',sans-serif;">
        <button type="submit" class="btn btn-sm btn-primary">تحديث</button>
    </form>
</div>

<!-- KPI Cards -->
<div class="stats-grid">
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#0a7e6e,#13a896);"></div>
        <div class="stat-icon" style="background:#e0f5f2;color:#0a7e6e;">🩹</div>
        <div class="stat-value" style="color:#0a7e6e;"><?php echo $total_wound_cases; ?></div>
        <div class="stat-label">إجمالي حالات الجروح</div>
        <div class="stat-sub">Total Wound Cases</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#3b82f6,#1d4ed8);"></div>
        <div class="stat-icon" style="background:#eff6ff;color:#3b82f6;">🔬</div>
        <div class="stat-value" style="color:#1d4ed8;"><?php echo $total_assessments; ?></div>
        <div class="stat-label">تقييمات القدم</div>
        <div class="stat-sub">Foot Assessments</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#10b981,#059669);"></div>
        <div class="stat-icon" style="background:#ecfdf5;color:#10b981;">✅</div>
        <div class="stat-value" style="color:#059669;"><?php echo $healed_count; ?></div>
        <div class="stat-label">تم شفاؤها بالكامل</div>
        <div class="stat-sub">Fully Healed</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#ef4444,#dc2626);"></div>
        <div class="stat-icon" style="background:#fef2f2;color:#ef4444;">🆘</div>
        <div class="stat-value" style="color:#dc2626;"><?php echo $high_risk_count; ?></div>
        <div class="stat-label">حالات حرجة (Wagner ≥ 3)</div>
        <div class="stat-sub">High Risk</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#8b5cf6,#7c3aed);"></div>
        <div class="stat-icon" style="background:#f5f3ff;color:#8b5cf6;">🦿</div>
        <div class="stat-value" style="color:#7c3aed;"><?php echo $amputation_count; ?></div>
        <div class="stat-label">حالات البتر</div>
        <div class="stat-sub">Amputations</div>
    </div>
    <div class="stat-card card-fade-in">
        <div class="stat-bar" style="background:linear-gradient(90deg,#f59e0b,#d97706);"></div>
        <div class="stat-icon" style="background:#fffbeb;color:#f59e0b;">⏱️</div>
        <div class="stat-value" style="color:#d97706;"><?php echo $avg_healing_days ? round($avg_healing_days, 1) : '—'; ?></div>
        <div class="stat-label">متوسط أيام الشفاء</div>
        <div class="stat-sub">Avg Healing Days</div>
    </div>
</div>

<!-- === SECTION 1: WOUND ANALYSIS === -->
<div class="card mb-4">
    <div class="card-header">
        <div class="card-title" style="font-size:18px;">🩹 تحليل الجروح — Wound Analysis</div>
    </div>
    
    <div class="flex flex-wrap gap-4">
        <!-- Wound Causes -->
        <div style="flex:1;min-width:250px;">
            <h4 style="color:var(--teal);font-weight:800;margin-bottom:10px;font-size:14px;">📊 توزيع أسباب الجروح</h4>
            <?php if ($cause_dist): ?>
            <div style="display:flex;flex-direction:column;gap:5px;">
                <?php $total_causes = array_sum(array_column($cause_dist, 'cnt')); ?>
                <?php foreach ($cause_dist as $c): 
                    $pct = round(($c['cnt'] / $total_causes) * 100, 1);
                ?>
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:2px;">
                        <span><?php echo escape_output($c['initial_cause']); ?></span>
                        <span><strong><?php echo $c['cnt']; ?></strong> (<?php echo $pct; ?>%)</span>
                    </div>
                    <div style="height:6px;background:var(--bg-input);border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?php echo $pct; ?>%;background:linear-gradient(90deg,#0a7e6e,#13a896);border-radius:3px;transition:width 0.5s ease;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:#94a3b8;">لا توجد بيانات كافية</p>
            <?php endif; ?>
        </div>

        <!-- Wound Depth -->
        <div style="flex:1;min-width:200px;">
            <h4 style="color:var(--teal);font-weight:800;margin-bottom:10px;font-size:14px;">📏 توزيع عمق الجروح</h4>
            <?php if ($depth_dist): ?>
            <div style="display:flex;flex-direction:column;gap:5px;">
                <?php $total_depth = array_sum(array_column($depth_dist, 'cnt')); ?>
                <?php foreach ($depth_dist as $d): 
                    $pct = round(($d['cnt'] / $total_depth) * 100, 1);
                    $colors = ['سطحي في الجلد' => '#10b981', 'الجلد وتحت الجلد' => '#f59e0b', 'العضلات' => '#f97316', 'العظم' => '#ef4444'];
                    $color = $colors[$d['wound_depth']] ?? '#6b7280';
                ?>
                <div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:2px;">
                        <span><?php echo escape_output($d['wound_depth']); ?></span>
                        <span><strong><?php echo $d['cnt']; ?></strong> (<?php echo $pct; ?>%)</span>
                    </div>
                    <div style="height:6px;background:var(--bg-input);border-radius:3px;overflow:hidden;">
                        <div style="height:100%;width:<?php echo $pct; ?>%;background:<?php echo $color; ?>;border-radius:3px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:#94a3b8;">لا توجد بيانات كافية</p>
            <?php endif; ?>
        </div>

        <!-- Wound Condition -->
        <div style="flex:1;min-width:200px;">
            <h4 style="color:var(--teal);font-weight:800;margin-bottom:10px;font-size:14px;">🩸 حالة الجروح</h4>
            <?php if ($condition_dist): ?>
            <div style="display:flex;flex-direction:column;gap:5px;">
                <?php foreach ($condition_dist as $cd): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;background:var(--bg-input);border-radius:6px;">
                    <span style="font-size:13px;"><?php echo escape_output($cd['wound_condition']); ?></span>
                    <span class="badge badge-info"><?php echo $cd['cnt']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:#94a3b8;">لا توجد بيانات كافية</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Wound Trends -->
    <div class="flex flex-wrap gap-4 mt-4">
        <div style="flex:1;min-width:300px;">
            <h4 style="color:var(--teal);font-weight:800;margin-bottom:10px;font-size:14px;">📈 اتجاه حالات الجروح الشهرية</h4>
            <div style="height:150px;"><canvas id="woundTrendChart" width="500" height="150"></canvas></div>
        </div>
        <div style="flex:1;min-width:200px;">
            <h4 style="color:var(--teal);font-weight:800;margin-bottom:10px;font-size:14px;">🦶 توزيع القدم المصابة</h4>
            <?php if ($foot_dist): ?>
            <div style="display:flex;gap:12px;padding:8px 0;">
                <?php foreach ($foot_dist as $fd): ?>
                <div style="flex:1;text-align:center;padding:12px;background:var(--teal-pale);border-radius:10px;">
                    <div style="font-size:24px;margin-bottom:4px;">🦶</div>
                    <div style="font-weight:800;font-size:20px;color:var(--teal);"><?php echo $fd['cnt']; ?></div>
                    <div style="font-size:12px;color:var(--gray);"><?php echo $fd['wound_foot']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="color:#94a3b8;">لا توجد بيانات</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- === SECTION 2: WAGNER & HEALING === -->
<div class="flex flex-wrap gap-4 mb-4">
    <!-- Wagner Distribution -->
    <div class="card" style="flex:2;min-width:300px;">
        <div class="card-header">
            <div class="card-title">📊 توزيع درجات Wagner</div>
        </div>
        <div style="height:200px;"><canvas id="wagnerChart" width="500" height="200"></canvas></div>
    </div>

    <!-- Healing Outcomes -->
    <div class="card" style="flex:1;min-width:250px;">
        <div class="card-header">
            <div class="card-title">📈 نتائج العلاج</div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;padding:4px 0;">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#ecfdf5;border-radius:8px;">
                <span style="font-size:13px;">✅ تم الشفاء (100%)</span>
                <span style="font-weight:800;color:#059669;font-size:18px;"><?php echo $healed_count; ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#fffbeb;border-radius:8px;">
                <span style="font-size:13px;">📈 تحسن ملحوظ (≥50%)</span>
                <span style="font-weight:800;color:#d97706;font-size:18px;"><?php echo $improved_count; ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#fef2f2;border-radius:8px;">
                <span style="font-size:13px;">⚠️ لا تحسن (&lt;50%)</span>
                <span style="font-weight:800;color:#dc2626;font-size:18px;"><?php echo $no_improvement_count; ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#f5f3ff;border-radius:8px;">
                <span style="font-size:13px;">🏥 تم التحويل</span>
                <span style="font-weight:800;color:#7c3aed;font-size:18px;"><?php echo $referral_count; ?></span>
            </div>
        </div>
    </div>
</div>

<!-- === SECTION 3: SENSATION & PULSE === -->
<div class="flex flex-wrap gap-4 mb-4">
    <div class="card" style="flex:1;min-width:280px;">
        <div class="card-header"><div class="card-title">🔬 توزيع الإحساس (Sensation)</div></div>
        <div style="height:180px;"><canvas id="sensationChart" width="400" height="180"></canvas></div>
    </div>
    <div class="card" style="flex:1;min-width:280px;">
        <div class="card-header"><div class="card-title">🩺 توزيع النبض (Pulse)</div></div>
        <div style="height:180px;"><canvas id="pulseChart" width="400" height="180"></canvas></div>
    </div>
    <div class="card" style="flex:1;min-width:250px;">
        <div class="card-header"><div class="card-title">🔧 التشوهات الأكثر شيوعاً</div></div>
        <?php if ($deformity_counts): ?>
        <div style="display:flex;flex-direction:column;gap:4px;">
            <?php $i = 0; foreach ($deformity_counts as $def => $cnt): if ($i >= 6) break; $i++; ?>
            <div style="display:flex;justify-content:space-between;font-size:13px;padding:4px 0;border-bottom:1px solid var(--border);">
                <span><?php echo escape_output($def); ?></span>
                <span class="badge badge-info"><?php echo $cnt; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color:#94a3b8;">لا توجد بيانات</p>
        <?php endif; ?>
    </div>
</div>

<!-- === SECTION 4: INFECTION & COMPLICATIONS === -->
<div class="flex flex-wrap gap-4 mb-4">
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title">⚠️ تحليل الالتهابات والمضاعفات</div></div>
        <?php if ($infection_dist): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:8px;">
            <?php foreach ($infection_dist as $inf): ?>
            <div style="text-align:center;padding:10px;background:var(--bg-input);border-radius:8px;border:1px solid var(--border);">
                <div style="font-size:18px;font-weight:800;color:<?php echo $inf['infection_status'] === 'لا توجد' ? '#059669' : '#dc2626'; ?>;">
                    <?php echo $inf['cnt']; ?>
                </div>
                <div style="font-size:11px;color:var(--gray);"><?php echo escape_output($inf['infection_status']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color:#94a3b8;">لا توجد بيانات التهابات</p>
        <?php endif; ?>
        <div style="margin-top:10px;padding:8px 12px;background:#fef2f2;border-radius:8px;display:flex;justify-content:space-between;align-items:center;">
            <span style="font-size:13px;">🏥 تطلب تنويم في المستشفى</span>
            <span style="font-weight:800;color:#dc2626;font-size:18px;"><?php echo $hosp_count; ?></span>
        </div>
    </div>
    <div class="card" style="flex:1;min-width:250px;">
        <div class="card-header"><div class="card-title">📚 التوعية والتعليم</div></div>
        <div style="text-align:center;padding:10px;">
            <div style="font-size:28px;font-weight:800;color:var(--teal);"><?php echo $education_count; ?></div>
            <div style="font-size:13px;color:var(--gray);">مريض تلقوا توعية</div>
            <div style="margin:8px 0;height:6px;background:var(--bg-input);border-radius:3px;overflow:hidden;">
                <div style="height:100%;width:<?php echo $education_percent; ?>%;background:linear-gradient(90deg,#0a7e6e,#13a896);border-radius:3px;"></div>
            </div>
            <div style="font-size:12px;color:var(--text-muted);"><?php echo $education_percent; ?>% من خطط العناية تشمل توعية</div>
        </div>
        <div style="border-top:1px solid var(--border);padding-top:10px;margin-top:6px;">
            <div style="font-size:13px;font-weight:700;color:var(--teal);margin-bottom:8px;">📖 مصادر التوعية المتاحة:</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php foreach ($awareness_topics as $topic): ?>
                <a href="<?php echo BASE_URL; ?>/modules/education/<?php echo $topic['link']; ?>" class="btn btn-sm btn-secondary" style="font-size:12px;padding:4px 10px;">
                    <?php echo $topic['icon']; ?> <?php echo $topic['topic']; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- === SECTION 5: HEALING TREND + ABPI === -->
<div class="flex flex-wrap gap-4 mb-4">
    <div class="card" style="flex:2;min-width:300px;">
        <div class="card-header">
            <div class="card-title">📈 اتجاه الشفاء الشهري</div>
            <div class="flex gap-2">
                <span style="font-size:12px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#0a7e6e;"></span> حالات جديدة</span>
                <span style="font-size:12px;"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#10b981;"></span> تم شفاؤها</span>
            </div>
        </div>
        <div style="height:180px;"><canvas id="healingTrendChart" width="600" height="180"></canvas></div>
    </div>
    <div class="card" style="flex:1;min-width:200px;">
        <div class="card-header"><div class="card-title">📐 متوسط حجم الجروح</div></div>
        <div style="text-align:center;padding:1rem;">
            <div style="font-size:36px;font-weight:800;color:var(--teal);"><?php echo $avg_wound_size ? number_format($avg_wound_size, 1) : '—'; ?></div>
            <div style="font-size:13px;color:var(--gray);">سم²</div>
        </div>
        <div style="margin-top:8px;padding:10px;background:var(--bg-input);border-radius:8px;">
            <div style="font-size:12px;color:var(--text-muted);text-align:center;">
                إجمالي حالات الجروح: <strong><?php echo $total_wound_cases; ?></strong><br>
                إجمالي التقييمات: <strong><?php echo $total_assessments; ?></strong>
            </div>
        </div>
    </div>
</div>

<!-- QI (Quality Improvement) Insights Section -->
<?php
// === Smart QI: Quality Improvement Insights ===

// QI Insight 1: High-risk patients needing urgent intervention
$qi_urgent = $mysqli->query("
    SELECT COUNT(DISTINCT v.patient_id) as cnt
    FROM visits v
    JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE fa.wagner_grade >= 3 
    AND (o.improvement_percentage IS NULL OR o.improvement_percentage < 25)
    AND v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

// QI Insight 2: Patients with no improvement despite treatment
$qi_no_improve = $mysqli->query("
    SELECT COUNT(DISTINCT v.patient_id) as cnt
    FROM visits v
    JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE o.improvement_percentage = 0
    AND v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

// QI Insight 3: Wound cases with infection (need antibiotics)
$qi_infection = $mysqli->query("
    SELECT COUNT(*) as cnt FROM foot_ulcers fu
    JOIN visits v ON fu.visit_id = v.visit_id
    WHERE fu.wound_condition IN ('متسخة', 'صديد', 'سوداء', 'تحوي جسم غريب')
    AND v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

// QI Insight 4: Critical sensory loss patients
$qi_sensory = $mysqli->query("
    SELECT COUNT(DISTINCT v.patient_id) as cnt FROM visits v
    JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    WHERE (fa.right_sensation = 'معدوم' OR fa.left_sensation = 'معدوم')
    AND v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

// QI Insight 5: Patients with large wounds (>10 cm²)
$qi_large = $mysqli->query("
    SELECT COUNT(*) as cnt FROM foot_ulcers fu
    JOIN visits v ON fu.visit_id = v.visit_id
    WHERE fu.wound_size_cm2 > 10
    AND v.visit_date BETWEEN '$from_date' AND '$to_date'
")->fetch_assoc()['cnt'];

// QI Insight 6: Wagner progression (patients who had serial assessments showing worsening)
$qi_progression = $mysqli->query("
    SELECT COUNT(*) as cnt FROM (
        SELECT fa1.patient_id
        FROM (
            SELECT v.patient_id, fa.wagner_grade, v.visit_date
            FROM visits v JOIN foot_assessments fa ON v.visit_id = fa.visit_id
            WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
            ORDER BY v.patient_id, v.visit_date DESC
        ) fa1
        JOIN (
            SELECT v.patient_id, fa.wagner_grade, v.visit_date
            FROM visits v JOIN foot_assessments fa ON v.visit_id = fa.visit_id
            WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'
            ORDER BY v.patient_id, v.visit_date DESC
        ) fa2 ON fa1.patient_id = fa2.patient_id AND fa1.visit_date > fa2.visit_date
        WHERE fa1.wagner_grade > fa2.wagner_grade
        GROUP BY fa1.patient_id
    ) progression
")->fetch_assoc()['cnt'];

// QI Score calculation
$qi_scores = [];
$qi_total = 0;
$qi_max = 100;

// Factor 1: Healing rate (weight 25)
$heal_pct = $total_wound_cases > 0 ? round(($healed_count / $total_wound_cases) * 100, 1) : 0;
$qi_heal_score = min(25, round($heal_pct / 100 * 25));
$qi_scores[] = ['label' => 'معدل الشفاء', 'score' => $qi_heal_score, 'max' => 25, 'pct' => $heal_pct];
$qi_total += $qi_heal_score;

// Factor 2: Foot exam completion (weight 20)
$exam_pct = $total_wound_cases > 0 ? min(100, round(($total_assessments / max($total_wound_cases, 1)) * 100)) : 0;
$qi_exam_score = min(20, round($exam_pct / 100 * 20));
$qi_scores[] = ['label' => 'إتمام فحص القدم', 'score' => $qi_exam_score, 'max' => 20, 'pct' => $exam_pct];
$qi_total += $qi_exam_score;

// Factor 3: Infection control (weight 20)
$inf_controlled = $total_wound_cases > 0 ? max(0, $total_wound_cases - $qi_infection) : 0;
$inf_pct = $total_wound_cases > 0 ? round(($inf_controlled / $total_wound_cases) * 100, 1) : 0;
$qi_inf_score = min(20, round($inf_pct / 100 * 20));
$qi_scores[] = ['label' => 'مكافحة العدوى', 'score' => $qi_inf_score, 'max' => 20, 'pct' => $inf_pct];
$qi_total += $qi_inf_score;

// Factor 4: No progression (weight 20)
$no_prog = max(0, $total_assessments - $qi_progression);
$prog_pct = $total_assessments > 0 ? round(($no_prog / $total_assessments) * 100, 1) : 0;
$qi_prog_score = min(20, round($prog_pct / 100 * 20));
$qi_scores[] = ['label' => 'منع تدهور Wagner', 'score' => $qi_prog_score, 'max' => 20, 'pct' => $prog_pct];
$qi_total += $qi_prog_score;

// Factor 5: Education provided (weight 15)
$edu_pct = $care_plan_count > 0 ? round(($education_count / $care_plan_count) * 100, 1) : 0;
$qi_edu_score = min(15, round($edu_pct / 100 * 15));
$qi_scores[] = ['label' => 'توعية المرضى', 'score' => $qi_edu_score, 'max' => 15, 'pct' => $edu_pct];
$qi_total += $qi_edu_score;

$qi_grade = $qi_total >= 80 ? 'ممتاز' : ($qi_total >= 60 ? 'جيد' : ($qi_total >= 40 ? 'مقبول' : 'يحتاج تحسين'));
$qi_color = $qi_total >= 80 ? '#10b981' : ($qi_total >= 60 ? '#f59e0b' : ($qi_total >= 40 ? '#f97316' : '#ef4444'));
?>

<!-- QI Score Card -->
<div class="card mb-4" style="background:linear-gradient(135deg, <?php echo $qi_color; ?>15, var(--bg-card));border-<?php echo 'right'; ?>:4px solid <?php echo $qi_color; ?>;">
    <div class="flex flex-wrap gap-4 items-center">
        <div style="text-align:center;min-width:120px;">
            <div style="font-size:2.5rem;font-weight:900;color:<?php echo $qi_color; ?>;"><?php echo $qi_total; ?>%</div>
            <div style="font-size:0.85rem;color:var(--text-muted);">مؤشر الجودة QI</div>
            <div style="display:inline-block;padding:2px 12px;border-radius:12px;background:<?php echo $qi_color; ?>;color:#fff;font-size:0.75rem;font-weight:700;margin-top:4px;"><?php echo $qi_grade; ?></div>
        </div>
        <div style="flex:1;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,1fr));gap:8px;">
            <?php foreach ($qi_scores as $qs): 
                $bar_color = $qs['pct'] >= 70 ? '#10b981' : ($qs['pct'] >= 40 ? '#f59e0b' : '#ef4444');
            ?>
            <div style="padding:8px;background:var(--bg-input);border-radius:8px;text-align:center;">
                <div style="font-size:1.1rem;font-weight:800;color:<?php echo $bar_color; ?>;"><?php echo $qs['score']; ?>/<?php echo $qs['max']; ?></div>
                <div style="font-size:0.7rem;color:var(--text-muted);"><?php echo $qs['label']; ?></div>
                <div style="height:4px;background:var(--border);border-radius:2px;margin-top:4px;overflow:hidden;">
                    <div style="height:100%;width:<?php echo min($qs['pct'], 100); ?>%;background:<?php echo $bar_color; ?>;border-radius:2px;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- QI Action Items -->
<div class="card mb-4" style="border-right:3px solid var(--teal);">
    <div class="card-header">
        <div class="card-title">💡 توصيات تحسين الجودة (QI Recommendations)</div>
        <span style="font-size:11px;color:var(--text-muted);">إجراءات مقترحة بناءً على البيانات</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:8px;">
        <?php if ($qi_urgent > 0): ?>
        <div style="padding:10px;background:#fef2f2;border-radius:8px;border-right:3px solid #dc2626;">
            <div style="font-weight:700;font-size:13px;color:#dc2626;">🆘 <?php echo $qi_urgent; ?> حالة حرجة (Wagner ≥ 3)</div>
            <div style="font-size:11px;color:#666;margin-top:2px;">تحتاج تدخل فوري — متابعة أسبوعية مع تقييم جراحي</div>
        </div>
        <?php endif; ?>
        
        <?php if ($qi_no_improve > 0): ?>
        <div style="padding:10px;background:#fef2f2;border-radius:8px;border-right:3px solid #dc2626;">
            <div style="font-weight:700;font-size:13px;color:#dc2626;">🔄 <?php echo $qi_no_improve; ?> حالة بدون تحسن</div>
            <div style="font-size:11px;color:#666;margin-top:2px;">مراجعة خطة العلاج — استشارة جراحة قدم سكري</div>
        </div>
        <?php endif; ?>
        
        <?php if ($qi_infection > 0): ?>
        <div style="padding:10px;background:#fffbeb;border-radius:8px;border-right:3px solid #f59e0b;">
            <div style="font-weight:700;font-size:13px;color:#d97706;">🦠 <?php echo $qi_infection; ?> جرح ملتهب</div>
            <div style="font-size:11px;color:#666;margin-top:2px;">مضادات حيوية فورية — مزرعة جرثومية</div>
        </div>
        <?php endif; ?>
        
        <?php if ($qi_sensory > 0): ?>
        <div style="padding:10px;background:#fffbeb;border-radius:8px;border-right:3px solid #f59e0b;">
            <div style="font-weight:700;font-size:13px;color:#d97706;">⚠️ <?php echo $qi_sensory; ?> مريض بفقدان الإحساس</div>
            <div style="font-size:11px;color:#666;margin-top:2px;">أحذية وقائية — توعية بالعناية اليومية بالقدم</div>
        </div>
        <?php endif; ?>
        
        <?php if ($qi_large > 0): ?>
        <div style="padding:10px;background:#fef2f2;border-radius:8px;border-right:3px solid #dc2626;">
            <div style="font-weight:700;font-size:13px;color:#dc2626;">📏 <?php echo $qi_large; ?> جرح كبير (&gt;10 سم²)</div>
            <div style="font-size:11px;color:#666;margin-top:2px;">تقييم جراحي — تسريع عملية الالتئام</div>
        </div>
        <?php endif; ?>
        
        <?php if ($qi_progression > 0): ?>
        <div style="padding:10px;background:#fef2f2;border-radius:8px;border-right:3px solid #dc2626;">
            <div style="font-weight:700;font-size:13px;color:#dc2626;">📈 <?php echo $qi_progression; ?> حالة تدهور Wagner</div>
            <div style="font-size:11px;color:#666;margin-top:2px;">تصعيد العلاج العاجل — تقييم الحاجة للتدخل الجراحي</div>
        </div>
        <?php endif; ?>
        
        <?php if ($qi_urgent === 0 && $qi_no_improve === 0 && $qi_infection === 0 && $qi_sensory === 0 && $qi_large === 0 && $qi_progression === 0): ?>
        <div style="padding:10px;background:#ecfdf5;border-radius:8px;border-right:3px solid #10b981;grid-column:1/-1;">
            <div style="font-weight:700;font-size:13px;color:#059669;">✅ لا توجد توصيات عاجلة — جودة الرعاية جيدة</div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="card" style="background:linear-gradient(135deg, var(--teal-pale), var(--bg-card));">
    <div style="display:flex;flex-wrap:wrap;gap:8px;justify-content:center;">
        <a href="<?php echo BASE_URL; ?>/modules/assessments/foot_exam.php" class="btn btn-primary">🦶 فحص قدم جديد</a>
        <a href="<?php echo BASE_URL; ?>/modules/assessments/care_plan.php" class="btn btn-primary">🩹 إضافة خطة عناية</a>
        <a href="<?php echo BASE_URL; ?>/modules/assessments/outcomes.php" class="btn btn-primary">📊 تسجيل نتائج متابعة</a>
        <a href="<?php echo BASE_URL; ?>/modules/education/index.php" class="btn btn-secondary">📚 مركز التوعية</a>
        <a href="<?php echo BASE_URL; ?>/modules/analytics/risk_alerts.php" class="btn btn-secondary">⚠️ تنبيهات الخطر</a>
        <a href="<?php echo BASE_URL; ?>/modules/analytics/quality_measures.php" class="btn btn-secondary">📋 مقاييس الجودة</a>
    </div>
</div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Wound Trend Chart
    const wTrend = <?php echo json_encode($monthly_wounds, JSON_UNESCAPED_UNICODE); ?>;
    if (wTrend.length > 0) {
        const wtc = new ClinicChart('woundTrendChart');
        wtc.drawLineChart(
            wTrend.map(d => d.month.slice(5,7) + '/' + d.month.slice(0,4)),
            wTrend.map(d => parseInt(d.cnt)),
            { lineColor: '#0a7e6e', fillColor: 'rgba(10, 126, 110, 0.1)', lineWidth: 2 }
        );
    }

    // Wagner Chart
    const wData = <?php echo json_encode($wagner_data, JSON_UNESCAPED_UNICODE); ?>;
    if (wData.length > 0) {
        const wc = new ClinicChart('wagnerChart');
        const wagnerLabels = ['درجة 0', 'درجة 1', 'درجة 2', 'درجة 3', 'درجة 4', 'درجة 5'];
        const labels = wData.map(d => wagnerLabels[d.wagner_grade] || 'W' + d.wagner_grade);
        const values = wData.map(d => parseInt(d.cnt));
        const colors = ['#10b981', '#f59e0b', '#f97316', '#ef4444', '#dc2626', '#7f1d1d'];
        wc.drawBarChart(labels, values, { colors: colors });
    }

    // Sensation Chart
    const sData = <?php echo json_encode($sensation_data, JSON_UNESCAPED_UNICODE); ?>;
    // Group by foot
    const sensationByFoot = {};
    sData.forEach(d => {
        if (!sensationByFoot[d.foot]) sensationByFoot[d.foot] = {};
        sensationByFoot[d.foot][d.sensation] = parseInt(d.cnt);
    });
    // Draw simple pie with right foot data
    if (sData.length > 0) {
        const rightSense = sensationByFoot['يمنى'] || {};
        const sItems = Object.entries(rightSense).map(([k, v]) => ({ label: k, value: v }));
        if (sItems.length > 0) {
            const sc = new ClinicChart('sensationChart');
            sc.drawPieChart(sItems, { donut: true, colors: ['#10b981', '#f59e0b', '#ef4444', '#8b5cf6'] });
        }
    }

    // Pulse Chart
    const pData = <?php echo json_encode($pulse_data, JSON_UNESCAPED_UNICODE); ?>;
    const pulseByFoot = {};
    pData.forEach(d => {
        if (!pulseByFoot[d.foot]) pulseByFoot[d.foot] = {};
        pulseByFoot[d.foot][d.pulse] = parseInt(d.cnt);
    });
    if (pData.length > 0) {
        const rightPulse = pulseByFoot['يمنى'] || {};
        const pItems = Object.entries(rightPulse).map(([k, v]) => ({ label: k, value: v }));
        if (pItems.length > 0) {
            const pc = new ClinicChart('pulseChart');
            pc.drawPieChart(pItems, { donut: true, colors: ['#10b981', '#f59e0b', '#ef4444'] });
        }
    }

    // Healing Trend Chart
    const hTrend = <?php echo json_encode($monthly_healings, JSON_UNESCAPED_UNICODE); ?>;
    if (wTrend.length > 0 || hTrend.length > 0) {
        const allMonths = [...new Set([
            ...wTrend.map(d => d.month),
            ...hTrend.map(d => d.month)
        ])].sort();
        
        if (allMonths.length > 0) {
            const wMap = {}; wTrend.forEach(d => wMap[d.month] = parseInt(d.cnt));
            const hMap = {}; hTrend.forEach(d => hMap[d.month] = parseInt(d.cnt));
            const labels = allMonths.map(m => m.slice(5,7) + '/' + m.slice(0,4));
            
            // Just draw the healed trend
            const hc = new ClinicChart('healingTrendChart');
            hc.drawLineChart(
                labels,
                allMonths.map(m => hMap[m] || 0),
                { lineColor: '#10b981', fillColor: 'rgba(16, 185, 129, 0.1)', lineWidth: 2 }
            );
        }
    }
});
</script>
</div></div>
