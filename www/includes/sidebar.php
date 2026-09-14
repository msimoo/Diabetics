<?php
/**
 * Sidebar Navigation — v3 with collapsible analytics submenu & sidebar toggle
 */
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

function is_active($dir, $file = null): string {
    global $current_dir, $current_page;
    $active = ($current_dir === $dir);
    if ($file) $active = $active && ($current_page === $file);
    return $active ? 'active' : '';
}

// All analytics pages for the collapsible submenu
$analytics_pages = [
    ['url' => 'analytics_hub.php', 'icon' => '📊', 'label' => 'مركز التحليلات'],
    ['url' => 'wound_analysis.php', 'icon' => '🩹', 'label' => 'تحليل الجروح والقدم'],
    ['url' => 'diabetic_analytics.php', 'icon' => '🩸', 'label' => 'تحليلات السكري'],
    ['url' => 'smart_dashboard.php', 'icon' => '🧠', 'label' => 'لوحة ذكية'],
    ['url' => 'quality_measures.php', 'icon' => '📋', 'label' => 'مقاييس الجودة'],
    ['url' => 'risk_prediction.php', 'icon' => '🔮', 'label' => 'التنبؤ بالمخاطر'],
    ['url' => 'risk_alerts.php', 'icon' => '⚠️', 'label' => 'تنبيهات الخطر'],
    ['url' => 'anomaly_detection.php', 'icon' => '🔍', 'label' => 'كشف الشذوذ'],
    ['url' => 'specialist_analytics.php', 'icon' => '🔬', 'label' => 'تحليلات متخصصة'],
    ['url' => 'statistics.php', 'icon' => '📈', 'label' => 'الإحصائيات'],
    ['url' => 'progress.php', 'icon' => '📈', 'label' => 'تتبع التقدم'],
    ['url' => 'visit_patterns.php', 'icon' => '📅', 'label' => 'أنماط الزيارات'],
    ['url' => 'clinic_flow.php', 'icon' => '⏱', 'label' => 'تدفق العيادة'],
    ['url' => 'cohort_analysis.php', 'icon' => '👥', 'label' => 'تحليل المجموعات'],
    ['url' => 'medication_adherence.php', 'icon' => '💊', 'label' => 'الالتزام بالعلاج'],
    ['url' => 'treatment_efficacy.php', 'icon' => '💉', 'label' => 'فعالية العلاج'],
    ['url' => 'doctor_performance.php', 'icon' => '👨‍⚕️', 'label' => 'أداء الأطباء'],
    ['url' => 'diabetic_trends.php', 'icon' => '📉', 'label' => 'اتجاهات السكري'],
    ['url' => 'patient_timeline.php', 'icon' => '⏳', 'label' => 'خط زمني للمريض'],
    ['url' => 'geographic_health.php', 'icon' => '🌍', 'label' => 'الصحة الجغرافية'],
    ['url' => 'similar_patients.php', 'icon' => '👤', 'label' => 'مرضى مشابهون'],
    ['url' => 'what_if_simulator.php', 'icon' => '🔮', 'label' => 'محاكي السيناريوهات'],
    ['url' => 'report_builder.php', 'icon' => '📊', 'label' => 'منشئ التقارير'],
    ['url' => 'cohort_builder.php', 'icon' => '🏗️', 'label' => 'بناء المجموعات'],
    ['url' => 'notifications.php', 'icon' => '🔔', 'label' => 'الإشعارات'],
];

