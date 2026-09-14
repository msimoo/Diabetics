"""
transformer.py — معالجة وتحويل البيانات النصية إلى أرقام
Transforms Arabic text data into numerical values for ML
"""
import sys
try:
    sys.stdout.reconfigure(encoding='utf-8')
except:
    pass

import pandas as pd
import numpy as np

SENSATION_MAP = {'طبيعي': 0, 'منخفض': 1, 'متناقض': 2, 'معدوم': 3}
PULSE_MAP = {'طبيعي متوسط': 0, 'ضعيف': 1, 'معدوم': 2}
SMOKING_MAP = {'لا': 0, 'سابق': 1, 'مدخن': 2}
DIABETES_TYPE_MAP = {'النوع الأول (Type 1)': 0, 'النوع الثاني (Type 2)': 1, 'سكري الحمل (GDM)': 2, 'MODY': 3, 'غير محدد': -1}
WOUND_CONDITION_MAP = {'نظيفة': 0, 'متسخة': 1, 'صديد': 2, 'سوداء': 3, 'تحوي جسم غريب': 4}
WOUND_DEPTH_MAP = {'سطحي في الجلد': 0, 'الجلد وتحت الجلد': 1, 'العضلات': 2, 'العظم': 3}
ACTIVITY_MAP = {'لا يوجد': 0, 'خفيف': 1, 'معتدل': 2, 'منتظم': 3}
TREATMENT_TYPE_MAP = {'حمية غذائية فقط': 0, 'أدوية فموية (OADs)': 1, 'أنسولين': 2, 'أنسولين + أدوية فموية': 3, 'مضخة أنسولين (Pump)': 4, 'GLP-1 RA': 5}
AMPUTATION_MAP = {'لا': 0, 'إصبع': 1, 'بتر رايس': 2, 'تحت الكاحل': 3, 'فوق الكاحل': 4, 'تحت الركبة': 5, 'فوق الركبة': 6}

def encode_sensation(value):
    if pd.isna(value) or value == '': return np.nan
    return SENSATION_MAP.get(value, np.nan)

def encode_pulse(value):
    if pd.isna(value) or value == '': return np.nan
    return PULSE_MAP.get(value, np.nan)

def encode_gender(value):
    if pd.isna(value): return np.nan
    return 1 if value == 'ذكر' else 0

def transform_patient_data(df):
    print("[TRANSFORM] جاري تحويل البيانات النصية إلى أرقام...")
    df = df.copy()
    if 'gender' in df.columns: df['gender_num'] = df['gender'].apply(encode_gender)
    for col in ['right_sensation', 'left_sensation']:
        if col in df.columns: df[f'{col}_num'] = df[col].apply(encode_sensation)
    for col in ['right_pulse', 'left_pulse']:
        if col in df.columns: df[f'{col}_num'] = df[col].apply(encode_pulse)
    if 'smoking_status' in df.columns: df['smoking_num'] = df['smoking_status'].map(SMOKING_MAP)
    if 'diabetes_type' in df.columns: df['diabetes_type_num'] = df['diabetes_type'].map(DIABETES_TYPE_MAP)
    if 'wound_condition' in df.columns: df['wound_condition_num'] = df['wound_condition'].map(WOUND_CONDITION_MAP)
    if 'wound_depth' in df.columns: df['wound_depth_num'] = df['wound_depth'].map(WOUND_DEPTH_MAP)
    if 'physical_activity' in df.columns: df['activity_num'] = df['physical_activity'].map(ACTIVITY_MAP)
    if 'treatment_type' in df.columns:
        df['treatment_type_num'] = df['treatment_type'].map(lambda x: TREATMENT_TYPE_MAP.get(x, np.nan) if pd.notna(x) else np.nan)
    if 'current_amputation' in df.columns: df['amputation_num'] = df['current_amputation'].map(AMPUTATION_MAP)
    # Feature engineering
    sens_cols = [c for c in df.columns if 'sensation_num' in c]
    pulse_cols = [c for c in df.columns if 'pulse_num' in c]
    if sens_cols and pulse_cols:
        df['neuropathy_index'] = df[sens_cols].sum(axis=1, min_count=1) + df[pulse_cols].sum(axis=1, min_count=1)
    if 'wagner_grade' in df.columns and 'wound_depth_num' in df.columns:
        df['ulcer_severity'] = df['wagner_grade'].fillna(0) + df['wound_depth_num'].fillna(0)
    if 'hba1c_value' in df.columns: df['hba1c_ratio'] = df['hba1c_value'] / 7.0
    if 'bmi' in df.columns:
        df['bmi_category'] = pd.cut(df['bmi'], bins=[0, 18.5, 25, 30, 100], labels=[0, 1, 2, 3]).astype(float)
    if 'blood_pressure_systolic' in df.columns and 'blood_pressure_diastolic' in df.columns:
        df['bp_risk'] = ((df['blood_pressure_systolic'] > 140).astype(int) + (df['blood_pressure_diastolic'] > 90).astype(int))
    if 'age' in df.columns:
        df['age_group'] = pd.cut(df['age'], bins=[0, 18, 30, 45, 60, 75, 150], labels=[0, 1, 2, 3, 4, 5]).astype(float)
    print(f"   [OK] تم تحويل {len(df.columns)} عمود")
    return df

def create_target_variable(df):
    df = df.copy()
    df['high_risk'] = 0
    conditions = []
    if 'wagner_grade' in df.columns: conditions.append(df['wagner_grade'] >= 3)
    if 'current_amputation' in df.columns: conditions.append(df['current_amputation'].isin(['فوق الركبة', 'تحت الركبة', 'فوق الكاحل', 'تحت الكاحل', 'بتر رايس', 'إصبع']))
    if 'improvement_percentage' in df.columns: conditions.append(df['improvement_percentage'] < 25)
    if 'neuropathy_index' in df.columns: conditions.append(df['neuropathy_index'] >= 4)
    if conditions:
        combined = conditions[0]
        for c in conditions[1:]: combined = combined | c
        df['high_risk'] = combined.astype(int)
    print(f"   [OK] الهدف: {df['high_risk'].sum()} حالة عالية الخطورة من اصل {len(df)}")
    return df
