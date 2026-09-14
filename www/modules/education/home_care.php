<?php
/**
 * Home Care Instructions Module
 * Generates personalized diabetes foot care instructions based on patient risk level
 */
$page_title = 'تعليمات الرعاية المنزلية | Home Care';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$page_title = 'تعليمات الرعاية المنزلية';
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
require_once __DIR__ . '/../../includes/ai_client.php';

$patient = null;
$risk_level = 'غير محدد';
$risk_color = 'gray';
$ai_data = null;
$ai_available = false;

if ($patient_id) {
    $stmt = $mysqli->prepare("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM patients WHERE patient_id = ? AND is_active = 1");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();

    // AI-powered risk prediction using analyzePatient (sends full clinical data)
    $ai_health = checkAIHealth();
    if ($ai_health['running']) {
        $ai_result = analyzePatient($patient_id);
        if ($ai_result['success'] && isset($ai_result['data']['ensemble_score']) && $ai_result['data']['ensemble_score'] !== null) {
            $ai_data = $ai_result['data'];
            $ai_available = true;
            $risk_score = $ai_data['ensemble_score'];
            if ($risk_score >= 80) { $risk_level = 'شديد جدا'; $risk_color = 'red'; }
            elseif ($risk_score >= 60) { $risk_level = 'مرتفع'; $risk_color = 'red'; }
            elseif ($risk_score >= 40) { $risk_level = 'متوسط'; $risk_color = 'orange'; }
            else { $risk_level = 'منخفض'; $risk_color = 'green'; }
        }
    }
    // Fallback to rule-based risk from Wagner grade (always used as baseline)
    $risk_stmt = $mysqli->prepare("SELECT wagner_grade FROM foot_assessments WHERE visit_id IN (SELECT visit_id FROM visits WHERE patient_id = ?) ORDER BY foot_assessments.assessment_id DESC LIMIT 1");
    $risk_stmt->bind_param('i', $patient_id);
    $risk_stmt->execute();
    $risk_result = $risk_stmt->get_result();
    if ($risk_row = $risk_result->fetch_assoc()) {
        $wagner = $risk_row['wagner_grade'] ?? null;
        if ($wagner !== null && !$ai_available) {
            if ($wagner >= 4) { $risk_level = 'حرج'; $risk_color = 'red'; }
            elseif ($wagner >= 3) { $risk_level = 'مرتفع'; $risk_color = 'red'; }
            elseif ($wagner >= 2) { $risk_level = 'متوسط'; $risk_color = 'orange'; }
            else { $risk_level = 'منخفض'; $risk_color = 'green'; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape_output($page_title); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/animations.css">
    <style>
        :root {
            --education-primary: #2d8a4e;
            --education-warning: #e6a817;
            --education-danger: #d32f2f;
        }
        .education-header {
            background: linear-gradient(135deg, var(--education-primary), #1b5e20);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        .education-header::before {
            content: '🏥';
            position: absolute;
            left: -20px;
            top: -20px;
            font-size: 120px;
            opacity: 0.1;
        }
        .education-header h2 { font-size: 1.8rem; margin-bottom: 0.5rem; }
        .education-header p { opacity: 0.9; font-size: 1rem; }
        .risk-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.2rem;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.9rem;
        }
        .risk-badge.green { background: #e8f5e9; color: #2e7d32; }
        .risk-badge.orange { background: #fff3e0; color: #e65100; }
        .risk-badge.red { background: #ffebee; color: #c62828; }
        .risk-badge.gray { background: #f5f5f5; color: #616161; }

        .instruction-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-right: 4px solid var(--education-primary);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .instruction-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .instruction-card.warning { border-right-color: var(--education-warning); }
        .instruction-card.danger { border-right-color: var(--education-danger); }
        .instruction-card h4 {
            font-size: 1.1rem;
            margin-bottom: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .instruction-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .instruction-card ul li {
            padding: 0.4rem 0;
            padding-right: 1.5rem;
            position: relative;
            font-size: 0.95rem;
            color: #444;
            line-height: 1.7;
        }
        .instruction-card ul li::before {
            content: '✓';
            position: absolute;
            right: 0;
            color: var(--education-primary);
            font-weight: 700;
        }
        .instruction-card.warning ul li::before { color: var(--education-warning); }
        .instruction-card.danger ul li::before { color: var(--education-danger); }

        .daily-routine-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.2rem;
            margin: 1.5rem 0;
        }
        .routine-item {
            background: white;
            border-radius: 12px;
            padding: 1.2rem;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 2px solid #e8f5e9;
            transition: all 0.3s;
        }
        .routine-item:hover { border-color: var(--education-primary); }
        .routine-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .routine-item h5 { font-size: 1rem; margin-bottom: 0.3rem; color: #333; }
        .routine-item p { font-size: 0.85rem; color: #666; }

        .emergency-box {
            background: linear-gradient(135deg, #fff3e0, #ffe0b2);
            border: 2px solid #ff9800;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        .emergency-box h4 { color: #e65100; margin-bottom: 0.8rem; }
        .emergency-box ul li { color: #555; }
        .emergency-box ul li::before { color: #ff9800; content: '⚠' !important; }

        .print-btn {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            background: var(--education-primary);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.8rem 1.5rem;
            font-size: 0.95rem;
            font-family: 'Tajawal', sans-serif;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(46,125,50,0.3);
            transition: all 0.3s;
            z-index: 100;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(46,125,50,0.4);
        }

        @media print {
            .no-print { display: none !important; }
            .instruction-card { break-inside: avoid; }
        }

        .patient-info-bar {
            background: white;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .patient-info-bar span {
            font-size: 0.95rem;
            color: #555;
        }
        .patient-info-bar strong { color: #333; }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>

            <div class="page-content page-entrance">
                <div class="page-header">
                    <h1><i class="icon">📋</i> <?= $page_title ?></h1>
                    <div class="header-actions">
                        <button onclick="window.print()" class="btn btn-primary no-print">
                            🖨️ طباعة التعليمات
                        </button>
                    </div>
                </div>

                <?php if (!$patient): ?>
                    <div class="empty-state">
                        <div class="empty-icon">👤</div>
                        <h3>اختر مريضاً</h3>
                        <p>يرجى اختيار مريض من قائمة المرضى لعرض تعليمات الرعاية المخصصة</p>
                        <a href="<?php echo BASE_URL; ?>/modules/patients/index.php" class="btn btn-primary">قائمة المرضى</a>
                    </div>
                <?php else: ?>
                    <!-- Education Header -->
                    <div class="education-header fade-in">
                        <h2>مرحباً، <?= escape_output($patient['full_name']) ?> 👋</h2>
                        <p>تعليمات العناية بالقدم المصممة خصيصاً لك بناءً على حالتك الصحية</p>
                        <div style="margin-top: 1rem;">
                            <span class="risk-badge <?= $risk_color ?>">
                                مستوى الخطورة: <?= $risk_level ?>
                            </span>
                            <span style="margin-right: 1rem; opacity:0.9;">
                                العمر: <?= $patient['age'] ?> سنة
                            </span>
                            <span style="margin-right: 1rem; opacity:0.9;">
                                تاريخ الزيارة: <?= date('Y-m-d') ?>
                            </span>
                        </div>
                    </div>

                    <!-- Patient Info Bar -->
                    <div class="patient-info-bar fade-in">
                        <span><strong>رقم الملف:</strong> <?= escape_output($patient['file_number']) ?></span>
                        <span><strong>الجنس:</strong> <?php echo $patient['gender'] === 'ذكر' ? 'ذكر' : 'أنثى'; ?></span>
                        <span><strong>رقم الهاتف:</strong> <?= escape_output($patient['phone_primary']) ?></span>
                        <span><strong>المدينة:</strong> <?= escape_output($patient['city']) ?></span>
                    </div>

                    <!-- Daily Routine -->
                    <h3 style="margin-bottom: 1rem;">🗓️ روتين العناية اليومية</h3>
                    <div class="daily-routine-grid fade-in">
                        <div class="routine-item">
                            <div class="routine-icon">👁️</div>
                            <h5>الفحص اليومي</h5>
                            <p>افحص قدميك يومياً بحثاً عن أي جروح أو تغيرات</p>
                        </div>
                        <div class="routine-item">
                            <div class="routine-icon">🧼</div>
                            <h5>النظافة</h5>
                            <p>اغسل قدميك بالماء الفاتر وجففهما جيداً</p>
                        </div>
                        <div class="routine-item">
                            <div class="routine-icon">🧴</div>
                            <h5>الترطيب</h5>
                            <p>رطب قدميك بكريم مرطب مناسب (تجنب بين الأصابع)</p>
                        </div>
                        <div class="routine-item">
                            <div class="routine-icon">👟</div>
                            <h5>الأحذية المناسبة</h5>
                            <p>ارتد أحذية طبية مريحة ومناسبة لمقاس قدميك</p>
                        </div>
                        <div class="routine-item">
                            <div class="routine-icon">🩸</div>
                            <h5>فحص السكر</h5>
                            <p>راقب مستوى السكر في الدم بانتظام</p>
                        </div>
                        <div class="routine-item">
                            <div class="routine-icon">🏃</div>
                            <h5>الحركة</h5>
                            <p>قم بتمارين خفيفة للقدمين لتنشيط الدورة الدموية</p>
                        </div>
                    </div>

                    <!-- Personalized Instructions by Risk Level -->
                    <h3 style="margin-bottom: 1rem;">📋 تعليمات مخصصة</h3>

                    <div class="instruction-card fade-in">
                        <h4>🦶 العناية الأساسية بالقدم</h4>
                        <ul>
                            <li>افحص قدميك يومياً باستخدام مرآة لرؤية أسفل القدم</li>
                            <li>اغسل قدميك بالماء الفاتر (وليس الساخن) وجففها بلطف</li>
                            <li>استخدم مرطباً خالياً من العطور على القدمين مع تجنب ما بين الأصابع</li>
                            <li>قص الأظافر بشكل مستقيم وبرد الحواف الحادة</li>
                            <li>لا تمشي حافي القدمين أبداً، حتى داخل المنزل</li>
                            <li>افحص حذائك يومياً بحثاً عن أجسام غريبة أو بطانة ممزقة</li>
                        </ul>
                    </div>

                    <?php if (in_array($risk_level, ['متوسط', 'مرتفع', 'حرج'])): ?>
                    <div class="instruction-card warning fade-in">
                        <h4>⚠️ تعليمات إضافية (مستوى خطورة <?= $risk_level ?>)</h4>
                        <ul>
                            <li>قم بزيارة العيادة أسبوعياً لفحص القدمين من قبل المختص</li>
                            <li>تجنب استخدام الكريمات القوية أو العلاجات المنزلية غير الموصوفة</li>
                            <li>استخدم جوارب طبية غير ضاغطة من القطن الناعم</li>
                            <li>تجنب التعرض للحرارة المباشرة (المدفأة، الماء الساخن، وسادات التدفئة)</li>
                            <li>ارتد أحذية عميقة وواسعة مع نعال طبية مخصصة</li>
                            <li>تجنب الجلوس بوضعية تربيع الساقين لفترات طويلة</li>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (in_array($risk_level, ['مرتفع', 'حرج'])): ?>
                    <div class="instruction-card danger fade-in">
                        <h4>🚨 عناية خاصة بالجروح والتقرحات</h4>
                        <ul>
                            <li>لا تعالج الجروح بنفسك — توجه فوراً إلى العيادة</li>
                            <li>استخدم الضمادات المعقمة فقط حسب تعليمات الطبيب</li>
                            <li>تجنب وضع أي أدوية أو مراهم غير موصوفة على الجروح</li>
                            <li>راقب علامات العدوى: احمرار، تورم، إفرازات ذات رائحة، حرارة موضعية</li>
                            <li>قم بتغيير الضمادات يومياً أو حسب تعليمات الممرض</li>
                            <li>حافظ على الجرح نظيفاً وجافاً في جميع الأوقات</li>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <!-- Nutrition & Diet -->
                    <h3 style="margin-bottom: 1rem;">🥗 التغذية والنظام الغذائي</h3>
                    <div class="instruction-card fade-in">
                        <h4>🍎 إرشادات غذائية لمرضى السكري</h4>
                        <ul>
                            <li>تناول وجبات صغيرة متعددة (5-6 وجبات يومياً) بدلاً من وجبات كبيرة</li>
                            <li>ركز على الألياف: الخضروات، الفواكه بقشرها، الحبوب الكاملة</li>
                            <li>اختر البروتينات الخالية من الدهون: الدجاج منزوع الجلد، السمك، البقوليات</li>
                            <li>تجنب السكريات المكررة والحلويات والمشروبات الغازية</li>
                            <li>استخدم الدهون الصحية: زيت الزيتون، الأفوكادو، المكسرات غير المملحة</li>
                            <li>اشرب 8-10 أكواب من الماء يومياً وتجنب المشروبات المحلاة</li>
                            <li>قلل من الملح والأطعمة المعلبة والمصنعة</li>
                        </ul>
                    </div>

                    <!-- Foods to eat / avoid -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                        <div class="instruction-card fade-in" style="border-right-color: var(--education-primary);">
                            <h4>✅ أطعمة مفضلة</h4>
                            <ul>
                                <li>الخضروات الورقية (سبانخ، خس، كرنب)</li>
                                <li>الحبوب الكاملة (شوفان، كينوا، برغل)</li>
                                <li>الأسماك الدهنية (سلمون، تونة، سردين)</li>
                                <li>البقوليات (عدس، حمص، فاصوليا)</li>
                                <li>المكسرات النيئة (لوز، جوز، بندق)</li>
                                <li>الفواكه منخفضة السكر (تفاح، توت، فراولة)</li>
                            </ul>
                        </div>
                        <div class="instruction-card warning fade-in" style="border-right-color: var(--education-warning);">
                            <h4>❌ أطعمة تجنبها</h4>
                            <ul>
                                <li>الحلويات والمعجنات والسكريات</li>
                                <li>المشروبات الغازية والعصائر المحلاة</li>
                                <li>الأطعمة المقلية والدهون المشبعة</li>
                                <li>الأرز الأبيض والخبز الأبيض</li>
                                <li>الأطعمة المعلبة عالية الصوديوم</li>
                                <li>الوجبات السريعة والأطعمة المصنعة</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Exercise -->
                    <h3 style="margin-bottom: 1rem;">🏋️ النشاط البدني</h3>
                    <div class="instruction-card fade-in">
                        <h4>🚶 تمارين مناسبة لمرضى القدم السكري</h4>
                        <ul>
                            <li>المشي لمدة 30 دقيقة يومياً (إذا لم يمنع الطبيب)</li>
                            <li>تمارين شد عضلات الساق والقدم بلطف</li>
                            <li>حركات دائرية للكاحل لتقوية المفاصل</li>
                            <li>تمارين رفع الأصابع وتقويس القدم</li>
                            <li>استخدم كرة صغيرة للمساج تحت القدم لتنشيط الدورة الدموية</li>
                            <li>توقف فوراً إذا شعرت بألم أو عدم ارتياح</li>
                        </ul>
                    </div>

                    <!-- Emergency -->
                    <h3 style="margin-bottom: 1rem;">🆘 حالات الطوارئ</h3>
                    <div class="emergency-box fade-in">
                        <h4>⚠️ متى يجب التوجه فوراً إلى الطوارئ؟</h4>
                        <ul>
                            <li>ارتفاع شديد في درجة حرارة الجسم (أكثر من 38.5°م)</li>
                            <li>ظهور احمرار مفاجئ أو تورم حول الجرح</li>
                            <li>إفرازات ذات رائحة كريهة من الجرح</li>
                            <li>ألم شديد ومفاجئ في القدم</li>
                            <li>تغير لون الجلد إلى الأزرق أو الأسود</li>
                            <li>انخفاض أو ارتفاع حاد في مستوى السكر في الدم</li>
                            <li>شعور بالدوار أو الإغماء أو تسارع دقات القلب</li>
                        </ul>
                        <p style="margin-top: 1rem; font-weight: 700; color: #c62828;">
                            📞 اتصل بالعيادة فوراً: <?php echo SITE_NAME; ?> | أو توجه إلى أقرب طوارئ
                        </p>
                    </div>

                    <!-- Next Appointment -->
                    <div class="instruction-card fade-in" style="border-right-color: #1565c0;">
                        <h4>📅 موعد المتابعة القادم</h4>
                        <p style="font-size: 1rem; color: #444;">
                            يرجى الالتزام بمواعيد المتابعة الدورية. في حال عدم تحديد موعد، يرجى الاتصال بالعيادة للحجز.
                        </p>
                        <div style="margin-top: 1rem; display: flex; gap: 0.8rem; flex-wrap: wrap;">
                            <span style="background: #e3f2fd; padding: 0.3rem 0.8rem; border-radius: 6px; font-size: 0.9rem;">
                                📞 <?= escape_output($patient['phone_primary']) ?>
                            </span>
                            <span style="background: #e3f2fd; padding: 0.3rem 0.8rem; border-radius: 6px; font-size: 0.9rem;">
                                🏥 <?= escape_output($patient['city']) ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
        </main>
    </div>

    <!-- Print Button -->
    <?php if ($patient): ?>
    <button onclick="window.print()" class="print-btn no-print">
        🖨️ طباعة التعليمات
    </button>
    <?php endif; ?>

    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
</body>
</html>