// Check if any analytics page is active (for expanding submenu)
$analytics_active = $current_dir === 'analytics';
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">🏥</div>
        <div class="sidebar-title">مركز سري</div>
        <div class="sidebar-subtitle">للغدد الصماء والسكري</div>
        <button class="sidebar-collapse-btn" onclick="toggleSidebar()" title="إخفاء القائمة">◀</button>
    </div>
    
    <ul class="sidebar-menu">
        <li class="menu-section">القائمة الرئيسية</li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/dashboard.php" class="<?php echo is_active('modules', 'dashboard.php') || ($current_dir === 'modules' && $current_page === 'dashboard.php') ? 'active' : ''; ?>">
                <span class="menu-icon">📊</span>
                <span class="menu-text">لوحة التحكم</span>
            </a>
        </li>

        <!-- ========== PATIENTS ========== -->
        <li class="menu-section">المرضى</li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/patients/add.php" class="<?php echo is_active('patients', 'add.php'); ?>">
                <span class="menu-icon">➕</span>
                <span class="menu-text">إضافة مريض</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/patients/index.php" class="<?php echo is_active('patients'); ?>">
                <span class="menu-icon">👥</span>
                <span class="menu-text">قائمة المرضى</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/patients/timeline.php" class="<?php echo is_active('patients', 'timeline.php'); ?>">
                <span class="menu-icon">⏳</span>
                <span class="menu-text">الخط الزمني</span>
            </a>
        </li>

        <!-- ========== VISITS & CLINICAL ========== -->
        <li class="menu-section">الزيارات والعيادة</li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/visits/add.php" class="<?php echo is_active('visits', 'add.php'); ?>">
                <span class="menu-icon">🩺</span>
                <span class="menu-text">زيارة جديدة</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/appointments/index.php" class="<?php echo is_active('appointments'); ?>">
                <span class="menu-icon">📅</span>
                <span class="menu-text">المواعيد</span>
            </a>
        </li>

        <!-- ========== ASSESSMENTS ========== -->
        <li class="menu-section">التقييمات</li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/assessments/foot_exam.php" class="<?php echo is_active('assessments', 'foot_exam.php'); ?>">
                <span class="menu-icon">🦶</span>
                <span class="menu-text">فحص القدم</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/assessments/outcomes.php" class="<?php echo is_active('assessments', 'outcomes.php'); ?>">
                <span class="menu-icon">📊</span>
                <span class="menu-text">نتائج المتابعة</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/assessments/care_plan.php" class="<?php echo is_active('assessments', 'care_plan.php'); ?>">
                <span class="menu-icon">🩹</span>
                <span class="menu-text">خطة العناية</span>
            </a>
        </li>

        <!-- ========== LAB TESTS ========== -->
        <li class="menu-section">🧪 الفحوصات</li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/lab_tests/order.php" class="<?php echo is_active('lab_tests', 'order.php'); ?>">
                <span class="menu-icon">📋</span>
                <span class="menu-text">طلب فحوصات</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/lab_tests/index.php" class="<?php echo is_active('lab_tests', 'index.php'); ?>">
                <span class="menu-icon">⚙️</span>
                <span class="menu-text">إدارة الفحوصات</span>
            </a>
        </li>

        <!-- ========== MEDICATIONS ========== -->
        <?php if (in_array($_SESSION['role'] ?? '', ['super_admin', 'admin', 'doctor'])): ?>
        <li class="menu-section">💊 الأدوية</li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/medications/index.php" class="<?php echo is_active('medications'); ?>">
                <span class="menu-icon">💊</span>
                <span class="menu-text">كتالوج الأدوية</span>
            </a>
        </li>
        <?php endif; ?>

        <!-- ========== AUTO INSTRUCTIONS ========== -->
        <li class="menu-section">📋 التعليمات</li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/auto_instructions/index.php" class="<?php echo is_active('auto_instructions', 'index.php'); ?>">
                <span class="menu-icon">⚙️</span>
                <span class="menu-text">إدارة التعليمات</span>
            </a>
        </li>

        <!-- ========== ANALYTICS (COLLAPSIBLE) ========== -->
        <li class="menu-section" style="display:flex;justify-content:space-between;align-items:center;">
            <span>التحليلات</span>
            <button class="analytics-toggle" onclick="toggleAnalyticsMenu()" style="background:none;border:none;color:var(--gold);cursor:pointer;font-size:14px;padding:0 4px;" title="إظهار/إخفاء التحليلات">📊</button>
        </li>
        <li class="analytics-menu-toggle">
            <a href="#" onclick="toggleAnalyticsMenu(); return false;" class="<?php echo $analytics_active ? 'active' : ''; ?>" style="cursor:pointer;">
                <span class="menu-icon">📊</span>
                <span class="menu-text">جميع التحليلات <span style="font-size:10px;opacity:0.6;">(<?php echo count($analytics_pages); ?>)</span></span>
                <span class="menu-arrow" id="analyticsArrow" style="margin-right:auto;font-size:10px;transition:transform 0.3s ease;transform:<?php echo $analytics_active ? 'rotate(90deg)' : 'rotate(0deg)'; ?>;">▶</span>
            </a>
        </li>
        <li>
            <ul class="analytics-submenu" id="analyticsSubmenu" style="list-style:none;padding:0;display:<?php echo $analytics_active ? 'block' : 'none'; ?>;">
                <?php foreach ($analytics_pages as $ap): 
                    $ap_file = basename($ap['url']);
                    $is_ap_active = $current_page === $ap_file && $current_dir === 'analytics';
                ?>
                <li>
                    <a href="<?php echo BASE_URL; ?>/modules/analytics/<?php echo $ap['url']; ?>" class="<?php echo $is_ap_active ? 'active' : ''; ?>" style="padding:7px 20px 7px 30px;font-size:13px;border-right:2px solid transparent;">
                        <span class="menu-icon" style="font-size:14px;width:20px;"><?php echo $ap['icon']; ?></span>
                        <span class="menu-text"><?php echo $ap['label']; ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </li>

        <!-- ========== EDUCATION ========== -->
        <li class="menu-section">التوعية</li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/education/index.php" class="<?php echo is_active('education', 'index.php'); ?>">
                <span class="menu-icon">📚</span>
                <span class="menu-text">مركز التوعية</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/education/diabetes_basics.php" class="<?php echo is_active('education', 'diabetes_basics.php'); ?>">
                <span class="menu-icon">📖</span>
                <span class="menu-text">فهم السكري</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/education/diet_plan.php" class="<?php echo is_active('education', 'diet_plan.php'); ?>">
                <span class="menu-icon">🥗</span>
                <span class="menu-text">النظام الغذائي</span>
            </a>
        </li>

        <!-- ========== AI ========== -->
        <li class="menu-section">🤖 الذكاء الاصطناعي</li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/ai/dashboard.php" class="<?php echo is_active('ai', 'dashboard.php'); ?>">
                <span class="menu-icon">🧠</span>
                <span class="menu-text">لوحة تحليل AI</span>
            </a>
        </li>

        <!-- ========== REPORTS ========== -->
        <li class="menu-section">التقارير</li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/reports/patient_report.php" class="<?php echo is_active('reports', 'patient_report.php'); ?>">
                <span class="menu-icon">👤</span>
                <span class="menu-text">تقارير المرضى</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/reports/clinic_report_pdf.php" class="<?php echo is_active('reports', 'clinic_report_pdf.php'); ?>">
                <span class="menu-icon">🏥</span>
                <span class="menu-text">تقرير العيادة</span>
            </a>
        </li>

        <!-- ========== ADMIN ========== -->
        <?php if (in_array($_SESSION['role'] ?? '', ['super_admin', 'admin'])): ?>
        <li style="border-top:1px dashed var(--border);margin:4px 12px;"></li>
        <li class="menu-section">⚙️ الإدارة</li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/users/index.php" class="<?php echo is_active('users'); ?>">
                <span class="menu-icon">👥</span>
                <span class="menu-text">إدارة المستخدمين</span>
            </a>
        </li>
        <li>
            <a href="<?php echo BASE_URL; ?>/modules/settings/index.php" class="<?php echo is_active('settings', 'index.php'); ?>">
                <span class="menu-icon">⚙️</span>
                <span class="menu-text">الإعدادات العامة</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</aside>

