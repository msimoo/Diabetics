<?php
/**
 * Foot Care Guide - Comprehensive Diabetic Foot Care Education Module
 */
$page_title = 'العناية بالقدم | Foot Care Guide';
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
?>
<style>
    .foot-hero {
        background: linear-gradient(135deg, #c62828, #b71c1c); color: white;
        padding: 2rem; border-radius: 16px; margin-bottom: 2rem; text-align: center;
        position: relative; overflow: hidden;
    }
    .foot-hero::before { content: '🦶'; position: absolute; right: -20px; top: -20px; font-size: 100px; opacity: 0.1; }

    .care-section { margin-bottom: 2rem; }
    .care-section h3 { margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }

    .step-card {
        display: flex; gap: 1rem; padding: 1.2rem; background: white;
        border-radius: 12px; margin-bottom: 0.8rem;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05); transition: all 0.3s;
        border-right: 4px solid #c62828;
    }
    .step-card:hover { transform: translateX(-4px); box-shadow: 0 6px 16px rgba(0,0,0,0.1); }
    .step-card .step-num {
        width: 40px; height: 40px; background: #c62828; color: white;
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-weight: 800; font-size: 1.2rem; flex-shrink: 0;
    }
    .step-card .step-content h5 { font-size: 1rem; margin-bottom: 0.3rem; }
    .step-card .step-content p { font-size: 0.88rem; color: #555; line-height: 1.7; }

    .do-dont-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    @media (max-width: 768px) { .do-dont-grid { grid-template-columns: 1fr; } }

    .do-card { background: #e8f5e9; border-radius: 12px; padding: 1.2rem; border-right: 4px solid #2e7d32; }
    .do-card h4 { color: #2e7d32; margin-bottom: 0.8rem; }
    .do-card ul { list-style: none; padding: 0; }
    .do-card ul li { padding: 0.3rem 1.5rem 0.3rem 0; position: relative; font-size: 0.9rem; color: #444; }
    .do-card ul li::before { content: '✅'; position: absolute; right: 0; }

    .dont-card { background: #ffebee; border-radius: 12px; padding: 1.2rem; border-right: 4px solid #c62828; }
    .dont-card h4 { color: #c62828; margin-bottom: 0.8rem; }
    .dont-card ul { list-style: none; padding: 0; }
    .dont-card ul li { padding: 0.3rem 1.5rem 0.3rem 0; position: relative; font-size: 0.9rem; color: #444; }
    .dont-card ul li::before { content: '❌'; position: absolute; right: 0; }

    .shoe-guide { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
    .shoe-item { text-align: center; padding: 1rem; background: white; border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
    .shoe-item .shoe-icon { font-size: 2rem; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="foot-hero fade-in">
    <h1>🦶 دليل العناية بالقدم</h1>
    <p>Comprehensive Foot Care Guide — الوقاية من القدم السكري وقرحه</p>
    <?php if ($patient): ?><div style="margin-top:0.8rem;background:rgba(255,255,255,0.15);padding:0.4rem 1rem;border-radius:8px;display:inline-block;">👤 <?php echo escape_output($patient['full_name']); ?></div><?php endif; ?>
</div>

<div class="card mb-4" style="background:#ffebee;border:1px solid #ef9a9a;">
    <div class="card-header"><div class="card-title" style="color:#c62828;">⚠️ لماذا العناية بالقدم مهمة لمرضى السكري؟</div></div>
    <p style="font-size:0.95rem;color:#555;line-height:1.8;padding:0.5rem;">
        مرض السكري قد يسبب تلفاً في الأعصاب (الاعتلال العصبي) وضعفاً في الدورة الدموية، مما يقلل الإحساس 
        بالقدم ويبطئ التئام الجروح. هذا يجعل مريض السكري أكثر عرضة للإصابة بقرح القدم التي قد تؤدي 
        إلى البتر إذا لم يتم علاجها مبكراً. مع العناية اليومية المناسبة، يمكن الوقاية من معظم مضاعفات القدم السكري.
    </p>
</div>

<!-- Daily Care Routine -->
<div class="care-section fade-in">
    <h3>🗓️ روتين العناية اليومي بالقدم</h3>
    <div class="step-card">
        <div class="step-num">1</div>
        <div class="step-content"><h5>🔍 الفحص اليومي</h5><p>افحص قدميك يومياً باستخدام مرآة لرؤية أسفل القدم. ابحث عن جروح، تقرحات، احمرار، تورم، تغير لون الجلد، أو تشققات.</p></div>
    </div>
    <div class="step-card">
        <div class="step-num">2</div>
        <div class="step-content"><h5>🚿 الغسل بالماء الفاتر</h5><p>اغسل القدمين يومياً بالماء الفاتر (وليس الساخن). استخدم صابوناً لطيفاً. اختبر درجة حرارة الماء بيدك أولاً.</p></div>
    </div>
    <div class="step-card">
        <div class="step-num">3</div>
        <div class="step-content"><h5>🧴 التجفيف اللطيف</h5><p>جفف القدمين بلطف بمنشفة ناعمة، خاصة بين الأصابع. لا تفرك بقوة لأن الجلد قد يكون حساساً.</p></div>
    </div>
    <div class="step-card">
        <div class="step-num">4</div>
        <div class="step-content"><h5>🧴 الترطيب</h5><p>ضع مرطباً لطيفاً خالياً من العطور على القدمين مع تجنب ما بين الأصابع (الرطوبة بين الأصابع تسبب الفطريات).</p></div>
    </div>
    <div class="step-card">
        <div class="step-num">5</div>
        <div class="step-content"><h5>✂️ العناية بالأظافر</h5><p>قص الأظافر بشكل مستقيم وبرد الحواف الحادة. لا تقص الزوايا لتجنب نمو الظفر تحت الجلد.</p></div>
    </div>
    <div class="step-card">
        <div class="step-num">6</div>
        <div class="step-content"><h5>👟 ارتداء الأحذية المناسبة</h5><p>لا تمشي حافي القدمين أبداً. ارتد جوارب قطنية نظيفة وأحذية طبية مريحة تناسب مقاس قدميك.</p></div>
    </div>
</div>

<!-- Do's and Don'ts -->
<h3 style="margin-bottom:1rem;">✅❌ افعل ولا تفعل</h3>
<div class="do-dont-grid fade-in">
    <div class="do-card">
        <h4>✅ افعل</h4>
        <ul>
            <li>افحص قدميك يومياً</li>
            <li>اغسل قدميك بالماء الفاتر</li>
            <li>جفف بين الأصابع جيداً</li>
            <li>ارتدي جوارب قطنية نظيفة</li>
            <li>استخدم أحذية طبية مناسبة</li>
            <li>قص الأظافر بشكل مستقيم</li>
            <li>رطب القدمين يومياً</li>
            <li>استشر الطبيب عند أي تغير</li>
        </ul>
    </div>
    <div class="dont-card">
        <h4>❌ لا تفعل</h4>
        <ul>
            <li>لا تمشي حافي القدمين</li>
            <li>لا تستخدم ماء ساخن</li>
            <li>لا تستخدم كريمات قوية بدون وصفة</li>
            <li>لا تقص الزوايا عند قص الأظافر</li>
            <li>لا تستخدم أدوات حادة لإزالة الجلد الميت</li>
            <li>لا ترتد أحذية ضيقة أو غير مريحة</li>
            <li>لا تهمل أي جرح حتى لو كان صغيراً</li>
            <li>لا تستخدم وسادات تدفئة على القدمين</li>
        </ul>
    </div>
</div>

<!-- Shoe Guide -->
<div class="care-section fade-in">
    <h3>👟 دليل اختيار الأحذية المناسبة</h3>
    <div class="shoe-guide">
        <div class="shoe-item"><div class="shoe-icon">👟</div><strong>مقاس مناسب</strong><br><span style="font-size:0.85rem;color:#555;">اترك مسافة إصبع بين أطول إصبع ومقدمة الحذاء</span></div>
        <div class="shoe-item"><div class="shoe-icon">🛡️</div><strong>عريض من الأمام</strong><br><span style="font-size:0.85rem;color:#555;">يسمح للأصابع بالحركة بحرية دون ضغط</span></div>
        <div class="shoe-item"><div class="shoe-icon">🧦</div><strong>بطانة ناعمة</strong><br><span style="font-size:0.85rem;color:#555;">تجنب الأحذية ذات الدرزات الداخلية الخشنة</span></div>
        <div class="shoe-item"><div class="shoe-icon">⚖️</div><strong>نعل طبي</strong><br><span style="font-size:0.85rem;color:#555;">نعل يوزع الضغط بالتساوي على القدم</span></div>
    </div>
</div>

<!-- When to see doctor -->
<div class="care-section fade-in">
    <h3>🆘 متى يجب استشارة الطبيب فوراً؟</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:0.8rem;">
        <div style="padding:1rem;background:#fef2f2;border-radius:8px;border-right:3px solid #dc2626;">
            <strong>🩹 وجود جرح مفتوح</strong>
            <p style="font-size:0.85rem;color:#555;">أي جرح في القدم لا يلتئم خلال 24 ساعة</p>
        </div>
        <div style="padding:1rem;background:#fef2f2;border-radius:8px;border-right:3px solid #dc2626;">
            <strong>🔴 احمرار مفاجئ</strong>
            <p style="font-size:0.85rem;color:#555;">احمرار أو تورم مفاجئ في أي جزء من القدم</p>
        </div>
        <div style="padding:1rem;background:#fef2f2;border-radius:8px;border-right:3px solid #dc2626;">
            <strong>🌡️ حرارة موضعية</strong>
            <p style="font-size:0.85rem;color:#555;">سخونة في منطقة معينة من القدم</p>
        </div>
        <div style="padding:1rem;background:#fef2f2;border-radius:8px;border-right:3px solid #dc2626;">
            <strong>🦶 تغير لون الجلد</strong>
            <p style="font-size:0.85rem;color:#555;">ازرقاق أو اسوداد في أي جزء من القدم</p>
        </div>
    </div>
</div>

<div class="flex flex-wrap gap-2 mt-4 justify-center no-print">
    <a href="index.php<?php echo $patient_id ? "?patient_id=$patient_id" : ''; ?>" class="btn btn-secondary">🔙 مركز التوعية</a>
    <a href="emergency.php<?php echo $patient_id ? "?patient_id=$patient_id" : ''; ?>" class="btn btn-primary">🚨 دليل الطوارئ</a>
</div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div></div>