"""
generate_synthetic_data.py — توليد 500 مريض وهمي لتدريب نماذج الذكاء الاصطناعي
Generates 500 realistic synthetic diabetic patients for ML model training
"""
import sys
try:
    sys.stdout.reconfigure(encoding='utf-8')
except:
    pass

import random
import pymysql
from datetime import datetime, timedelta
import string

# ===== Database Connection =====
DB = pymysql.connect(
    host='localhost', user='root', password='', database='clinic_diabetes',
    charset='utf8mb4'
)
cursor = DB.cursor()

# ===== Realistic Data Pools =====
FIRST_NAMES = ['محمد', 'أحمد', 'علي', 'عمر', 'خالد', 'عبدالله', 'فهد', 'سعود', 'ناصر', 'ماجد',
    'إبراهيم', 'سالم', 'صالح', 'ياسر', 'حسن', 'حسين', 'موسى', 'عيسى', 'محمود', 'وائل',
    'فاطمة', 'مريم', 'نورة', 'سارة', 'هدى', 'منى', 'لينا', 'رنا', 'سلمى', 'دلال']
LAST_NAMES = ['السري', 'القحطاني', 'العتيبي', 'الزهراني', 'الدوسري', 'الغامدي', 'الشهري',
    'المالكي', 'العنزي', 'الحربي', 'الجهني', 'المطيري', 'الشمري', 'الخالدي', 'الهذلي',
    'القرني', 'الشمراني', 'الثقفي', 'البقمي', 'الزهراني']
CITIES = ['الرياض', 'جدة', 'مكة المكرمة', 'المدينة المنورة', 'الدمام', 'الخبر', 'أبها',
    'تبوك', 'بريدة', 'حائل', 'نجران', 'جيزان', 'الطائف', 'الباحة', 'عرعر']
DIABETES_TYPES = ['النوع الثاني (Type 2)', 'النوع الأول (Type 1)', 'سكري الحمل (GDM)']
SMOKING_STATUSES = ['لا', 'مدخن', 'سابق']
ACTIVITY_LEVELS = ['لا يوجد', 'خفيف', 'معتدل', 'منتظم']
WAGNER_GRADES = [0, 0, 0, 0, 1, 1, 1, 2, 2, 3, 3, 4, 5]  # Weighted: more 0s = lower grade
WOUND_CONDITIONS = ['نظيفة', 'متسخة', 'صديد', 'سوداء']
WOUND_DEPTHS = ['سطحي في الجلد', 'الجلد وتحت الجلد', 'العضلات', 'العظم']
SENSATIONS = ['طبيعي', 'منخفض', 'معدوم', 'متناقض']
PULSES = ['طبيعي متوسط', 'ضعيف', 'معدوم']
TREATMENT_TYPES = ['حمية غذائية فقط', 'أدوية فموية (OADs)', 'أنسولين', 'أنسولين + أدوية فموية']
INITIAL_CAUSES = ['طعنة شوكة', 'طعنة دبوس', 'ضربة', 'تليف وخشونة', 'حذاء جديد', 'بقاقة', 'حرق', 'غير معروف']
AMPUTATION_TYPES = ['لا', 'لا', 'لا', 'لا', 'لا', 'إصبع', 'بتر رايس', 'تحت الكاحل']
VISIT_REASONS = ['متابعة', 'متابعة', 'متابعة', 'شكوى محددة', 'طوارئ', 'مراجعة']
DEFORMITIES_LIST = ['قدم مخلبية', 'قدم مسطحة', 'بيس كافوس', 'أصابع مزدحمة', 'شاركوت',
    'أصابع مطرقة', 'ثفنات', 'عظام مشط بارزة']

print("=" * 60)
print("توليد بيانات اصطناعية لـ 500 مريض")
print("Generating 500 synthetic diabetic patients")
print("=" * 60)

# ===== Clear existing data =====
print("\n[DEL] تنظيف البيانات القديمة...")
tables = ['outcomes', 'foot_ulcers', 'foot_assessments', 'treatments', 'lab_results',
           'complications', 'blood_sugar_readings', 'vital_signs', 'care_sessions',
           'care_plan', 'medical_history', 'visits', 'patients']
for t in tables:
    cursor.execute(f"DELETE FROM {t}")
DB.commit()
print("[OK] تم حذف البيانات القديمة")

# ===== Create admin user if needed =====
cursor.execute("SELECT user_id FROM users LIMIT 1")
admin = cursor.fetchone()
if not admin:
    cursor.execute("""
        INSERT INTO users (username, password_hash, full_name, role, is_active)
        VALUES ('admin', '$2y$12$LJ3m4ys3Lk0TSwHCpNqrPOpMGxWcYxYxYxYxYxYxYxYxYxYxYxY',
                'مدير النظام', 'admin', 1)
    """)
    admin_id = 1
else:
    admin_id = admin[0]

