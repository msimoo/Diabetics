<?php
/**
 * Diabetes Clinic Management System - Database Installer
 * Run this script ONCE to create the database and all tables.
 * Delete or secure this file after installation.
 */

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'clinic_diabetes';

// Connect without database first
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "✅ Database '$DB_NAME' created successfully.<br>";
} else {
    die("❌ Error creating database: " . $conn->error);
}

$conn->select_db($DB_NAME);

// ==================== CREATE TABLES ====================

// 1. users
$conn->query("CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'doctor', 'nurse', 'receptionist') NOT NULL DEFAULT 'receptionist',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'users' created.<br>";

// 2. patients
$conn->query("CREATE TABLE IF NOT EXISTS patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    file_number VARCHAR(20) NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    date_of_birth DATE,
    age INT,
    gender ENUM('ذكر', 'أنثى') NOT NULL,
    phone_primary VARCHAR(20),
    phone_secondary VARCHAR(20),
    identity_number VARCHAR(20),
    nationality VARCHAR(50),
    marital_status ENUM('أعزب/عزباء', 'متزوج/ة', 'مطلق/ة', 'أرمل/ة'),
    occupation VARCHAR(100),
    address TEXT,
    city VARCHAR(50),
    distance_from_center DECIMAL(5,1),
    first_visit_date DATE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    photo VARCHAR(255),
    created_by INT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'patients' created.<br>";

// 3. medical_history
$conn->query("CREATE TABLE IF NOT EXISTS medical_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    diabetes_type ENUM('النوع الأول (Type 1)', 'النوع الثاني (Type 2)', 'سكري الحمل (GDM)', 'MODY', 'غير محدد'),
    diagnosis_year YEAR,
    duration_years INT,
    diagnosis_method SET('أعراض كلاسيكية', 'FPG ≥ 126', 'HbA1c ≥ 6.5%', 'OGTT', 'مصادفة أثناء فحص آخر'),
    companion_name VARCHAR(100),
    family_history SET('أب/أم', 'أخ/أخت', 'أجداد', 'لا يوجد'),
    smoking_status ENUM('لا', 'مدخن', 'سابق'),
    physical_activity ENUM('لا يوجد', 'خفيف', 'معتدل', 'منتظم'),
    has_hypertension TINYINT(1) DEFAULT 0,
    has_kidney_disease TINYINT(1) DEFAULT 0,
    has_eye_retinopathy TINYINT(1) DEFAULT 0,
    has_eye_cataract TINYINT(1) DEFAULT 0,
    has_eye_glaucoma TINYINT(1) DEFAULT 0,
    other_chronic_diseases TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'medical_history' created.<br>";

// 4. visits
$conn->query("CREATE TABLE IF NOT EXISTS visits (
    visit_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    visit_number INT NOT NULL DEFAULT 1,
    visit_date DATE NOT NULL,
    visit_reason ENUM('متابعة', 'شكوى محددة', 'طوارئ', 'مراجعة'),
    chief_complaint TEXT,
    doctor_notes TEXT,
    treatment_plan TEXT,
    next_review_date DATE,
    created_by INT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'visits' created.<br>";

// 5. vital_signs
$conn->query("CREATE TABLE IF NOT EXISTS vital_signs (
    vital_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    weight DECIMAL(5,1),
    height INT,
    bmi DECIMAL(4,1),
    blood_pressure_systolic INT,
    blood_pressure_diastolic INT,
    blood_pressure_text VARCHAR(10),
    waist_circumference DECIMAL(5,1),
    temperature DECIMAL(4,1),
    heart_rate INT,
    oxygen_saturation DECIMAL(4,1),
    notes TEXT,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'vital_signs' created.<br>";

// 6. blood_sugar_readings
$conn->query("CREATE TABLE IF NOT EXISTS blood_sugar_readings (
    reading_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    fpg_value DECIMAL(5,1),
    fpg_date DATE,
    ppg_value DECIMAL(5,1),
    ppg_date DATE,
    hba1c_value DECIMAL(4,1),
    hba1c_date DATE,
    random_sugar_value DECIMAL(5,1),
    random_sugar_date DATE,
    lab_device VARCHAR(100),
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'blood_sugar_readings' created.<br>";

// 7. treatments
$conn->query("CREATE TABLE IF NOT EXISTS treatments (
    treatment_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    treatment_type SET('حمية غذائية فقط', 'أدوية فموية (OADs)', 'أنسولين', 'أنسولين + أدوية فموية', 'مضخة أنسولين (Pump)', 'GLP-1 RA'),
    oral_meds_details TEXT,
    insulin_details TEXT,
    other_meds TEXT,
    antibiotics_oral TEXT,
    antibiotics_iv TEXT,
    antibiotics_duration VARCHAR(50),
    topical_ointments TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'treatments' created.<br>";

// 8. complications
$conn->query("CREATE TABLE IF NOT EXISTS complications (
    complication_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    has_retinopathy TINYINT(1) DEFAULT 0,
    has_nephropathy TINYINT(1) DEFAULT 0,
    has_neuropathy TINYINT(1) DEFAULT 0,
    has_cad TINYINT(1) DEFAULT 0,
    has_cva TINYINT(1) DEFAULT 0,
    has_pad TINYINT(1) DEFAULT 0,
    diabetic_foot ENUM('لا يوجد', 'نعم', 'تاريخ سابق'),
    hypoglycemia_severity ENUM('لا', 'نادرة', 'متكررة'),
    notes TEXT,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'complications' created.<br>";

// 9. lab_results
$conn->query("CREATE TABLE IF NOT EXISTS lab_results (
    lab_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    total_cholesterol DECIMAL(5,1),
    ldl DECIMAL(5,1),
    hdl DECIMAL(5,1),
    triglycerides DECIMAL(5,1),
    creatinine DECIMAL(4,2),
    egfr INT,
    microalbumin DECIMAL(6,1),
    tsh DECIMAL(5,2),
    test_date DATE,
    other_labs TEXT,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'lab_results' created.<br>";

// 10. foot_assessments
$conn->query("CREATE TABLE IF NOT EXISTS foot_assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    right_sensation ENUM('طبيعي', 'منخفض', 'معدوم', 'متناقض'),
    right_pulse ENUM('طبيعي متوسط', 'ضعيف', 'معدوم'),
    right_deformities SET('قدم مخلبية', 'قدم مسطحة', 'بيس كافوس', 'أصابع مزدحمة', 'شاركوت', 'أصابع مطرقة', 'ثفنات', 'عظام مشط بارزة'),
    left_sensation ENUM('طبيعي', 'منخفض', 'معدوم', 'متناقض'),
    left_pulse ENUM('طبيعي متوسط', 'ضعيف', 'معدوم'),
    left_deformities SET('قدم مخلبية', 'قدم مسطحة', 'بيس كافوس', 'أصابع مزدحمة', 'شاركوت', 'أصابع مطرقة', 'ثفنات', 'عظام مشط بارزة'),
    abpi_right DECIMAL(4,2),
    abpi_left DECIMAL(4,2),
    wagner_grade TINYINT,
    notes TEXT,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'foot_assessments' created.<br>";

// 11. foot_ulcers
$conn->query("CREATE TABLE IF NOT EXISTS foot_ulcers (
    ulcer_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    cause VARCHAR(200),
    initial_cause ENUM('طعنة شوكة', 'طعنة دبوس', 'ضربة', 'تليف وخشونة', 'حذاء جديد', 'بقاقة', 'حرق', 'غير معروف'),
    patient_reaction VARCHAR(200),
    first_practitioner ENUM('ممرض', 'طبيب عمومي', 'جراح', 'جهة غير صحية'),
    wound_condition ENUM('نظيفة', 'متسخة', 'صديد', 'سوداء', 'تحوي جسم غريب'),
    wound_depth ENUM('سطحي في الجلد', 'الجلد وتحت الجلد', 'العضلات', 'العظم'),
    wound_location_x DECIMAL(5,2),
    wound_location_y DECIMAL(5,2),
    wound_foot ENUM('يمنى', 'يسرى'),
    wound_size_cm2 DECIMAL(5,1),
    wound_image VARCHAR(255),
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'foot_ulcers' created.<br>";

// 12. outcomes
$conn->query("CREATE TABLE IF NOT EXISTS outcomes (
    outcome_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    improvement_percentage TINYINT,
    improvement_date DATE,
    healing_date DATE,
    previous_amputation ENUM('لا', 'فوق الركبة', 'تحت الركبة', 'فوق الكاحل', 'تحت الكاحل', 'بتر رايس', 'إصبع'),
    previous_amputation_date DATE,
    previous_amputation_location VARCHAR(50),
    current_amputation ENUM('لا', 'فوق الركبة', 'تحت الركبة', 'فوق الكاحل', 'تحت الكاحل', 'بتر رايس', 'إصبع'),
    current_amputation_date DATE,
    referral_to VARCHAR(100),
    referral_reason TEXT,
    is_deceased TINYINT(1) DEFAULT 0,
    death_date DATE,
    death_cause ENUM('مضاعفات قدم السكري', 'سبب آخر'),
    death_cause_detail TEXT,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'outcomes' created.<br>";

// 13. care_plan
$conn->query("CREATE TABLE IF NOT EXISTS care_plan (
    care_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    care_type SET('تقليم أظافر', 'نظافة قدم', 'ترطيب قدم'),
    dressing_frequency ENUM('1', '2-3', '4 فأكثر'),
    offloading_needed TINYINT(1) DEFAULT 0,
    offloading_type VARCHAR(100),
    dietary_plan TEXT,
    emergency_instructions TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'care_plan' created.<br>";

// 14. care_sessions
$conn->query("CREATE TABLE IF NOT EXISTS care_sessions (
    session_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL,
    session_date DATE NOT NULL,
    performed_by INT,
    care_provided TEXT,
    wound_condition_after TEXT,
    notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE,
    FOREIGN KEY (performed_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'care_sessions' created.<br>";

// ==================== INSERT DEFAULT ADMIN USER ====================
$default_password = password_hash('admin123', PASSWORD_BCRYPT);
$check = $conn->query("SELECT user_id FROM users WHERE username = 'admin'");
if ($check->num_rows === 0) {
    $conn->query("INSERT INTO users (username, password_hash, full_name, email, role) VALUES 
                  ('admin', '$default_password', 'System Admin', 'admin@clinic.com', 'admin')");
    echo "✅ Default admin user created (username: admin, password: admin123).<br>";
} else {
    echo "ℹ️ Admin user already exists.<br>";
}

// ==================== NEW TABLES (v2.0) ====================

// 1. CITIES — قابل للتهيئة من شاشة الإعدادات
$conn->query("CREATE TABLE IF NOT EXISTS cities (
    city_id INT AUTO_INCREMENT PRIMARY KEY,
    city_name_ar VARCHAR(100) NOT NULL,
    city_name_en VARCHAR(100),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'cities' created.<br>";

// 2. DIABETES TYPES
$conn->query("CREATE TABLE IF NOT EXISTS diabetes_types (
    type_id INT AUTO_INCREMENT PRIMARY KEY,
    type_name_ar VARCHAR(100) NOT NULL,
    type_name_en VARCHAR(100),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'diabetes_types' created.<br>";

// 3. LOOKUP OPTIONS
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

// 4. MEDICATION CATEGORIES
$conn->query("CREATE TABLE IF NOT EXISTS medication_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'medication_categories' created.<br>";

// 5. MEDICATIONS
$conn->query("CREATE TABLE IF NOT EXISTS medications (
    medication_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200),
    active_ingredient VARCHAR(200),
    dosage_form VARCHAR(50),
    strength VARCHAR(50),
    unit VARCHAR(30),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES medication_categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'medications' created.<br>";

// 6. LAB TEST CATEGORIES
$conn->query("CREATE TABLE IF NOT EXISTS lab_test_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    name_ar VARCHAR(100) NOT NULL,
    name_en VARCHAR(100),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'lab_test_categories' created.<br>";

// 7. LAB TEST TYPES
$conn->query("CREATE TABLE IF NOT EXISTS lab_test_types (
    test_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name_ar VARCHAR(200) NOT NULL,
    name_en VARCHAR(200),
    abbreviation VARCHAR(50),
    unit VARCHAR(50),
    normal_min DECIMAL(10,2),
    normal_max DECIMAL(10,2),
    normal_text VARCHAR(200),
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES lab_test_categories(category_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'lab_test_types' created.<br>";

// 8. LAB TEST ORDERS
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
    FOREIGN KEY (ordered_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'lab_test_orders' created.<br>";

// 9. LAB TEST ORDER ITEMS
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
    FOREIGN KEY (entered_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'lab_test_order_items' created.<br>";

// 10. AUTO INSTRUCTIONS
$conn->query("CREATE TABLE IF NOT EXISTS auto_instructions (
    instruction_id INT AUTO_INCREMENT PRIMARY KEY,
    title_ar VARCHAR(200) NOT NULL,
    content_ar TEXT NOT NULL,
    content_en TEXT,
    diabetes_type VARCHAR(50),
    min_hba1c DECIMAL(4,1),
    max_hba1c DECIMAL(4,1),
    min_wagner INT,
    max_wagner INT,
    has_wound TINYINT(1),
    smoking_status VARCHAR(20),
    category ENUM('عام', 'تغذية', 'عناية قدم', 'أدوية', 'طوارئ', 'تمارين') NOT NULL DEFAULT 'عام',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'auto_instructions' created.<br>";

// 11. ROLE PERMISSIONS
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

// 12. BACKUP LOG
$conn->query("CREATE TABLE IF NOT EXISTS backup_log (
    backup_id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    file_size_kb INT,
    backup_type ENUM('يدوي', 'تلقائي', 'مجدول') NOT NULL DEFAULT 'يدوي',
    status ENUM('ناجح', 'فشل') NOT NULL DEFAULT 'ناجح',
    error_message TEXT,
    created_by INT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "✅ Table 'backup_log' created.<br>";

// 13. UPDATE USERS TABLE — add new columns and role enum
$columns = [];
$col_result = $conn->query("SHOW COLUMNS FROM users");
while ($c = $col_result->fetch_assoc()) { $columns[] = $c['Field']; }

if (in_array('role', $columns)) {
    $conn->query("UPDATE users SET role = 'nurse' WHERE role NOT IN ('super_admin','admin','doctor','nurse')");
    $conn->query("UPDATE users SET role = 'doctor' WHERE role = 'user'");
    $conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'doctor', 'medical_assistant', 'nurse') NOT NULL DEFAULT 'nurse'");
    echo "✅ Users table role column updated.<br>";
}
if (!in_array('phone', $columns)) {
    $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(20) AFTER email");
    echo "✅ Added 'phone' to users.<br>";
}
if (!in_array('specialty', $columns)) {
    $conn->query("ALTER TABLE users ADD COLUMN specialty VARCHAR(100) AFTER role");
    echo "✅ Added 'specialty' to users.<br>";
}

// ==================== INSERT DEFAULT DATA ====================

// Default cities
if ($conn->query("SELECT COUNT(*) as cnt FROM cities")->fetch_assoc()['cnt'] === 0) {
    $default_cities = [
        ['الرياض', 'Riyadh'], ['جدة', 'Jeddah'], ['مكة المكرمة', 'Makkah'],
        ['المدينة المنورة', 'Madinah'], ['الدمام', 'Dammam'], ['الخبر', 'Khobar'],
        ['أبها', 'Abha'], ['تبوك', 'Tabuk'], ['بريدة', 'Buraydah'],
        ['حائل', 'Hail'], ['نجران', 'Najran'], ['جيزان', 'Jazan'],
        ['الطائف', 'Taif'], ['الباحة', 'Al-Bahah'], ['عرعر', 'Arar'], ['سكاكا', 'Sakaka'],
    ];
    $stmt = $conn->prepare("INSERT INTO cities (city_name_ar, city_name_en) VALUES (?, ?)");
    foreach ($default_cities as $c) { $stmt->bind_param('ss', $c[0], $c[1]); $stmt->execute(); }
    echo "✅ Default cities inserted.<br>";
}

// Default diabetes types
if ($conn->query("SELECT COUNT(*) as cnt FROM diabetes_types")->fetch_assoc()['cnt'] === 0) {
    $default_types = [
        ['النوع الأول (Type 1)', 'Type 1 Diabetes', 1],
        ['النوع الثاني (Type 2)', 'Type 2 Diabetes', 2],
        ['سكري الحمل (GDM)', 'Gestational Diabetes', 3],
        ['MODY', 'MODY', 4],
        ['سكري ثانوي', 'Secondary Diabetes', 5],
        ['غير محدد', 'Unspecified', 6],
    ];
    $stmt = $conn->prepare("INSERT INTO diabetes_types (type_name_ar, type_name_en, sort_order) VALUES (?, ?, ?)");
    foreach ($default_types as $dt) { $stmt->bind_param('ssi', $dt[0], $dt[1], $dt[2]); $stmt->execute(); }
    echo "✅ Default diabetes types inserted.<br>";
}

// Default medication categories
if ($conn->query("SELECT COUNT(*) as cnt FROM medication_categories")->fetch_assoc()['cnt'] === 0) {
    $default_cats = [
        ['أدوية السكري الفموية', 'Oral Diabetes Medications'],
        ['أنسولين', 'Insulin'], ['خافضات الضغط', 'Antihypertensives'],
        ['مضادات حيوية', 'Antibiotics'], ['مضادات فطريات', 'Antifungals'],
        ['خافضات الدهون', 'Lipid-lowering agents'], ['مضادات التخثر', 'Anticoagulants'],
        ['مدرات البول', 'Diuretics'], ['مسكنات ألم', 'Analgesics'],
        ['أدوية الجهاز الهضمي', 'GI Medications'], ['فيتامينات ومكملات', 'Vitamins & Supplements'],
        ['مراهم وكريمات', 'Ointments & Creams'], ['أدوية أخرى', 'Other Medications'],
    ];
    $stmt = $conn->prepare("INSERT INTO medication_categories (name_ar, name_en) VALUES (?, ?)");
    foreach ($default_cats as $mc) { $stmt->bind_param('ss', $mc[0], $mc[1]); $stmt->execute(); }
    echo "✅ Default medication categories inserted.<br>";
}

// Default lab test categories
if ($conn->query("SELECT COUNT(*) as cnt FROM lab_test_categories")->fetch_assoc()['cnt'] === 0) {
    $default_lc = [
        ['سكر وهرمونات', 'Blood Sugar & Hormones', 1], ['دهون الدم', 'Lipid Profile', 2],
        ['وظائف الكلى', 'Kidney Function', 3], ['وظائف الكبد', 'Liver Function', 4],
        ['صورة الدم الكاملة', 'CBC', 5], ['تجلط الدم', 'Coagulation', 6],
        ['الهرمونات', 'Hormones', 7], ['أملاح ومعادن', 'Electrolytes & Minerals', 8],
        ['تحليل بول', 'Urinalysis', 9], ['ميكروبيولوجيا', 'Microbiology', 10],
        ['فحوصات أخرى', 'Other Tests', 11],
    ];
    $stmt = $conn->prepare("INSERT INTO lab_test_categories (name_ar, name_en, sort_order) VALUES (?, ?, ?)");
    foreach ($default_lc as $lc) { $stmt->bind_param('ssi', $lc[0], $lc[1], $lc[2]); $stmt->execute(); }
    echo "✅ Default lab test categories inserted.<br>";
}

// Default lab test types
if ($conn->query("SELECT COUNT(*) as cnt FROM lab_test_types")->fetch_assoc()['cnt'] === 0) {
    $cats = []; $r = $conn->query("SELECT category_id, name_ar FROM lab_test_categories");
    while ($row = $r->fetch_assoc()) { $cats[$row['name_ar']] = $row['category_id']; }
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
        if ($cat_id) { $stmt->bind_param('isssdd', $cat_id, $t[1], $t[2], $t[3], $t[4], $t[5]); $stmt->execute(); }
    }
    echo "✅ Default lab test types inserted.<br>";
}

// Default lookup options
if ($conn->query("SELECT COUNT(*) as cnt FROM lookup_options")->fetch_assoc()['cnt'] === 0) {
    $default_options = [
        ['marital_status', 'أعزب/عزباء', 'Single'], ['marital_status', 'متزوج/ة', 'Married'],
        ['marital_status', 'مطلق/ة', 'Divorced'], ['marital_status', 'أرمل/ة', 'Widowed'],
        ['visit_reason', 'متابعة', 'Follow-up'], ['visit_reason', 'شكوى محددة', 'Specific complaint'],
        ['visit_reason', 'طوارئ', 'Emergency'], ['visit_reason', 'مراجعة', 'Review'],
        ['nationality', 'سعودي', 'Saudi'], ['nationality', 'مصري', 'Egyptian'],
        ['nationality', 'سوداني', 'Sudanese'], ['nationality', 'يمني', 'Yemeni'],
        ['nationality', 'سوري', 'Syrian'], ['nationality', 'أردني', 'Jordanian'],
        ['nationality', 'هندي', 'Indian'], ['nationality', 'باكستاني', 'Pakistani'],
        ['nationality', 'فلبيني', 'Filipino'], ['nationality', 'أندونيسي', 'Indonesian'],
        ['nationality', 'أخرى', 'Other'],
        ['occupation', 'موظف حكومي', 'Government employee'], ['occupation', 'موظف قطاع خاص', 'Private sector'],
        ['occupation', 'طالب', 'Student'], ['occupation', 'متقاعد', 'Retired'],
        ['occupation', 'رب/ربة منزل', 'Housewife'], ['occupation', 'أخرى', 'Other'],
    ];
    $stmt = $conn->prepare("INSERT INTO lookup_options (category, option_value_ar, option_value_en) VALUES (?, ?, ?)");
    foreach ($default_options as $o) { $stmt->bind_param('sss', $o[0], $o[1], $o[2]); $stmt->execute(); }
    echo "✅ Default lookup options inserted.<br>";
}

// Default role permissions
if ($conn->query("SELECT COUNT(*) as cnt FROM role_permissions")->fetch_assoc()['cnt'] === 0) {
    $all_modules = ['patients', 'visits', 'appointments', 'assessments', 'lab_tests',
        'medications', 'analytics', 'reports', 'education', 'settings', 'users', 'backup', 'ai'];
    $roles = ['super_admin', 'admin', 'doctor', 'medical_assistant', 'nurse'];
    $perms = [
        'super_admin' => [1,1,1,1], 'admin' => [1,1,1,0], 'doctor' => [1,1,1,0],
        'medical_assistant' => [1,1,0,0], 'nurse' => [1,0,0,0],
    ];
    $stmt = $conn->prepare("INSERT INTO role_permissions (role, page_module, can_view, can_create, can_edit, can_delete) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($roles as $role) {
        $p = $perms[$role];
        foreach ($all_modules as $module) {
            $restricted = in_array($module, ['settings', 'users', 'backup']) && !in_array($role, ['super_admin', 'admin']);
            if ($restricted) { $stmt->bind_param('ssiii', $role, $module, 0, 0, 0, 0); }
            else { $stmt->bind_param('ssiii', $role, $module, $p[0], $p[1], $p[2], $p[3]); }
            $stmt->execute();
        }
    }
    echo "✅ Default role permissions inserted.<br>";
}

// Default auto instructions
if ($conn->query("SELECT COUNT(*) as cnt FROM auto_instructions")->fetch_assoc()['cnt'] === 0) {
    $default_instructions = [
        ['تعليمات عامة لمرضى السكري', '• قياس السكري يومياً\n• الالتزام بمواعيد الأدوية\n• مراجعة الطبيب بانتظام\n• ممارسة المشي 30 دقيقة يومياً\n• شرب كمية كافية من الماء', 'عام', null, null, null, null, null, null, null],
        ['تعليمات عند ارتفاع السكر (HbA1c > 7%)', '• مراجعة الطبيب لتعديل الجرعات\n• الإكثار من شرب الماء\n• تقليل النشويات والسكريات\n• قياس السكر 4 مرات يومياً', 'أدوية', null, 7, null, null, null, null, null],
        ['تعليمات العناية بالقدم (بدون جروح)', '• فحص القدم يومياً\n• غسل القدمين بماء دافئ\n• ترطيب القدمين مع تجنب بين الأصابع\n• قص الأظافر بشكل مستقيم', 'عناية قدم', null, null, null, 0, 1, 0, null],
        ['تعليمات عند وجود جرح (Wagner ≥ 2)', '• مراجعة العيادة فوراً\n• عدم المشي على القدم المصابة\n• تنظيف الجرح بمحلول ملحي\n• تغيير الغيار يومياً\n• مراقبة علامات الالتهاب', 'عناية قدم', null, null, null, 2, null, 1, null],
        ['تعليمات غذائية للنوع الثاني', '• تقسيم الوجبات إلى 3 رئيسية + 2 خفيفة\n• تقليل الأرز والخبز الأبيض\n• الإكثار من الخضروات الورقية\n• تجنب المشروبات المحلاة', 'تغذية', 'النوع الثاني (Type 2)', null, null, null, null, null, null],
        ['تعليمات للمدخنين', '• التدخين يزيد خطر البتر بنسبة 400%\n• الإقلاع عن التدخين ضروري\n• التواصل مع عيادة مكافحة التدخين', 'عام', null, null, null, null, null, null, 'مدخن'],
        ['تعليمات حالات الطوارئ', 'عند هبوط السكر (<70): تناول 3 حبات سكر أو عصير فواكه، قياس بعد 15 دقيقة\nعند ارتفاع السكر (>300): شرب ماء بكثرة، قياس الكيتون، التوجه للطوارئ', 'طوارئ', null, null, null, null, null, null, null],
        ['تعليمات التمارين الرياضية', '• المشي 30 دقيقة يومياً (5 أيام في الأسبوع)\n• قياس السكر قبل وبعد التمرين\n• حمل قطعة سكر أثناء التمرين', 'تمارين', null, null, null, 0, 1, 0, null],
    ];
    $stmt = $conn->prepare("INSERT INTO auto_instructions (title_ar, content_ar, category, diabetes_type, min_hba1c, max_hba1c, min_wagner, max_wagner, has_wound, smoking_status, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    foreach ($default_instructions as $inst) {
        $stmt->bind_param('ssssddddds', $inst[0], $inst[1], $inst[2], $inst[3], $inst[4], $inst[5], $inst[6], $inst[7], $inst[8], $inst[9]);
        $stmt->execute();
    }
    echo "✅ Default auto instructions inserted.<br>";
}

echo "<br><hr><h3>✅ Installation Complete!</h3>";
echo "<p><a href='index.php'>Go to Login Page</a></p>";
echo "<p style='color:red;'><strong>⚠️ IMPORTANT: Delete or rename install.php after installation for security!</strong></p>";

$conn->close();
?>
