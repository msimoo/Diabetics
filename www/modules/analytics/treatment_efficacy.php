<?php
/**
 * Treatment Efficacy Analysis
 * Tracks which treatments, antibiotics, and medications are most effective
 */
$page_title = 'فعالية العلاج | Treatment Efficacy';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

// ===== 1. Treatment type vs healing rate =====
$treat_efficacy = [];
$result = $mysqli->query("
    SELECT t.treatment_type, 
           COUNT(DISTINCT t.treatment_id) as total_cases,
           COUNT(DISTINCT CASE WHEN o.improvement_percentage >= 75 THEN t.treatment_id END) as improved,
           COUNT(DISTINCT CASE WHEN o.improvement_percentage = 100 THEN t.treatment_id END) as healed,
           AVG(o.improvement_percentage) as avg_improvement
    FROM treatments t
    JOIN visits v ON t.visit_id = v.visit_id
    JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE t.treatment_type IS NOT NULL AND t.treatment_type != ''
    GROUP BY t.treatment_type
    ORDER BY avg_improvement DESC
");
while ($row = $result->fetch_assoc()) {
    $treat_efficacy[] = $row;
}

// ===== 2. Antibiotics efficacy =====
$abx_efficacy = [];
$result = $mysqli->query("
    SELECT t.antibiotics_oral, t.antibiotics_iv,
           COUNT(DISTINCT t.treatment_id) as total_cases,
           AVG(o.improvement_percentage) as avg_improvement,
           AVG(DATEDIFF(o.healing_date, v.visit_date)) as avg_healing_days
    FROM treatments t
    JOIN visits v ON t.visit_id = v.visit_id
    JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE (t.antibiotics_oral IS NOT NULL AND t.antibiotics_oral != '')
       OR (t.antibiotics_iv IS NOT NULL AND t.antibiotics_iv != '')
    GROUP BY t.antibiotics_oral, t.antibiotics_iv
    ORDER BY avg_improvement DESC
");
while ($row = $result->fetch_assoc()) {
    $abx_efficacy[] = $row;
}

// ===== 3. Wound condition vs healing outcome =====
$wound_outcome = [];
$result = $mysqli->query("
    SELECT fu.wound_condition,
           COUNT(*) as total,
           AVG(o.improvement_percentage) as avg_improvement,
           COUNT(CASE WHEN o.improvement_percentage = 100 THEN 1 END) as healed_count
    FROM foot_ulcers fu
    JOIN visits v ON fu.visit_id = v.visit_id
    JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE fu.wound_condition IS NOT NULL AND fu.wound_condition != ''
    GROUP BY fu.wound_condition
    ORDER BY avg_improvement DESC
");
while ($row = $result->fetch_assoc()) {
    $wound_outcome[] = $row;
}

// ===== 4. Wagner grade vs healing time =====
$wagner_healing = [];
$result = $mysqli->query("
    SELECT fa.wagner_grade,
           COUNT(*) as total,
           AVG(o.improvement_percentage) as avg_improvement,
           AVG(DATEDIFF(o.healing_date, v.visit_date)) as avg_days
    FROM foot_assessments fa
    JOIN visits v ON fa.visit_id = v.visit_id
    JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE fa.wagner_grade IS NOT NULL
    GROUP BY fa.wagner_grade
    ORDER BY fa.wagner_grade
");
while ($row = $result->fetch_assoc()) {
    $wagner_healing[] = $row;
}

// ===== 5. Dressing frequency vs healing =====
$dressing_efficacy = [];
$result = $mysqli->query("
    SELECT cp.dressing_frequency,
           COUNT(*) as total,
           AVG(o.improvement_percentage) as avg_improvement,
           AVG(DATEDIFF(o.healing_date, v.visit_date)) as avg_days
    FROM care_plan cp
    JOIN visits v ON cp.visit_id = v.visit_id
    JOIN outcomes o ON v.visit_id = o.visit_id
    WHERE cp.dressing_frequency IS NOT NULL AND cp.dressing_frequency != ''
    GROUP BY cp.dressing_frequency
    ORDER BY avg_improvement DESC
");
while ($row = $result->fetch_assoc()) {
    $dressing_efficacy[] = $row;
}
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">
            
            <div class="page-header">
                <h1 class="page-title">💊 تحليل فعالية العلاج</h1>
                <p class="page-subtitle">Treatment Efficacy Analysis — Track what works best</p>
            </div>

            <!-- KPI Summary -->
            <div class="stats-grid">
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#0a7e6e,#13a896);"></div>
                    <div class="stat-icon" style="background:#e0f5f2;color:#0a7e6e;">💊</div>
                    <div class="stat-value" style="color:#0a7e6e;"><?php echo count($treat_efficacy); ?></div>
                    <div class="stat-label">أنواع العلاج</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#3b82f6,#1d4ed8);"></div>
                    <div class="stat-icon" style="background:#eff6ff;color:#3b82f6;">📈</div>
                    <div class="stat-value" style="color:#1d4ed8;">
                        <?php 
                        $avg_all = array_sum(array_column($treat_efficacy, 'avg_improvement')) / max(count($treat_efficacy), 1);
                        echo number_format($avg_all, 0) . '%';
                        ?>
                    </div>
                    <div class="stat-label">متوسط التحسن</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#10b981,#059669);"></div>
                    <div class="stat-icon" style="background:#ecfdf5;color:#10b981;">✅</div>
                    <div class="stat-value" style="color:#059669;">
                        <?php 
                        $total_healed = array_sum(array_column($treat_efficacy, 'healed'));
                        $total_cases = array_sum(array_column($treat_efficacy, 'total_cases'));
                        echo $total_cases > 0 ? round($total_healed / $total_cases * 100) . '%' : '—';
                        ?>
                    </div>
                    <div class="stat-label">نسبة الشفاء</div>
                </div>
                <div class="stat-card card-fade-in">
                    <div class="stat-bar" style="background:linear-gradient(90deg,#8b5cf6,#7c3aed);"></div>
                    <div class="stat-icon" style="background:#f5f3ff;color:#8b5cf6;">🦠</div>
                    <div class="stat-value" style="color:#7c3aed;"><?php echo count($abx_efficacy); ?></div>
                    <div class="stat-label">حالات مضادات حيوية</div>
                </div>
            </div>

            <!-- Treatment Type Efficacy -->
            <div class="card mb-4">
                <div class="card-header"><div class="card-title">💊 فعالية نوع العلاج</div></div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>نوع العلاج</th>
                                <th>إجمالي الحالات</th>
                                <th>تحسن</th>
                                <th>شفاء تام</th>
                                <th>نسبة التحسن</th>
                                <th>مؤشر الفعالية</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($treat_efficacy)): ?>
                            <tr><td colspan="6" style="color:#94a3b8;">لا توجد بيانات كافية للتحليل</td></tr>
                            <?php else: foreach ($treat_efficacy as $t): ?>
                            <tr>
                                <td><strong><?php echo escape_output($t['treatment_type']); ?></strong></td>
                                <td><?php echo $t['total_cases']; ?></td>
                                <td><?php echo $t['improved']; ?></td>
                                <td><?php echo $t['healed']; ?></td>
                                <td><?php echo number_format($t['avg_improvement'], 0); ?>%</td>
                                <td>
                                    <?php 
                                    $efficacy = $t['total_cases'] > 0 ? ($t['improved'] / $t['total_cases']) * 100 : 0;
                                    if ($efficacy >= 80): ?><span class="badge badge-success">ممتاز</span>
                                    <?php elseif ($efficacy >= 60): ?><span class="badge badge-info">جيد</span>
                                    <?php elseif ($efficacy >= 40): ?><span class="badge badge-warning">متوسط</span>
                                    <?php else: ?><span class="badge badge-danger">ضعيف</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Two column layout -->
            <div class="flex flex-wrap gap-4 mb-4">
                <!-- Antibiotics Efficacy -->
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header"><div class="card-title">🦠 المضادات الحيوية</div></div>
                    <div class="table-container">
                        <table style="font-size:13px;">
                            <thead><tr><th>النوع</th><th>حالات</th><th>تحسن</th></tr></thead>
                            <tbody>
                                <?php if (empty($abx_efficacy)): ?>
                                <tr><td colspan="3" style="color:#94a3b8;">لا توجد بيانات</td></tr>
                                <?php else: foreach ($abx_efficacy as $a): ?>
                                <tr>
                                    <td><?php echo escape_output($a['antibiotics_oral'] ?: $a['antibiotics_iv']); ?></td>
                                    <td><?php echo $a['total_cases']; ?></td>
                                    <td><?php echo number_format($a['avg_improvement'], 0); ?>%</td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Wound Condition vs Outcome -->
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header"><div class="card-title">🩹 حالة الجرح والنتيجة</div></div>
                    <div class="table-container">
                        <table style="font-size:13px;">
                            <thead><tr><th>حالة الجرح</th><th>حالات</th><th>تحسن</th><th>شفاء</th></tr></thead>
                            <tbody>
                                <?php if (empty($wound_outcome)): ?>
                                <tr><td colspan="4" style="color:#94a3b8;">لا توجد بيانات</td></tr>
                                <?php else: foreach ($wound_outcome as $w): ?>
                                <tr>
                                    <td><?php echo escape_output($w['wound_condition']); ?></td>
                                    <td><?php echo $w['total']; ?></td>
                                    <td><?php echo number_format($w['avg_improvement'], 0); ?>%</td>
                                    <td><?php echo $w['healed_count']; ?></td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Dressing Frequency -->
                <div class="card" style="flex:1;min-width:200px;">
                    <div class="card-header"><div class="card-title">🔄 مرات الغيار</div></div>
                    <div class="table-container">
                        <table style="font-size:13px;">
                            <thead><tr><th>العدد</th><th>حالات</th><th>تحسن</th></tr></thead>
                            <tbody>
                                <?php if (empty($dressing_efficacy)): ?>
                                <tr><td colspan="3" style="color:#94a3b8;">لا توجد بيانات</td></tr>
                                <?php else: foreach ($dressing_efficacy as $d): ?>
                                <tr>
                                    <td><?php echo escape_output($d['dressing_frequency']); ?>/أسبوع</td>
                                    <td><?php echo $d['total']; ?></td>
                                    <td><?php echo number_format($d['avg_improvement'], 0); ?>%</td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Wagner Grade vs Healing -->
            <div class="card">
                <div class="card-header"><div class="card-title">📊 درجة Wagner مقابل سرعة الشفاء</div></div>
                <div class="table-container">
                    <table>
                        <thead><tr><th>درجة Wagner</th><th>إجمالي</th><th>متوسط التحسن</th><th>متوسط أيام الشفاء</th></tr></thead>
                        <tbody>
                            <?php if (empty($wagner_healing)): ?>
                            <tr><td colspan="4" style="color:#94a3b8;">لا توجد بيانات</td></tr>
                            <?php else: foreach ($wagner_healing as $w): ?>
                            <tr>
                                <td><strong>Wagner <?php echo $w['wagner_grade']; ?></strong></td>
                                <td><?php echo $w['total']; ?></td>
                                <td><?php echo number_format($w['avg_improvement'], 0); ?>%</td>
                                <td><?php echo $w['avg_days'] ? round($w['avg_days']) . ' يوم' : '—'; ?></td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