# ===== Generate Patients =====
print(f"\n[GEN] توليد {500} مريض...")
patient_ids = []
for i in range(500):
    first = random.choice(FIRST_NAMES)
    last = random.choice(LAST_NAMES)
    name = f"{first} {last}"
    age = random.randint(18, 85)
    gender = 'ذكر' if random.random() < 0.55 else 'أنثى'
    city = random.choice(CITIES)
    phone = f"05{random.randint(10000000, 99999999)}"
    file_number = f"2026-{i+1:04d}"
    nationality = 'سعودي' if random.random() < 0.85 else 'غير سعودي'
    marital = random.choice(['أعزب/عزباء', 'متزوج/ة', 'مطلق/ة', 'أرمل/ة'])
    dob = (datetime.now() - timedelta(days=age*365)).strftime('%Y-%m-%d')
    first_visit = (datetime.now() - timedelta(days=random.randint(1, 1095))).strftime('%Y-%m-%d')

    cursor.execute("""
        INSERT INTO patients (file_number, full_name, age, gender, phone_primary, city,
            nationality, marital_status, date_of_birth, first_visit_date, is_active, created_by)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, 1, %s)
    """, (file_number, name, age, gender, phone, city, nationality, marital, dob, first_visit, admin_id))
    patient_ids.append((cursor.lastrowid, age, gender, first_visit))

DB.commit()
print(f"[OK] تم توليد {len(patient_ids)} مريض")

# ===== Generate Visits + Clinical Data =====
print(f"\n[GEN] توليد الزيارات والبيانات السريرية...")
visit_count = 0
for pid, age, gender, first_visit_date in patient_ids:
    # Each patient has 1-12 visits
    num_visits = random.randint(1, 12)
    base_date = datetime.strptime(first_visit_date, '%Y-%m-%d')
    diabetes_type = random.choice(DIABETES_TYPES)
    smoking = random.choice(SMOKING_STATUSES)
    activity = random.choice(ACTIVITY_LEVELS)
    duration = max(1, age - random.randint(20, 50)) if age > 35 else random.randint(1, 10)
    has_hypertension = 1 if random.random() < 0.4 else 0
    has_kidney = 1 if random.random() < 0.15 else 0
    has_retinopathy = 1 if random.random() < 0.2 and duration > 5 else 0
    has_nephropathy = 1 if random.random() < 0.1 and duration > 8 else 0
    has_neuropathy = 1 if random.random() < 0.25 and duration > 5 else 0
    has_cad = 1 if random.random() < 0.15 and age > 50 else 0
    has_pad = 1 if random.random() < 0.2 and duration > 5 else 0

    # Medical history (once per patient)
    cursor.execute("""
        INSERT INTO medical_history (patient_id, diabetes_type, duration_years, smoking_status,
            physical_activity, has_hypertension, has_kidney_disease, has_eye_retinopathy,
            other_chronic_diseases)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)
    """, (pid, diabetes_type, duration, smoking, activity,
          has_hypertension, has_kidney, has_retinopathy,
          'ارتفاع ضغط الدم' if has_hypertension else ''))

    # Complications (once per patient)
    cursor.execute("""
        INSERT INTO complications (patient_id, has_retinopathy, has_nephropathy, has_neuropathy, has_cad, has_pad)
        VALUES (%s, %s, %s, %s, %s, %s)
    """, (pid, has_retinopathy, has_nephropathy, has_neuropathy, has_cad, has_pad))

    for vn in range(num_visits):
        visit_date = base_date + timedelta(days=vn * random.randint(14, 120))
        if visit_date > datetime.now():
            break
        reason = random.choice(VISIT_REASONS)
        cursor.execute("""
            INSERT INTO visits (patient_id, visit_number, visit_date, visit_reason, created_by)
            VALUES (%s, %s, %s, %s, %s)
        """, (pid, vn + 1, visit_date.strftime('%Y-%m-%d'), reason, admin_id))
        vid = cursor.lastrowid
        visit_count += 1

        # Vital signs
        weight = random.uniform(55, 120) if gender == 'ذكر' else random.uniform(45, 100)
        height = random.randint(155, 185) if gender == 'ذكر' else random.randint(148, 175)
        bmi = round(weight / ((height/100) ** 2), 1)
        bp_sys = random.randint(110, 180)
        bp_dia = random.randint(60, 110)
        cursor.execute("""
            INSERT INTO vital_signs (visit_id, weight, height, bmi, blood_pressure_systolic, blood_pressure_diastolic)
            VALUES (%s, %s, %s, %s, %s, %s)
        """, (vid, round(weight, 1), height, bmi, bp_sys, bp_dia))

        # Blood sugar (worsening over time for uncontrolled patients)
        hba1c_base = random.uniform(5.5, 11.0)
        hba1c = min(14, hba1c_base + vn * random.uniform(-0.3, 0.8))
        hba1c = round(max(4.5, hba1c), 1)
        fpg = round(hba1c * 18 + random.uniform(-30, 30), 1)  # Approximate conversion
        cursor.execute("""
            INSERT INTO blood_sugar_readings (visit_id, fpg_value, hba1c_value, hba1c_date)
            VALUES (%s, %s, %s, %s)
        """, (vid, max(70, fpg), hba1c, visit_date.strftime('%Y-%m-%d')))

        # Treatment (varies by HbA1c)
        if hba1c < 7: ttype = 'حمية غذائية فقط'
        elif hba1c < 8: ttype = 'أدوية فموية (OADs)'
        elif hba1c < 10: ttype = 'أنسولين + أدوية فموية'
        else: ttype = 'أنسولين'
        cursor.execute("""
            INSERT INTO treatments (visit_id, treatment_type, oral_meds_details, insulin_details)
            VALUES (%s, %s, %s, %s)
        """, (vid, ttype, 'ميتفورمين 500mg' if 'فموية' in ttype else '', 'جلارجين 20 وحدة' if 'أنسولين' in ttype else ''))

        # Lab results (every 3 visits)
        if vn % 3 == 0:
            ldl = round(random.uniform(70, 190), 1)
            hdl = round(random.uniform(30, 65), 1)
            creatinine = round(random.uniform(0.5, 2.5), 2)
            cursor.execute("""
                INSERT INTO lab_results (visit_id, ldl, hdl, triglycerides, creatinine)
                VALUES (%s, %s, %s, %s, %s)
            """, (vid, ldl, hdl, round(random.uniform(80, 350)), creatinine))

        # Foot assessment (from visit 2 onwards for ~60% of patients)
        if vn >= 1 and random.random() < 0.6:
            wagner = random.choice(WAGNER_GRADES)
            sensation = random.choice(SENSATIONS)
            pulse = random.choice(PULSES)
            abpi_right = round(random.uniform(0.5, 1.4), 2)
            abpi_left = round(random.uniform(0.5, 1.4), 2)
            # Make ABPI lower for higher Wagner grades
            if wagner >= 3:
                abpi_right = round(random.uniform(0.3, 0.9), 2)
                abpi_left = round(random.uniform(0.3, 0.9), 2)
            cursor.execute("""
                INSERT INTO foot_assessments (visit_id, right_sensation, right_pulse, left_sensation, left_pulse,
                    abpi_right, abpi_left, wagner_grade)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
            """, (vid, sensation, pulse, sensation, pulse, abpi_right, abpi_left, wagner))

            # Foot ulcer (for Wagner >= 2)
            if wagner >= 2:
                condition = random.choice(WOUND_CONDITIONS)
                depth = random.choice(WOUND_DEPTHS)
                size = round(random.uniform(0.5, 25), 1)
                cause = random.choice(INITIAL_CAUSES)
                foot = random.choice(['يمنى', 'يسرى'])
                cursor.execute("""
                    INSERT INTO foot_ulcers (visit_id, initial_cause, wound_condition, wound_depth, wound_size_cm2, wound_foot)
                    VALUES (%s, %s, %s, %s, %s, %s)
                """, (vid, cause, condition, depth, size, foot))

            # Outcomes (progressive improvement or worsening)
            if wagner >= 1:
                # Simulate healing progress
                improvement_steps = [0, 25, 25, 50, 50, 75, 75, 100]
                imp_idx = min(vn - 1, len(improvement_steps) - 1)
                improvement = improvement_steps[imp_idx] if random.random() < 0.7 else improvement_steps[max(0, imp_idx - 1)]
                amputation = 'لا'
                if wagner >= 4 and random.random() < 0.3:
                    amputation = random.choice(['إصبع', 'بتر رايس', 'تحت الكاحل'])
                cursor.execute("""
                    INSERT INTO outcomes (visit_id, improvement_percentage, current_amputation)
                    VALUES (%s, %s, %s)
                """, (vid, improvement, amputation))

