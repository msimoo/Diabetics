"""
config.py — إعدادات الاتصال بقاعدة البيانات
Database connection configuration for clinic system
"""

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'clinic_diabetes',
    'charset': 'utf8mb4',
}

# مسار حفظ ملفات CSV
OUTPUT_DIR = './output/'

# اسماء الجداول المطلوبة
TABLES = {
    'patients': 'patients',
    'medical_history': 'medical_history',
    'visits': 'visits',
    'vital_signs': 'vital_signs',
    'blood_sugar': 'blood_sugar_readings',
    'treatments': 'treatments',
    'complications': 'complications',
    'lab_results': 'lab_results',
    'foot_assessments': 'foot_assessments',
    'foot_ulcers': 'foot_ulcers',
    'outcomes': 'outcomes',
}

# معايير التصنيف السريري
CLINICAL_THRESHOLDS = {
    'hba1c_normal': 7.0,        # HbA1c < 7% = مضبوط
    'hba1c_high': 9.0,          # HbA1c > 9% = مرتفع جداً
    'bmi_obese': 30.0,          # BMI > 30 = سمنة
    'bmi_overweight': 25.0,     # BMI > 25 = زيادة وزن
    'bp_high_sys': 140,         # ضغط انقباضي > 140
    'bp_high_dia': 90,          # ضغط انبساطي > 90
    'wagner_critical': 3,       # Wagner >= 3 = خطير
    'abpi_low': 0.9,            # ABPI < 0.9 = مرض شرياني طرفي
}
