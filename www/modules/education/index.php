<?php
/**
 * Education Hub - Central Patient Education Portal
 * Comprehensive diabetes education resources
 */
$page_title = 'مركز التوعية | Education Hub';
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

$modules = [
    'diabetes_basics' => [
        'title' => 'فهم مرض السكري',
        'icon' => '📖', 'color' => '#0a7e6e',
        'desc' => 'معلومات أساسية عن مرض السكري، أنواعه، أعراضه، وكيفية التعايش معه',
        'link' => 'diabetes_basics.php' . ($patient_id ? "?patient_id=$patient_id" : '')
    ],
    'diet_plan' => [
        'title' => 'النظام الغذائي',
        'icon' => '🥗', 'color' => '#e65100',
        'desc' => 'خطط غذائية متوازنة، وجبات مقترحة، ونصائح للتغذية الصحية',
        'link' => 'diet_plan.php' . ($patient_id ? "?patient_id=$patient_id" : '')
    ],
    'exercise_guide' => [
        'title' => 'النشاط البدني',
        'icon' => '🏃', 'color' => '#1565c0',
        'desc' => 'تمارين مناسبة لمرضى السكري، برامج رياضية، واحتياطات مهمة',
        'link' => 'exercise_guide.php' . ($patient_id ? "?patient_id=$patient_id" : '')
    ],
    'medication_guide' => [
        'title' => 'الأدوية والعلاج',
        'icon' => '💊', 'color' => '#7b1fa2',
        'desc' => 'دليل الأدوية، الأنسولين، الجرعات، والآثار الجانبية المحتملة',
        'link' => 'medication_guide.php' . ($patient_id ? "?patient_id=$patient_id" : '')
    ],
    'foot_care_guide' => [
        'title' => 'العناية بالقدم',
        'icon' => '🦶', 'color' => '#c62828',
        'desc' => 'دليل شامل للعناية بالقدم لمرضى السكري، الوقاية من القرح',
        'link' => 'foot_care_guide.php' . ($patient_id ? "?patient_id=$patient_id" : '')
    ],
    'home_care' => [
        'title' => 'الرعاية المنزلية',
        'icon' => '🏠', 'color' => '#2d8a4e',
        'desc' => 'تعليمات الرعاية المنزلية اليومية، روتين العناية الشخصية',
        'link' => 'home_care.php' . ($patient_id ? "?patient_id=$patient_id" : '')
    ],
    'emergency' => [
        'title' => 'دليل الطوارئ',
        'icon' => '🚨', 'color' => '#d32f2f',
        'desc' => 'إرشادات عاجلة، علامات الخطر، أرقام الطوارئ، وخطة العمل',
        'link' => 'emergency.php' . ($patient_id ? "?patient_id=$patient_id" : '')
    ],
];
?>
<style>
    .edu-hero {
        background: linear-gradient(135deg, #0a7e6e, #1565c0);
        color: white; padding: 2.5rem; border-radius: 16px; margin-bottom: 2rem;
        position: relative; overflow: hidden; text-align: center;
    }
    .edu-hero::before {
        content: '📚'; position: absolute; right: -30px; top: -30px; font-size: 120px; opacity: 0.1;
    }
    .edu-hero h1 { font-size: 2rem; margin-bottom: 0.5rem; }
    .edu-hero p { font-size: 1.05rem; opacity: 0.9; }

    .module-grid {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.2rem;
    }
    .module-card {
        background: white; border-radius: 14px; padding: 1.5rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.06); transition: all 0.3s;
        position: relative; overflow: hidden; cursor: pointer;
        border-bottom: 3px solid transparent;
    }
    .module-card:hover {
        transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.1);
    }
    .module-card .mc-icon { font-size: 2.5rem; margin-bottom: 0.8rem; }
    .module-card h3 { font-size: 1.15rem; margin-bottom: 0.5rem; }
    .module-card p { font-size: 0.88rem; color: #666; line-height: 1.7; }
    .module-card .mc-badge {
        display: inline-block; padding: 0.2rem 0.8rem; border-radius: 20px;
        font-size: 0.75rem; font-weight: 600; color: white; margin-top: 0.8rem;
    }

    .patient-selector {
        background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
        border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;
    }

    @media (max-width: 768px) {
        .module-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="app-layout"><div class="main-content"><div class="page-content page-entrance">

<div class="edu-hero fade-in">
    <h1>📚 مركز التوعية والتثقيف الصحي</h1>
    <p>Patient Education Hub — موارد شاملة لمرضى السكري وعائلاتهم</p>
    <?php if ($patient): ?>
    <div style="margin-top:1rem;background:rgba(255,255,255,0.15);padding:0.5rem 1.2rem;border-radius:10px;display:inline-block;">
        👤 <?php echo escape_output($patient['full_name']); ?> — 📁 <?php echo escape_output($patient['file_number']); ?>
    </div>
    <?php endif; ?>
</div>

<!-- Patient Selector -->
<?php if (!$patient_id): ?>
<div class="patient-selector fade-in">
    <div class="flex flex-wrap gap-3 items-center">
        <div style="font-size:2rem;">👤</div>
        <div>
            <h3 style="margin-bottom:0.3rem;">اختر مريضاً للحصول على تعليمات مخصصة</h3>
            <p style="color:#555;font-size:0.9rem;">اختر مريضاً من القائمة لعرض محتوى تعليمي مخصص بناءً على حالته الصحية</p>
        </div>
        <select onchange="if(this.value) window.location.href='?patient_id='+this.value" 
                style="padding:0.6rem 1rem;border:1px solid #ccc;border-radius:8px;font-family:'Tajawal',sans-serif;min-width:200px;">
            <option value="">-- اختر مريضاً --</option>
            <?php 
            $patients = $mysqli->query("SELECT patient_id, full_name, file_number FROM patients WHERE is_active = 1 ORDER BY full_name");
            while ($p = $patients->fetch_assoc()): ?>
            <option value="<?php echo $p['patient_id']; ?>"><?php echo escape_output($p['full_name']); ?> — 📁 <?php echo escape_output($p['file_number']); ?></option>
            <?php endwhile; ?>
        </select>
    </div>
</div>
<?php endif; ?>

<!-- Education Modules -->
<div class="module-grid fade-in">
    <?php foreach ($modules as $key => $mod): ?>
    <a href="<?php echo $mod['link']; ?>" style="text-decoration:none;color:inherit;">
        <div class="module-card" style="border-bottom-color:<?php echo $mod['color']; ?>;">
            <div class="mc-icon"><?php echo $mod['icon']; ?></div>
            <h3><?php echo $mod['title']; ?></h3>
            <p><?php echo $mod['desc']; ?></p>
            <span class="mc-badge" style="background:<?php echo $mod['color']; ?>;">عرض المحتوى ←</span>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- Quick Tips -->
<div class="card mt-4">
    <div class="card-header"><div class="card-title">💡 نصائح سريعة لمرضى السكري</div></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem;padding:0.5rem;">
        <div style="display:flex;align-items:start;gap:0.8rem;padding:0.8rem;background:#f8fafc;border-radius:10px;">
            <span style="font-size:1.5rem;">🩸</span>
            <div><strong>راقب سكرك</strong><br><span style="font-size:0.85rem;color:#666;">افحص مستوى السكر في الدم بانتظام وسجل القراءات</span></div>
        </div>
        <div style="display:flex;align-items:start;gap:0.8rem;padding:0.8rem;background:#f8fafc;border-radius:10px;">
            <span style="font-size:1.5rem;">🥗</span>
            <div><strong>تناول طعاماً صحياً</strong><br><span style="font-size:0.85rem;color:#666;">اختر وجبات متوازنة غنية بالألياف والبروتين</span></div>
        </div>
        <div style="display:flex;align-items:start;gap:0.8rem;padding:0.8rem;background:#f8fafc;border-radius:10px;">
            <span style="font-size:1.5rem;">🏃</span>
            <div><strong>تحرك يومياً</strong><br><span style="font-size:0.85rem;color:#666;">المشي 30 دقيقة يومياً يحسن التحكم في السكر</span></div>
        </div>
        <div style="display:flex;align-items:start;gap:0.8rem;padding:0.8rem;background:#f8fafc;border-radius:10px;">
            <span style="font-size:1.5rem;">💊</span>
            <div><strong>التزم بالعلاج</strong><br><span style="font-size:0.85rem;color:#666;">لا تترك أدويتك أو جرعات الأنسولين مهما كانت الظروف</span></div>
        </div>
    </div>
</div>

</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div></div>