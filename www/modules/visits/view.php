<?php
/**
 * View Visit Details
 */
$page_title = 'تفاصيل الزيارة | Visit Details';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$visit_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch visit with patient info
$stmt = $mysqli->prepare("SELECT v.*, p.full_name, p.file_number, p.age, p.gender, u.full_name as doctor_name
                          FROM visits v 
                          JOIN patients p ON v.patient_id = p.patient_id
                          LEFT JOIN users u ON v.created_by = u.user_id
                          WHERE v.visit_id = ?");
$stmt->bind_param('i', $visit_id);
$stmt->execute();
$visit = $stmt->get_result()->fetch_assoc();

if (!$visit) {
    echo "<div class='app-layout'><div class='main-content'><div class='page-content'><div class='alert alert-danger'>❌ الزيارة غير موجودة</div></div></div></div>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit;
}

// Fetch related data
$vitals = $mysqli->prepare("SELECT * FROM vital_signs WHERE visit_id = ?");
$vitals->bind_param('i', $visit_id);
$vitals->execute();
$vital_data = $vitals->get_result()->fetch_assoc();

$sugar = $mysqli->prepare("SELECT * FROM blood_sugar_readings WHERE visit_id = ?");
$sugar->bind_param('i', $visit_id);
$sugar->execute();
$sugar_data = $sugar->get_result()->fetch_assoc();

$treatment = $mysqli->prepare("SELECT * FROM treatments WHERE visit_id = ?");
$treatment->bind_param('i', $visit_id);
$treatment->execute();
$treatment_data = $treatment->get_result()->fetch_assoc();

$labs = $mysqli->prepare("SELECT * FROM lab_results WHERE visit_id = ?");
$labs->bind_param('i', $visit_id);
$labs->execute();
$lab_data = $labs->get_result()->fetch_assoc();

$foot = $mysqli->prepare("SELECT * FROM foot_assessments WHERE visit_id = ?");
$foot->bind_param('i', $visit_id);
$foot->execute();
$foot_data = $foot->get_result()->fetch_assoc();

$ulcer = $mysqli->prepare("SELECT * FROM foot_ulcers WHERE visit_id = ?");
$ulcer->bind_param('i', $visit_id);
$ulcer->execute();
$ulcer_data = $ulcer->get_result()->fetch_assoc();

$outcome = $mysqli->prepare("SELECT * FROM outcomes WHERE visit_id = ?");
$outcome->bind_param('i', $visit_id);
$outcome->execute();
$outcome_data = $outcome->get_result()->fetch_assoc();
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">
            
            <!-- Visit Header -->
            <div class="card mb-4" style="background:linear-gradient(135deg, var(--teal), var(--teal-light));color:#fff;">
                <div class="flex flex-wrap gap-3 items-center justify-between">
                    <div>
                        <h1 style="font-family:'Cairo',sans-serif;font-size:20px;font-weight:900;">
                            🩺 زيارة #<?php echo $visit['visit_number']; ?>
                        </h1>
                        <p style="opacity:0.85;font-size:14px;margin-top:4px;">
                            <?php echo escape_output($visit['full_name']); ?> — 📁 <?php echo escape_output($visit['file_number']); ?>
                            — 📅 <?php echo $visit['visit_date']; ?>
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $visit['patient_id']; ?>" class="btn btn-secondary" style="background:rgba(255,255,255,0.2);color:#fff;border:none;">
                            👤 ملف المريض
                        </a>
                        <button onclick="window.print()" class="btn btn-primary" style="background:var(--gold);color:var(--dark);border:none;">
                            🖨️ طباعة
                        </button>
                    </div>
                </div>
            </div>

            <!-- Doctor Notes -->
            <div class="card mb-4">
                <div class="card-header"><div class="card-title">📋 ملاحظات الطبيب</div></div>
                <div class="flex flex-wrap gap-4">
                    <div style="flex:1;min-width:200px;">
                        <strong>الشكوى الرئيسية:</strong>
                        <p style="color:var(--gray);margin-top:4px;"><?php echo nl2br(escape_output($visit['chief_complaint'] ?? '—')); ?></p>
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <strong>ملاحظات الزيارة:</strong>
                        <p style="color:var(--gray);margin-top:4px;"><?php echo nl2br(escape_output($visit['doctor_notes'] ?? '—')); ?></p>
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <strong>الخطة العلاجية:</strong>
                        <p style="color:var(--gray);margin-top:4px;"><?php echo nl2br(escape_output($visit['treatment_plan'] ?? '—')); ?></p>
                    </div>
                </div>
                <?php if ($visit['next_review_date']): ?>
                <div class="mt-3" style="padding:10px 14px;background:var(--teal-pale);border-radius:8px;display:inline-block;">
                    📅 تاريخ المراجعة القادمة: <strong><?php echo $visit['next_review_date']; ?></strong>
                </div>
                <?php endif; ?>
            </div>

            <!-- Vital Signs -->
            <div class="card mb-4">
                <div class="card-header"><div class="card-title">💉 العلامات الحيوية</div></div>
                <?php if ($vital_data): ?>
                <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));">
                    <div class="stat-card"><div class="stat-label">الوزن</div><div class="stat-value" style="font-size:18px;color:var(--teal);"><?php echo $vital_data['weight']; ?> <small>كغم</small></div></div>
                    <div class="stat-card"><div class="stat-label">الطول</div><div class="stat-value" style="font-size:18px;color:var(--teal);"><?php echo $vital_data['height']; ?> <small>سم</small></div></div>
                    <div class="stat-card"><div class="stat-label">BMI</div><div class="stat-value" style="font-size:18px;color:var(--teal);"><?php echo $vital_data['bmi']; ?></div></div>
                    <div class="stat-card"><div class="stat-label">ضغط الدم</div><div class="stat-value" style="font-size:18px;color:var(--teal);"><?php echo $vital_data['blood_pressure_text'] ?: ($vital_data['blood_pressure_systolic'] . '/' . $vital_data['blood_pressure_diastolic']); ?></div></div>
                    <div class="stat-card"><div class="stat-label">محيط الخصر</div><div class="stat-value" style="font-size:18px;color:var(--teal);"><?php echo $vital_data['waist_circumference']; ?> <small>سم</small></div></div>
                    <div class="stat-card"><div class="stat-label">درجة الحرارة</div><div class="stat-value" style="font-size:18px;color:var(--teal);"><?php echo $vital_data['temperature']; ?> <small>°م</small></div></div>
                </div>
                <?php else: ?>
                <p style="color:#94a3b8;">لم تسجل العلامات الحيوية</p>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/modules/visits/vitals.php?visit_id=<?php echo $visit_id; ?>" class="btn btn-sm btn-primary mt-3">✏️ تعديل</a>
            </div>

            <!-- Blood Sugar -->
            <div class="card mb-4">
                <div class="card-header"><div class="card-title">🔬 قراءات سكر الدم</div></div>
                <?php if ($sugar_data): ?>
                <table>
                    <thead><tr><th>القراءة</th><th>القيمة</th><th>التاريخ</th></tr></thead>
                    <tbody>
                        <tr><td>سكر الصيام (FPG)</td><td><?php echo $sugar_data['fpg_value'] ?: '—'; ?> mg/dL</td><td><?php echo $sugar_data['fpg_date'] ?: '—'; ?></td></tr>
                        <tr><td>سكر بعد الأكل (PPG)</td><td><?php echo $sugar_data['ppg_value'] ?: '—'; ?> mg/dL</td><td><?php echo $sugar_data['ppg_date'] ?: '—'; ?></td></tr>
                        <tr><td>HbA1c</td><td><strong><?php echo $sugar_data['hba1c_value'] ?: '—'; ?>%</strong></td><td><?php echo $sugar_data['hba1c_date'] ?: '—'; ?></td></tr>
                        <tr><td>سكر عشوائي</td><td><?php echo $sugar_data['random_sugar_value'] ?: '—'; ?> mg/dL</td><td><?php echo $sugar_data['random_sugar_date'] ?: '—'; ?></td></tr>
                    </tbody>
                </table>
                <?php else: ?>
                <p style="color:#94a3b8;">لم تسجل قراءات السكر</p>
                <?php endif; ?>
            </div>

            <!-- Two columns: Treatment + Labs -->
            <div class="flex flex-wrap gap-4 mb-4">
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header"><div class="card-title">💊 العلاج الحالي</div></div>
                    <?php if ($treatment_data): ?>
                    <div style="font-size:13px;">
                        <p><strong>النوع:</strong> <?php echo $treatment_data['treatment_type'] ?: '—'; ?></p>
                        <p><strong>الأدوية الفموية:</strong> <?php echo nl2br(escape_output($treatment_data['oral_meds_details'] ?? '—')); ?></p>
                        <p><strong>الأنسولين:</strong> <?php echo nl2br(escape_output($treatment_data['insulin_details'] ?? '—')); ?></p>
                        <p><strong>أدوية أخرى:</strong> <?php echo nl2br(escape_output($treatment_data['other_meds'] ?? '—')); ?></p>
                    </div>
                    <?php else: ?>
                    <p style="color:#94a3b8;">لم يسجل علاج</p>
                    <?php endif; ?>
                </div>
                <div class="card" style="flex:1;min-width:280px;">
                    <div class="card-header"><div class="card-title">🧪 الفحوصات المخبرية</div></div>
                    <?php if ($lab_data): ?>
                    <table style="font-size:13px;">
                        <tr><td style="font-weight:700;">الكوليسترول</td><td><?php echo $lab_data['total_cholesterol'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;">LDL</td><td><?php echo $lab_data['ldl'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;">HDL</td><td><?php echo $lab_data['hdl'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;">الدهون الثلاثية</td><td><?php echo $lab_data['triglycerides'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;">Creatinine</td><td><?php echo $lab_data['creatinine'] ?: '—'; ?></td></tr>
                        <tr><td style="font-weight:700;">eGFR</td><td><?php echo $lab_data['egfr'] ?: '—'; ?></td></tr>
                    </table>
                    <?php else: ?>
                    <p style="color:#94a3b8;">لم تسجل فحوصات مخبرية</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Foot Assessment -->
            <div class="card mb-4">
                <div class="card-header"><div class="card-title">🦶 تقييم القدم</div></div>
                <?php if ($foot_data): ?>
                <div class="flex flex-wrap gap-4">
                    <div style="flex:1;min-width:200px;">
                        <h4 style="font-weight:800;color:var(--teal);margin-bottom:8px;">القدم اليمنى</h4>
                        <p>الإحساس: <strong><?php echo $foot_data['right_sensation'] ?: '—'; ?></strong></p>
                        <p>النبض: <strong><?php echo $foot_data['right_pulse'] ?: '—'; ?></strong></p>
                        <p>التشوهات: <?php echo $foot_data['right_deformities'] ?: 'لا توجد'; ?></p>
                    </div>
                    <div style="flex:1;min-width:200px;">
                        <h4 style="font-weight:800;color:var(--teal);margin-bottom:8px;">القدم اليسرى</h4>
                        <p>الإحساس: <strong><?php echo $foot_data['left_sensation'] ?: '—'; ?></strong></p>
                        <p>النبض: <strong><?php echo $foot_data['left_pulse'] ?: '—'; ?></strong></p>
                        <p>التشوهات: <?php echo $foot_data['left_deformities'] ?: 'لا توجد'; ?></p>
                    </div>
                    <div style="flex:1;min-width:150px;">
                        <p>ABPI الأيمن: <strong><?php echo $foot_data['abpi_right'] ?? '—'; ?></strong></p>
                        <p>ABPI الأيسر: <strong><?php echo $foot_data['abpi_left'] ?? '—'; ?></strong></p>
                        <p>درجة Wagner: <strong style="font-size:20px;color:<?php echo ($foot_data['wagner_grade'] ?? 0) >= 3 ? '#ef4444' : '#10b981'; ?>;">
                            <?php echo $foot_data['wagner_grade'] ?? '—'; ?>
                        </strong></p>
                    </div>
                </div>
                <?php else: ?>
                <p style="color:#94a3b8;">لم يتم تقييم القدم</p>
                <?php endif; ?>
                <a href="<?php echo BASE_URL; ?>/modules/assessments/foot_exam.php?visit_id=<?php echo $visit_id; ?>" class="btn btn-sm btn-primary mt-3">✏️ تقييم القدم</a>
            </div>

            <!-- Ulcer -->
            <?php if ($ulcer_data): ?>
            <div class="card mb-4">
                <div class="card-header"><div class="card-title">🩹 الجرح</div></div>
                <div class="flex flex-wrap gap-4">
                    <div style="flex:1;">
                        <p>السبب: <strong><?php echo $ulcer_data['initial_cause'] ?: '—'; ?></strong></p>
                        <p>حالة الجرح: <strong><?php echo $ulcer_data['wound_condition'] ?: '—'; ?></strong></p>
                        <p>العمق: <strong><?php echo $ulcer_data['wound_depth'] ?: '—'; ?></strong></p>
                    </div>
                    <div style="flex:1;">
                        <p>القدم: <strong><?php echo $ulcer_data['wound_foot'] ?? '—'; ?></strong></p>
                        <p>المساحة: <strong><?php echo $ulcer_data['wound_size_cm2'] ? $ulcer_data['wound_size_cm2'] . ' سم²' : '—'; ?></strong></p>
                        <?php if ($ulcer_data['wound_location_x'] && $ulcer_data['wound_location_y']): ?>
                        <p>الموقع: (<?php echo $ulcer_data['wound_location_x']; ?>, <?php echo $ulcer_data['wound_location_y']; ?>)</p>
                        <?php endif; ?>
                    </div>
                    <?php if ($ulcer_data['wound_image']): ?>
                    <div>
                        <img src="<?php echo BASE_URL; ?>/uploads/wounds/<?php echo $ulcer_data['wound_image']; ?>" style="max-width:200px;border-radius:8px;">
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Outcomes -->
            <?php if ($outcome_data): ?>
            <div class="card mb-4">
                <div class="card-header"><div class="card-title">📈 النتائج</div></div>
                <div class="flex flex-wrap gap-4">
                    <div style="flex:1;">
                        <p>نسبة التحسن: <strong style="font-size:24px;"><?php echo $outcome_data['improvement_percentage'] ?? '0'; ?>%</strong></p>
                        <?php if ($outcome_data['healing_date']): ?>
                        <p>تاريخ الشفاء: <strong><?php echo $outcome_data['healing_date']; ?></strong></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($outcome_data['current_amputation'] && $outcome_data['current_amputation'] !== 'لا'): ?>
                    <div style="flex:1;">
                        <div class="alert alert-danger">⚠️ بتر حالي: <?php echo $outcome_data['current_amputation']; ?> (<?php echo $outcome_data['current_amputation_date']; ?>)</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
