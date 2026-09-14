<?php
/**
 * Medication Guide - Diabetes Medications Education Module
 */
$page_title = 'دليل الأدوية | Medication Guide';
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

$med_categories = [
    'oads' => [
        'title' => 'أدوية فموية (OADs)', 'icon' => '💊',
        'meds' => [
            ['name' => 'ميتفورمين (Metformin)', 'class' => 'بيغوانيد', 'action' => 'يقلل إنتاج السكر في الكبد ويحسن حساسية الأنسولين', 'dose' => '500-2000 ملغم يومياً', 'side' => 'اضطرابات هضمية خفيفة'],
            ['name' => 'غليمبيريد (Glimepiride)', 'class' => 'سلفونيليوريا', 'action' => 'يحفز البنكرياس لإفراز المزيد من الأنسولين', 'dose' => '1-4 ملغم يومياً', 'side' => 'هبوط السكر، زيادة الوزن'],
            ['name' => 'سيتاغليبتين (Sitagliptin)', 'class' => 'DPP-4 Inhibitor', 'action' => 'يزيد هرمون الإنكريتين الذي يحفز إفراز الأنسولين', 'dose' => '100 ملغم يومياً', 'side' => 'نادراً ما يسبب آثاراً جانبية'],
            ['name' => 'إمباغليفلوزين (Empagliflozin)', 'class' => 'SGLT2 Inhibitor', 'action' => 'يزيد طرح السكر في البول', 'dose' => '10-25 ملغم يومياً', 'side' => 'التهابات بولية'],
            ['name' => 'بيوغليتازون (Pioglitazone)', 'class' => 'Thiazolidinedione', 'action' => 'يحسن حساسية الأنسولين في الخلايا الدهنية والعضلات', 'dose' => '15-45 ملغم يومياً', 'side' => 'احتباس سوائل، زيادة الوزن'],
        ]
    ],
    'insulin' => [
        'title' => 'الأنسولين', 'icon' => '💉',
        'meds' => [
            ['name' => 'أنسولين سريع (NovoRapid/Humalog)', 'class' => 'سريع المفعول', 'action' => 'يبدأ العمل خلال 15 دقيقة، يخفض سكر الوجبات', 'dose' => 'حسب إرشادات الطبيب', 'side' => 'هبوط السكر'],
            ['name' => 'أنسولين قاعدي (Lantus/Levemir)', 'class' => 'طويل المفعول', 'action' => 'يعمل لمدة 24 ساعة للحفاظ على المستوى الأساسي للسكر', 'dose' => 'مرة واحدة يومياً', 'side' => 'هبوط السكر'],
            ['name' => 'أنسولين ممزوج (Mixtard)', 'class' => 'ممزوج', 'action' => 'مزيج من الأنسولين سريع وقاعدي بنسب مختلفة', 'dose' => 'مرتين يومياً', 'side' => 'هبوط السكر'],
        ]
    ],
    'other' => [
        'title' => 'أدوية مساعدة', 'icon' => '🧴',
        'meds' => [
            ['name' => 'مضادات حيوية للجروح', 'class' => 'مضاد ميكروبي', 'action' => 'علاج التهابات القدم السكري والجروح الملتهبة', 'dose' => 'حسب وصفة الطبيب', 'side' => 'حساسية، اضطرابات هضمية'],
            ['name' => 'مراهم موضعية', 'class' => 'عناية بالجروح', 'action' => 'تساعد في التئام الجروح ومنع العدوى', 'dose' => 'تطبيق موضعي', 'side' => 'نادراً ما تسبب تهيجاً'],
        ]
    ]
];
?>
<style>
    .med-hero {
        background: linear-gradient(135deg, #7b1fa2, #4a148c); color: white;
        padding: 2rem; border-radius: 16px; margin-bottom: 2rem; text-align: center;
        position: relative; overflow: hidden;
    }
    .med-hero::before { content: '💊'; position: absolute; right: -20px; top: -20px; font-size: 100px; opacity: 0.1; }
    .med-category { margin-bottom: 2rem; }
    .med-category h3 { margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    .med-table { width: 100%; border-collapse: collapse; }
    .med-table th { background: #f5f5f5; padding: 0.7rem; text-align: right; font-size: 0.85rem; }
    .med-table td { padding: 0.8rem; border-bottom: 1px solid #eee; font-size: 0.9rem; vertical-align: top; }
    .med-table tr:hover { background: #fafafa; }
    .med-table .med-name { font-weight: 700; color: #7b1fa2; }
    .med-table .med-class { font-size: 0.8rem; color: #666; }
    .med-table .med-action { font-size: 0.85rem; color: #444; }

    .warning-box {
        background: linear-gradient(135deg, #fff3e0, #ffe0b2);
        border: 2px solid #ff9800; border-radius: 12px; padding: 1.5rem; margin: 1.5rem 0;
    }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">
<div class="med-hero fade-in">
    <h1>💊 دليل الأدوية والعلاج</h1>
    <p>Medication Guide — شرح مبسط لأدوية السكري المختلفة</p>
    <?php if ($patient): ?><div style="margin-top:0.8rem;background:rgba(255,255,255,0.15);padding:0.4rem 1rem;border-radius:8px;display:inline-block;">👤 <?php echo escape_output($patient['full_name']); ?></div><?php endif; ?>
</div>

<div class="card mb-4" style="background:#f3e5f5;border:1px solid #ce93d8;">
    <div class="card-header"><div class="card-title" style="color:#7b1fa2;">📌 مبادئ مهمة في العلاج</div></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.8rem;padding:0.5rem;">
        <div style="text-align:center;padding:0.8rem;"><span style="font-size:1.8rem;">🕐</span><br><strong>الانتظام</strong><br><span style="font-size:0.85rem;color:#555;">تناول الأدوية في نفس الوقت يومياً</span></div>
        <div style="text-align:center;padding:0.8rem;"><span style="font-size:1.8rem;">📋</span><br><strong>الجرعات</strong><br><span style="font-size:0.85rem;color:#555;">لا تغير الجرعة بدون استشارة الطبيب</span></div>
        <div style="text-align:center;padding:0.8rem;"><span style="font-size:1.8rem;">🩸</span><br><strong>المراقبة</strong><br><span style="font-size:0.85rem;color:#555;">راقب سكرك بانتظام لضبط الجرعات</span></div>
        <div style="text-align:center;padding:0.8rem;"><span style="font-size:1.8rem;">🏥</span><br><strong>المتابعة</strong><br><span style="font-size:0.85rem;color:#555;">زر طبيبك بانتظام لمتابعة العلاج</span></div>
    </div>
</div>

<?php foreach ($med_categories as $cat): ?>
<div class="med-category fade-in">
    <h3><?php echo $cat['icon']; ?> <?php echo $cat['title']; ?></h3>
    <div style="overflow-x:auto;">
        <table class="med-table">
            <thead><tr><th>الدواء</th><th>التصنيف</th><th>طريقة العمل</th><th>الجرعة</th><th>الآثار الجانبية</th></tr></thead>
            <tbody>
                <?php foreach ($cat['meds'] as $m): ?>
                <tr>
                    <td><div class="med-name"><?php echo $m['name']; ?></div></td>
                    <td><span class="med-class"><?php echo $m['class']; ?></span></td>
                    <td><span class="med-action"><?php echo $m['action']; ?></span></td>
                    <td style="font-size:0.85rem;"><?php echo $m['dose']; ?></td>
                    <td style="font-size:0.85rem;color:#d32f2f;"><?php echo $m['side']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<div class="warning-box fade-in">
    <h3 style="color:#e65100;margin-bottom:0.8rem;">⚠️ تنبيهات مهمة</h3>
    <ul style="list-style:none;padding:0;">
        <li style="padding:0.4rem 1.5rem 0.4rem 0;position:relative;font-size:0.9rem;color:#444;">❌ لا توقف أو تغير جرعة أدويتك بدون استشارة الطبيب المعالج</li>
        <li style="padding:0.4rem 1.5rem 0.4rem 0;position:relative;font-size:0.9rem;color:#444;">💧 احفظ الأنسولين في الثلاجة (2-8°م) ولا تستخدمه إذا تغير لونه</li>
        <li style="padding:0.4rem 1.5rem 0.4rem 0;position:relative;font-size:0.9rem;color:#444;">📅 تحقق من تاريخ صلاحية جميع الأدوية بانتظام</li>
        <li style="padding:0.4rem 1.5rem 0.4rem 0;position:relative;font-size:0.9rem;color:#444;">🤒 في حالة المرض أو العدوى، استشر طبيبك فقد تحتاج تعديل الجرعة</li>
        <li style="padding:0.4rem 1.5rem 0.4rem 0;position:relative;font-size:0.9rem;color:#444;">🆘 إذا شعرت بأعراض هبوط السكر (دوخة، تعرق، رعشة)، تناول مصدر سكر فوراً</li>
    </ul>
</div>

<div class="flex flex-wrap gap-2 mt-4 justify-center no-print">
    <a href="index.php<?php echo $patient_id ? "?patient_id=$patient_id" : ''; ?>" class="btn btn-secondary">🔙 مركز التوعية</a>
    <a href="foot_care_guide.php<?php echo $patient_id ? "?patient_id=$patient_id" : ''; ?>" class="btn btn-primary">🦶 العناية بالقدم</a>
</div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div></div>