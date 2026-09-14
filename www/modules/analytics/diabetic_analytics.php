<?php
/**
 * Diabetic Patient Analytics
 * Comprehensive diabetes-specific analytics with KPIs, trends, and insights
 */
$page_title = 'تحليلات السكري | Diabetic Analytics';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// Date filters
$from_date = $mysqli->real_escape_string($_GET['from_date'] ?? date('Y-m-d', strtotime('-12 months')));
$to_date = $mysqli->real_escape_string($_GET['to_date'] ?? date('Y-m-d'));

// === DIABETES-SPECIFIC KPIs ===

// 1. Diabetes type distribution
$diabetes_types = [];
$result = $mysqli->query("SELECT COALESCE(mh.diabetes_type, 'غير محدد') as type, COUNT(DISTINCT p.patient_id) as cnt
    FROM patients p LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
    WHERE p.is_active = 1 GROUP BY mh.diabetes_type ORDER BY cnt DESC");
while ($row = $result->fetch_assoc()) { $diabetes_types[] = $row; }

// 2. HbA1c distribution
$hba1c_ranges = [];
$result = $mysqli->query("SELECT 
    CASE 
        WHEN bs.hba1c_value < 7 THEN 'مضبوط (<7%)'
        WHEN bs.hba1c_value BETWEEN 7 AND 8 THEN 'مقبول (7-8%)'
        WHEN bs.hba1c_value BETWEEN 8 AND 9 THEN 'غير مضبوط (8-9%)'
        WHEN bs.hba1c_value BETWEEN 9 AND 10 THEN 'مرتفع (9-10%)'
        ELSE 'حرج (>10%)'
    END as range_label, COUNT(DISTINCT v.patient_id) as cnt
    FROM blood_sugar_readings bs JOIN visits v ON bs.visit_id = v.visit_id
    WHERE bs.hba1c_value IS NOT NULL AND v.visit_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY range_label");
while ($row = $result->fetch_assoc()) { $hba1c_ranges[] = $row; }

// 3. Average HbA1c trend (monthly)
$hba1c_trend = [];
$result = $mysqli->query("SELECT DATE_FORMAT(v.visit_date, '%Y-%m') as month, AVG(bs.hba1c_value) as avg_hba1c
    FROM visits v JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    WHERE bs.hba1c_value IS NOT NULL AND v.visit_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY DATE_FORMAT(v.visit_date, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) { $hba1c_trend[] = $row; }

// 4. FPG average trend
$fpg_trend = [];
$result = $mysqli->query("SELECT DATE_FORMAT(v.visit_date, '%Y-%m') as month, AVG(bs.fpg_value) as avg_fpg
    FROM visits v JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    WHERE bs.fpg_value IS NOT NULL AND v.visit_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY DATE_FORMAT(v.visit_date, '%Y-%m') ORDER BY month");
while ($row = $result->fetch_assoc()) { $fpg_trend[] = $row; }

// 5. Patients with uncontrolled diabetes (HbA1c > 7%)
$uncontrolled = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c 
    FROM visits v JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    WHERE bs.hba1c_value > 7 AND v.visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc()['c'];

// 6. Patients with well-controlled diabetes (HbA1c < 7%)
$controlled = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c 
    FROM visits v JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    WHERE bs.hba1c_value <= 7 AND v.visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc()['c'];

// 7. Smoking status distribution
$smoking_dist = [];
$result = $mysqli->query("SELECT COALESCE(mh.smoking_status, 'غير محدد') as status, COUNT(*) as cnt 
    FROM medical_history mh GROUP BY mh.smoking_status ORDER BY cnt DESC");
while ($row = $result->fetch_assoc()) { $smoking_dist[] = $row; }

// 8. Physical activity distribution
$activity_dist = [];
$result = $mysqli->query("SELECT COALESCE(mh.physical_activity, 'غير محدد') as activity, COUNT(*) as cnt 
    FROM medical_history mh GROUP BY mh.physical_activity ORDER BY cnt DESC");
while ($row = $result->fetch_assoc()) { $activity_dist[] = $row; }

// 9. Complication rates
$complication_rates = [];
$result = $mysqli->query("SELECT 
    SUM(has_retinopathy) as retinopathy,
    SUM(has_nephropathy) as nephropathy,
    SUM(has_neuropathy) as neuropathy,
    SUM(has_cad) as cad,
    SUM(has_cva) as cva,
    SUM(has_pad) as pad,
    COUNT(*) as total
    FROM complications");
$comp_data = $result->fetch_assoc();
$comp_total = $comp_data['total'] ?: 1;

// 10. Lipid panel averages
$lipids = $mysqli->query("SELECT 
    AVG(total_cholesterol) as avg_chol,
    AVG(ldl) as avg_ldl,
    AVG(hdl) as avg_hdl,
    AVG(triglycerides) as avg_trig
    FROM lab_results WHERE visit_id IN (
        SELECT visit_id FROM visits WHERE visit_date BETWEEN '$from_date' AND '$to_date'
    )")->fetch_assoc();

// 11. Total patients with data
$total_with_data = $mysqli->query("SELECT COUNT(DISTINCT v.patient_id) as c 
    FROM visits v JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id 
    WHERE v.visit_date BETWEEN '$from_date' AND '$to_date'")->fetch_assoc()['c'];

// 12. Best controlled patients (top 5 by lowest HbA1c)
$best_controlled = $mysqli->query("SELECT p.patient_id, p.full_name, p.file_number, MIN(bs.hba1c_value) as best_hba1c
    FROM patients p JOIN visits v ON p.patient_id = v.patient_id
    JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    WHERE p.is_active = 1 AND bs.hba1c_value IS NOT NULL
    AND v.visit_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY p.patient_id HAVING best_hba1c < 7
    ORDER BY best_hba1c LIMIT 5");

// 13. Worst controlled patients (top 5 by highest HbA1c)
$worst_controlled = $mysqli->query("SELECT p.patient_id, p.full_name, p.file_number, MAX(bs.hba1c_value) as worst_hba1c
    FROM patients p JOIN visits v ON p.patient_id = v.patient_id
    JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    WHERE p.is_active = 1 AND bs.hba1c_value IS NOT NULL
    AND v.visit_date BETWEEN '$from_date' AND '$to_date'
    GROUP BY p.patient_id
    ORDER BY worst_hba1c DESC LIMIT 5");

// Helper
$pct = $comp_total > 0 ? fn($val) => round(($val ?? 0) / $comp_total * 100, 1) : fn($val) => 0;
?>
<style>
    .diab-hero {
        background: linear-gradient(135deg, #00695c, #004d40); color: white;
        padding: 2rem; border-radius: 16px; margin-bottom: 2rem; text-align: center;
        position: relative; overflow: hidden;
    }
    .diab-hero::before { content: '🩸'; position: absolute; right: -20px; top: -20px; font-size: 100px; opacity: 0.1; }

    .kpi-card {
        background: white; border-radius: 12px; padding: 1.2rem; text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.3s;
    }
    .kpi-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
    .kpi-card .kpi-value { font-size: 2rem; font-weight: 800; }
    .kpi-card .kpi-label { font-size: 0.85rem; color: #666; margin-top: 0.2rem; }
    .kpi-card .kpi-bar { height: 4px; border-radius: 2px; margin-top: 0.5rem; }

    .insight-card {
        padding: 1rem; border-radius: 10px; border-right: 4px solid;
        background: #f8fafc; margin-bottom: 0.5rem;
    }
    .patient-link { text-decoration: none; color: inherit; }
    .patient-link:hover { text-decoration: underline; color: var(--teal); }

    .glucose-status {
        display: inline-block; padding: 3px 10px; border-radius: 20px; font-weight: 600; font-size: 12px;
    }
    .status-good { background: #e8f5e9; color: #2e7d32; }
    .status-warning { background: #fff3e0; color: #e65100; }
    .status-critical { background: #ffebee; color: #c62828; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="diab-hero fade-in">
    <h1>🩸 تحليلات مرضى السكري</h1>
    <p>Diabetic Patient Analytics — مؤشرات تحكم السكر، مضاعفات، واتجاهات علاجية</p>
    <form method="get" class="flex flex-wrap gap-2 items-center justify-center" style="margin-top:1rem;">
        <label style="font-size:13px;color:rgba(255,255,255,0.8);">من</label>
        <input type="date" name="from_date" value="<?php echo $from_date; ?>" style="padding:5px 8px;border-radius:6px;border:none;font-size:13px;font-family:'Tajawal',sans-serif;">
        <label style="font-size:13px;color:rgba(255,255,255,0.8);">إلى</label>
        <input type="date" name="to_date" value="<?php echo $to_date; ?>" style="padding:5px 8px;border-radius:6px;border:none;font-size:13px;font-family:'Tajawal',sans-serif;">
        <button type="submit" class="btn btn-sm" style="background:rgba(255,255,255,0.2);color:white;border:1px solid rgba(255,255,255,0.3);">تحديث</button>
    </form>
</div>

<!-- KPI Cards -->
<div class="stats-grid mb-4">
    <div class="kpi-card">
        <div class="kpi-value" style="color:#0a7e6e;"><?php echo $total_with_data; ?></div>
        <div class="kpi-label">مرضى بقراءات سكر</div>
        <div class="kpi-bar" style="background:linear-gradient(90deg,#0a7e6e,#13a896);"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" style="color:#2e7d32;"><?php echo $controlled; ?></div>
        <div class="kpi-label">مضبوط (HbA1c ≤ 7%)</div>
        <div class="kpi-bar" style="background:linear-gradient(90deg,#2e7d32,#4caf50);"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" style="color:#c62828;"><?php echo $uncontrolled; ?></div>
        <div class="kpi-label">غير مضبوط (HbA1c > 7%)</div>
        <div class="kpi-bar" style="background:linear-gradient(90deg,#c62828,#ef5350);"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" style="color:#1565c0;"><?php echo $lipids['avg_chol'] ? number_format($lipids['avg_chol'], 1) : '—'; ?></div>
        <div class="kpi-label">متوسط الكوليسترول</div>
        <div class="kpi-bar" style="background:linear-gradient(90deg,#1565c0,#42a5f5);"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" style="color:#e65100;"><?php echo $lipids['avg_ldl'] ? number_format($lipids['avg_ldl'], 1) : '—'; ?></div>
        <div class="kpi-label">متوسط LDL</div>
        <div class="kpi-bar" style="background:linear-gradient(90deg,#e65100,#ff9800);"></div>
    </div>
    <div class="kpi-card">
        <div class="kpi-value" style="color:#7b1fa2;"><?php echo $lipids['avg_hdl'] ? number_format($lipids['avg_hdl'], 1) : '—'; ?></div>
        <div class="kpi-label">متوسط HDL</div>
        <div class="kpi-bar" style="background:linear-gradient(90deg,#7b1fa2,#ab47bc);"></div>
    </div>
</div>

<div class="flex flex-wrap gap-4 mb-4">
    <!-- Diabetes Types -->
    <div class="card" style="flex:1;min-width:250px;">
        <div class="card-header"><div class="card-title">📊 أنواع السكري</div></div>
        <div style="height:200px;"><canvas id="diabTypesChart" width="400" height="200"></canvas></div>
        <?php if (empty($diabetes_types)): ?><p style="color:#94a3b8;text-align:center;">لا توجد بيانات</p><?php endif; ?>
    </div>
    <!-- HbA1c Distribution -->
    <div class="card" style="flex:1;min-width:250px;">
        <div class="card-header"><div class="card-title">📊 توزيع HbA1c</div></div>
        <div style="height:200px;"><canvas id="hba1cRangeChart" width="400" height="200"></canvas></div>
        <?php if (empty($hba1c_ranges)): ?><p style="color:#94a3b8;text-align:center;">لا توجد بيانات</p><?php endif; ?>
    </div>
    <!-- Smoking & Activity -->
    <div class="card" style="flex:1;min-width:200px;">
        <div class="card-header"><div class="card-title">🚬 التدخين</div></div>
        <div style="display:flex;flex-direction:column;gap:4px;padding:0.3rem 0;">
            <?php foreach ($smoking_dist as $s): ?>
            <div style="display:flex;justify-content:space-between;font-size:13px;">
                <span><?php echo $s['status'] ?: 'غير محدد'; ?></span>
                <span class="badge badge-info"><?php echo $s['cnt']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- HbA1c Trend Chart -->
<div class="card mb-4">
    <div class="card-header"><div class="card-title">📈 اتجاه HbA1c وفاطر (FPG)</div></div>
    <div style="height:260px;"><canvas id="diabTrendChart" width="900" height="260"></canvas></div>
</div>

<!-- Complication Rates -->
<div class="card mb-4">
    <div class="card-header"><div class="card-title">🚨 معدلات المضاعفات</div></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:0.8rem;padding:0.5rem;">
        <div style="text-align:center;padding:0.8rem;background:#fce4ec;border-radius:10px;">
            <div style="font-size:1.5rem;color:#c62828;"><?php echo $pct($comp_data['retinopathy']); ?>%</div>
            <div style="font-size:0.8rem;">👁️ اعتلال الشبكية</div>
        </div>
        <div style="text-align:center;padding:0.8rem;background:#fce4ec;border-radius:10px;">
            <div style="font-size:1.5rem;color:#c62828;"><?php echo $pct($comp_data['nephropathy']); ?>%</div>
            <div style="font-size:0.8rem;">🫘 اعتلال الكلى</div>
        </div>
        <div style="text-align:center;padding:0.8rem;background:#fce4ec;border-radius:10px;">
            <div style="font-size:1.5rem;color:#c62828;"><?php echo $pct($comp_data['neuropathy']); ?>%</div>
            <div style="font-size:0.8rem;">🦶 اعتلال الأعصاب</div>
        </div>
        <div style="text-align:center;padding:0.8rem;background:#fce4ec;border-radius:10px;">
            <div style="font-size:1.5rem;color:#c62828;"><?php echo $pct($comp_data['cad']); ?>%</div>
            <div style="font-size:0.8rem;">❤️ أمراض القلب</div>
        </div>
        <div style="text-align:center;padding:0.8rem;background:#fce4ec;border-radius:10px;">
            <div style="font-size:1.5rem;color:#c62828;"><?php echo $pct($comp_data['pad']); ?>%</div>
            <div style="font-size:0.8rem;">🩸 أمراض الشرايين</div>
        </div>
    </div>
</div>

<!-- Best & Worst Controlled -->
<div class="flex flex-wrap gap-4 mb-4">
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title" style="color:#2e7d32;">✅ أفضل تحكم بالسكر</div></div>
        <div style="display:flex;flex-direction:column;gap:6px;">
            <?php while ($p = $best_controlled->fetch_assoc()): ?>
            <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $p['patient_id']; ?>" class="patient-link insight-card" style="border-right-color:#2e7d32;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span><strong><?php echo escape_output($p['full_name']); ?></strong><br><span style="font-size:11px;color:#94a3b8;">📁 <?php echo escape_output($p['file_number']); ?></span></span>
                    <span class="glucose-status status-good">HbA1c <?php echo $p['best_hba1c']; ?>%</span>
                </div>
            </a>
            <?php endwhile; ?>
            <?php if ($best_controlled->num_rows === 0): ?><p style="color:#94a3b8;text-align:center;padding:1rem;">لا توجد بيانات</p><?php endif; ?>
        </div>
    </div>
    <div class="card" style="flex:1;min-width:300px;">
        <div class="card-header"><div class="card-title" style="color:#c62828;">⚠️ بحاجة لتحكم أفضل</div></div>
        <div style="display:flex;flex-direction:column;gap:6px;">
            <?php while ($p = $worst_controlled->fetch_assoc()): ?>
            <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $p['patient_id']; ?>" class="patient-link insight-card" style="border-right-color:#c62828;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span><strong><?php echo escape_output($p['full_name']); ?></strong><br><span style="font-size:11px;color:#94a3b8;">📁 <?php echo escape_output($p['file_number']); ?></span></span>
                    <span class="glucose-status status-critical">HbA1c <?php echo $p['worst_hba1c']; ?>%</span>
                </div>
            </a>
            <?php endwhile; ?>
            <?php if ($worst_controlled->num_rows === 0): ?><p style="color:#94a3b8;text-align:center;padding:1rem;">لا توجد بيانات</p><?php endif; ?>
        </div>
    </div>
</div>

<!-- === QI (Quality Improvement) Score for Diabetes === -->
<?php
// Smart QI: Diabetes-specific quality metrics

// QI Factor 1: HbA1c Control Rate (weight 30)
$total_hba1c_patients = $controlled + $uncontrolled;
$hba1c_control_pct = $total_hba1c_patients > 0 ? round(($controlled / $total_hba1c_patients) * 100, 1) : 0;
$qi_hba1c_score = min(30, round($hba1c_control_pct / 100 * 30));

// QI Factor 2: Complication Management (weight 25)
// Lower complication rate = better score
$comp_pct = $comp_total > 0 ? round(($comp_data['retinopathy'] + $comp_data['nephropathy'] + $comp_data['neuropathy'] + $comp_data['cad'] + $comp_data['cva'] + $comp_data['pad']) / ($comp_total * 6) * 100, 1) : 0;
$comp_score = max(0, 100 - $comp_pct);
$qi_comp_score = min(25, round($comp_score / 100 * 25));

// QI Factor 3: Smoking Rate (weight 20, lower is better)
$smoking_count = 0;
foreach ($smoking_dist as $s) { if ($s['status'] === 'مدخن') $smoking_count = $s['cnt']; }
$total_patients_with_smoking_data = array_sum(array_column($smoking_dist, 'cnt'));
$smoking_pct = $total_patients_with_smoking_data > 0 ? round(($smoking_count / $total_patients_with_smoking_data) * 100, 1) : 0;
$smoking_score = max(0, 100 - $smoking_pct);
$qi_smoking_score = min(20, round($smoking_score / 100 * 20));

// QI Factor 4: Lipid Control (weight 25, lower LDL is better)
$ldl_val = $lipids['avg_ldl'] ?? 0;
$ldl_score = $ldl_val > 0 ? max(0, min(100, round((1 - ($ldl_val - 70) / 130) * 100))) : 50;
$qi_lipid_score = min(25, round($ldl_score / 100 * 25));

$qi_diabetes_total = $qi_hba1c_score + $qi_comp_score + $qi_smoking_score + $qi_lipid_score;
$qi_grade = $qi_diabetes_total >= 80 ? 'ممتاز' : ($qi_diabetes_total >= 60 ? 'جيد' : ($qi_diabetes_total >= 40 ? 'مقبول' : 'يحتاج تحسين'));
$qi_color = $qi_diabetes_total >= 80 ? '#10b981' : ($qi_diabetes_total >= 60 ? '#f59e0b' : ($qi_diabetes_total >= 40 ? '#f97316' : '#ef4444'));

// QI Recommendations
$qi_recs = [];
$qi_urge_count = $uncontrolled;
if ($hba1c_control_pct < 50) $qi_recs[] = ['icon' => '🩸', 'text' => "نسبة التحكم بـ HbA1c {$hba1c_control_pct}% — أقل من الهدف (50%)", 'type' => 'critical'];
if ($smoking_pct > 20) $qi_recs[] = ['icon' => '🚬', 'text' => "نسبة التدخين {$smoking_pct}% — برنامج إقلاع للمدخنين", 'type' => 'warning'];
if ($comp_pct > 40) $qi_recs[] = ['icon' => '⚠️', 'text' => "معدل المضاعفات {$comp_pct}% — فحص دوري للمضاعفات", 'type' => 'critical'];
if ($ldl_val > 100) $qi_recs[] = ['icon' => '🩸', 'text' => "متوسط LDL {$ldl_val} mg/dL — بحاجة لتحسين علاج الدهون", 'type' => 'warning'];
if ($qi_urge_count > 5) $qi_recs[] = ['icon' => '🆘', 'text' => "{$qi_urge_count} مريض غير مضبوط HbA1c — متابعة مكثفة", 'type' => 'critical'];
?>

<!-- QI Score for Diabetes -->
<div class="card mb-4" style="background:linear-gradient(135deg, <?php echo $qi_color; ?>15, var(--bg-card));border-right:4px solid <?php echo $qi_color; ?>;">
    <div class="flex flex-wrap gap-4 items-center">
        <div style="text-align:center;min-width:120px;">
            <div style="font-size:2.5rem;font-weight:900;color:<?php echo $qi_color; ?>;"><?php echo $qi_diabetes_total; ?>%</div>
            <div style="font-size:0.85rem;color:var(--text-muted);">مؤشر جودة السكري</div>
            <div style="display:inline-block;padding:2px 12px;border-radius:12px;background:<?php echo $qi_color; ?>;color:#fff;font-size:0.75rem;font-weight:700;margin-top:4px;"><?php echo $qi_grade; ?></div>
        </div>
        <div style="flex:1;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;">
            <div style="padding:8px;background:var(--bg-input);border-radius:8px;text-align:center;">
                <div style="font-size:1.1rem;font-weight:800;color:<?php echo $hba1c_control_pct >= 50 ? '#10b981' : '#ef4444'; ?>;"><?php echo $qi_hba1c_score; ?>/30</div>
                <div style="font-size:0.7rem;color:var(--text-muted);">التحكم بـ HbA1c</div>
                <div style="height:4px;background:var(--border);border-radius:2px;margin-top:4px;"><div style="height:100%;width:<?php echo min($hba1c_control_pct, 100); ?>%;background:<?php echo $hba1c_control_pct >= 50 ? '#10b981' : '#ef4444'; ?>;border-radius:2px;"></div></div>
            </div>
            <div style="padding:8px;background:var(--bg-input);border-radius:8px;text-align:center;">
                <div style="font-size:1.1rem;font-weight:800;color:<?php echo $comp_score >= 60 ? '#10b981' : '#ef4444'; ?>;"><?php echo $qi_comp_score; ?>/25</div>
                <div style="font-size:0.7rem;color:var(--text-muted);">إدارة المضاعفات</div>
                <div style="height:4px;background:var(--border);border-radius:2px;margin-top:4px;"><div style="height:100%;width:<?php echo min($comp_score, 100); ?>%;background:<?php echo $comp_score >= 60 ? '#10b981' : '#ef4444'; ?>;border-radius:2px;"></div></div>
            </div>
            <div style="padding:8px;background:var(--bg-input);border-radius:8px;text-align:center;">
                <div style="font-size:1.1rem;font-weight:800;color:<?php echo $smoking_score >= 70 ? '#10b981' : '#ef4444'; ?>;"><?php echo $qi_smoking_score; ?>/20</div>
                <div style="font-size:0.7rem;color:var(--text-muted);">مكافحة التدخين</div>
                <div style="height:4px;background:var(--border);border-radius:2px;margin-top:4px;"><div style="height:100%;width:<?php echo min($smoking_score, 100); ?>%;background:<?php echo $smoking_score >= 70 ? '#10b981' : '#ef4444'; ?>;border-radius:2px;"></div></div>
            </div>
            <div style="padding:8px;background:var(--bg-input);border-radius:8px;text-align:center;">
                <div style="font-size:1.1rem;font-weight:800;color:<?php echo $ldl_score >= 50 ? '#10b981' : '#ef4444'; ?>;"><?php echo $qi_lipid_score; ?>/25</div>
                <div style="font-size:0.7rem;color:var(--text-muted);">التحكم بالدهون</div>
                <div style="height:4px;background:var(--border);border-radius:2px;margin-top:4px;"><div style="height:100%;width:<?php echo min($ldl_score, 100); ?>%;background:<?php echo $ldl_score >= 50 ? '#10b981' : '#ef4444'; ?>;border-radius:2px;"></div></div>
            </div>
        </div>
    </div>
    <?php if ($qi_recs): ?>
    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border);display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:6px;">
        <?php foreach ($qi_recs as $r): 
            $bg = $r['type'] === 'critical' ? '#fef2f2' : '#fffbeb';
            $border = $r['type'] === 'critical' ? '#dc2626' : '#f59e0b';
        ?>
        <div style="padding:8px 10px;background:<?php echo $bg; ?>;border-radius:6px;border-right:3px solid <?php echo $border; ?>;font-size:12px;">
            <?php echo $r['icon']; ?> <?php echo $r['text']; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Lipid Profile -->
<div class="card">
    <div class="card-header"><div class="card-title">🧪 تحليل الدهون</div></div>
    <table>
        <thead><tr><th>المؤشر</th><th>المتوسط</th><th>الحالة</th></tr></thead>
        <tbody>
            <tr><td>الكوليسترول الكلي</td><td><strong><?php echo $lipids['avg_chol'] ? number_format($lipids['avg_chol'], 1) : '—'; ?></strong></td><td><?php echo ($lipids['avg_chol'] ?? 0) > 200 ? '<span class="glucose-status status-critical">مرتفع</span>' : '<span class="glucose-status status-good">طبيعي</span>'; ?></td></tr>
            <tr><td>LDL (الضار)</td><td><strong><?php echo $lipids['avg_ldl'] ? number_format($lipids['avg_ldl'], 1) : '—'; ?></strong></td><td><?php echo ($lipids['avg_ldl'] ?? 0) > 100 ? '<span class="glucose-status status-critical">مرتفع</span>' : '<span class="glucose-status status-good">طبيعي</span>'; ?></td></tr>
            <tr><td>HDL (النافع)</td><td><strong><?php echo $lipids['avg_hdl'] ? number_format($lipids['avg_hdl'], 1) : '—'; ?></strong></td><td><?php echo ($lipids['avg_hdl'] ?? 0) < 40 ? '<span class="glucose-status status-critical">منخفض</span>' : '<span class="glucose-status status-good">طبيعي</span>'; ?></td></tr>
            <tr><td>الدهون الثلاثية</td><td><strong><?php echo $lipids['avg_trig'] ? number_format($lipids['avg_trig'], 1) : '—'; ?></strong></td><td><?php echo ($lipids['avg_trig'] ?? 0) > 150 ? '<span class="glucose-status status-critical">مرتفع</span>' : '<span class="glucose-status status-good">طبيعي</span>'; ?></td></tr>
        </tbody>
    </table>
</div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Diabetes types chart
    const dTypes = <?php echo json_encode($diabetes_types, JSON_UNESCAPED_UNICODE); ?>;
    if (dTypes.length > 0) {
        const dc = new ClinicChart('diabTypesChart');
        dc.drawPieChart(
            dTypes.map(d => ({ label: d.type.length > 15 ? d.type.substring(0, 15) + '...' : d.type, value: parseInt(d.cnt) })),
            { donut: true, colors: ['#0a7e6e', '#1565c0', '#e65100', '#7b1fa2', '#c62828'] }
        );
    }

    // HbA1c ranges chart
    const hRanges = <?php echo json_encode($hba1c_ranges, JSON_UNESCAPED_UNICODE); ?>;
    if (hRanges.length > 0) {
        const hc = new ClinicChart('hba1cRangeChart');
        hc.drawBarChart(
            hRanges.map(d => d.range_label),
            hRanges.map(d => parseInt(d.cnt)),
            { colors: ['#2e7d32', '#f59e0b', '#e65100', '#dc2626', '#7f1d1d'] }
        );
    }

    // HbA1c + FPG trend
    const hba1cData = <?php echo json_encode($hba1c_trend, JSON_UNESCAPED_UNICODE); ?>;
    const fpgData = <?php echo json_encode($fpg_trend, JSON_UNESCAPED_UNICODE); ?>;
    if (hba1cData.length > 0 || fpgData.length > 0) {
        const allMonths = [...new Set([
            ...hba1cData.map(d => d.month),
            ...fpgData.map(d => d.month)
        ])].sort();
        
        if (allMonths.length > 0) {
            const labels = allMonths.map(m => m.slice(5,7) + '/' + m.slice(0,4));
            const hba1cMap = {}; hba1cData.forEach(d => hba1cMap[d.month] = parseFloat(d.avg_hba1c));
            const fpgMap = {}; fpgData.forEach(d => fpgMap[d.month] = parseFloat(d.avg_fpg));
            
            const tc = new ClinicChart('diabTrendChart');
            tc.drawLineChart(labels, allMonths.map(m => hba1cMap[m] || 0), { lineColor: '#dc2626', fillColor: 'rgba(220, 38, 38, 0.1)' });
        }
    }
});
</script>
</div></div>