<style>
/* Sidebar collapse button */
.sidebar-collapse-btn {
    background: rgba(255,255,255,0.1);
    border: none;
    color: var(--gold-light);
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 8px auto 0;
    transition: all 0.3s ease;
    opacity: 0.6;
}
.sidebar-collapse-btn:hover {
    background: rgba(255,255,255,0.2);
    opacity: 1;
}

/* Sidebar collapsed state */
.sidebar.collapsed .sidebar-collapse-btn {
    transform: rotate(180deg);
}
.sidebar.collapsed .analytics-submenu,
.sidebar.collapsed .analytics-menu-toggle .menu-text {
    display: none !important;
}
.sidebar.collapsed .analytics-menu-toggle a {
    justify-content: center;
}
.sidebar.collapsed .analytics-menu-toggle .menu-arrow {
    display: none;
}

/* Analytics submenu styling */
.analytics-submenu li a {
    padding: 7px 20px 7px 30px !important;
    font-size: 13px;
    border-right: 2px solid transparent;
}
.analytics-submenu li a:hover {
    background: rgba(255,255,255,0.08);
    border-right-color: var(--teal);
}
.analytics-submenu li a.active {
    background: rgba(255,255,255,0.12);
    color: var(--gold-light);
    border-right-color: var(--gold);
}

/* Dark mode adjustments */
[data-theme="dark"] .analytics-submenu li a {
    color: rgba(255,255,255,0.5);
}
[data-theme="dark"] .analytics-submenu li a:hover {
    background: rgba(19, 168, 150, 0.1);
    color: var(--gold-light);
}
[data-theme="dark"] .analytics-submenu li a.active {
    background: rgba(19, 168, 150, 0.15);
    color: var(--gold-light);
    border-right-color: var(--gold);
}

/* Sidebar toggle button in navbar */
.sidebar-toggle-side {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: var(--text-muted);
    padding: 6px 8px;
    border-radius: 8px;
    transition: var(--transition);
    display: none;
    align-items: center;
    justify-content: center;
}
.sidebar-toggle-side:hover {
    background: var(--teal-pale);
    color: var(--teal);
}

@media (max-width: 768px) {
    .sidebar-toggle-side {
        display: flex;
    }
}
</style>

<script>
// Sidebar-specific on-load: restore analytics submenu from PHP state
// Core sidebar functions (toggleSidebar, toggleAnalyticsMenu) are in main.js
document.addEventListener('DOMContentLoaded', function() {
    const submenu = document.getElementById('analyticsSubmenu');
    const arrow = document.getElementById('analyticsArrow');
    if (submenu) {
        const stored = localStorage.getItem('analytics-submenu-open');
        if (stored === '1' || <?php echo $analytics_active ? 'true' : 'false'; ?>) {
            submenu.style.display = 'block';
            if (arrow) arrow.style.transform = 'rotate(90deg)';
        }
    }
});
</script>
