<?php
/**
 * Database Upgrade Script v2.0
 * Adds all missing tables for Tasks 2, 3, 6, 7, 8, 10 from todo_1.md
 * Run this AFTER install.php once.
 */

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'clinic_diabetes';

$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
    die("❌ Connection failed: " . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
echo "✅ Connected to database '$DB_NAME'.<br>";

// ============================================================
// 1. CITIES — قابل للتهيئة من شاشة الإعدادات
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS cities (
    city_id INT AUTO_INCREMENT PRIMARY KEY,
    city_name_ar VARCHAR(100) NOT NULL,
    city_name_en VARCHAR(100),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'cities' created.<br>";

// ============================================================
// 2. DIABETES TYPES — قابل للتهيئة من شاشة الإعدادات
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS diabetes_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name_ar VARCHAR(100) NOT NULL,
    type_name_en VARCHAR(100),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'diabetes_types' created.<br>";

// ============================================================
// 3. LOOKUP OPTIONS — قائمة خيارات عامة للفورمات
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS lookup_options (
    option_id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(50) NOT NULL,
    option_value_ar VARCHAR(200) NOT NULL,
    option_value_en VARCHAR(200),
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'lookup_options' created.<br>";

// ============================================================
// 4. MEDICATION CATEGORIES
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS medication_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'medication_categories' created.<br>";

// ============================================================
// 5. MEDICATIONS — كتالوج الأدوية
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS medications (
    medication_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200),
    active_ingredient VARCHAR(200),
    dosage_form VARCHAR(50) COMMENT 'قرص, كبسولة, محلول, حقنة, مرهم',
    strength VARCHAR(50) COMMENT 'الجرعة مثل 500mg, 10mg',
    unit VARCHAR(30),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES medication_categories(category_id) ON DELETE SET NULL,
    INDEX idx_name (name_ar)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'medications' created.<br>";

// ============================================================
// 6. LAB TEST CATEGORIES
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS lab_test_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'lab_test_categories' created.<br>";

// ============================================================
// 7. LAB TEST TYPES — قائمة الفحوصات مع النطاقات الطبيعية
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS lab_test_types (
    test_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200),
    abbreviation VARCHAR(50),
    unit VARCHAR(50),
    normal_min DECIMAL(10,2),
    normal_max DECIMAL(10,2),
    normal_text VARCHAR(200) COMMENT 'نص تفسير النطاق الطبيعي',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES lab_test_categories(category_id) ON DELETE SET NULL,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'lab_test_types' created.<br>";

// ============================================================
// 8. LAB TEST ORDERS — طلب فحوصات لمريض
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS lab_test_orders (
    order_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    visit_id INT,
    ordered_by INT,
    order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('معلق', 'تم السحب', 'النتائج جاهزة', 'ملغي') NOT NULL DEFAULT 'معلق',
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE SET NULL,
    FOREIGN KEY (ordered_by) REFERENCES users(user_id),
    INDEX idx_patient (patient_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'lab_test_orders' created.<br>";

// ============================================================
// 9. LAB TEST ORDER ITEMS — بنود طلب الفحص
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS lab_test_order_items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    test_type_id INT NOT NULL,
    result_value VARCHAR(100),
    result_flag ENUM('طبيعي', 'مرتفع', 'منخفض', 'حرج') DEFAULT NULL,
    result_date DATETIME,
    entered_by INT,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES lab_test_orders(order_id) ON DELETE CASCADE,
    FOREIGN KEY (test_type_id) REFERENCES lab_test_types(test_id),
    FOREIGN KEY (entered_by) REFERENCES users(user_id),
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'lab_test_order_items' created.<br>";

// ============================================================
// 10. AUTO INSTRUCTIONS — قوالب التعليمات التلقائية
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS auto_instructions (
    instruction_id INT AUTO_INCREMENT PRIMARY KEY,
    title_ar VARCHAR(200) NOT NULL,
    content_ar TEXT NOT NULL,
    content_en TEXT,
    diabetes_type VARCHAR(50) COMMENT 'يُطبق على نوع سكري محدد أو * للكل',
    min_hba1c DECIMAL(4,1) COMMENT 'الحد الأدنى للهيموجلوبين التراكمي',
    max_hba1c DECIMAL(4,1) COMMENT 'الحد الأقصى للهيموجلوبين التراكمي',
    min_wagner INT COMMENT 'الحد الأدنى لدرجة واغنر',
    max_wagner INT COMMENT 'الحد الأقصى لدرجة واغنر',
    has_wound TINYINT(1) COMMENT 'يوجد جرح = 1, لا يوجد = 0, غير محدد = NULL',
    smoking_status VARCHAR(20) COMMENT 'مدخن, لا, سابق',
    category ENUM('عام', 'تغذية', 'عناية قدم', 'أدوية', 'طوارئ', 'تمارين') NOT NULL DEFAULT 'عام',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'auto_instructions' created.<br>";

// ============================================================
// 11. ROLE PERMISSIONS — صلاحيات الأدوار
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS role_permissions (
    permission_id INT AUTO_INCREMENT PRIMARY KEY,
    role VARCHAR(50) NOT NULL,
    page_module VARCHAR(100) NOT NULL,
    can_view TINYINT(1) NOT NULL DEFAULT 1,
    can_create TINYINT(1) NOT NULL DEFAULT 0,
    can_edit TINYINT(1) NOT NULL DEFAULT 0,
    can_delete TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_role_page (role, page_module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'role_permissions' created.<br>";

// ============================================================
// 12. BACKUP LOG — سجل النسخ الاحتياطي
// ============================================================
$conn->query("CREATE TABLE IF NOT EXISTS backup_log (
    backup_id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    file_size_kb INT,
    backup_type ENUM('يدوي', 'تلقائي', 'مجدول') NOT NULL DEFAULT 'يدوي',
    status ENUM('ناجح', 'فشل') NOT NULL DEFAULT 'ناجح',
    error_message TEXT,
    created_by INT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    INDEX idx_type (backup_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'backup_log' created.<br>";

// ============================================================
// 13. UPDATE USERS TABLE — إضافة حقول جديدة
// ============================================================
// Check if columns exist first
$columns = [];
$col_result = $conn->query("SHOW COLUMNS FROM users");
while ($c = $col_result->fetch_assoc()) {
    $columns[] = $c['Field'];
}

// Update role enum to include all 5 roles
if (in_array('role', $columns)) {
    // Map old roles to new ones before altering the column
    $conn->query("UPDATE users SET role = 'nurse' WHERE role NOT IN ('super_admin','admin','doctor','nurse')");
    $conn->query("UPDATE users SET role = 'doctor' WHERE role = 'user'");
    $result = $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'doctor', 'medical_assistant', 'nurse') NOT NULL DEFAULT 'nurse'");
    if ($result) {
        echo "✅ Users table role column updated.<br>";
    } else {
        echo "⚠️ Could not update role column: " . $conn->error . "<br>";
    }
}

if (!in_array('phone', $columns)) {
    $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER email");
    echo "✅ Added 'phone' to users.<br>";
}

if (!in_array('specialty', $columns)) {
    $conn->query("ALTER TABLE users ADD COLUMN specialty VARCHAR(100) AFTER role");
    echo "✅ Added 'specialty' to users.<br>";
}

// ============================================================
// 14. INSERT DEFAULT LOOKUP DATA
// ============================================================

// Default cities
$city_count = $conn->query("SELECT COUNT(*) as cnt FROM cities")->fetch_assoc()['cnt'];
if ($city_count === 0) {
    $default_cities = [
        ['الرياض', 'Riyadh'],
        ['جدة', 'Jeddah'],
        ['مكة المكرمة', 'Makkah'],
        ['المدينة المنورة', 'Madinah'],
        ['الدمام', 'Dammam'],
        ['الخبر', 'Khobar'],
        ['أبها', 'Abha'],
        ['تبوك', 'Tabuk'],
        ['بريدة', 'Buraydah'],
        ['حائل', 'Hail'],
        ['نجران', 'Najran'],
        ['جيزان', 'Jazan'],
        ['الطائف', 'Taif'],
        ['الباحة', 'Al-Bahah'],
        ['عرعر', 'Arar'],
        ['سكاكا', 'Sakaka'],
    ];
    $stmt = $conn->prepare("INSERT INTO cities (city_name_ar, city_name_en) VALUES (?, ?)");
    foreach ($default_cities as $c) {
        $stmt->bind_param('ss', $c[0], $c[1]);
        $stmt->execute();
    }
    echo "✅ Default cities inserted.<br>";
}

// Default diabetes types
$dt_count = $conn->query("SELECT COUNT(*) as cnt FROM diabetes_types")->fetch_assoc()['cnt'];
if ($dt_count === 0) {
    $default_types = [
        ['النوع الأول (Type 1)', 'Type 1 Diabetes'],
        ['النوع الثاني (Type 2)', 'Type 2 Diabetes'],
        ['سكري الحمل (GDM)', 'Gestational Diabetes'],
        ['MODY', 'MODY'],
        ['سكري ثانوي', 'Secondary Diabetes'],
        ['غير محدد', 'Unspecified'],
    ];
    $stmt = $conn->prepare("INSERT INTO diabetes_types (type_name_ar, type_name_en, sort_order) VALUES (?, ?, ?)");
    $i = 1;
    foreach ($default_types as $dt) {
        $stmt->bind_param('ssi', $dt[0], $dt[1], $i);
        $stmt->execute();
        $i++;
    }
    echo "✅ Default diabetes types inserted.<br>";
}

// Default medication categories
$mc_count = $conn->query("SELECT COUNT(*) as cnt FROM medication_categories")->fetch_assoc()['cnt'];
if ($mc_count === 0) {
    $default_cats = [
        ['أدوية السكري الفموية', 'Oral Diabetes Medications'],
        ['أنسولين', 'Insulin'],
        ['خافضات الضغط', 'Antihypertensives'],
        ['مضادات حيوية', 'Antibiotics'],
        ['مضادات فطريات', 'Antifungals'],
        ['خافضات الدهون', 'Lipid-lowering agents'],
        ['مضادات التخثر', 'Anticoagulants'],
        ['مدرات البول', 'Diuretics'],
        ['مسكنات ألم', 'Analgesics'],
        ['أدوية الجهاز الهضمي', 'GI Medications'],
        ['فيتامينات ومكملات', 'Vitamins & Supplements'],
        ['مراهم وكريمات', 'Ointments & Creams'],
        ['أدوية أخرى', 'Other Medications'],
    ];
    $stmt = $conn->prepare("INSERT INTO medication_categories (name_ar, name_en) VALUES (?, ?)");
    foreach ($default_cats as $mc) {
        $stmt->bind_param('ss', $mc[0], $mc[1]);
        $stmt->execute();
    }
    echo "✅ Default medication categories inserted.<br>";
}

// Default lab test categories
$lc_count = $conn->query("SELECT COUNT(*) as cnt FROM lab_test_categories")->fetch_assoc()['cnt'];
if ($lc_count === 0) {
    $default_lc = [
        ['سكر وهرمونات', 'Blood Sugar & Hormones'],
        ['دهون الدم', 'Lipid Profile'],
        ['وظائف الكلى', 'Kidney Function'],
        ['وظائف الكبد', 'Liver Function'],
        ['صورة الدم الكاملة', 'CBC'],
        ['تجلط الدم', 'Coagulation'],
        ['الهرمونات', 'Hormones'],
        ['أملاح ومعادن', 'Electrolytes & Minerals'],
        ['تحليل بول', 'Urinalysis'],
        ['ميكروبيولوجيا', 'Microbiology'],
        ['فحوصات أخرى', 'Other Tests'],
    ];
    $stmt = $conn->prepare("INSERT INTO lab_test_categories (name_ar, name_en, sort_order) VALUES (?, ?, ?)");
    $i = 1;
    foreach ($default_lc as $lc) {
        $stmt->bind_param('ssi', $lc[0], $lc[1], $i);
        $stmt->execute();
        $i++;
    }
    echo "✅ Default lab test categories inserted.<br>";
}

// Default lab test types
$lt_count = $conn->query("SELECT COUNT(*) as cnt FROM lab_test_types")->fetch_assoc()['cnt'];
if ($lt_count === 0) {
    // Get category IDs
    $cats = [];
    $r = $conn->query("SELECT category_id, name_ar FROM lab_test_categories");
    while ($row = $r->fetch_assoc()) {
        $cats[$row['name_ar']] = $row['category_id'];
    }
    
    $default_tests = [
        ['سكر وهرمونات', 'السكر التراكمي (HbA1c)', 'HbA1c', '%', 4.0, 7.0],
        ['سكر وهرمونات', 'سكر صائم (FPG)', 'FPG', 'mg/dL', 70, 126],
        ['سكر وهرمونات', 'سكر فاطر (PPG)', 'PPG', 'mg/dL', 70, 180],
        ['سكر وهرمونات', 'TSH', 'TSH', 'mIU/L', 0.4, 4.0],
        ['دهون الدم', 'الكوليسترول الكلي', 'Total Chol', 'mg/dL', 0, 200],
        ['دهون الدم', 'LDL', 'LDL', 'mg/dL', 0, 100],
        ['دهون الدم', 'HDL', 'HDL', 'mg/dL', 40, 100],
        ['دهون الدم', 'الدهون الثلاثية', 'TG', 'mg/dL', 0, 150],
        ['وظائف الكلى', 'الكرياتينين (Creatinine)', 'Cr', 'mg/dL', 0.6, 1.2],
        ['وظائف الكلى', 'اليوريا (Urea)', 'Urea', 'mg/dL', 7, 20],
        ['وظائف الكلى', 'eGFR', 'eGFR', 'mL/min', 90, 999],
        ['وظائف الكلى', 'بروتين البول (Microalbumin)', 'Microalbumin', 'mg/g Cr', 0, 30],
        ['وظائف الكبد', 'ALT (SGPT)', 'ALT', 'U/L', 0, 40],
        ['وظائف الكبد', 'AST (SGOT)', 'AST', 'U/L', 0, 40],
        ['صورة الدم الكاملة', 'هيموجلوبين (Hemoglobin)', 'Hb', 'g/dL', 13, 17],
        ['صورة الدم الكاملة', 'كريات الدم البيضاء (WBC)', 'WBC', '10³/µL', 4.5, 11],
        ['صورة الدم الكاملة', 'صفائح الدم (Platelets)', 'Plt', '10³/µL', 150, 450],
    ];
    
    $stmt = $conn->prepare("INSERT INTO lab_test_types (category_id, name_ar, abbreviation, unit, normal_min, normal_max) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($default_tests as $t) {
        $cat_id = $cats[$t[0]] ?? null;
        if ($cat_id) {
            $stmt->bind_param('isssdd', $cat_id, $t[1], $t[2], $t[3], $t[4], $t[5]);
            $stmt->execute();
        }
    }
    echo "✅ Default lab test types inserted.<br>";
}

// Default lookup options
$lo_count = $conn->query("SELECT COUNT(*) as cnt FROM lookup_options")->fetch_assoc()['cnt'];
if ($lo_count === 0) {
    $default_options = [
        // Marital status
        ['marital_status', 'أعزب/عزباء', 'Single'],
        ['marital_status', 'متزوج/ة', 'Married'],
        ['marital_status', 'مطلق/ة', 'Divorced'],
        ['marital_status', 'أرمل/ة', 'Widowed'],
        // Visit reasons
        ['visit_reason', 'متابعة', 'Follow-up'],
        ['visit_reason', 'شكوى محددة', 'Specific complaint'],
        ['visit_reason', 'طوارئ', 'Emergency'],
        ['visit_reason', 'مراجعة', 'Review'],
        // Wound causes
        ['wound_cause', 'طعنة شوكة', 'Thorn prick'],
        ['wound_cause', 'طعنة دبوس', 'Pin prick'],
        ['wound_cause', 'ضربة', 'Hit'],
        ['wound_cause', 'تليف وخشونة', 'Callus/roughness'],
        ['wound_cause', 'حذاء جديد', 'New shoe'],
        ['wound_cause', 'بقاقة', 'Friction'],
        ['wound_cause', 'حرق', 'Burn'],
        ['wound_cause', 'غير معروف', 'Unknown'],
        // Nationalities
        ['nationality', 'سعودي', 'Saudi'],
        ['nationality', 'مصري', 'Egyptian'],
        ['nationality', 'سوداني', 'Sudanese'],
        ['nationality', 'يمني', 'Yemeni'],
        ['nationality', 'سوري', 'Syrian'],
        ['nationality', 'أردني', 'Jordanian'],
        ['nationality', 'فلسطيني', 'Palestinian'],
        ['nationality', 'عراقي', 'Iraqi'],
        ['nationality', 'هندي', 'Indian'],
        ['nationality', 'باكستاني', 'Pakistani'],
        ['nationality', 'بنجلاديشي', 'Bangladeshi'],
        ['nationality', 'فلبيني', 'Filipino'],
        ['nationality', 'أندونيسي', 'Indonesian'],
        ['nationality', 'أخرى', 'Other'],
        // Occupations
        ['occupation', 'موظف حكومي', 'Government employee'],
        ['occupation', 'موظف قطاع خاص', 'Private sector'],
        ['occupation', 'طالب', 'Student'],
        ['occupation', 'متقاعد', 'Retired'],
        ['occupation', 'رب/ربة منزل', 'Housewife/Househusband'],
        ['occupation', 'عامل', 'Worker'],
        ['occupation', 'سائق', 'Driver'],
        ['occupation', 'معلم', 'Teacher'],
        ['occupation', 'طبيب', 'Doctor'],
        ['occupation', 'ممرض', 'Nurse'],
        ['occupation', 'أخرى', 'Other'],
        // Treatment types
        ['treatment_type', 'حمية غذائية فقط', 'Diet only'],
        ['treatment_type', 'أدوية فموية (OADs)', 'Oral medications'],
        ['treatment_type', 'أنسولين', 'Insulin'],
        ['treatment_type', 'أنسولين + أدوية فموية', 'Insulin + OADs'],
        ['treatment_type', 'مضخة أنسولين (Pump)', 'Insulin pump'],
        ['treatment_type', 'GLP-1 RA', 'GLP-1 RA'],
    ];
    
    $stmt = $conn->prepare("INSERT INTO lookup_options (category, option_value_ar, option_value_en) VALUES (?, ?, ?)");
    foreach ($default_options as $o) {
        $stmt->bind_param('sss', $o[0], $o[1], $o[2]);
        $stmt->execute();
    }
    echo "✅ Default lookup options inserted.<br>";
}

// Default role permissions
$rp_count = $conn->query("SELECT COUNT(*) as cnt FROM role_permissions")->fetch_assoc()['cnt'];
if ($rp_count === 0) {
    $all_modules = [
        'patients', 'visits', 'appointments', 'assessments', 'lab_tests',
        'medications', 'analytics', 'reports', 'education', 'settings',
        'users', 'backup', 'ai'
    ];
    
    $roles = ['super_admin', 'admin', 'doctor', 'medical_assistant', 'nurse'];
    $permissions = [
        'super_admin' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 1],
        'admin' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
        'doctor' => ['view' => 1, 'create' => 1, 'edit' => 1, 'delete' => 0],
        'medical_assistant' => ['view' => 1, 'create' => 1, 'edit' => 0, 'delete' => 0],
        'nurse' => ['view' => 1, 'create' => 0, 'edit' => 0, 'delete' => 0],
    ];
    
    $stmt = $conn->prepare("INSERT INTO role_permissions (role, page_module, can_view, can_create, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($roles as $role) {
        $p = $permissions[$role];
        foreach ($all_modules as $module) {
            // Restrict settings & users to super_admin and admin only
            if (in_array($module, ['settings', 'users', 'backup']) && !in_array($role, ['super_admin', 'admin'])) {
                $stmt->bind_param('ssiii', $role, $module, 0, 0, 0, 0);
            } else {
                $stmt->bind_param('ssiii', $role, $module, $p['view'], $p['create'], $p['edit'], $p['delete']);
            }
            $stmt->execute();
        }
    }
    echo "✅ Default role permissions inserted.<br>";
}

// Default auto instructions
$ai_count = $conn->query("SELECT COUNT(*) as cnt FROM auto_instructions")->fetch_assoc()['cnt'];
if ($ai_count === 0) {
    $default_instructions = [
        [
            'title' => 'تعليمات عامة لمرضى السكري',
            'content' => '• قياس السكري يومياً\n• الالتزام بمواعيد الأدوية\n• مراجعة الطبيب بانتظام\n• ممارسة المشي 30 دقيقة يومياً\n• شرب كمية كافية من الماء',
            'category' => 'عام',
            'diabetes' => null, 'min_hba1c' => null, 'max_hba1c' => null, 'min_w' => null, 'max_w' => null, 'wound' => null, 'smoking' => null
        ],
        [
            'title' => 'تعليمات عند ارتفاع السكر (HbA1c > 7%)',
            'content' => '• مراجعة الطبيب لتعديل الجرعات\n• الإكثار من شرب الماء\n• تقليل النشويات والسكريات\n• قياس السكر 4 مرات يومياً\n• مراجعة خطة العلاج مع الطبيب',
            'category' => 'أدوية',
            'diabetes' => null, 'min_hba1c' => 7, 'max_hba1c' => null, 'min_w' => null, 'max_w' => null, 'wound' => null, 'smoking' => null
        ],
        [
            'title' => 'تعليمات العناية بالقدم (بدون جروح)',
            'content' => '• فحص القدم يومياً\n• غسل القدمين بماء دافئ وتجفيفها جيداً\n• ترطيب القدمين مع تجنب بين الأصابع\n• قص الأظافر بشكل مستقيم\n• ارتداء حذاء طبي مناسب',
            'category' => 'عناية قدم',
            'diabetes' => null, 'min_hba1c' => null, 'max_hba1c' => null, 'min_w' => 0, 'max_w' => 1, 'wound' => 0, 'smoking' => null
        ],
        [
            'title' => 'تعليمات عند وجود جرح في القدم (Wagner ≥ 2)',
            'content' => '• مراجعة العيادة فوراً\n• عدم المشي على القدم المصابة\n• تنظيف الجرح بمحلول ملحي\n• تغيير الغيار يومياً حسب تعليمات الممرض\n• مراقبة علامات الالتهاب (احمرار، انتفاخ، صديد)',
            'category' => 'عناية قدم',
            'diabetes' => null, 'min_hba1c' => null, 'max_hba1c' => null, 'min_w' => 2, 'max_w' => null, 'wound' => 1, 'smoking' => null
        ],
        [
            'title' => 'تعليمات غذائية لمرضى السكري من النوع الثاني',
            'content' => '• تقسيم الوجبات إلى 3 وجبات رئيسية + وجبتين خفيفتين\n• تقليل الأرز والخبز الأبيض\n• الإكثار من الخضروات الورقية\n• تجنب المشروبات المحلاة\n• تناول البروتين مع كل وجبة',
            'category' => 'تغذية',
            'diabetes' => 'النوع الثاني (Type 2)', 'min_hba1c' => null, 'max_hba1c' => null, 'min_w' => null, 'max_w' => null, 'wound' => null, 'smoking' => null
        ],
        [
            'title' => 'تعليمات للمدخنين مرضى السكري',
            'content' => '• التدخين يزيد خطر البتر بنسبة 400%\n• الإقلاع عن التدخين ضروري جداً\n• يمكنك التواصل مع عيادة مكافحة التدخين\n• مضغ العلكة الخالية من السكر كبديل\n• استشارة الطبيب لوصف أدوية مساعدة للإقلاع',
            'category' => 'عام',
            'diabetes' => null, 'min_hba1c' => null, 'max_hba1c' => null, 'min_w' => null, 'max_w' => null, 'wound' => null, 'smoking' => 'مدخن'
        ],
        [
            'title' => 'تعليمات حالات الطوارئ',
            'content' => 'عند هبوط السكر (أقل من 70):\n• تناول 3 حبات سكر أو عصير فواكه فوراً\n• قياس السكر بعد 15 دقيقة\n• إذا لم يتحسن، كرر أو اذهب للمستشفى\n\nعند ارتفاع السكر (أكثر من 300):\n• شرب الماء بكثرة\n• قياس الكيتون في البول\n• التوجه للطوارئ إذا كان كيتون إيجابي',
            'category' => 'طوارئ',
            'diabetes' => null, 'min_hba1c' => null, 'max_hba1c' => null, 'min_w' => null, 'max_w' => null, 'wound' => null, 'smoking' => null
        ],
        [
            'title' => 'تعليمات التمارين الرياضية',
            'content' => '• المشي 30 دقيقة يومياً (5 أيام في الأسبوع)\n• قياس السكر قبل وبعد التمرين\n• حمل قطعة سكر أثناء التمرين\n• تجنب التمارين الشاقة إذا كان السكر > 250\n• ارتداء حذاء رياضي مناسب',
            'category' => 'تمارين',
            'diabetes' => null, 'min_hba1c' => null, 'max_hba1c' => null, 'min_w' => 0, 'max_w' => 1, 'wound' => 0, 'smoking' => null
        ],
    ];
    
    $stmt = $conn->prepare("INSERT INTO auto_instructions (title_ar, content_ar, category, diabetes_type, min_hba1c, max_hba1c, min_wagner, max_wagner, has_wound, smoking_status, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($default_instructions as $inst) {
        $stmt->bind_param('sssdddddis', 
            $inst['title'], $inst['content'], $inst['category'],
            $inst['diabetes'], $inst['min_hba1c'], $inst['max_hba1c'],
            $inst['min_w'], $inst['max_w'], $inst['wound'], $inst['smoking']
        );
        $stmt->execute();
    }
    echo "✅ Default auto instructions inserted.<br>";
}

echo "<br><hr><h3>✅ Database Upgrade Complete!</h3>";
echo "<p>All new tables and default data have been created successfully.</p>";

$conn->close();
?>
