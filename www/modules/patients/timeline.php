<?php
/**
 * Patient Timeline - Chronological view of all patient data
 */
$page_title = '⏳ الخط الزمني | Timeline';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$patient = null;

if ($patient_id > 0) {
    $stmt = $mysqli->prepare("SELECT * FROM patients WHERE patient_id = ?");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
}

// Fetch all patients if none selected
$patients = $mysqli->query("SELECT patient_id, file_number, full_name FROM patients WHERE is_active = 1 ORDER BY full_name ASC");
?>
<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">

            <div class="page-header flex justify-between items-center flex-wrap gap-3">
                <div>
                    <h1 class="page-title">⏳ الخط الزمني للمريض</h1>
                    <p class="page-subtitle">جميع بيانات المريض في صفحة واحدة مرتبة زمنياً</p>
                </div>
            </div>

            <!-- Patient Selector -->
            <div class="card mb-4">
                <form method="get" class="flex gap-3 items-center flex-wrap">
                    <div class="field" style="flex:1;min-width:250px;">
                        <label>اختر المريض</label>
                        <select name="id" onchange="this.form.submit()" style="padding:10px 14px;font-size:15px;">
                            <option value="">-- اختر مريض --</option>
                            <?php while ($p = $patients->fetch_assoc()): ?>
                                <option value="<?php echo $p['patient_id']; ?>" <?php echo $patient_id === (int)$p['patient_id'] ? 'selected' : ''; ?>>
                                    [<?php echo $p['file_number']; ?>] <?php echo $p['full_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <?php if ($patient): ?>
                        <a href="<?php echo BASE_URL; ?>/modules/patients/view.php?id=<?php echo $patient_id; ?>" class="btn btn-secondary mt-4">👤 بطاقة المريض</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (!$patient): ?>
                <div class="card">
                    <p class="text-muted text-center" style="padding:40px 0;font-size:16px;">👈 الرجاء اختيار مريض من القائمة</p>
                </div>
            <?php else: 
                // Fetch ALL data for this patient
                
                // 1. Medical History
                $med_history = $mysqli->query("SELECT * FROM medical_history WHERE patient_id = $patient_id ORDER BY created_at DESC LIMIT 1")->fetch_assoc();
                
                // 2. All Visits with related data
                $visits = $mysqli->query("
                    SELECT v.*, u.full_name as doctor_name,
                           vs.weight, vs.height, vs.bmi, vs.blood_pressure_systolic, vs.blood_pressure_diastolic, vs.blood_pressure_text, vs.waist_circumference,
                           bsr.fpg_value, bsr.ppg_value, bsr.hba1c_value, bsr.random_sugar_value,
                           t.treatment_type, t.oral_meds_details, t.insulin_details,
                           fa.right_sensation, fa.right_pulse, fa.left_sensation, fa.left_pulse, fa.wagner_grade, fa.abpi_right, fa.abpi_left,
                           fu.cause, fu.initial_cause, fu.wound_condition, fu.wound_depth, fu.wound_size_cm2, fu.wound_foot,
                           lr.total_cholesterol, lr.ldl, lr.hdl, lr.triglycerides, lr.creatinine, lr.egfr, lr.microalbumin,
                           o.improvement_percentage, o.improvement_date, o.healing_date, o.current_amputation,
                           cp.care_type, cp.dressing_frequency, cp.offloading_needed, cp.dietary_plan
                    FROM visits v
                    LEFT JOIN users u ON v.created_by = u.user_id
                    LEFT JOIN vital_signs vs ON v.visit_id = vs.visit_id
                    LEFT JOIN blood_sugar_readings bsr ON v.visit_id = bsr.visit_id
                    LEFT JOIN treatments t ON v.visit_id = t.visit_id
                    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
                    LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
                    LEFT JOIN lab_results lr ON v.visit_id = lr.visit_id
                    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
                    LEFT JOIN care_plan cp ON v.visit_id = cp.visit_id
                    WHERE v.patient_id = $patient_id
                    ORDER BY v.visit_date ASC, v.created_at ASC
                ");
                
                // 3. Complications
                $complications = $mysqli->query("SELECT * FROM complications WHERE patient_id = $patient_id ORDER BY updated_at DESC LIMIT 1")->fetch_assoc();
                
                // 4. Care Sessions
                $care_sessions = $mysqli->query("
                    SELECT cs.*, u.full_name as performed_by_name 
                    FROM care_sessions cs 
                    JOIN visits v ON cs.visit_id = v.visit_id 
                    LEFT JOIN users u ON cs.performed_by = u.user_id
                    WHERE v.patient_id = $patient_id 
                    ORDER BY cs.session_date ASC
                ");

                // 5. Appointments
                $appointments = $mysqli->query("
                    SELECT * FROM appointments 
                    WHERE patient_id = $patient_id 
                    ORDER BY appointment_date ASC, appointment_time ASC
                ");
            ?>

            <!-- Patient Info Badge -->
            <div class="card mb-4" style="background:linear-gradient(135deg, var(--teal), var(--teal-light));color:#fff;">
                <div class="flex flex-wrap gap-4 items-center justify-between">
                    <div>
                        <h2 style="font-family:'Cairo',sans-serif;font-size:20px;"><?php echo escape_output($patient['full_name']); ?></h2>
                        <div class="flex gap-4 mt-2" style="font-size:13px;opacity:0.9;">
                            <span>📁 <?php echo $patient['file_number']; ?></span>
                            <span>🧑 <?php echo $patient['gender']; ?></span>
                            <span>🎂 <?php echo $patient['date_of_birth'] ?: '—'; ?></span>
                            <span>📞 <?php echo $patient['phone_primary'] ?: '—'; ?></span>
                            <span>🏙️ <?php echo $patient['city'] ?: '—'; ?></span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <span class="badge" style="background:rgba(255,255,255,0.2);color:#fff;">
                            <?php echo $visits->num_rows; ?> زيارة
                        </span>
                        <?php if ($med_history && $med_history['diabetes_type']): ?>
                            <span class="badge" style="background:rgba(255,255,255,0.2);color:#fff;">
                                <?php echo $med_history['diabetes_type']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Timeline -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">⏳ الخط الزمني</div>
                </div>
                <div style="position:relative;padding-right:30px;">
                    <!-- Timeline vertical line -->
                    <div style="position:absolute;right:8px;top:0;bottom:0;width:2px;background:var(--teal-mid);"></div>

                    <!-- Birth Entry -->
                    <div style="position:relative;padding:12px 20px 12px 0;border-bottom:1px solid var(--border);">
                        <div style="position:absolute;right:-22px;top:14px;width:14px;height:14px;border-radius:50%;background:var(--gold);border:3px solid var(--bg-card);"></div>
                        <div style="font-size:12px;color:var(--text-muted);">تاريخ الميلاد</div>
                        <div style="font-weight:600;"><?php echo $patient['date_of_birth'] ?: 'غير مسجل'; ?></div>
                    </div>

                    <!-- Medical History -->
                    <?php if ($med_history): ?>
                    <div style="position:relative;padding:12px 20px 12px 0;border-bottom:1px solid var(--border);">
                        <div style="position:absolute;right:-22px;top:14px;width:14px;height:14px;border-radius:50%;background:var(--blue);border:3px solid var(--bg-card);"></div>
                        <div style="font-size:12px;color:var(--text-muted);">التاريخ المرضي</div>
                        <div style="font-weight:600;"><?php echo $med_history['diabetes_type'] ?: 'غير محدد'; ?></div>
                        <div style="font-size:13px;color:var(--text-muted);">
                            <?php if ($med_history['diagnosis_year']): ?>تشخيص: <?php echo $med_history['diagnosis_year']; ?><?php endif; ?>
                            <?php if ($med_history['duration_years']): ?> | المدة: <?php echo $med_history['duration_years']; ?> سنة<?php endif; ?>
                            <?php if ($med_history['smoking_status'] && $med_history['smoking_status'] !== 'لا'): ?> | تدخين: <?php echo $med_history['smoking_status']; ?><?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Complications -->
                    <?php if ($complications && array_sum(array_slice($complications, 2, 6)) > 0): ?>
                    <div style="position:relative;padding:12px 20px 12px 0;border-bottom:1px solid var(--border);">
                        <div style="position:absolute;right:-22px;top:14px;width:14px;height:14px;border-radius:50%;background:var(--orange);border:3px solid var(--bg-card);"></div>
                        <div style="font-size:12px;color:var(--text-muted);">المضاعفات</div>
                        <div class="flex gap-2 flex-wrap mt-1">
                            <?php $comp_labels = ['has_retinopathy'=>'اعتلال الشبكية','has_nephropathy'=>'اعتلال الكلى','has_neuropathy'=>'اعتلال الأعصاب','has_cad'=>'الشرايين التاجية','has_cva'=>'السكتة الدماغية','has_pad'=>'الشرايين الطرفية']; ?>
                            <?php foreach ($comp_labels as $key => $label): ?>
                                <?php if (!empty($complications[$key])): ?>
                                    <span class="badge badge-warning"><?php echo $label; ?></span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Visits -->
                    <?php if ($visits->num_rows > 0): while ($v = $visits->fetch_assoc()): 
                        $has_data = $v['weight'] || $v['hba1c_value'] || $v['wagner_grade'] || $v['treatment_type'] || $v['wound_condition'];
                    ?>
                    <div style="position:relative;padding:14px 20px 14px 0;border-bottom:1px solid var(--border);">
                        <div style="position:absolute;right:-22px;top:16px;width:14px;height:14px;border-radius:50%;background:var(--teal);border:3px solid var(--bg-card);"></div>
                        <div class="flex justify-between items-start flex-wrap gap-2">
                            <div>
                                <div style="font-size:12px;color:var(--text-muted);">
                                    زيارة #<?php echo $v['visit_number']; ?> | <?php echo $v['visit_date']; ?>
                                    <?php if ($v['doctor_name']): ?> | د. <?php echo $v['doctor_name']; ?><?php endif; ?>
                                </div>
                                <div style="font-weight:600;margin-top:2px;">
                                    <?php echo $v['visit_reason'] ?: 'متابعة'; ?>
                                    <?php if ($v['chief_complaint']): ?> — <?php echo escape_output($v['chief_complaint']); ?><?php endif; ?>
                                </div>
                            </div>
                            <a href="<?php echo BASE_URL; ?>/modules/visits/view.php?id=<?php echo $v['visit_id']; ?>" class="btn btn-sm btn-secondary">عرض</a>
                        </div>
                        
                        <!-- Data chips -->
                        <?php if ($has_data): ?>
                        <div class="flex flex-wrap gap-2 mt-2">
                            <?php if ($v['weight']): ?><span class="badge badge-info">⚖️ <?php echo $v['weight']; ?> كجم</span><?php endif; ?>
                            <?php if ($v['bmi']): ?><span class="badge badge-info">📊 BMI: <?php echo $v['bmi']; ?></span><?php endif; ?>
                            <?php if ($v['blood_pressure_systolic']): ?><span class="badge badge-info">💓 <?php echo $v['blood_pressure_systolic']; ?>/<?php echo $v['blood_pressure_diastolic']; ?></span><?php endif; ?>
                            <?php if ($v['hba1c_value']): ?><span class="badge badge-<?php echo $v['hba1c_value'] > 7 ? 'danger' : 'success'; ?>">🩸 HbA1c: <?php echo $v['hba1c_value']; ?>%</span><?php endif; ?>
                            <?php if ($v['fpg_value']): ?><span class="badge badge-warning">🍬 FPG: <?php echo $v['fpg_value']; ?></span><?php endif; ?>
                            <?php if ($v['total_cholesterol']): ?><span class="badge badge-info">🩺 Chol: <?php echo $v['total_cholesterol']; ?></span><?php endif; ?>
                            <?php if ($v['wagner_grade'] !== null): ?><span class="badge badge-<?php echo $v['wagner_grade'] >= 3 ? 'danger' : 'warning'; ?>">🦶 Wagner: <?php echo $v['wagner_grade']; ?></span><?php endif; ?>
                            <?php if ($v['wound_condition']): ?><span class="badge badge-danger">🩹 <?php echo $v['wound_condition']; ?></span><?php endif; ?>
                            <?php if ($v['wound_size_cm2']): ?><span class="badge badge-warning">📏 <?php echo $v['wound_size_cm2']; ?> سم²</span><?php endif; ?>
                            <?php if ($v['abpi_right']): ?><span class="badge badge-info">ABPI: <?php echo $v['abpi_right']; ?>/<?php echo $v['abpi_left']; ?></span><?php endif; ?>
                            <?php if ($v['treatment_type']): ?><span class="badge badge-success">💊 <?php echo $v['treatment_type']; ?></span><?php endif; ?>
                            <?php if ($v['improvement_percentage'] !== null): ?><span class="badge badge-<?php echo $v['improvement_percentage'] >= 75 ? 'success' : 'warning'; ?>">📈 تحسن: <?php echo $v['improvement_percentage']; ?>%</span><?php endif; ?>
                            <?php if ($v['healing_date']): ?><span class="badge badge-success">✅ تم الشفاء <?php echo $v['healing_date']; ?></span><?php endif; ?>
                            <?php if ($v['current_amputation'] && $v['current_amputation'] !== 'لا'): ?><span class="badge badge-danger">🦿 بتر: <?php echo $v['current_amputation']; ?></span><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Doctor Notes -->
                        <?php if ($v['doctor_notes']): ?>
                        <div style="font-size:13px;color:var(--text-muted);margin-top:6px;padding:6px 10px;background:var(--bg-input);border-radius:6px;">
                            📝 <?php echo escape_output($v['doctor_notes']); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; endif; ?>

                    <!-- Appointments -->
                    <?php if ($appointments && $appointments->num_rows > 0): while ($ap = $appointments->fetch_assoc()): ?>
                    <div style="position:relative;padding:12px 20px 12px 0;border-bottom:1px solid var(--border);">
                        <div style="position:absolute;right:-22px;top:14px;width:14px;height:14px;border-radius:50%;background:var(--gold);border:3px solid var(--bg-card);"></div>
                        <div style="font-size:12px;color:var(--text-muted);">📅 موعد</div>
                        <div style="font-weight:600;"><?php echo $ap['appointment_date']; ?> — <?php echo date('H:i', strtotime($ap['appointment_time'])); ?></div>
                        <div style="font-size:13px;">
                            <span class="badge badge-info"><?php echo $ap['appointment_type']; ?></span>
                            <span class="badge badge-<?php echo $ap['status'] === 'مؤكد' ? 'success' : ($ap['status'] === 'ملغي' ? 'danger' : 'warning'); ?>"><?php echo $ap['status']; ?></span>
                        </div>
                    </div>
                    <?php endwhile; endif; ?>

                    <!-- Care Sessions -->
                    <?php if ($care_sessions && $care_sessions->num_rows > 0): while ($cs = $care_sessions->fetch_assoc()): ?>
                    <div style="position:relative;padding:12px 20px 12px 0;border-bottom:1px solid var(--border);">
                        <div style="position:absolute;right:-22px;top:14px;width:14px;height:14px;border-radius:50%;background:var(--blue);border:3px solid var(--bg-card);"></div>
                        <div style="font-size:12px;color:var(--text-muted);">🩹 جلسة عناية | <?php echo $cs['session_date']; ?></div>
                        <div style="font-weight:600;">بواسطة: <?php echo $cs['performed_by_name'] ?: '—'; ?></div>
                        <?php if ($cs['care_provided']): ?><div style="font-size:13px;color:var(--text-muted);"><?php echo escape_output($cs['care_provided']); ?></div><?php endif; ?>
                    </div>
                    <?php endwhile; endif; ?>

                </div>
            </div>

            <?php endif; ?>

        </div>
        <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
    </div>
</div>
