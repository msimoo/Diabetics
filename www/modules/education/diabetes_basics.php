<?php
/**
 * Diabetes Basics - Understanding Diabetes Module
 * Comprehensive patient education about diabetes types, symptoms, and management
 */
$page_title = 'فهم مرض السكري | Diabetes Basics';
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
    .basics-hero {
        background: linear-gradient(135deg, #0a7e6e, #1565c0);
        color: white; padding: 2.5rem; border-radius: 16px; margin-bottom: 2rem;
        position: relative; overflow: hidden; text-align: center;
    }
    .basics-hero::before { content: '📖'; position: absolute; right: -20px; top: -20px; font-size: 100px; opacity: 0.1; }
    .info-card {
        background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); border-right: 4px solid #0a7e6e;
        transition: transform 0.3s;
    }
    .info-card:hover { transform: translateX(-4px); }
    .info-card h3 { color: #0a7e6e; margin-bottom: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }
    .info-card ul { list-style: none; padding: 0; }
    .info-card ul li { padding: 0.4rem 1.5rem 0.4rem 0; position: relative; font-size: 0.95rem; color: #444; line-height: 1.7; }
    .info-card ul li::before { content: '•'; position: absolute; right: 0; color: #0a7e6e; font-weight: 700; }

    .diabetes-type-card {
        background: white; border-radius: 12px; padding: 1.5rem; text-align: center;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: all 0.3s;
    }
    .diabetes-type-card:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,0.1); }
    .diabetes-type-card .type-icon { font-size: 2.5rem; margin-bottom: 0.5rem; }
    .diabetes-type-card h4 { font-size: 1.1rem; margin-bottom: 0.5rem; }
    .diabetes-type-card p { font-size: 0.85rem; color: #555; line-height: 1.7; }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="basics-hero fade-in">
    <h1>📖 فهم مرض السكري</h1>
    <p>Diabetes Basics — دليل شامل لفهم مرض السكري والتعايش معه</p>
    <?php if ($patient): ?>
    <div style="margin-top:0.8rem;background:rgba(255,255,255,0.15);padding:0.4rem 1rem;border-radius:8px;display:inline-block;font-size:0.9rem;">
        👤 <?php echo escape_output($patient['full_name']); ?>
    </div>
    <?php endif; ?>
</div>

<!-- What is Diabetes -->
<div class="info-card fade-in">
    <h3>🩸 ما هو مرض السكري؟</h3>
    <p style="color:#555;line-height:1.8;font-size:0.95rem;">
        مرض السكري هو حالة مزمنة ترتفع فيها مستويات السكر (الجلوكوز) في الدم بسبب عدم قدرة 
        البنكرياس على إنتاج كمية كافية من الأنسولين، أو لأن خلايا الجسم لا تستجيب بشكل صحيح 
        للأنسولين المنتج. الأنسولين هو الهرمون المسؤول عن نقل السكر من الدم إلى الخلايا 
        لإنتاج الطاقة.
    </p>
</div>

<!-- Types of Diabetes -->
<h3 style="margin-bottom:1rem;">📋 أنواع مرض السكري</h3>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1rem;margin-bottom:1.5rem;">
    <div class="diabetes-type-card fade-in">
        <div class="type-icon">🧬</div>
        <h4>النوع الأول (Type 1)</h4>
        <p>مرض مناعي ذاتي حيث يهاجم الجهاز المناعي خلايا البنكرياس المنتجة للأنسولين. يظهر عادة في سن مبكرة ويحتاج المريض إلى حقن الأنسولين يومياً مدى الحياة.</p>
        <div style="margin-top:0.8rem;background:#e3f2fd;padding:0.3rem 0.8rem;border-radius:20px;display:inline-block;font-size:0.8rem;color:#1565c0;font-weight:600;">
            يشكل 5-10% من حالات السكري
        </div>
    </div>
    <div class="diabetes-type-card fade-in">
        <div class="type-icon">⚖️</div>
        <h4>النوع الثاني (Type 2)</h4>
        <p>الأكثر شيوعاً، يرتبط غالباً بالسمنة وقلة النشاط البدني والعوامل الوراثية. يحدث عندما تصبح خلايا الجسم مقاومة للأنسولين. يمكن السيطرة عليه بالحمية والرياضة والأدوية.</p>
        <div style="margin-top:0.8rem;background:#fff3e0;padding:0.3rem 0.8rem;border-radius:20px;display:inline-block;font-size:0.8rem;color:#e65100;font-weight:600;">
            يشكل 90-95% من حالات السكري
        </div>
    </div>
    <div class="diabetes-type-card fade-in">
        <div class="type-icon">🤰</div>
        <h4>سكري الحمل (GDM)</h4>
        <p>يظهر لأول مرة أثناء الحمل، خاصة في الثلث الثاني والثالث. عادة ما يختفي بعد الولادة، لكنه يزيد من خطر الإصابة بالنوع الثاني لاحقاً.</p>
        <div style="margin-top:0.8rem;background:#fce4ec;padding:0.3rem 0.8rem;border-radius:20px;display:inline-block;font-size:0.8rem;color:#c62828;font-weight:600;">
            يصاب به 2-10% من الحوامل
        </div>
    </div>
</div>

<!-- Common Symptoms -->
<div class="info-card fade-in" style="border-right-color:#d32f2f;">
    <h3>⚠️ الأعراض الشائعة</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.5rem;">
        <div style="padding:0.8rem;background:#fef2f2;border-radius:8px;"><strong>💧 العطش الشديد</strong><br><span style="font-size:0.85rem;color:#555;">شعور دائم بالعطش</span></div>
        <div style="padding:0.8rem;background:#fef2f2;border-radius:8px;"><strong>🚽 كثرة التبول</strong><br><span style="font-size:0.85rem;color:#555;">خاصة في الليل</span></div>
        <div style="padding:0.8rem;background:#fef2f2;border-radius:8px;"><strong>😴 الإرهاق</strong><br><span style="font-size:0.85rem;color:#555;">شعور دائم بالتعب</span></div>
        <div style="padding:0.8rem;background:#fef2f2;border-radius:8px;"><strong>📉 فقدان الوزن</strong><br><span style="font-size:0.85rem;color:#555;">بدون سبب واضح</span></div>
        <div style="padding:0.8rem;background:#fef2f2;border-radius:8px;"><strong>👁️ تشوش الرؤية</strong><br><span style="font-size:0.85rem;color:#555;">عدم وضوح في النظر</span></div>
        <div style="padding:0.8rem;background:#fef2f2;border-radius:8px;"><strong>🩹 بطء التئام الجروح</strong><br><span style="font-size:0.85rem;color:#555;">الجروح تستغرق وقتاً أطول</span></div>
    </div>
</div>

<!-- Glycemic Targets -->
<div class="info-card fade-in" style="border-right-color:#1565c0;">
    <h3>🎯 مستويات السكر المستهدفة</h3>
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;">
            <thead><tr style="background:#e3f2fd;">
                <th style="padding:0.6rem;text-align:right;">القياس</th>
                <th style="padding:0.6rem;text-align:center;">النتيجة الطبيعية</th>
                <th style="padding:0.6rem;text-align:center;">مريض السكري</th>
            </tr></thead>
            <tbody>
                <tr><td style="padding:0.5rem;border-bottom:1px solid #eee;">سكر صائم (FPG)</td><td style="padding:0.5rem;text-align:center;">< 100 mg/dL</td><td style="padding:0.5rem;text-align:center;">80-130 mg/dL</td></tr>
                <tr><td style="padding:0.5rem;border-bottom:1px solid #eee;">سكر فاطر (PPG)</td><td style="padding:0.5rem;text-align:center;">< 140 mg/dL</td><td style="padding:0.5rem;text-align:center;">< 180 mg/dL</td></tr>
                <tr><td style="padding:0.5rem;border-bottom:1px solid #eee;">السكر التراكمي (HbA1c)</td><td style="padding:0.5rem;text-align:center;">< 5.7%</td><td style="padding:0.5rem;text-align:center;">< 7%</td></tr>
                <tr><td style="padding:0.5rem;">سكر عشوائي</td><td style="padding:0.5rem;text-align:center;">< 200 mg/dL</td><td style="padding:0.5rem;text-align:center;">—</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Complications -->
<div class="info-card fade-in" style="border-right-color:#e65100;">
    <h3>🚨 المضاعفات المحتملة</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:0.8rem;">
        <div style="padding:1rem;background:#fff3e0;border-radius:8px;border-right:3px solid #e65100;">
            <strong>👁️ اعتلال الشبكية</strong>
            <p style="font-size:0.85rem;color:#555;margin-top:0.3rem;">تلف الأوعية الدموية في شبكية العين قد يؤدي إلى فقدان البصر</p>
        </div>
        <div style="padding:1rem;background:#fff3e0;border-radius:8px;border-right:3px solid #e65100;">
            <strong>🩺 اعتلال الكلى</strong>
            <p style="font-size:0.85rem;color:#555;margin-top:0.3rem;">تلف الكلى قد يؤدي إلى الفشل الكلوي والحاجة للغسيل الكلوي</p>
        </div>
        <div style="padding:1rem;background:#fff3e0;border-radius:8px;border-right:3px solid #e65100;">
            <strong>🦶 القدم السكري</strong>
            <p style="font-size:0.85rem;color:#555;margin-top:0.3rem;">تلف الأعصاب وضعف الدورة الدموية يؤدي إلى قرح القدم</p>
        </div>
        <div style="padding:1rem;background:#fff3e0;border-radius:8px;border-right:3px solid #e65100;">
            <strong>❤️ أمراض القلب</strong>
            <p style="font-size:0.85rem;color:#555;margin-top:0.3rem;">زيادة خطر الإصابة بالنوبات القلبية والسكتات الدماغية</p>
        </div>
    </div>
</div>

<!-- Self Management -->
<div class="info-card fade-in" style="border-right-color:#2d8a4e;">
    <h3>✅ إدارة السكري الذاتية</h3>
    <ul>
        <li><strong>مراقبة السكر:</strong> قياس مستوى السكر في الدم بانتظام باستخدام جهاز قياس السكر المنزلي</li>
        <li><strong>النظام الغذائي:</strong> اتباع نظام غذائي متوازن غني بالألياف ومنخفض السكريات</li>
        <li><strong>النشاط البدني:</strong> ممارسة الرياضة بانتظام (30 دقيقة على الأقل يومياً)</li>
        <li><strong>الأدوية:</strong> الالتزام بتناول الأدوية في مواعيدها المحددة</li>
        <li><strong>الفحص الدوري:</strong> زيارة الطبيب بانتظام لفحص السكر التراكمي ومضاعفات السكري</li>
        <li><strong>العناية بالقدم:</strong> فحص القدمين يومياً وارتداء الأحذية المناسبة</li>
    </ul>
</div>

<!-- Navigation -->
<div class="flex flex-wrap gap-2 mt-4 justify-center no-print">
    <a href="index.php<?php echo $patient_id ? "?patient_id=$patient_id" : ''; ?>" class="btn btn-secondary">🔙 مركز التوعية</a>
    <a href="diet_plan.php<?php echo $patient_id ? "?patient_id=$patient_id" : ''; ?>" class="btn btn-primary">🥗 النظام الغذائي</a>
    <a href="exercise_guide.php<?php echo $patient_id ? "?patient_id=$patient_id" : ''; ?>" class="btn btn-primary">🏃 التمارين الرياضية</a>
</div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div></div>