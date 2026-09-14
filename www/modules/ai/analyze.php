<?php
/**
 * analyze.php — تحليل مريض بالذكاء الاصطناعي
 * AI-powered patient analysis with risk prediction, healing estimate, and recommendations
 */
require_once __DIR__ . '/../../includes/auth_check.php';

$patient_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$patient_id) { header('Location: dashboard.php'); exit; }

$page_title = '🧠 تحليل المريض';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

require_once __DIR__ . '/../../includes/ai_client.php';

// جلب بيانات المريض
$patient = $mysqli->query("SELECT p.*, 
    mh.diabetes_type, mh.duration_years, mh.smoking_status, mh.physical_activity,
    mh.other_chronic_diseases,
    c.has_retinopathy, c.has_nephropathy, c.has_neuropathy,
    (SELECT COUNT(*) FROM visits WHERE patient_id = p.patient_id) as total_visits,
    (SELECT MAX(visit_date) FROM visits WHERE patient_id = p.patient_id) as last_visit
    FROM patients p 
    LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
    LEFT JOIN complications c ON p.patient_id = c.patient_id
    WHERE p.patient_id = $patient_id")->fetch_assoc();

if (!$patient) { die('المريض غير موجود'); }

// جلب آخر البيانات السريرية
$latest = $mysqli->query("SELECT v.visit_date,
    vs.weight, vs.bmi, vs.blood_pressure_systolic, vs.blood_pressure_diastolic,
    bs.hba1c_value, bs.fpg_value,
    fa.wagner_grade, fa.right_sensation, fa.left_sensation,
    fa.right_pulse, fa.left_pulse, fa.abpi_right, fa.abpi_left,
    fu.wound_condition, fu.wound_depth, fu.wound_size_cm2, fu.initial_cause,
    o.improvement_percentage, o.current_amputation,
    t.treatment_type
    FROM visits v
    LEFT JOIN vital_signs vs ON v.visit_id = vs.visit_id
    LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    LEFT JOIN treatments t ON v.visit_id = t.visit_id
    WHERE v.patient_id = $patient_id
    ORDER BY v.visit_date DESC LIMIT 1")->fetch_assoc();

// تحليل AI (يُرسل إلى Flask API)
$ai_result = analyzePatient($patient_id);
$risk = $ai_result['success'] ? ($ai_result['data']['risk'] ?? []) : null;
$healing = $ai_result['success'] ? ($ai_result['data']['healing'] ?? []) : null;
$recommendations = $ai_result['success'] ? ($ai_result['data']['recommendations'] ?? []) : null;
$ensemble_score = $ai_result['success'] ? ($ai_result['data']['ensemble_score'] ?? null) : null;
$overall_class = $ai_result['success'] ? ($ai_result['data']['overall_class'] ?? null) : null;
$overall_color = $ai_result['success'] ? ($ai_result['data']['overall_color'] ?? null) : null;

// متوسط HbA1c (للتقييم)
$avg_hba1c = $mysqli->query("SELECT ROUND(AVG(hba1c_value), 1) as avg FROM blood_sugar_readings bs 
    JOIN visits v ON bs.visit_id = v.visit_id WHERE v.patient_id = $patient_id")->fetch_assoc()['avg'];
?>

<style>
.risk-meter {
    height: 12px;
    border-radius: 6px;
    background: #e2e8f0;
    margin: 12px 0;
    overflow: hidden;
}
.risk-fill {
    height: 100%;
    border-radius: 6px;
    transition: width 1s ease;
}
.result-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
}
.result-card h3 {
    font-size: 1rem;
    margin: 0 0 12px;
    color: var(--text);
    display: flex;
    align-items: center;
    gap: 8px;
}
.alert-item {
    padding: 10px 14px;
    border-radius: 8px;
    margin-bottom: 8px;
    font-size: 0.9rem;
    line-height: 1.5;
}
.alert-danger { background: #fef2f2; border-right: 4px solid #dc2626; }
.alert-warning { background: #fffbeb; border-right: 4px solid #f59e0b; }
.alert-success { background: #f0fdf4; border-right: 4px solid #059669; }
.alert-info { background: #eff6ff; border-right: 4px solid #3b82f6; }
.guideline-item {
    padding: 6px 0;
    font-size: 0.88rem;
    color: var(--text);
    border-bottom: 1px solid var(--bg-input);
}
.guideline-item:last-child { border-bottom: none; }
.recommendation-section {
    margin-bottom: 16px;
}
.recommendation-section h4 {
    font-size: 0.95rem;
    margin: 0 0 8px;
    color: var(--text);
}
.rec-card {
    background: #f8fafc;
    border-radius: 10px;
    padding: 14px;
    margin-bottom: 10px;
}
.rec-card h5 {
    font-size: 0.9rem;
    margin: 0 0 6px;
    color: var(--primary);
}
.rec-card ul {
    margin: 0;
    padding-right: 20px;
    font-size: 0.85rem;
    color: #475569;
    line-height: 1.7;
}
.rec-card ul li { margin-bottom: 2px; }
.ai-loading {
    text-align: center;
    padding: 40px;
}
.ai-loading .spinner {
    width: 40px;
    height: 40px;
    border: 4px solid #e2e8f0;
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 12px;
}
@keyframes spin { to { transform: rotate(360deg); } }
.risk-gauge {
    width: 140px;
    height: 140px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    font-weight: 700;
    position: relative;
}
.risk-gauge .score { font-size: 2rem; line-height: 1; }
.risk-gauge .label { font-size: 0.8rem; opacity: 0.9; margin-top: 4px; }
.patient-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 10px;
}
.patient-info-item {
    padding: 8px 12px;
    background: #f8fafc;
    border-radius: 8px;
    font-size: 0.85rem;
}
.patient-info-item .info-label { color: var(--gray); font-size: 0.8rem; }
.patient-info-item .info-value { font-weight: 600; color: var(--text); margin-top: 2px; }
</style>

<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">

            <!-- ===== رأس الصفحة ===== -->
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px;">
                <div>
                    <h1 style="font-size:1.3rem;margin:0;">🧠 تحليل الذكاء الاصطناعي</h1>
                    <p style="color:var(--gray);margin:4px 0 0;">تحليل شامل باستخدام خوارزميات تعلم الآلة</p>
                </div>
                <div>
                    <a href="dashboard.php" class="btn btn-outline" style="text-decoration:none;">🔙 العودة</a>
                    <button onclick="rerunAnalysis(event, <?php echo $patient_id; ?>)" class="btn btn-primary" style="margin-right:8px;">🔄 إعادة التحليل</button>
                </div>
            </div>

            <!-- ===== معلومات المريض ===== -->
            <div class="result-card">
                <h3>👤 بيانات المريض</h3>
                <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:12px;">
                    <div style="font-size:1.1rem;font-weight:700;"><?php echo escape_output($patient['full_name']); ?></div>
                    <span style="color:var(--gray);">📁 <?php echo escape_output($patient['file_number']); ?></span>
                    <span style="color:var(--gray);">🎂 <?php echo (int)$patient['age']; ?> سنة</span>
                    <span style="color:var(--gray);"><?php echo escape_output($patient['gender']); ?></span>
                    <span style="color:var(--gray);">📍 <?php echo escape_output($patient['city'] ?: '—'); ?></span>
                </div>
                <div class="patient-info-grid">
                    <div class="patient-info-item"><div class="info-label">نوع السكري</div><div class="info-value"><?php echo escape_output($patient['diabetes_type'] ?: '—'); ?></div></div>
                    <div class="patient-info-item"><div class="info-label">مدة السكري</div><div class="info-value"><?php echo (int)$patient['duration_years'] ?: '—'; ?> سنة</div></div>
                    <div class="patient-info-item"><div class="info-label">التدخين</div><div class="info-value"><?php echo escape_output($patient['smoking_status'] ?: '—'); ?></div></div>
                    <div class="patient-info-item"><div class="info-label">آخر زيارة</div><div class="info-value"><?php echo $patient['last_visit'] ?: '—'; ?></div></div>
                    <div class="patient-info-item"><div class="info-label">إجمالي الزيارات</div><div class="info-value"><?php echo (int)$patient['total_visits']; ?></div></div>
                    <div class="patient-info-item"><div class="info-label">متوسط HbA1c</div><div class="info-value"><?php echo $avg_hba1c ?: '—'; ?>%</div></div>
                    <div class="patient-info-item"><div class="info-label">درجة Wagner</div><div class="info-value"><?php echo $latest['wagner_grade'] ?? '—'; ?></div></div>
                    <div class="patient-info-item"><div class="info-label">حالة الجرح</div><div class="info-value"><?php echo escape_output($latest['wound_condition'] ?? '—'); ?></div></div>
                </div>
            </div>

            <!-- ===== النتيجة المدمجة ===== -->
            <?php if ($ensemble_score !== null): ?>
            <div class="result-card" style="text-align:center;">
                <h3 style="justify-content:center;">🎯 النتيجة المدمجة للذكاء الاصطناعي</h3>
                <?php 
                    $gauge_size = 140;
                    $pct = min($ensemble_score, 100);
                    $border_color = $overall_color ?? '#10b981';
                    $conic = "conic-gradient($border_color 0% " . $pct . "%, #e2e8f0 " . $pct . "% 100%)";
                ?>
                <div style="display:flex;align-items:center;justify-content:center;gap:30px;flex-wrap:wrap;">
                    <div style="width:<?php echo $gauge_size; ?>px;height:<?php echo $gauge_size; ?>px;border-radius:50%;background:<?php echo $conic; ?>;display:flex;align-items:center;justify-content:center;">
                        <div style="width:<?php echo $gauge_size - 20; ?>px;height:<?php echo $gauge_size - 20; ?>px;border-radius:50%;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;">
                            <span style="font-size:2rem;font-weight:700;color:<?php echo $border_color; ?>;"><?php echo $ensemble_score; ?></span>
                            <span style="font-size:0.75rem;color:var(--gray);">/ 100</span>
                        </div>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:1.2rem;font-weight:700;color:<?php echo $border_color; ?>;"><?php echo $overall_class; ?></div>
                        <div style="color:var(--gray);font-size:0.85rem;margin-top:4px;">التصنيف الشامل للمخاطر</div>
                        <div class="risk-meter" style="width:200px;">
                            <div class="risk-fill" style="width:<?php echo $pct; ?>%;background:<?php echo $border_color; ?>;"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ===== نتائج التحليل ===== -->
            <div class="row">
                <!-- ===== توقع الخطر ===== -->
                <div class="col-md-6">
                    <div class="result-card">
                        <h3>🔮 توقع الخطر</h3>
                        <?php if ($risk && !isset($risk['error'])): 
                            $risk_pct = $risk['risk_score'] ?? 0;
                            $risk_color = $risk_pct >= 80 ? '#dc2626' : ($risk_pct >= 60 ? '#ef4444' : ($risk_pct >= 40 ? '#f59e0b' : '#10b981'));
                        ?>
                        <div style="text-align:center;margin-bottom:16px;">
                            <div class="risk-meter" style="height:16px;">
                                <div class="risk-fill" style="width:<?php echo min($risk_pct, 100); ?>%;background:<?php echo $risk_color; ?>;"></div>
                            </div>
                            <div style="display:flex;justify-content:space-between;font-size:0.8rem;color:var(--gray);">
                                <span>0%</span>
                                <span style="font-size:1.5rem;font-weight:700;color:<?php echo $risk_color; ?>;"><?php echo $risk_pct; ?>%</span>
                                <span>100%</span>
                            </div>
                            <div style="font-weight:600;color:<?php echo $risk_color; ?>;margin-top:4px;">
                                <?php echo htmlspecialchars($risk['risk_class'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <div style="flex:1;text-align:center;padding:8px;background:#f8fafc;border-radius:8px;">
                                <div style="font-size:0.75rem;color:var(--gray);">Random Forest</div>
                                <div style="font-weight:600;"><?php echo $risk['rf_probability'] ?? '—'; ?>%</div>
                            </div>
                            <div style="flex:1;text-align:center;padding:8px;background:#f8fafc;border-radius:8px;">
                                <div style="font-size:0.75rem;color:var(--gray);">XGBoost</div>
                                <div style="font-weight:600;"><?php echo $risk['xgb_probability'] ?? '—'; ?>%</div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="text-align:center;padding:20px;">
                            <div style="font-size:32px;margin-bottom:8px;">🔮</div>
                            <p style="color:var(--gray);"><?php echo $ai_result['error'] ?? 'خادم AI غير متاح'; ?></p>
                            <p style="font-size:0.85rem;color:var(--gray);">قم بتشغيل: <code>python ai_api/app.py</code></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ===== تقدير مدة الشفاء ===== -->
                <div class="col-md-6">
                    <div class="result-card">
                        <h3>⏱️ تقدير مدة الشفاء</h3>
                        <?php if ($healing && !isset($healing['error'])): 
                            $weeks = $healing['estimated_weeks'] ?? 0;
                            $ci = $healing['confidence_interval'] ?? [0, 0];
                        ?>
                        <div style="text-align:center;margin-bottom:16px;">
                            <div style="font-size:3rem;font-weight:700;color:var(--primary);"><?php echo $weeks; ?></div>
                            <div style="color:var(--gray);font-size:0.95rem;">أسابيع متوقعة للشفاء</div>
                            <div style="margin-top:8px;font-size:0.85rem;color:var(--gray);">
                                فترة الثقة: <?php echo $ci[0]; ?> — <?php echo $ci[1]; ?> أسبوع
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <div style="flex:1;text-align:center;padding:8px;background:#f8fafc;border-radius:8px;">
                                <div style="font-size:0.75rem;color:var(--gray);">Random Forest</div>
                                <div style="font-weight:600;"><?php echo $healing['rf_estimate'] ?? '—'; ?> أسبوع</div>
                            </div>
                            <div style="flex:1;text-align:center;padding:8px;background:#f8fafc;border-radius:8px;">
                                <div style="font-size:0.75rem;color:var(--gray);">Gradient Boosting</div>
                                <div style="font-weight:600;"><?php echo $healing['gb_estimate'] ?? '—'; ?> أسبوع</div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div style="text-align:center;padding:20px;">
                            <div style="font-size:32px;margin-bottom:8px;">⏱️</div>
                            <p style="color:var(--gray);"><?php echo $ai_result['error'] ?? 'خادم AI غير متاح'; ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ===== التوصيات ===== -->
            <div class="result-card">
                <h3>💡 التوصيات الذكية</h3>
                <?php if ($recommendations): ?>
                    <div class="row">
                        <!-- تنبيهات -->
                        <?php $alerts = $recommendations['risk_alerts'] ?? []; ?>
                        <?php if (!empty($alerts)): ?>
                        <div class="col-12" style="margin-bottom:16px;">
                            <h4>⚠️ التنبيهات</h4>
                            <?php foreach ($alerts as $alert): ?>
                                <div class="alert-item <?php echo str_contains($alert, '🔴') ? 'alert-danger' : 'alert-warning'; ?>">
                                    <?php echo nl2br(htmlspecialchars($alert)); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- النظام الغذائي -->
                        <?php $diet = $recommendations['diet_plan'] ?? []; ?>
                        <?php if (!empty($diet)): ?>
                        <div class="col-md-6">
                            <div class="rec-card">
                                <h5>🥗 <?php echo htmlspecialchars($diet['title'] ?? 'النظام الغذائي'); ?></h5>
                                <p style="font-size:0.85rem;color:var(--gray);margin-bottom:8px;">
                                    <?php echo htmlspecialchars($diet['type'] ?? ''); ?> 
                                    — <?php echo htmlspecialchars($diet['calories'] ?? ''); ?>
                                </p>
                                <?php $guidelines = $diet['guidelines'] ?? []; ?>
                                <?php if (!empty($guidelines)): ?>
                                <ul>
                                    <?php foreach ($guidelines as $g): ?>
                                    <li><?php echo htmlspecialchars($g); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- العناية المنزلية -->
                        <?php $home_care = $recommendations['home_care'] ?? []; ?>
                        <?php if (!empty($home_care)): ?>
                        <div class="col-md-6">
                            <div class="rec-card">
                                <h5>🏠 <?php echo htmlspecialchars($home_care['title'] ?? 'العناية المنزلية'); ?></h5>
                                <?php foreach (['daily_foot_check' => '📅 الفحص اليومي', 'wound_care' => '🩹 العناية بالجرح', 'footwear' => '👟 الأحذية'] as $key => $section_title): ?>
                                    <?php $items = $home_care[$key] ?? []; ?>
                                    <?php if (!empty($items)): ?>
                                    <div style="margin-bottom:8px;">
                                        <strong style="font-size:0.85rem;"><?php echo $section_title; ?></strong>
                                        <ul>
                                            <?php foreach ($items as $item): ?>
                                            <li><?php echo htmlspecialchars($item); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- توصيات دوائية -->
                        <?php $med_advice = $recommendations['medication_advice'] ?? []; ?>
                        <?php if (!empty($med_advice['items'] ?? [])): ?>
                        <div class="col-md-6">
                            <div class="rec-card">
                                <h5>💊 <?php echo htmlspecialchars($med_advice['title'] ?? 'توصيات دوائية'); ?></h5>
                                <ul>
                                    <?php foreach ($med_advice['items'] as $item): ?>
                                    <li><?php echo htmlspecialchars($item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- تعليمات الطوارئ -->
                        <?php $emergency = $recommendations['emergency_instructions'] ?? []; ?>
                        <?php if (!empty($emergency['items'] ?? [])): ?>
                        <div class="col-md-6">
                            <div class="rec-card" style="border:1px solid #fecaca;background:#fef2f2;">
                                <h5>🚨 <?php echo htmlspecialchars($emergency['title'] ?? 'إجراءات الطوارئ'); ?></h5>
                                <ul>
                                    <?php foreach ($emergency['items'] as $item): ?>
                                    <li><?php echo htmlspecialchars($item); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if (!empty($emergency['emergency_numbers'])): ?>
                                <div style="margin-top:8px;padding-top:8px;border-top:1px solid #fecaca;">
                                    <?php foreach ($emergency['emergency_numbers'] as $name => $num): ?>
                                    <div style="font-size:0.9rem;"><?php echo htmlspecialchars($name); ?>: <strong><?php echo htmlspecialchars($num); ?></strong></div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php elseif (!$ai_health['running']): ?>
                    <div style="text-align:center;padding:20px;">
                        <p style="color:var(--gray);">⚠️ خادم AI غير متاح. التوصيات غير متوفرة.</p>
                    </div>
                <?php else: ?>
                    <div style="text-align:center;padding:20px;">
                        <p style="color:var(--gray);">لا توجد توصيات متاحة.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ===== صورة التنبيهات حسب درجة الخطر ===== -->
            <?php if ($risk && !isset($risk['error'])): ?>
            <div class="result-card" style="text-align:center;">
                <?php 
                    $r = $risk['risk_score'] ?? 0;
                    if ($r >= 80): 
                ?>
                    <div style="padding:16px;background:#fef2f2;border-radius:10px;border:1px solid #fecaca;">
                        <div style="font-size:2rem;">🚨</div>
                        <div style="font-weight:700;color:#dc2626;">🔴 خطر عالي — يُنصح بالمراجعة العاجلة</div>
                        <p style="color:#991b1b;font-size:0.9rem;margin-top:4px;">نسبة الخطورة ≥ 80% — يحتاج تدخل فوري ومتابعة مكثفة</p>
                    </div>
                <?php elseif ($r >= 50): ?>
                    <div style="padding:16px;background:#fffbeb;border-radius:10px;border:1px solid #fde68a;">
                        <div style="font-size:2rem;">⚠️</div>
                        <div style="font-weight:700;color:#d97706;">🟡 خطر متوسط — مراقبة مستمرة</div>
                        <p style="color:#92400e;font-size:0.9rem;margin-top:4px;">نسبة الخطورة ≥ 50% — يحتاج متابعة منتظمة ومراقبة التطورات</p>
                    </div>
                <?php else: ?>
                    <div style="padding:16px;background:#f0fdf4;border-radius:10px;border:1px solid #bbf7d0;">
                        <div style="font-size:2rem;">✅</div>
                        <div style="font-weight:700;color:#059669;">🟢 خطر منخفض</div>
                        <p style="color:#166534;font-size:0.9rem;margin-top:4px;">نسبة الخطورة < 50% — متابعة روتينية</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script>
// إعادة تحليل المريض
function rerunAnalysis(event, patientId) {
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '⏳ جاري التحليل...';
    
    fetch('<?php echo BASE_URL; ?>/api/ai_analysis.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ patient_id: patientId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('❌ فشل التحليل: ' + (data.error || 'خطأ غير معروف'));
            btn.disabled = false;
            btn.innerHTML = '🔄 إعادة التحليل';
        }
    })
    .catch(err => {
        alert('❌ فشل الاتصال: ' + err.message);
        btn.disabled = false;
        btn.innerHTML = '🔄 إعادة التحليل';
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
