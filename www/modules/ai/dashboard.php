<?php
/**
 * dashboard.php — لوحة تحليل الذكاء الاصطناعي
 * AI Dashboard: Patient analysis with ML-powered predictions
 */
require_once __DIR__ . '/../../includes/auth_check.php';
$page_title = '🧠 تحليل الذكاء الاصطناعي';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

require_once __DIR__ . '/../../includes/ai_client.php';

// فحص حالة خادم AI
$ai_health = checkAIHealth();

// جلب قائمة المرضى للتحليل
$patients = $mysqli->query("SELECT patient_id, full_name, file_number, age, gender, city,
    (SELECT MAX(visit_date) FROM visits WHERE patient_id = p.patient_id) as last_visit,
    (SELECT fa.wagner_grade FROM visits v2 
     LEFT JOIN foot_assessments fa ON v2.visit_id = fa.visit_id 
     WHERE v2.patient_id = p.patient_id ORDER BY v2.visit_date DESC LIMIT 1) as wagner_grade
    FROM patients p WHERE is_active = 1 ORDER BY full_name ASC LIMIT 50");
?>

<style>
.ai-hero {
    background: linear-gradient(135deg, var(--primary), #0d9488, #0891b2);
    border-radius: 16px;
    padding: 30px;
    color: #fff;
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}
.ai-hero::before {
    content: '🧠';
    position: absolute;
    right: -20px;
    top: -20px;
    font-size: 120px;
    opacity: 0.15;
}
.ai-hero h1 { margin: 0; font-size: 1.5rem; }
.ai-hero p { margin: 8px 0 0; opacity: 0.9; font-size: 0.95rem; }
.ai-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-top: 12px;
}
.ai-status.online { background: rgba(255,255,255,0.2); color: #fff; }
.ai-status.offline { background: rgba(255,255,255,0.15); color: #fca5a5; }
.patient-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px;
    transition: all 0.2s;
    cursor: pointer;
}
.patient-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(10, 126, 110, 0.12);
    transform: translateY(-2px);
}
.patient-card .name { font-weight: 600; color: var(--text); }
.patient-card .meta { font-size: 0.85rem; color: var(--gray); margin-top: 4px; }
.patient-card .wagner-badge {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 700;
}
.patient-card .wagner-badge.w3 { background: #fee2e2; color: #dc2626; }
.patient-card .wagner-badge.w2 { background: #fef3c7; color: #d97706; }
.patient-card .wagner-badge.w1 { background: #d1fae5; color: #059669; }
.analyze-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: var(--primary);
    color: #fff;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: all 0.2s;
    text-decoration: none;
}
.analyze-btn:hover { background: var(--primary-dark); transform: translateY(-1px); }
.analyze-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
</style>

<div class="app-layout">
    <div class="main-content">
        <div class="page-content page-entrance">
            
            <div class="ai-hero">
                <h1>🧠 تحليل الذكاء الاصطناعي</h1>
                <p>تحليل شامل للمرضى باستخدام خوارزميات تعلم الآلة — توقع المخاطر، تقدير مدة الشفاء، وتوصيات علاجية ذكية</p>
                <div class="ai-status <?php echo $ai_health['running'] ? 'online' : 'offline'; ?>">
                    <?php if ($ai_health['running']): ?>
                        ✅ الخادم يعمل 
                        <?php if ($ai_health['risk_loaded']): ?>• 🧠 نموذج الخطر محمّل<?php endif; ?>
                        <?php if ($ai_health['healing_loaded']): ?>• ⏱️ نموذج الشفاء محمّل<?php endif; ?>
                        <?php if (!$ai_health['risk_loaded'] && !$ai_health['healing_loaded']): ?>• بدون نماذج (قواعد افتراضية)<?php endif; ?>
                    <?php else: ?>
                        ⚠️ <?php echo htmlspecialchars($ai_health['message'] ?? 'الخادم غير متاح'); ?>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!$ai_health['running']): ?>
            <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:12px;padding:20px;margin-bottom:24px;">
                <strong>⚠️ خادم الذكاء الاصطناعي غير قيد التشغيل</strong>
                <p style="margin:8px 0 0;color:#92400e;font-size:0.9rem;">
                    لتشغيل خادم AI، افتح نافذة Terminal جديدة وشغل:<br>
                    <code style="background:#fff7ed;padding:4px 10px;border-radius:4px;display:inline-block;margin-top:4px;">cd <?php echo dirname(dirname(dirname(__DIR__))); ?> && python ai_api/app.py</code>
                </p>
            </div>
            <?php endif; ?>

            <!-- ===== قائمة المرضى ===== -->
            <div style="margin-bottom:20px;">
                <h2 style="font-size:1.15rem;color:var(--text);margin-bottom:12px;">اختر مريضاً للتحليل</h2>
            </div>
            
            <div class="row">
                <?php if ($patients && $patients->num_rows > 0): ?>
                    <?php while ($p = $patients->fetch_assoc()): 
                        $wagner = $p['wagner_grade'] ?? null;
                        $wagner_class = $wagner >= 3 ? 'w3' : ($wagner >= 2 ? 'w2' : ($wagner !== null ? 'w1' : ''));
                    ?>
                    <div class="col-md-4 col-lg-3" style="margin-bottom:16px;">
                        <div class="patient-card" onclick="window.location.href='analyze.php?id=<?php echo $p['patient_id']; ?>'">
                            <div class="name"><?php echo escape_output($p['full_name']); ?></div>
                            <div class="meta">📁 <?php echo escape_output($p['file_number']); ?></div>
                            <div class="meta">
                                <?php echo (int)$p['age']; ?> سنة • 
                                <?php echo escape_output($p['gender']); ?> • 
                                <?php echo escape_output($p['city'] ?: '—'); ?>
                            </div>
                            <div style="margin-top:8px;display:flex;align-items:center;justify-content:space-between;">
                                <?php if ($wagner !== null): ?>
                                    <span class="wagner-badge <?php echo $wagner_class; ?>">Wagner <?php echo $wagner; ?></span>
                                <?php else: ?>
                                    <span style="color:var(--gray);font-size:0.8rem;">لا يوجد تقييم</span>
                                <?php endif; ?>
                                <span style="color:var(--gray);font-size:0.8rem;">
                                    <?php echo $p['last_visit'] ?: 'لا زيارات'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align:center;padding:60px 20px;">
                        <div style="font-size:48px;margin-bottom:16px;">👥</div>
                        <h3>لا يوجد مرضى</h3>
                        <p style="color:var(--gray);">أضف مرضى أولاً من قسم إدارة المرضى</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
