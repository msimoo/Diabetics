<?php
/**
 * Emergency Guide Module
 * Emergency instructions and warning signs for diabetic foot patients
 */
$page_title = 'دليل الطوارئ | Emergency Guide';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$page_title = 'دليل الطوارئ';
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

$patient = null;
if ($patient_id) {
    $stmt = $mysqli->prepare("SELECT * FROM patients WHERE patient_id = ? AND is_active = 1");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();
}

// Emergency contact info
$emergency_hotlines = [
    ['name' => 'الإسعاف', 'number' => '123', 'icon' => '🚑'],
    ['name' => 'الدفاع المدني', 'number' => '998', 'icon' => '🔥'],
    ['name' => 'شرطة', 'number' => '999', 'icon' => '👮'],
];
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
        .emergency-header {
            background: linear-gradient(135deg, #d32f2f, #b71c1c);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
            animation: pulse-alert 2s infinite;
        }
        @keyframes pulse-alert {
            0%, 100% { box-shadow: 0 0 0 0 rgba(211,47,47,0.4); }
            50% { box-shadow: 0 0 0 15px rgba(211,47,47,0); }
        }
        .emergency-header::before {
            content: '🚨';
            position: absolute;
            right: -30px;
            top: -30px;
            font-size: 150px;
            opacity: 0.1;
        }
        .emergency-header h1 { font-size: 2rem; margin-bottom: 0.3rem; }
        .emergency-header p { font-size: 1.1rem; opacity: 0.95; }

        .emergency-banner {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            border: 3px solid #ef5350;
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        .emergency-banner h2 {
            color: #c62828;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
        .emergency-banner .hotline-numbers {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        .hotline-card {
            background: white;
            border-radius: 12px;
            padding: 1.2rem 1.5rem;
            min-width: 150px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .hotline-card:hover { transform: scale(1.05); }
        .hotline-card .icon { font-size: 2rem; }
        .hotline-card .number {
            font-size: 2rem;
            font-weight: 800;
            color: #d32f2f;
            direction: ltr;
            margin: 0.3rem 0;
        }
        .hotline-card .label { font-size: 0.9rem; color: #555; }

        .warning-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.2rem;
            margin: 1.5rem 0;
        }
        .warning-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-right: 4px solid;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .warning-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }
        .warning-card.red { border-right-color: #d32f2f; }
        .warning-card.orange { border-right-color: #e65100; }
        .warning-card.yellow { border-right-color: #f9a825; }
        .warning-card h4 {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.8rem;
            font-size: 1.05rem;
        }
        .warning-card p { font-size: 0.95rem; color: #555; line-height: 1.7; }
        .warning-card ul {
            list-style: none;
            padding: 0;
            margin: 0.5rem 0 0;
        }
        .warning-card ul li {
            padding: 0.3rem 1.5rem 0.3rem 0;
            position: relative;
            font-size: 0.9rem;
            color: #555;
        }
        .warning-card.red ul li::before {
            content: '✕';
            position: absolute;
            right: 0;
            color: #d32f2f;
            font-weight: 700;
        }
        .warning-card.orange ul li::before {
            content: '⚠';
            position: absolute;
            right: 0;
            color: #e65100;
        }
        .warning-card.yellow ul li::before {
            content: '●';
            position: absolute;
            right: 0;
            color: #f9a825;
        }

        .step-by-step {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        .step-item {
            display: flex;
            gap: 1rem;
            padding: 1rem 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .step-item:last-child { border-bottom: none; }
        .step-number {
            width: 36px;
            height: 36px;
            background: #d32f2f;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            flex-shrink: 0;
        }
        .step-content h5 { font-size: 1rem; margin-bottom: 0.3rem; color: #333; }
        .step-content p { font-size: 0.9rem; color: #666; }

        .hospital-card {
            background: white;
            border-radius: 12px;
            padding: 1.2rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.8rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        .hospital-card:hover { transform: translateX(-4px); }
        .hospital-card .h-icon { font-size: 2rem; }
        .hospital-card .h-info h5 { font-size: 1rem; margin-bottom: 0.2rem; }
        .hospital-card .h-info p { font-size: 0.85rem; color: #666; }

        .contact-form-section {
            background: linear-gradient(135deg, #fce4ec, #f8bbd0);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }
        .contact-form-section h3 { color: #c62828; margin-bottom: 1rem; }

        .whatsapp-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #25D366;
            color: white;
            padding: 0.8rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
            font-size: 0.95rem;
        }
        .whatsapp-btn:hover { background: #1da851; transform: translateY(-2px); }

        @media print {
            .no-print { display: none !important; }
            .warning-grid { break-inside: avoid; }
        }
        @media (max-width: 768px) {
            .emergency-banner .hotline-numbers { flex-direction: column; align-items: center; }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
        <div class="page-content page-entrance">
                <!-- Emergency Header -->
                <div class="emergency-header fade-in">
                    <h1>🚨 دليل الطوارئ</h1>
                    <p>إرشادات عاجلة لمرضى القدم السكري — احتفظ بهذه المعلومات في متناول يدك</p>
                    <?php if ($patient): ?>
                    <div style="margin-top: 0.8rem; background:rgba(255,255,255,0.15); padding:0.5rem 1rem; border-radius:8px; display:inline-block;">
                        👤 <?= escape_output($patient['full_name']) ?> — ملف رقم: <?= escape_output($patient['file_number']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Emergency Banner -->
                <div class="emergency-banner fade-in">
                    <h2>🆘 في حالة الطوارئ الطبية، اتصل فوراً</h2>
                    <div class="hotline-numbers">
                        <?php foreach ($emergency_hotlines as $hotline): ?>
                        <div class="hotline-card">
                            <div class="icon"><?= $hotline['icon'] ?></div>
                            <div class="number"><?= $hotline['number'] ?></div>
                            <div class="label"><?= escape_output($hotline['name']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p style="margin-top: 1rem; font-size: 1.1rem; color: #c62828; font-weight: 700;">
                        أو توجه إلى أقرب مستشفى فوراً
                    </p>
                </div>

                <!-- Warning Signs -->
                <h3 style="margin-bottom: 1rem;">⚠️ علامات الخطر — استشر الطبيب فوراً</h3>
                <div class="warning-grid fade-in">
                    <div class="warning-card red">
                        <h4>🔴 علامات خطيرة (طارئ)</h4>
                        <ul>
                            <li>ألم شديد ومفاجئ في القدم أو الساق</li>
                            <li>تغير لون الجلد إلى الأزرق أو الأسود أو الأرجواني</li>
                            <li>ظهور بثور أو تقرحات عميقة فجأة</li>
                            <li>إفرازات ذات رائحة كريهة من الجرح</li>
                            <li>ارتفاع مفاجئ في درجة حرارة الجسم (أكثر من 38.5°م)</li>
                            <li>تورم مفاجئ مع احمرار يمتد لأعلى الساق</li>
                            <li>عدم القدرة على تحريك القدم أو الأصابع</li>
                        </ul>
                    </div>
                    <div class="warning-card orange">
                        <h4>🟠 علامات متوسطة (مراجعة خلال 24 ساعة)</h4>
                        <ul>
                            <li>احمرار حول الجرح أو التقرح</li>
                            <li>تورم خفيف إلى متوسط في القدم</li>
                            <li>إفرازات بسيطة من الجرح</li>
                            <li>سخونة موضعية في منطقة الجرح</li>
                            <li>ظهور كدمات بدون سبب واضح</li>
                            <li>الشعور بوخز أو تنميل متزايد</li>
                            <li>صعوبة في ارتداء الحذاء بسبب التورم</li>
                        </ul>
                    </div>
                    <div class="warning-card yellow">
                        <h4>🟡 علامات تحذيرية (مراجعة خلال أسبوع)</h4>
                        <ul>
                            <li>جفاف وتشقق الجلد في القدمين</li>
                            <li>تغير لون الأظافر أو سمكها</li>
                            <li>ظهور مسامير أو ثآليل جديدة</li>
                            <li>الشعور بالحرقان الخفيف في القدمين</li>
                            <li>تغير في الإحساس (زيادة أو نقصان)</li>
                            <li>تعب غير عادي أو إرهاق</li>
                            <li>بطء في التئام الجروح الصغيرة</li>
                        </ul>
                    </div>
                </div>

                <!-- Step by Step Emergency Action Plan -->
                <h3 style="margin-bottom: 1rem;">📋 خطة العمل في حالة الطوارئ</h3>
                <div class="step-by-step fade-in">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h5>توقف عن أي نشاط واجلس فوراً</h5>
                            <p>لا تحاول المشي أو تحميل الوزن على القدم المصابة. اجلس في وضع مريح وارفع قدميك لأعلى.</p>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h5>افحص الجرح أو المنطقة المصابة</h5>
                            <p>استخدم مرآة إذا لزم الأمر. لا تلمس الجرح بأيدٍ غير نظيفة. لاحظ اللون والحجم ودرجة الحرارة.</p>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h5>نظف الجرح بلطف</h5>
                            <p>اغسل الجرح بمحلول ملحي معقم أو ماء مغلي ومبرد. استخدم شاشاً معقماً وجافاً لتغطية الجرح.</p>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h5>لا تضع أي أدوية أو مراهم</h5>
                            <p>لا تستخدم المضادات الحيوية أو المراهم بدون وصفة طبية. بعض الكريمات قد تزيد الوضع سوءاً.</p>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">5</div>
                        <div class="step-content">
                            <h5>اتصل بالعيادة أو الطوارئ فوراً</h5>
                            <p>استخدم أرقام الطوارئ أعلاه. أخبرهم أنك مريض سكري وتعاني من مشكلة في القدم.</p>
                        </div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">6</div>
                        <div class="step-content">
                            <h5>لا تأكل أو تشرب أي شيء</h5>
                            <p>قد تحتاج إلى تدخل جراحي طارئ. من المهم أن تكون المعدة فارغة في حال احتجت تخديراً.</p>
                        </div>
                    </div>
                </div>

                <!-- What to bring to ER -->
                <div class="contact-form-section fade-in">
                    <h3>🎒 ماذا تحضر معك إلى الطوارئ؟</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.8rem;">
                        <div style="background:white; border-radius:8px; padding:1rem; text-align:center;">
                            <div style="font-size:2rem; margin-bottom:0.3rem;">🆔</div>
                            <div style="font-weight:600;">الهوية الشخصية</div>
                        </div>
                        <div style="background:white; border-radius:8px; padding:1rem; text-align:center;">
                            <div style="font-size:2rem; margin-bottom:0.3rem;">📋</div>
                            <div style="font-weight:600;">التقارير الطبية</div>
                        </div>
                        <div style="background:white; border-radius:8px; padding:1rem; text-align:center;">
                            <div style="font-size:2rem; margin-bottom:0.3rem;">💊</div>
                            <div style="font-weight:600;">الأدوية الحالية</div>
                        </div>
                        <div style="background:white; border-radius:8px; padding:1rem; text-align:center;">
                            <div style="font-size:2rem; margin-bottom:0.3rem;">📱</div>
                            <div style="font-weight:600;">هاتف العيادة</div>
                        </div>
                        <div style="background:white; border-radius:8px; padding:1rem; text-align:center;">
                            <div style="font-size:2rem; margin-bottom:0.3rem;">🩸</div>
                            <div style="font-weight:600;">جهاز قياس السكر</div>
                        </div>
                        <div style="background:white; border-radius:8px; padding:1rem; text-align:center;">
                            <div style="font-size:2rem; margin-bottom:0.3rem;">👟</div>
                            <div style="font-weight:600;">حذاء طبي مريح</div>
                        </div>
                    </div>
                    <?php if ($patient): ?>
                    <div style="margin-top:1rem; text-align:center;">
                        <a href="https://wa.me/<?= preg_replace('/\D/', '', $patient['phone_primary']) ?>?text=حالة طارئة%20لمريض%20السكري%20<?= urlencode($patient['full_name']) ?>%20ملف%20رقم%20<?= urlencode($patient['file_number']) ?>" 
                           target="_blank" class="whatsapp-btn">
                            💬 أرسل رسالة واتساب فورية للعيادة
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Print & Save -->
                <div style="display: flex; gap: 1rem; justify-content: center; margin: 2rem 0; flex-wrap: wrap;">
                    <button onclick="window.print()" class="btn btn-primary no-print">
                        🖨️ طباعة دليل الطوارئ
                    </button>
                    <?php if ($patient): ?>
                    <a href="<?php echo BASE_URL; ?>/modules/education/home_care.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-secondary no-print">
                        📋 العودة لتعليمات الرعاية
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
        </div>
    </div>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
</body>
</html>
