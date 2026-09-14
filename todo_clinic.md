# 🏥 نظام عيادة السكري — خطة البناء الكاملة (بدون إطارات)
# Diabetes Clinic Management System — Full Build Plan (No Frameworks)

> **المصدر:** `app/clinic.html` + `app/ToDo.md`
> **التقنيات:** PHP خام، MySQL، JavaScript خام، CSS خام، Canvas API
> **الهدف:** نظام متكامل لإدارة مرضى السكري وتتبع جروح القدم مع تحليلات ذكية

---

## 📑 فهرس المحتويات
1. [قاعدة البيانات (SQL كامل)](#1-قاعدة-البيانات-sql-كامل)
2. [هيكل المشروع الكامل](#2-هيكل-المشروع-الكامل)
3. [نظام تسجيل الدخول](#3-نظام-تسجيل-الدخول)
4. [وحدة إدارة المرضى](#4-وحدة-إدارة-المرضى)
5. [وحدة الزيارات والسجلات الطبية](#5-وحدة-الزيارات-والسجلات-الطبية)
6. [وحدة تقييم القدم](#6-وحدة-تقييم-القدم)
7. [محرك التحليلات الذكي](#7-محرك-التحليلات-الذكي)
8. [وحدة تثقيف المريض](#8-وحدة-تثقيف-المريض)
9. [التقارير والطباعة](#9-التقارير-والطباعة)
10. [واجهات API (AJAX)](#10-واجهات-api-ajax)
11. [الأمان](#11-الأمان)
12. [جدول التنفيذ](#12-جدول-التنفيذ)

---

## 1. قاعدة البيانات (SQL كامل)

### 1.1 إنشاء قاعدة البيانات

```sql
CREATE DATABASE IF NOT EXISTS clinic_diabetes
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE clinic_diabetes;
```

### 1.2 جدول المستخدمين (users)

```sql
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,          -- bcrypt hash
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'doctor', 'nurse', 'receptionist') NOT NULL DEFAULT 'receptionist',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.3 جدول المرضى (patients)

```sql
CREATE TABLE patients (
    patient_id INT AUTO_INCREMENT PRIMARY KEY,
    file_number VARCHAR(20) NOT NULL UNIQUE,       -- XXXX-YYYY
    full_name VARCHAR(150) NOT NULL,
    date_of_birth DATE,
    age INT,                                        -- يُحسب تلقائياً
    gender ENUM('ذكر', 'أنثى') NOT NULL,
    phone_primary VARCHAR(20),
    phone_secondary VARCHAR(20),
    identity_number VARCHAR(20),                    -- رقم الهوية/الإقامة
    nationality VARCHAR(50),
    marital_status ENUM('أعزب/عزباء', 'متزوج/ة', 'مطلق/ة', 'أرمل/ة'),
    occupation VARCHAR(100),
    address TEXT,
    city VARCHAR(50),
    distance_from_center DECIMAL(5,1),              -- المسافة من المركز (كم)
    first_visit_date DATE,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    photo VARCHAR(255),
    created_by INT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.4 جدول التاريخ الطبي (medical_history)

```sql
CREATE TABLE medical_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    diabetes_type ENUM('النوع الأول (Type 1)', 'النوع الثاني (Type 2)',
                       'سكري الحمل (GDM)', 'MODY', 'غير محدد'),
    diagnosis_year YEAR,
    duration_years INT,                             -- مدة الإصابة
    diagnosis_method SET('أعراض كلاسيكية', 'FPG ≥ 126', 'HbA1c ≥ 6.5%',
                         'OGTT', 'مصادفة أثناء فحص آخر'),
    companion_name VARCHAR(100),                    -- مرافق المريض
    family_history SET('أب/أم', 'أخ/أخت', 'أجداد', 'لا يوجد'),
    smoking_status ENUM('لا', 'مدخن', 'سابق'),
    physical_activity ENUM('لا يوجد', 'خفيف', 'معتدل', 'منتظم'),
    has_hypertension TINYINT(1) DEFAULT 0,
    has_kidney_disease TINYINT(1) DEFAULT 0,
    has_eye_retinopathy TINYINT(1) DEFAULT 0,
    has_eye_cataract TINYINT(1) DEFAULT 0,          -- موية بيضاء
    has_eye_glaucoma TINYINT(1) DEFAULT 0,           -- موية زرقاء
    other_chronic_diseases TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.5 جدول الزيارات (visits)

```sql
CREATE TABLE visits (
    visit_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    visit_number INT NOT NULL DEFAULT 1,            -- رقم الزيارة تسلسلي
    visit_date DATE NOT NULL,
    visit_reason ENUM('متابعة', 'شكوى محددة', 'طوارئ', 'مراجعة'),
    chief_complaint TEXT,                            -- الشكوى الرئيسية
    doctor_notes TEXT,
    treatment_plan TEXT,
    next_review_date DATE,
    created_by INT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.6 جدول العلامات الحيوية (vital_signs)

```sql
CREATE TABLE vital_signs (
    vital_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    weight DECIMAL(5,1),                            -- كغم
    height INT,                                     -- سم
    bmi DECIMAL(4,1),                               -- يُحسب تلقائياً
    blood_pressure_systolic INT,                    -- الضغط الانقباضي
    blood_pressure_diastolic INT,                   -- الضغط الانبساطي
    blood_pressure_text VARCHAR(10),                 -- نص مثل "120/80"
    waist_circumference DECIMAL(5,1),               -- سم
    temperature DECIMAL(4,1),                        -- °م
    heart_rate INT,
    oxygen_saturation DECIMAL(4,1),
    notes TEXT,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.7 جدول قراءات سكر الدم (blood_sugar_readings)

```sql
CREATE TABLE blood_sugar_readings (
    reading_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    fpg_value DECIMAL(5,1),                         -- سكر الصيام mg/dL
    fpg_date DATE,
    ppg_value DECIMAL(5,1),                         -- سكر بعد الأكل mg/dL
    ppg_date DATE,
    hba1c_value DECIMAL(4,1),                       -- HbA1c %
    hba1c_date DATE,
    random_sugar_value DECIMAL(5,1),
    random_sugar_date DATE,
    lab_device VARCHAR(100),
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.8 جدول العلاج الحالي (treatments)

```sql
CREATE TABLE treatments (
    treatment_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    treatment_type SET('حمية غذائية فقط', 'أدوية فموية (OADs)', 'أنسولين',
                       'أنسولين + أدوية فموية', 'مضخة أنسولين (Pump)', 'GLP-1 RA'),
    oral_meds_details TEXT,                          -- الأسماء والجرعات
    insulin_details TEXT,                             -- النوع والجرعة
    other_meds TEXT,                                  -- أدوية أخرى
    antibiotics_oral TEXT,                            -- مضادات حيوية فموية
    antibiotics_iv TEXT,                              -- مضادات حيوية وريدية
    antibiotics_duration VARCHAR(50),
    topical_ointments TEXT,                           -- مراهم موضعية
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.9 جدول المضاعفات (complications)

```sql
CREATE TABLE complications (
    complication_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    has_retinopathy TINYINT(1) DEFAULT 0,
    has_nephropathy TINYINT(1) DEFAULT 0,
    has_neuropathy TINYINT(1) DEFAULT 0,
    has_cad TINYINT(1) DEFAULT 0,                    -- أمراض القلب التاجية
    has_cva TINYINT(1) DEFAULT 0,                    -- الأوعية الدماغية
    has_pad TINYINT(1) DEFAULT 0,                    -- الأوعية الطرفية
    diabetic_foot ENUM('لا يوجد', 'نعم', 'تاريخ سابق'),
    hypoglycemia_severity ENUM('لا', 'نادرة', 'متكررة'),
    notes TEXT,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.10 جدول الفحوصات المخبرية (lab_results)

```sql
CREATE TABLE lab_results (
    lab_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    total_cholesterol DECIMAL(5,1),                  -- mg/dL
    ldl DECIMAL(5,1),                                -- mg/dL
    hdl DECIMAL(5,1),                                -- mg/dL
    triglycerides DECIMAL(5,1),                      -- mg/dL
    creatinine DECIMAL(4,2),                         -- mg/dL
    egfr INT,                                        -- mL/min/1.73m²
    microalbumin DECIMAL(6,1),                       -- mg/g Cr
    tsh DECIMAL(5,2),                                -- mIU/L
    test_date DATE,
    other_labs TEXT,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.11 جدول تقييم القدم السريري (foot_assessments)

```sql
CREATE TABLE foot_assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    -- القدم اليمنى
    right_sensation ENUM('طبيعي', 'منخفض', 'معدوم', 'متناقض'),
    right_pulse ENUM('طبيعي متوسط', 'ضعيف', 'معدوم'),
    right_deformities SET('قدم مخلبية', 'قدم مسطحة', 'بيس كافوس',
                          'أصابع مزدحمة', 'شاركوت', 'أصابع مطرقة',
                          'ثفنات', 'عظام مشط بارزة'),
    -- القدم اليسرى
    left_sensation ENUM('طبيعي', 'منخفض', 'معدوم', 'متناقض'),
    left_pulse ENUM('طبيعي متوسط', 'ضعيف', 'معدوم'),
    left_deformities SET('قدم مخلبية', 'قدم مسطحة', 'بيس كافوس',
                         'أصابع مزدحمة', 'شاركوت', 'أصابع مطرقة',
                         'ثفنات', 'عظام مشط بارزة'),
    -- القياسات
    abpi_right DECIMAL(4,2),                         -- مؤشر ضغط الكاحل
    abpi_left DECIMAL(4,2),
    wagner_grade TINYINT,                             -- 0-5
    notes TEXT,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.12 جدول جروح القدم (foot_ulcers)

```sql
CREATE TABLE foot_ulcers (
    ulcer_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    cause VARCHAR(200),                               -- سبب الإصابة
    initial_cause ENUM('طعنة شوكة', 'طعنة دبوس', 'ضربة', 'تليف وخشونة',
                       'حذاء جديد', 'بقاقة', 'حرق', 'غير معروف'),
    patient_reaction VARCHAR(200),                    -- ردة فعل المريض
    first_practitioner ENUM('ممرض', 'طبيب عمومي', 'جراح', 'جهة غير صحية'),
    wound_condition ENUM('نظيفة', 'متسخة', 'صديد', 'سوداء', 'تحوي جسم غريب'),
    wound_depth ENUM('سطحي في الجلد', 'الجلد وتحت الجلد', 'العضلات', 'العظم'),
    wound_location_x DECIMAL(5,2),                    -- إحداثيات الرسم (0-100)
    wound_location_y DECIMAL(5,2),
    wound_foot ENUM('يمنى', 'يسرى'),
    wound_size_cm2 DECIMAL(5,1),
    wound_image VARCHAR(255),                          -- مسار الصورة المرفوعة
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.13 جدول النتائج والمتابعة (outcomes)

```sql
CREATE TABLE outcomes (
    outcome_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    improvement_percentage TINYINT,                    -- 0, 25, 50, 75, 100
    improvement_date DATE,
    healing_date DATE,                                 -- تاريخ الشفاء الكامل
    -- البتر السابق
    previous_amputation ENUM('لا', 'فوق الركبة', 'تحت الركبة',
                             'فوق الكاحل', 'تحت الكاحل', 'بتر رايس', 'إصبع'),
    previous_amputation_date DATE,
    previous_amputation_location VARCHAR(50),
    -- البتر الحالي
    current_amputation ENUM('لا', 'فوق الركبة', 'تحت الركبة',
                            'فوق الكاحل', 'تحت الكاحل', 'بتر رايس', 'إصبع'),
    current_amputation_date DATE,
    -- التحويل
    referral_to VARCHAR(100),
    referral_reason TEXT,
    -- الوفاة
    is_deceased TINYINT(1) DEFAULT 0,
    death_date DATE,
    death_cause ENUM('مضاعفات قدم السكري', 'سبب آخر'),
    death_cause_detail TEXT,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.14 جدول خطة العناية (care_plan)

```sql
CREATE TABLE care_plan (
    care_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id INT NOT NULL UNIQUE,
    care_type SET('تقليم أظافر', 'نظافة قدم', 'ترطيب قدم'),
    dressing_frequency ENUM('1', '2-3', '4 فأكثر'),     -- مرات الغيار في الأسبوع
    offloading_needed TINYINT(1) DEFAULT 0,
    offloading_type VARCHAR(100),
    dietary_plan TEXT,
    emergency_instructions TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 1.15 جدول جلسات العناية (care_sessions)

```sql
CREATE TABLE care_sessions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 2. هيكل المشروع الكامل

```
clinic-system/
│
├── index.php                          # صفحة تسجيل الدخول
├── logout.php                         # تسجيل الخروج
├── .htaccess                          # إعادة التوجيه والأمان
│
├── config/
│   ├── database.php                   # اتصال قاعدة البيانات
│   ├── session.php                    # إدارة الجلسات
│   ├── helpers.php                    # الدوال المساعدة
│   └── constants.php                  # الثوابت (مسارات، إعدادات)
│
├── assets/
│   ├── css/
│   │   ├── style.css                  # النمط الرئيسي (~2000 سطر)
│   │   ├── rtl.css                    # تحسينات RTL
│   │   ├── print.css                  # أنماط الطباعة
│   │   └── animations.css            # الحركات والانتقالات
│   │
│   ├── js/
│   │   ├── main.js                    # الدوال الأساسية (toast, modal, loader)
│   │   ├── forms.js                   # معالجة النماذج (validation, AJAX)
│   │   ├── chart.js                   # رسم المخططات (Canvas API)
│   │   ├── foot-canvas.js            # رسم تفاعلي للقدم
│   │   └── search.js                  # بحث حي
│   │
│   ├── images/
│   │   ├── logo.svg                   # شعار المركز
│   │   ├── foot-right.svg            # رسم القدم اليمنى
│   │   ├── foot-left.svg             # رسم القدم اليسرى
│   │   └── default-avatar.png
│   │
│   └── fonts/
│       ├── Tajawal-Regular.ttf
│       ├── Tajawal-Bold.ttf
│       ├── Tajawal-Black.ttf
│       ├── Cairo-Regular.ttf
│       └── Cairo-Bold.ttf
│
├── includes/
│   ├── header.php                     # <head> + بداية <body>
│   ├── navbar.php                     # شريط التنقل العلوي
│   ├── sidebar.php                    # القائمة الجانبية
│   ├── footer.php                     # نهاية <body> + السكريبتات
│   └── auth_check.php                 # التحقق من تسجيل الدخول
│
├── modules/
│   ├── dashboard.php                  # لوحة التحكم الرئيسية
│   │
│   ├── patients/
│   │   ├── index.php                  # قائمة المرضى
│   │   ├── add.php                    # إضافة مريض
│   │   ├── view.php                   # عرض ملف المريض
│   │   ├── edit.php                   # تعديل بيانات المريض
│   │   ├── delete.php                 # حذف (تعطيل) المريض
│   │   └── search.php                 # بحث AJAX
│   │
│   ├── visits/
│   │   ├── index.php                  # قائمة الزيارات لمريض
│   │   ├── add.php                    # إنشاء زيارة جديدة
│   │   ├── view.php                   # عرض تفاصيل الزيارة
│   │   ├── medical_history.php        # التاريخ الطبي
│   │   ├── vitals.php                 # العلامات الحيوية
│   │   ├── blood_sugar.php            # قراءات سكر الدم
│   │   ├── treatments.php             # العلاج الحالي
│   │   ├── complications.php          # المضاعفات
│   │   └── lab_results.php            # الفحوصات المخبرية
│   │
│   ├── assessments/
│   │   ├── foot_exam.php              # فحص القدم السريري
│   │   ├── ulcer.php                  # تقييم الجرح
│   │   ├── outcomes.php               # النتائج والمتابعة
│   │   └── care_plan.php              # خطة العناية
│   │
│   ├── analytics/
│   │   ├── dashboard.php              # لوحة التحليلات
│   │   ├── risk_alerts.php            # تنبيهات الخطر
│   │   ├── progress.php               # تتبع التقدم
│   │   └── statistics.php             # إحصائيات العيادة
│   │
│   ├── education/
│   │   ├── home_care.php              # تعليمات العناية المنزلية
│   │   ├── diet_plan.php              # خطة النظام الغذائي
│   │   └── emergency.php              # تعليمات الطوارئ
│   │
│   ├── reports/
│   │   ├── visit_summary.php          # ملخص الزيارة (طباعة)
│   │   ├── patient_report.php         # تقرير المريض الكامل
│   │   └── monthly_report.php         # التقرير الشهري
│   │
│   └── users/
│       ├── index.php                  # إدارة المستخدمين
│       ├── add.php
│       └── profile.php
│
├── api/
│   ├── patients.php                   # API المرضى
│   ├── visits.php                     # API الزيارات
│   ├── vitals.php                     # API العلامات الحيوية
│   ├── search.php                     # API البحث
│   └── analytics.php                  # API التحليلات
│
├── uploads/
│   ├── photos/                        # صور المرضى
│   ├── wounds/                        # صور الجروح
│   └── reports/                       # التقارير المولدة
│
└── install.php                        # مثبت النظام (مرة واحدة)
```

---

## 3. نظام تسجيل الدخول

### 3.1 ملف: `config/database.php`

```php
<?php
// اتصال MySQLi مع دعم UTF-8 الكامل
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'clinic_diabetes';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    die('فشل الاتصال بقاعدة البيانات');
}
$mysqli->set_charset('utf8mb4');
$mysqli->query("SET NAMES utf8mb4");
$mysqli->query("SET character_set_client = 'utf8mb4'");
$mysqli->query("SET character_set_results = 'utf8mb4'");
?>
```

### 3.2 ملف: `config/session.php`

```php
<?php
// بدء جلسة آمنة
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_after'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function login_user(int $user_id, string $role, string $full_name): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['role'] = $role;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
}

function logout_user(): void {
    $_SESSION = [];
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

// التحقق من انتهاء الجلسة (30 دقيقة)
if (is_logged_in() && (time() - $_SESSION['login_time'] > 1800)) {
    logout_user();
}
?>
```

### 3.3 ملف: `config/helpers.php`

```php
<?php
function sanitize_input($data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function escape_output(string $data): string {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function calculate_bmi(float $weight, int $height): float {
    if ($height <= 0) return 0;
    $height_m = $height / 100;
    return round($weight / ($height_m * $height_m), 1);
}

function calculate_age(string $dob): int {
    $birth = new DateTime($dob);
    $now = new DateTime();
    return $now->diff($birth)->y;
}

function get_arabic_date(string $date): string {
    $months = [
        'January' => 'يناير', 'February' => 'فبراير', 'March' => 'مارس',
        'April' => 'أبريل', 'May' => 'مايو', 'June' => 'يونيو',
        'July' => 'يوليو', 'August' => 'أغسطس', 'September' => 'سبتمبر',
        'October' => 'أكتوبر', 'November' => 'نوفمبر', 'December' => 'ديسمبر'
    ];
    $en_date = date('F d, Y', strtotime($date));
    return str_replace(array_keys($months), array_values($months), $en_date);
}

function upload_file(array $file, string $target_dir, array $allowed_types = ['jpg', 'jpeg', 'png', 'gif']): string {
    // إنشاء المجلد إذا لم يكن موجوداً
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_types)) {
        throw new Exception('نوع الملف غير مسموح');
    }

    $new_name = uniqid() . '.' . $ext;
    $target = $target_dir . '/' . $new_name;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception('فشل رفع الملف');
    }

    return $new_name;
}
?>
```

### 3.4 ملف: `index.php` (صفحة تسجيل الدخول)

**الوظائف:**
- نموذج تسجيل دخول (البريد الإلكتروني، كلمة المرور)
- التحقق من البيانات باستخدام `password_verify()`
- إنشاء جلسة آمنة
- إعادة التوجيه إلى لوحة التحكم
- تذكرني (cookie اختياري)
- رسائل خطأ متحركة
- تصميم زجاجي مع خلفية متدرجة داكنة مثل `www/index.php`

**جافاسكريبت:**
- togglePassword(): إظهار/إخفاء كلمة المرور
- تحريك النموذج عند الدخول
- التحقق من صحة الإدخال قبل الإرسال

---

## 4. وحدة إدارة المرضى

### 4.1 ملف: `modules/patients/add.php`

**وظائف PHP:**
```php
// استقبال بيانات النموذج
// توليد رقم ملف تلقائي: YYYY-XXXX (السنة-رقم تسلسلي)
function generate_file_number(): string {
    global $mysqli;
    $year = date('Y');
    $result = $mysqli->query("SELECT COUNT(*) as cnt FROM patients WHERE YEAR(created_at) = $year");
    $row = $result->fetch_assoc();
    $next = $row['cnt'] + 1;
    return $year . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
}

// حفظ بيانات المريض
$stmt = $mysqli->prepare("INSERT INTO patients (...) VALUES (...)");
$stmt->bind_param('sssisssssssssi', ...);
$stmt->execute();
```

**حقول النموذج (مطابقة clinic.html القسم 1):**
| الحقل | النوع | الحقل في DB |
|-------|-------|-------------|
| الاسم الكامل | text | full_name |
| رقم الملف | text (تلقائي) | file_number |
| تاريخ الميلاد | date | date_of_birth |
| العمر | text (تلقائي) | age |
| الجنس | select | gender |
| رقم الهاتف | tel | phone_primary |
| رقم الهاتف 2 | tel | phone_secondary |
| رقم الهوية | text | identity_number |
| الجنسية | text | nationality |
| الحالة الاجتماعية | select | marital_status |
| المهنة | text | occupation |
| تاريخ الزيارة | date | first_visit_date |
| العنوان | text | address |
| المسافة من المركز | number | distance_from_center |

### 4.2 ملف: `modules/patients/index.php` (قائمة المرضى)

**وظائف PHP:**
```php
// جلب جميع المرضى النشطين مع pagination
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$query = "SELECT p.*, 
          (SELECT COUNT(*) FROM visits v WHERE v.patient_id = p.patient_id) as visit_count,
          (SELECT MAX(visit_date) FROM visits v WHERE v.patient_id = p.patient_id) as last_visit
          FROM patients p 
          WHERE p.is_active = 1 
          ORDER BY p.created_at DESC 
          LIMIT ? OFFSET ?";
```

**وظائف JavaScript:**
```javascript
// بحث حي باسم المريض
function searchPatients(query) {
    fetch(`/api/search.php?q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => renderPatientTable(data));
}

// تصفية حسب نوع السكري
function filterByType(type) { ... }

// ترتيب الأعمدة
function sortTable(column) { ... }
```

### 4.3 ملف: `modules/patients/view.php` (عرض ملف المريض)

**الأقسام المعروضة:**
1. بطاقة المريض (البيانات الشخصية)
2. التاريخ الطبي
3. قائمة الزيارات (جدول زمني)
4. آخر العلامات الحيوية
5. آخر قراءات السكر
6. ملخص المضاعفات
7. تقييمات القدم (آخر 5)
8. رسم بياني لتطور HbA1c عبر الزمن
9. أزرار: طباعة، تعديل، زيارة جديدة

---

## 5. وحدة الزيارات والسجلات الطبية

### 5.1 ملف: `modules/visits/add.php`

**سير العمل:**
1. اختيار المريض (أو تمرير patient_id من صفحة المريض)
2. إدخال تاريخ الزيارة وسببها
3. حفظ الزيارة → الحصول على visit_id
4. استخدام visit_id في باقي النماذج (vitals, blood sugar, etc.)

**رسم بياني لسير الزيارة:**
```
start → حدد المريض → أنشئ زيارة → → → 
                                        ↓
                       ┌──────────────────────────────────┐
                       │  tabs:                           │
                       │  [التاريخ الطبي] [العلامات] [سكر] │
                       │  [العلاج] [المضاعفات] [مختبر]     │
                       │  [فحص القدم] [الجرح] [العناية]    │
                       └──────────────────────────────────┘
                                        ↓
                                 حفظ كل قسم → ← →
                                        ↓
                                  عرض ملخص الزيارة
                                        ↓
                                   [طباعة] [إنهاء]
```

### 5.2 ملف: `modules/visits/vitals.php`

**وظائف JavaScript:**
```javascript
// حساب BMI تلقائياً
function calculateBMI() {
    const weight = document.getElementById('weight').value;
    const height = document.getElementById('height').value;
    if (weight && height) {
        const bmi = weight / Math.pow(height / 100, 2);
        document.getElementById('bmi').value = bmi.toFixed(1);
        // عرض تصنيف BMI
        showBMIClassification(bmi);
    }
}

// تصنيف BMI
function showBMIClassification(bmi) {
    let classification = '';
    let color = '';
    if (bmi < 18.5)      { classification = 'نقص وزن'; color = '#f59e0b'; }
    else if (bmi < 25)   { classification = 'طبيعي'; color = '#10b981'; }
    else if (bmi < 30)   { classification = 'زيادة وزن'; color = '#f97316'; }
    else                  { classification = 'سمنة'; color = '#ef4444'; }
    // عرض التصنيف
}
```

### 5.3 ملف: `modules/visits/blood_sugar.php`

**الجداول مطابقة clinic.html:**
- FPG (سكر الصيام) → القيمة، التاريخ، المختبر
- PPG (بعد الأكل) → القيمة، التاريخ، المختبر
- HbA1c → القيمة (%)، التاريخ، المختبر
- سكر عشوائي → القيمة، التاريخ

**JavaScript للتحليل اللحظي:**
```javascript
// تحليل قراءات السكر وعرض التقييم
function analyzeBloodSugar() {
    const hba1c = parseFloat(document.getElementById('hba1c').value);
    if (hba1c) {
        let status = '', color = '';
        if (hba1c < 5.7)      { status = 'طبيعي'; color = '#10b981'; }
        else if (hba1c < 6.5) { status = 'مقدمات السكري'; color = '#f59e0b'; }
        else                  { status = 'سكري'; color = '#ef4444'; }
        showAlert(`HbA1c: ${hba1c}% — ${status}`, color);
    }
}
```

### 5.4 ملف: `modules/visits/lab_results.php`

| الفحص | النطاق الطبيعي | وحدة |
|-------|---------------|------|
| الكوليسترول الكلي | < 200 | mg/dL |
| LDL | < 100 | mg/dL |
| HDL | > 40 (رجال), > 50 (نساء) | mg/dL |
| الدهون الثلاثية | < 150 | mg/dL |
| Creatinine | 0.6-1.2 | mg/dL |
| eGFR | > 90 | mL/min |
| Microalbumin | < 30 | mg/g |
| TSH | 0.4-4.0 | mIU/L |

**JavaScript لتلوين القيم غير الطبيعية:**
```javascript
function highlightAbnormalValues() {
    // لكل حقل، تحقق من القيمة مقابل النطاق الطبيعي
    const ranges = {
        total_cholesterol: { min: 0, max: 200 },
        ldl: { min: 0, max: 100 },
        hdl: { min: 40, max: 100 },
        triglycerides: { min: 0, max: 150 },
        creatinine: { min: 0.6, max: 1.2 },
        egfr: { min: 90, max: 999 },
        tsh: { min: 0.4, max: 4.0 }
    };
    // لون أحمر للقيم خارج النطاق
}
```

---

## 6. وحدة تقييم القدم

### 6.1 ملف: `modules/assessments/foot_exam.php`

**نموذج فحص القدم (يمين/يسار):**
```
┌──────────────────────────────────────┐
│  فحص القدم السريري                    │
│                                      │
│  ┌──────────┐  ┌──────────┐          │
│  │ القدم    │  │ القدم    │          │
│  │ اليمنى   │  │ اليسرى   │          │
│  │          │  │          │          │
│  │ الإحساس: │  │ الإحساس: │          │
│  │ ○ طبيعي  │  │ ○ طبيعي  │          │
│  │ ○ منخفض  │  │ ○ منخفض  │          │
│  │ ○ معدوم  │  │ ○ معدوم  │          │
│  │          │  │          │          │
│  │ النبض:   │  │ النبض:   │          │
│  │ ○ طبيعي  │  │ ○ طبيعي  │          │
│  │ ○ ضعيف   │  │ ○ ضعيف   │          │
│  │ ○ معدوم  │  │ ○ معدوم  │          │
│  └──────────┘  └──────────┘          │
│                                      │
│  ABPI الأيمن: [____]                 │
│  ABPI الأيسر: [____]                 │
│                                      │
│  درجة Wagner: ○0 ○1 ○2 ○3 ○4 ○5     │
└──────────────────────────────────────┘
```

### 6.2 ملف: `modules/assessments/ulcer.php` + `assets/js/foot-canvas.js`

**رسم تفاعلي للقدم باستخدام Canvas:**

```javascript
// foot-canvas.js — رسم تفاعلي للقدم لتحديد مكان الجرح
class FootCanvas {
    constructor(canvasId, footType) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext('2d');
        this.footType = footType; // 'right' or 'left'
        this.markers = [];
        this.scale = 1;
        this.init();
    }

    init() {
        // رسم القدم الأساسية
        this.drawFoot();
        // أحداث الفأرة
        this.canvas.addEventListener('click', (e) => this.handleClick(e));
        this.canvas.addEventListener('mousemove', (e) => this.handleHover(e));
    }

    drawFoot() {
        const ctx = this.ctx;
        const w = this.canvas.width;
        const h = this.canvas.height;

        ctx.clearRect(0, 0, w, h);

        // رسم شكل القدم (مبسط)
        ctx.beginPath();
        if (this.footType === 'right') {
            // شكل القدم اليمنى
            ctx.moveTo(w * 0.3, h * 0.05);  // إصبع القدم الكبير
            ctx.quadraticCurveTo(w * 0.5, h * 0.02, w * 0.5, h * 0.02);
            ctx.quadraticCurveTo(w * 0.7, h * 0.05, w * 0.8, h * 0.15);
            ctx.lineTo(w * 0.85, h * 0.4);
            ctx.lineTo(w * 0.82, h * 0.95);  // الكعب
            ctx.lineTo(w * 0.35, h * 0.95);
            ctx.lineTo(w * 0.3, h * 0.5);
            ctx.lineTo(w * 0.28, h * 0.2);
            ctx.closePath();
        } else {
            // شكل القدم اليسرى (معكوس)
            // ...
        }

        ctx.fillStyle = '#fef3c7';
        ctx.fill();
        ctx.strokeStyle = '#d97706';
        ctx.lineWidth = 2;
        ctx.stroke();

        // إضافة خطوط الأصابع
        // إضافة خطوط مناطق الضغط (الكعب، مشط القدم، الأصابع)
        this.drawAnatomicalRegions();
    }

    drawAnatomicalRegions() {
        // رسم مناطق تشريحية للمساعدة في التحديد
        const regions = [
            { name: 'الأصابع', x: 0.4, y: 0.05, w: 0.3, h: 0.15 },
            { name: 'مشط القدم', x: 0.3, y: 0.2, w: 0.4, h: 0.3 },
            { name: 'قوس القدم', x: 0.35, y: 0.5, w: 0.3, h: 0.2 },
            { name: 'الكعب', x: 0.35, y: 0.75, w: 0.3, h: 0.2 },
        ];
        // رسم الخطوط المتقطعة للمناطق
    }

    handleClick(e) {
        const rect = this.canvas.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width * 100;
        const y = (e.clientY - rect.top) / rect.height * 100;

        // إضافة علامة في موقع النقر
        this.markers.push({ x, y, type: 'ulcer' });
        this.drawMarker(x, y);

        // حفظ الإحداثيات في hidden inputs
        document.getElementById('wound_location_x_' + this.footType).value = x.toFixed(2);
        document.getElementById('wound_location_y_' + this.footType).value = y.toFixed(2);
    }

    drawMarker(x, y) {
        const ctx = this.ctx;
        const w = this.canvas.width;
        const h = this.canvas.height;
        const cx = (x / 100) * w;
        const cy = (y / 100) * h;

        // دائرة حمراء مع وميض
        ctx.beginPath();
        ctx.arc(cx, cy, 8, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(239, 68, 68, 0.8)';
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.stroke();

        // علامة X في المنتصف
        ctx.beginPath();
        ctx.moveTo(cx - 4, cy - 4);
        ctx.lineTo(cx + 4, cy + 4);
        ctx.moveTo(cx + 4, cy - 4);
        ctx.lineTo(cx - 4, cy + 4);
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.stroke();
    }

    clearMarkers() {
        this.markers = [];
        this.drawFoot();
    }
}

// تهيئة الرسم التفاعلي
const rightFoot = new FootCanvas('footCanvasRight', 'right');
const leftFoot = new FootCanvas('footCanvasLeft', 'left');
```

### 6.3 ملف: `modules/assessments/outcomes.php`

**خيارات تقييم تحسن القرحة:**
- [ ] 0% — لا تحسن
- [ ] 25% — تحسن طفيف
- [ ] 50% — تحسن متوسط
- [ ] 75% — تحسن كبير
- [ ] 100% — التئام كامل (تسجيل تاريخ الشفاء)

---

## 7. محرك التحليلات الذكي

### 7.1 ملف: `modules/analytics/risk_alerts.php`

**خوارزمية تصنيف الخطورة (Risk Stratification):**

```php
<?php
function calculate_risk_score($patient_id) {
    global $mysqli;
    $score = 0;
    $alerts = [];

    // جلب أحدث بيانات المريض
    $query = "SELECT 
        mh.smoking_status,
        fa.right_sensation, fa.left_sensation,
        fa.right_pulse, fa.left_pulse,
        fa.wagner_grade,
        bs.hba1c_value,
        fu.wound_condition
    FROM patients p
    LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
    WHERE p.patient_id = ?
    ORDER BY v.visit_date DESC
    LIMIT 1";

    // عوامل الخطر
    // 1. التدخين (+20 نقطة)
    if ($data['smoking_status'] === 'مدخن') {
        $score += 20;
        $alerts[] = '🚬 مريض مدخن — خطر البتر مرتفع';
    }

    // 2. الإحساس معدوم (+25 نقطة)
    if ($data['right_sensation'] === 'معدوم' || $data['left_sensation'] === 'معدوم') {
        $score += 25;
        $alerts[] = '⚠️ فقدان الإحساس في القدم — خطر الإصابة بالقرحة';
    }

    // 3. النبض معدوم (+25 نقطة)
    if ($data['right_pulse'] === 'معدوم' || $data['left_pulse'] === 'معدوم') {
        $score += 25;
        $alerts[] = '🚨 انعدام النبض الطرفي — خطر نقص التروية';
    }

    // 4. HbA1c مرتفع (+15 نقطة لكل 1% فوق 7%)
    if ($data['hba1c_value'] > 7) {
        $extra = ($data['hba1c_value'] - 7) * 15;
        $score += min($extra, 30);
        $alerts[] = '📈 HbA1c مرتفع (' . $data['hba1c_value'] . '%) — السكري غير مضبوط';
    }

    // 5. Wagner ≥ 3 (+30 نقطة)
    if ($data['wagner_grade'] >= 3) {
        $score += 30;
        $alerts[] = '🆘 درجة Wagner ' . $data['wagner_grade'] . ' — خطر بتر مرتفع جداً';
    }

    // 6. جرح متسخ أو به صديد (+15 نقطة)
    if ($data['wound_condition'] === 'متسخة' || $data['wound_condition'] === 'صديد') {
        $score += 15;
        $alerts[] = '🦠 الجرح ملتهب — يحتاج تدخل فوري';
    }

    // تصنيف الخطورة
    $risk_class = 'منخفض';
    $risk_color = '#10b981';
    if ($score >= 100) {
        $risk_class = 'شديد جداً';
        $risk_color = '#dc2626';
    } elseif ($score >= 70) {
        $risk_class = 'مرتفع';
        $risk_color = '#ef4444';
    } elseif ($score >= 40) {
        $risk_class = 'متوسط';
        $risk_color = '#f59e0b';
    }

    return [
        'score' => $score,
        'class' => $risk_class,
        'color' => $risk_color,
        'alerts' => $alerts
    ];
}
?>
```

### 7.2 ملف: `modules/analytics/progress.php`

**تتبع تقدم التئام الجرح:**
```php
<?php
// حساب نسبة التحسن عبر الزيارات
function get_healing_progress($patient_id) {
    global $mysqli;

    $query = "SELECT v.visit_date, o.improvement_percentage
              FROM visits v
              JOIN outcomes o ON v.visit_id = o.visit_id
              WHERE v.patient_id = ?
              ORDER BY v.visit_date ASC";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $dates = [];
    $values = [];
    while ($row = $result->fetch_assoc()) {
        $dates[] = $row['visit_date'];
        $values[] = (int)$row['improvement_percentage'];
    }

    return ['dates' => $dates, 'values' => $values];
}
?>
```

### 7.3 ملف: `assets/js/chart.js`

```javascript
// رسم بياني بتقنية Canvas خالصة (بدون Chart.js)
class ClinicChart {
    constructor(canvasId) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext('2d');
        this.width = this.canvas.width;
        this.height = this.canvas.height;
    }

    // رسم خط بياني
    drawLineChart(labels, values, options = {}) {
        const ctx = this.ctx;
        const padding = 40;
        const chartW = this.width - padding * 2;
        const chartH = this.height - padding * 2;

        ctx.clearRect(0, 0, this.width, this.height);

        // الخلفية
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(0, 0, this.width, this.height);

        // شبكة الخلفية
        ctx.strokeStyle = '#e2e8f0';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 4; i++) {
            const y = padding + (chartH / 4) * i;
            ctx.beginPath();
            ctx.moveTo(padding, y);
            ctx.lineTo(this.width - padding, y);
            ctx.stroke();
        }

        // رسم البيانات
        const maxVal = Math.max(...values, 10);
        const minVal = Math.min(...values, 0);
        const range = maxVal - minVal || 1;

        // نقاط البيانات
        ctx.beginPath();
        values.forEach((val, i) => {
            const x = padding + (chartW / (values.length - 1 || 1)) * i;
            const y = padding + chartH - ((val - minVal) / range) * chartH;
            if (i === 0) ctx.moveTo(x, y);
            else ctx.lineTo(x, y);
        });

        ctx.strokeStyle = options.lineColor || '#0a7e6e';
        ctx.lineWidth = 3;
        ctx.stroke();

        // تعبئة تحت الخط
        const lastIdx = values.length - 1;
        ctx.lineTo(padding + chartW, padding + chartH);
        ctx.lineTo(padding, padding + chartH);
        ctx.closePath();
        ctx.fillStyle = options.fillColor || 'rgba(10, 126, 110, 0.1)';
        ctx.fill();

        // نقاط دائرية
        values.forEach((val, i) => {
            const x = padding + (chartW / (values.length - 1 || 1)) * i;
            const y = padding + chartH - ((val - minVal) / range) * chartH;
            ctx.beginPath();
            ctx.arc(x, y, 5, 0, Math.PI * 2);
            ctx.fillStyle = '#fff';
            ctx.fill();
            ctx.strokeStyle = options.lineColor || '#0a7e6e';
            ctx.lineWidth = 2;
            ctx.stroke();
        });

        // تسميات المحور X
        ctx.fillStyle = '#64748b';
        ctx.font = '12px Tajawal, sans-serif';
        ctx.textAlign = 'center';
        labels.forEach((label, i) => {
            const x = padding + (chartW / (labels.length - 1 || 1)) * i;
            ctx.fillText(label, x, this.height - 10);
        });
    }

    // رسم شريطي
    drawBarChart(labels, values, options = {}) {
        // تنفيذ مشابه...
    }

    // رسم دائري (Pie chart)
    drawPieChart(data, options = {}) {
        const ctx = this.ctx;
        const cx = this.width / 2;
        const cy = this.height / 2;
        const radius = Math.min(cx, cy) - 30;

        const colors = ['#0a7e6e', '#c9a84c', '#ef4444', '#f59e0b', '#3b82f6'];
        const total = data.reduce((a, b) => a + b.value, 0);

        let startAngle = -Math.PI / 2;
        data.forEach((item, i) => {
            const sliceAngle = (item.value / total) * Math.PI * 2;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.arc(cx, cy, radius, startAngle, startAngle + sliceAngle);
            ctx.closePath();
            ctx.fillStyle = colors[i % colors.length];
            ctx.fill();

            // تسمية
            const midAngle = startAngle + sliceAngle / 2;
            const labelX = cx + Math.cos(midAngle) * (radius + 20);
            const labelY = cy + Math.sin(midAngle) * (radius + 20);
            ctx.fillStyle = '#1e293b';
            ctx.font = '12px Tajawal, sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(item.label + ' (' + item.value + ')', labelX, labelY);

            startAngle += sliceAngle;
        });
    }
}
```

### 7.4 لوحة تحكم analytics (dashboard)

**إحصائيات رئيسية:**
- [ ] إجمالي المرضى المسجلين
- [ ] الحالات النشطة (لم تلتئم بعد)
- [ ] الحالات التي تم شفاؤها
- [ ] متوسط درجة Wagner
- [ ] توزيع أنواع السكري (رسم دائري)
- [ ] نسبة البتر (٪)
- [ ] أكثر أسباب الإصابة شيوعاً
- [ ] متوسط سرعة التئام الجروح (بالأيام)
- [ ] رسم بياني لاتجاه HbA1c الشهري

---

## 8. وحدة تثقيف المريض

### 8.1 ملف: `modules/education/home_care.php`

**توليد تعليمات مخصصة بناءً على التقييم:**

```php
<?php
function generate_care_instructions($patient_id) {
    global $mysqli;

    // جلب آخر تقييم
    $query = "SELECT fu.wound_condition, fa.wagner_grade,
              fa.right_sensation, fa.left_sensation
              FROM visits v
              LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
              LEFT JOIN foot_assessments fa ON v.visit_id = fa.assessment_id
              WHERE v.patient_id = ?
              ORDER BY v.visit_date DESC LIMIT 1";

    $instructions = [];

    // تعليمات أساسية للجميع
    $instructions[] = [
        'title' => '📋 الفحص اليومي للقدم',
        'items' => [
            'افحص قدميك يومياً باستخدام مرآة لرؤية باطن القدم',
            'ابحث عن أي جروح، تشققات، احمرار، أو تورم',
            'إذا كنت لا تستطيع الانحناء، اطلب من أحد أفراد الأسرة المساعدة'
        ]
    ];

    // تعليمات إضافية للجروح
    if (has_wound) {
        $instructions[] = [
            'title' => '🩹 العناية بالجرح',
            'items' => [
                'نظف الجرح يومياً بمحلول ملحي معقم',
                'غير الضماد حسب تعليمات الطبيب',
                'لا تمشي حافي القدمين أبداً',
                'اتصل بالعيادة فوراً إذا لاحظت احمراراً أو صديداً'
            ]
        ];
    }

    // تعليمات للمرضى الذين فقدوا الإحساس
    if (no_sensation) {
        $instructions[] = [
            'title' => '👟 العناية بالأحذية',
            'items' => [
                'ارتدِ أحذية طبية مريحة ومناسبة',
                'افحص داخل الحذاء قبل لبسه',
                'تجنب الأحذية الضيقة أو ذات الكعوب العالية',
                'استخدم جوارب قطنية نظيفة يومياً'
            ]
        ];
    }

    return $instructions;
}
?>
```

### 8.2 ملف: `modules/education/diet_plan.php`

**قوالب الأنظمة الغذائية:**
- [ ] نظام غذائي لمرضى السكري (سعرات محسوبة)
- [ ] نظام غذائي منخفض الدهون
- [ ] نظام غذائي منخفض الملح (لمرضى الضغط)
- [ ] جدول الأطعمة المسموحة والممنوعة

### 8.3 ملف: `modules/education/emergency.php`

**تعليمات الطوارئ المطبوعة:**
```
┌────────────────────────────────────────┐
│  🆘 إجراءات الطوارئ                     │
│                                        │
│  إذا حدث جرح أو قطع:                   │
│  1. نظف الجرح بمحلول ملحي              │
│  2. ضع ضمادة معقمة                     │
│  3. اتصل بالعيادة فوراً                 │
│                                        │
│  إذا حدث حرق:                          │
│  1. اغمر القدم في ماء بارد لمدة 10 د   │
│  2. لا تضع ثلجاً مباشرة                │
│  3. راجع الطوارئ فوراً                  │
│                                        │
│  أرقام الطوارئ:                        │
│  📞 العيادة: 05XXXXXXXX                │
│  📞 الإسعاف: 997                       │
└────────────────────────────────────────┘
```

---

## 9. التقارير والطباعة

### 9.1 ملف: `modules/reports/visit_summary.php`

**تقرير قابل للطباعة يتضمن:**
1. رأس الصفحة (شعار المركز، المعلومات)
2. بيانات المريض
3. التاريخ الطبي
4. العلامات الحيوية (جدول)
5. قراءات السكر (جدول)
6. العلاج الحالي
7. تقييم القدم (يمين/يسار)
8. الجروح مع الرسم التفاعلي
9. النتائج والمتابعة
10. توقيع الطبيب
11. توقيع المريض

**CSS للطباعة:**
```css
@media print {
    @page { size: A4; margin: 1.5cm; }
    body { font-family: 'Tajawal', sans-serif; color: #000; }
    .no-print { display: none !important; }
    .page-break { page-break-before: always; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
    th { background: #f0f0f0 !important; }
    .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 10px; color: #666; }
}
```

### 9.2 ملف: `modules/reports/patient_report.php`

**تقرير كامل للمريض:**
- جميع الزيارات مرتبة
- تطور HbA1c عبر الزمن (رسم بياني)
- تطور وزن المريض
- تاريخ الجروح والعمليات
- ملخص العلاج

---

## 10. واجهات API (AJAX)

### 10.1 ملف: `api/search.php`

```php
<?php
// بحث حي عن المرضى
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/session.php';

require_login();

$q = $_GET['q'] ?? '';
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$search = '%' . $mysqli->real_escape_string($q) . '%';
$stmt = $mysqli->prepare("SELECT patient_id, file_number, full_name, phone_primary,
                          DATE_FORMAT(date_of_birth, '%Y-%m-%d') as dob,
                          TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) as age
                          FROM patients
                          WHERE is_active = 1
                          AND (full_name LIKE ? OR file_number LIKE ? OR phone_primary LIKE ?)
                          LIMIT 10");
$stmt->bind_param('sss', $search, $search, $search);
$stmt->execute();
$result = $stmt->get_result();

$patients = [];
while ($row = $result->fetch_assoc()) {
    $patients[] = $row;
}

echo json_encode($patients);
?>
```

### 10.2 ملف: `api/patients.php`

```php
<?php
// API RESTful للمرضى
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/session.php';

require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'get') {
            // جلب مريض واحد
        } elseif ($action === 'list') {
            // جلب قائمة
        }
        break;
    case 'POST':
        if ($action === 'add') {
            // إضافة مريض
        }
        break;
    case 'PUT':
        if ($action === 'update') {
            // تحديث مريض
        }
        break;
    case 'DELETE':
        if ($action === 'delete') {
            // تعطيل مريض
        }
        break;
}
?>
```

### 10.3 ملف: `api/analytics.php`

```php
<?php
// API التحليلات
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/session.php';

require_login();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'risk_assessment':
        // إرجاع تصنيف الخطر لمريض
        $patient_id = (int)$_GET['patient_id'];
        echo json_encode(calculate_risk_score($patient_id));
        break;

    case 'healing_progress':
        // إرجاع تقدم التئام الجرح
        $patient_id = (int)$_GET['patient_id'];
        echo json_encode(get_healing_progress($patient_id));
        break;

    case 'statistics':
        // إحصائيات العيادة
        echo json_encode(get_clinic_statistics());
        break;
}
?>
```

---

## 11. الأمان

### 11.1 حماية SQL Injection
```php
// ✅ استخدم prepared statements في كل استعلام
$stmt = $mysqli->prepare("SELECT * FROM patients WHERE patient_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

// ❌ لا تستخدم الاستعلام المباشر
// $result = $mysqli->query("SELECT * FROM patients WHERE id = $id"); // خطر!
```

### 11.2 حماية XSS
```php
// عند عرض أي بيانات من قاعدة البيانات
echo escape_output($patient_name);
// بدلاً من
// echo $patient_name;
```

### 11.3 CSRF Protection
```php
// في كل نموذج
echo csrf_field();

// في معالج النموذج
if (!verify_csrf_token($_POST['csrf_token'])) {
    die('طلب غير مصرح به');
}
```

### 11.4 كلمات المرور
```php
// عند إنشاء مستخدم
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// عند التحقق
if (password_verify($input_password, $stored_hash)) {
    // تسجيل الدخول
}
```

### 11.5 رفع الملفات
```php
// التحقق من نوع الملف
$allowed = ['jpg', 'jpeg', 'png', 'gif'];
$ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    throw new Exception('نوع الملف غير مسموح');
}

// الحد الأقصى للحجم (5MB)
if ($file_size > 5 * 1024 * 1024) {
    throw new Exception('حجم الملف كبير جداً');
}
```

---

## 12. جدول التنفيذ

### الأسبوع 1: الأساسيات
| اليوم | المهمة | الملفات |
|-------|--------|---------|
| 1 | إنشاء قاعدة البيانات وجميع الجداول | `install.php` |
| 2 | config/database.php, config/session.php, config/helpers.php | 3 ملفات |
| 3 | index.php (تسجيل الدخول) + style.css (الأساس) | ملفان |
| 4 | includes/(header, navbar, sidebar, footer, auth_check) | 5 ملفات |
| 5 | dashboard.php + إحصائيات أولية | ملف واحد |
| 6-7 | اختبار وربط جميع المكونات الأساسية | |

### الأسبوع 2: إدارة المرضى
| اليوم | المهمة | الملفات |
|-------|--------|---------|
| 1 | patients/add.php | ملف واحد |
| 2 | patients/index.php (قائمة + بحث) | ملف واحد |
| 3 | patients/view.php + patients/edit.php | ملفان |
| 4 | api/search.php + api/patients.php | ملفان |
| 5 | تطوير forms.js (تحقق، إرسال) | ملف واحد |
| 6-7 | اختبار كامل CRUD | |

### الأسبوع 3-4: الزيارات والسجلات الطبية
| اليوم | المهمة | الملفات |
|-------|--------|---------|
| 1 | visits/add.php (إنشاء زيارة) | ملف واحد |
| 2 | visits/vitals.php + حساب BMI | ملف واحد |
| 3 | visits/medical_history.php + visits/blood_sugar.php | ملفان |
| 4 | visits/treatments.php + visits/complications.php | ملفان |
| 5 | visits/lab_results.php + تلوين القيم | ملف واحد |
| 6 | visits/view.php (عرض الزيارة كاملة) | ملف واحد |
| 7 | api/visits.php + اختبار | ملف واحد |

### الأسبوع 5: تقييم القدم
| اليوم | المهمة | الملفات |
|-------|--------|---------|
| 1 | assessments/foot_exam.php (يمين/يسار) | ملف واحد |
| 2 | foot-canvas.js (رسم القدم التفاعلي) + SVG | ملفان |
| 3 | assessments/ulcer.php + رفع الصور | ملف واحد |
| 4 | assessments/outcomes.php + assessments/care_plan.php | ملفان |
| 5 | ربط Canvas مع قاعدة البيانات + اختبار | |

### الأسبوع 6: التحليلات والتقارير
| اليوم | المهمة | الملفات |
|-------|--------|---------|
| 1 | analytics/risk_alerts.php (خوارزمية) | ملف واحد |
| 2 | analytics/progress.php + chart.js | ملفان |
| 3 | analytics/statistics.php + analytics/dashboard.php | ملفان |
| 4 | api/analytics.php | ملف واحد |
| 5 | reports/visit_summary.php + print.css | ملفان |
| 6 | reports/patient_report.php + monthly_report.php | ملفان |
| 7 | اختبار جميع الرسوم البيانية والتقارير | |

### الأسبوع 7: تثقيف المريض + تحسينات
| اليوم | المهمة | الملفات |
|-------|--------|---------|
| 1 | education/home_care.php (توليد التعليمات) | ملف واحد |
| 2 | education/diet_plan.php (قوالب غذائية) | ملف واحد |
| 3 | education/emergency.php + طباعة | ملف واحد |
| 4 | users/(index, add, profile) | ملفان |
| 5 | تحسين UI: animations.css, rtl.css | ملفان |
| 6 | main.js: toasts, modals, loaders | ملف واحد |
| 7 | اختبار شامل + إصلاح الأخطاء | |

### الأسبوع 8: النشر والاختبار النهائي
| اليوم | المهمة |
|-------|--------|
| 1 | فحص أمني كامل (SQLi, XSS, CSRF, files) |
| 2 | اختبار على متصفحات مختلفة |
| 3 | اختبار على الجوال والتابلت |
| 4 | تحسين الأداء (فهرسة DB، ضغط الصور) |
| 5 | إنشاء ملف .htaccess + تهيئة النشر |
| 6 | backup_db.php + تعليمات التشغيل |
| 7 | تسليم النظام + توثيق المستخدم |

---

## ملخص Form Mapping (clinic.html ←→ Database)

| قسم clinic.html | الجدول | الحقول الرئيسية |
|----------------|--------|----------------|
| أولاً: البيانات الشخصية | patients | full_name, file_number, dob, age, gender, phone, identity_no, nationality, marital_status, occupation, address, visit_date |
| ثانياً: تاريخ السكري | medical_history | diabetes_type, diagnosis_year, duration, diagnosis_method, companion, family_history, smoking, activity |
| ثالثاً: العلامات الحيوية | vital_signs | weight, height, bmi, bp, waist, temperature |
| رابعاً: قراءات سكر الدم | blood_sugar_readings | fpg, ppg, hba1c, random, dates, lab_device |
| خامساً: العلاج الحالي | treatments | treatment_type, oral_meds, insulin, other_meds |
| سادساً: المضاعفات | complications | retinopathy, nephropathy, neuropathy, cad, cva, pad, foot, hypo |
| سابعاً: التاريخ العائلي | medical_history | family_history, smoking, physical_activity |
| ثامناً: الفحوصات المخبرية | lab_results | cholesterol, ldl, hdl, tg, creatinine, egfr, microalbumin, tsh |
| تاسعاً: ملاحظات الطبيب | visits | doctor_notes, treatment_plan, next_review_date |
| التوقيعات | visits (metadata) | (طباعة فقط) |

---

## 🎯 معايير النجاح

- [ ] النظام يعمل بدون أي إطار عمل (PHP خام، JS خام، CSS خام)
- [ ] دعم كامل للغة العربية (RTL)
- [ ] جميع استعلامات SQL محمية (Prepared Statements)
- [ ] جميع المخرجات HTML محمية (htmlspecialchars)
- [ ] Canvaس تفاعلي لتحديد مكان الجرح على رسم القدم
- [ ] محرك تحليلات يصنف خطورة المرضى تلقائياً
- [ ] توليد تعليمات عناية منزلية مخصصة لكل مريض
- [ ] تقارير قابلة للطباعة
- [ ] تصميم متجاوب (جوال، تابلت، كمبيوتر)
- [ ] قاعدة بيانات طبيعية (Normalized) مع مفاتيح خارجية

---

*آخر تحديث: 23 يونيو 2026*
*المصدر: app/clinic.html, app/ToDo.md*
