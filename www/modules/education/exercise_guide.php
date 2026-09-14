<?php
/**
 * Exercise Guide - Physical Activity Module for Diabetic Patients
 */
$page_title = 'النشاط البدني | Exercise Guide';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$patient = null;
if ($patient_id) {
    $stmt = $mysqli->prepare("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM patients WHERE patient_id = ? AND is_active = 1");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $patient = $stmt->get_result()->fetch_assoc();
}

$exercises = [
    'walking' => ['icon' => '🚶', 'title' => 'المشي', 'duration' => '20-30 دقيقة', 'level' => 'مبتدئ',
        'desc' => 'أفضل نشاط بدني لمرضى السكري. المشي المنتظم يحسن حساسية الأنسولين ويخفض مستوى السكر.',
        'tips' => ['ارتد حذاء مريح ومناسب', 'ابدأ بمشي بطيء لمدة 10 دقائق', 'زد المدة تدريجياً', 'افحص قدميك قبل وبعد المشي', 'احمل معك قطعة سكر في حالة الهبوط']],
    'stretching' => ['icon' => '🤸', 'title' => 'تمارين الإطالة', 'duration' => '10-15 دقيقة', 'level' => 'مبتدئ',
        'desc' => 'تمارين إطالة لطيفة تحسن المرونة وتنشط الدورة الدموية في الأطراف.',
        'tips' => ['تنفس بعمق أثناء الإطالة', 'لا تصل إلى درجة الألم', 'كرر كل تمرين 3-5 مرات', 'حافظ على استقامة الظهر']],
    'ankle' => ['icon' => '🦶', 'title' => 'تمارين الكاحل', 'duration' => '5-10 دقيقة', 'level' => 'مبتدئ',
        'desc' => 'تمارين مخصصة لتقوية الكاحل وتحسين الدورة الدموية في القدمين.',
        'tips' => ['اجلس على كرسي مريح', 'حرك الكاحل في دوائر', 'ارفع وأخفض أصابع القدم', 'كرر 10 مرات لكل قدم']],
    'resistance' => ['icon' => '🏋️', 'title' => 'تمارين المقاومة', 'duration' => '15-20 دقيقة', 'level' => 'متوسط',
        'desc' => 'تمارين تقوية العضلات باستخدام وزن الجسم أو أربطة المقاومة الخفيفة.',
        'tips' => ['استخدم أربطة مقاومة خفيفة', 'ركز على التنفس الصحيح', 'خذ راحة 30 ثانية بين التمارين', 'لا تنس إحماء العضلات قبل التمرين']],
    'swimming' => ['icon' => '🏊', 'title' => 'السباحة', 'duration' => '20-30 دقيقة', 'level' => 'متوسط',
        'desc' => 'رياضة ممتازة لمرضى السكري لأنها لا تحمل الوزن على المفاصل والقدمين.',
        'tips' => ['جفف قدميك جيداً بعد السباحة', 'افحص قدميك بحثاً عن جروح', 'اشرب الماء قبل وبعد', 'لا تسبح إذا كان لديك جرح مفتوح']],
    'cycling' => ['icon' => '🚴', 'title' => 'ركوب الدراجة', 'duration' => '20-30 دقيقة', 'level' => 'متوسط',
        'desc' => 'تمارين ركوب الدراجة الثابتة أو العادية تحسن صحة القلب والأوعية الدموية.',
        'tips' => ['اضبط مقعد الدراجة بشكل مريح', 'ارتد حذاء رياضي مناسب', 'حافظ على وتيرة ثابتة', 'افحص القدمين بعد التمرين']],
];
?>
<style>
    .exercise-hero {
        background: linear-gradient(135deg, #1565c0, #0d47a1); color: white;
        padding: 2rem; border-radius: 16px; margin-bottom: 2rem; text-align: center;
        position: relative; overflow: hidden;
    }
    .exercise-hero::before { content: '🏃'; position: absolute; right: -20px; top: -20px; font-size: 100px; opacity: 0.1; }
    .exercise-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.2rem; }
    .ex-card {
        background: white; border-radius: 12px; padding: 1.5rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.3s;
        border-bottom: 3px solid #1565c0;
    }
    .ex-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
    .ex-card .ex-icon { font-size: 2.2rem; margin-bottom: 0.5rem; }
    .ex-card h4 { font-size: 1.1rem; margin-bottom: 0.3rem; }
    .ex-card .ex-meta { display: flex; gap: 0.8rem; font-size: 0.8rem; color: #666; margin-bottom: 0.8rem; }
    .ex-card .ex-desc { font-size: 0.9rem; color: #555; line-height: 1.7; margin-bottom: 0.8rem; }
    .ex-card .ex-tips { background: #e3f2fd; border-radius: 8px; padding: 0.8rem; }
    .ex-card .ex-tips h5 { font-size: 0.85rem; color: #1565c0; margin-bottom: 0.4rem; }
    .ex-card .ex-tips ul { list-style: none; padding: 0; margin: 0; }
    .ex-card .ex-tips ul li { font-size: 0.82rem; color: #444; padding: 0.2rem 0; padding-right: 1.2rem; position: relative; }
    .ex-card .ex-tips ul li::before { content: '✓'; position: absolute; right: 0; color: #1565c0; }

    .precautions-card {
        background: linear-gradient(135deg, #fff3e0, #ffe0b2);
        border: 2px solid #ff9800; border-radius: 12px; padding: 1.5rem; margin: 1.5rem 0;
    }
    .precautions-card h3 { color: #e65100; margin-bottom: 0.8rem; }

    @media (max-width: 768px) { .exercise-grid { grid-template-columns: 1fr; } }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="exercise-hero fade-in">
    <h1>🏃 النشاط البدني والتمارين الرياضية</h1>
    <p>Exercise Guide — تمارين مناسبة وآمنة لمرضى السكري</p>
    <?php if ($patient): ?><div style="margin-top:0.8rem;background:rgba(255,255,255,0.15);padding:0.4rem 1rem;border-radius:8px;display:inline-block;">👤 <?php echo escape_output($patient['full_name']); ?></div><?php endif; ?>
</div>

<div class="card mb-4" style="background:#e8f5e9;border:1px solid #a5d6a7;">
    <div class="card-header" style="border-bottom-color:#a5d6a7;"><div class="card-title" style="color:#2e7d32;">💪 فوائد التمارين الرياضية لمرضى السكري</div></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.8rem;padding:0.5rem;">
        <div style="text-align:center;padding:0.8rem;"><span style="font-size:1.8rem;">📉</span><br><strong>خفض السكر</strong><br><span style="font-size:0.8rem;color:#555;">تحسن حساسية الأنسولين</span></div>
        <div style="text-align:center;padding:0.8rem;"><span style="font-size:1.8rem;">❤️</span><br><strong>صحة القلب</strong><br><span style="font-size:0.8rem;color:#555;">تقوية القلب والأوعية</span></div>
        <div style="text-align:center;padding:0.8rem;"><span style="font-size:1.8rem;">⚖️</span><br><strong>التحكم بالوزن</strong><br><span style="font-size:0.8rem;color:#555;">حرق السعرات الحرارية</span></div>
        <div style="text-align:center;padding:0.8rem;"><span style="font-size:1.8rem;">😊</span><br><strong>تحسين المزاج</strong><br><span style="font-size:0.8rem;color:#555;">تقليل التوتر والقلق</span></div>
        <div style="text-align:center;padding:0.8rem;"><span style="font-size:1.8rem;">🦶</span><br><strong>الدورة الدموية</strong><br><span style="font-size:0.8rem;color:#555;">تنشيط الدورة في الأطراف</span></div>
    </div>
</div>

<div class="exercise-grid fade-in">
    <?php foreach ($exercises as $ex): ?>
    <div class="ex-card">
        <div class="ex-icon"><?php echo $ex['icon']; ?></div>
        <h4><?php echo $ex['title']; ?></h4>
        <div class="ex-meta">
            <span>⏱️ <?php echo $ex['duration']; ?></span>
            <span>📊 <?php echo $ex['level']; ?></span>
        </div>
        <div class="ex-desc"><?php echo $ex['desc']; ?></div>
        <div class="ex-tips">
            <h5>💡 نصائح</h5>
            <ul><?php foreach ($ex['tips'] as $tip): ?><li><?php echo $tip; ?></li><?php endforeach; ?></ul>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="precautions-card fade-in">
    <h3>⚠️ احتياطات مهمة قبل التمرين</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:0.8rem;">
        <div><strong>🩸 افحص سكرك</strong><br><span style="font-size:0.85rem;color:#555;">تأكد من أن سكر الدم بين 100-250 mg/dL قبل التمرين</span></div>
        <div><strong>👟 ارتد الأحذية المناسبة</strong><br><span style="font-size:0.85rem;color:#555;">استخدم حذاء رياضياً مريحاً ومناسباً لمقاس قدميك</span></div>
        <div><strong>💧 اشرب الماء</strong><br><span style="font-size:0.85rem;color:#555;">حافظ على ترطيب جسمك قبل وأثناء وبعد التمرين</span></div>
        <div><strong>🦶 افحص قدميك</strong><br><span style="font-size:0.85rem;color:#555;">افحص القدمين قبل وبعد التمرين بحثاً عن أي جروح</span></div>
        <div><strong>🍬 احمل مصدر سكر</strong><br><span style="font-size:0.85rem;color:#555;">احمل معك قطعة سكر أو عصير في حالة هبوط السكر</span></div>
        <div><strong>🛑 توقف عند اللزوم</strong><br><span style="font-size:0.85rem;color:#555;">توقف فوراً إذا شعرت بدوار أو ألم أو ضيق في التنفس</span></div>
    </div>
</div>

<div class="flex flex-wrap gap-2 mt-4 justify-center no-print">
    <a href="index.php<?php echo $patient_id ? "?patient_id=$patient_id" : ''; ?>" class="btn btn-secondary">🔙 مركز التوعية</a>
    <a href="diet_plan.php<?php echo $patient_id ? "?patient_id=$patient_id" : ''; ?>" class="btn btn-primary">🥗 النظام الغذائي</a>
</div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div></div>