DB.commit()
print(f"[OK] تم توليد {visit_count} زيارة")
print(f"[OK] بيانات 500 مريض جاهزة")

# ===== Summary =====
cursor.execute("SELECT COUNT(*) FROM patients")
p = cursor.fetchone()[0]
cursor.execute("SELECT COUNT(*) FROM visits")
v = cursor.fetchone()[0]
cursor.execute("SELECT COUNT(*) FROM foot_assessments")
fa = cursor.fetchone()[0]
cursor.execute("SELECT COUNT(*) FROM foot_ulcers")
fu = cursor.fetchone()[0]
cursor.execute("SELECT COUNT(*) FROM outcomes")
o = cursor.fetchone()[0]
cursor.execute("SELECT COUNT(*) FROM blood_sugar_readings")
bs = cursor.fetchone()[0]

print("\n" + "=" * 60)
print("ملخص البيانات المولدة:")
print(f"  المرضى: {p}")
print(f"  الزيارات: {v}")
print(f"  تقييمات القدم: {fa}")
print(f"  جروح القدم: {fu}")
print(f"  النتائج: {o}")
print(f"  قراءات السكر: {bs}")
print("=" * 60)

cursor.close()
DB.close()
print("\n[DONE] تم توليد البيانات بنجاح!")
print("شغل الآن: python -m data_pipeline.main && python -m ai_engine.main --train")
