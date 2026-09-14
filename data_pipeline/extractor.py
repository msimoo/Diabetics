"""
extractor.py — استخراج البيانات من قاعدة بيانات MySQL
Extracts all patient clinical data from the clinic database
"""
import sys
try:
    sys.stdout.reconfigure(encoding='utf-8')
except:
    pass

import pandas as pd
from sqlalchemy import create_engine
from .config import DB_CONFIG


def get_engine():
    conn_str = (
        f"mysql+pymysql://{DB_CONFIG['user']}:{DB_CONFIG['password']}"
        f"@{DB_CONFIG['host']}/{DB_CONFIG['database']}"
        f"?charset={DB_CONFIG['charset']}"
    )
    return create_engine(conn_str)


def extract_all(engine):
    print("[EXTRACT] جاري استخراج البيانات...")
    patients = pd.read_sql("SELECT * FROM patients WHERE is_active = 1", engine)
    print(f"   [OK] المرضى: {len(patients)}")
    medical_history = pd.read_sql("SELECT * FROM medical_history", engine)
    print(f"   [OK] التاريخ الطبي: {len(medical_history)}")
    visits = pd.read_sql("SELECT * FROM visits ORDER BY visit_date", engine)
    print(f"   [OK] الزيارات: {len(visits)}")
    vital_signs = pd.read_sql("SELECT * FROM vital_signs", engine)
    print(f"   [OK] العلامات الحيوية: {len(vital_signs)}")
    blood_sugar = pd.read_sql("SELECT * FROM blood_sugar_readings", engine)
    print(f"   [OK] قراءات السكر: {len(blood_sugar)}")
    treatments = pd.read_sql("SELECT * FROM treatments", engine)
    print(f"   [OK] العلاجات: {len(treatments)}")
    complications = pd.read_sql("SELECT * FROM complications", engine)
    print(f"   [OK] المضاعفات: {len(complications)}")
    lab_results = pd.read_sql("SELECT * FROM lab_results", engine)
    print(f"   [OK] الفحوصات المخبرية: {len(lab_results)}")
    foot_assessments = pd.read_sql("SELECT * FROM foot_assessments", engine)
    print(f"   [OK] تقييمات القدم: {len(foot_assessments)}")
    foot_ulcers = pd.read_sql("SELECT * FROM foot_ulcers", engine)
    print(f"   [OK] جروح القدم: {len(foot_ulcers)}")
    outcomes = pd.read_sql("SELECT * FROM outcomes", engine)
    print(f"   [OK] النتائج: {len(outcomes)}")
    return {
        'patients': patients, 'medical_history': medical_history, 'visits': visits,
        'vital_signs': vital_signs, 'blood_sugar': blood_sugar, 'treatments': treatments,
        'complications': complications, 'lab_results': lab_results,
        'foot_assessments': foot_assessments, 'foot_ulcers': foot_ulcers, 'outcomes': outcomes,
    }


def get_latest_per_patient(engine):
    query = """SELECT p.patient_id, p.full_name, p.age, p.gender, p.city,
        mh.diabetes_type, mh.duration_years, mh.smoking_status, mh.physical_activity,
        mh.has_hypertension, mh.has_kidney_disease, mh.has_eye_retinopathy,
        vs.weight, vs.bmi, vs.blood_pressure_systolic, vs.blood_pressure_diastolic,
        bs.hba1c_value, bs.fpg_value,
        fa.wagner_grade, fa.right_sensation, fa.left_sensation,
        fa.right_pulse, fa.left_pulse, fa.abpi_right, fa.abpi_left,
        fu.wound_condition, fu.wound_depth, fu.wound_size_cm2, fu.initial_cause,
        o.improvement_percentage, o.current_amputation,
        t.treatment_type,
        (SELECT COUNT(*) FROM visits WHERE patient_id = p.patient_id) as total_visits,
        DATEDIFF(CURDATE(), (SELECT MAX(v4.visit_date) FROM visits v4 WHERE v4.patient_id = p.patient_id)) as days_since_last_visit
    FROM patients p
    LEFT JOIN medical_history mh ON p.patient_id = mh.patient_id
    LEFT JOIN visits v ON p.patient_id = v.patient_id
    LEFT JOIN vital_signs vs ON v.visit_id = vs.visit_id
    LEFT JOIN blood_sugar_readings bs ON v.visit_id = bs.visit_id
    LEFT JOIN foot_assessments fa ON v.visit_id = fa.visit_id
    LEFT JOIN foot_ulcers fu ON v.visit_id = fu.visit_id
    LEFT JOIN outcomes o ON v.visit_id = o.visit_id
    LEFT JOIN treatments t ON v.visit_id = t.visit_id
    WHERE p.is_active = 1
        AND v.visit_id = (SELECT MAX(v5.visit_id) FROM visits v5 WHERE v5.patient_id = p.patient_id)"""
    df = pd.read_sql(query, engine)
    print(f"[OK] أحدث البيانات: {len(df)} مريض")
    return df
