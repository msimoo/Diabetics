"""
cleaner.py — تنظيف البيانات والتعامل مع القيم المفقودة
Cleans data by handling missing values and preparing for ML
"""
import sys
try:
    sys.stdout.reconfigure(encoding='utf-8')
except:
    pass

import pandas as pd
import numpy as np

def clean_data(df):
    print("[CLEAN] جاري تنظيف البيانات...")
    df = df.copy()
    cols_to_drop = ['full_name', 'file_number', 'city', 'address', 'phone_primary', 'phone_secondary',
        'identity_number', 'occupation', 'nationality', 'photo', 'wound_image', 'notes', 'doctor_notes',
        'treatment_plan', 'chief_complaint', 'other_chronic_diseases', 'companion_name', 'other_meds',
        'antibiotics_oral', 'antibiotics_iv', 'topical_ointments', 'oral_meds_details', 'insulin_details',
        'referral_to', 'referral_reason', 'death_cause_detail', 'lab_device']
    cols_to_drop = [c for c in cols_to_drop if c in df.columns]
    df = df.drop(columns=cols_to_drop)
    df = df.dropna(axis=1, how='all')
    numeric_cols = df.select_dtypes(include=[np.number]).columns
    for col in numeric_cols:
        missing_pct = df[col].isna().mean()
        if missing_pct == 0: continue
        elif missing_pct > 0.8:
            df = df.drop(columns=[col])
            print(f"   [WARN] حذف العمود {col} ({missing_pct*100:.0f}% مفقود)")
        elif col in ['age', 'bmi', 'hba1c_value', 'fpg_value', 'wagner_grade', 'abpi_right', 'abpi_left']:
            median_val = df[col].median()
            df[col] = df[col].fillna(median_val)
            print(f"   [FILL] تعبئة {col} بالوسيط ({median_val:.1f})")
        else:
            median_val = df[col].median()
            df[col] = df[col].fillna(median_val)
    cat_cols = df.select_dtypes(include=['object', 'category']).columns
    for col in cat_cols:
        df[col] = df[col].fillna(df[col].mode()[0] if not df[col].mode().empty else 'غير محدد')
    for col in numeric_cols:
        if col in df.columns and col not in ['high_risk', 'gender_num', 'diabetes_type_num', 'smoking_num', 'activity_num', 'age_group', 'bmi_category', 'treatment_type_num']:
            Q1, Q3 = df[col].quantile(0.01), df[col].quantile(0.99)
            IQR = Q3 - Q1
            outliers_before = ((df[col] < Q1 - 3*IQR) | (df[col] > Q3 + 3*IQR)).sum()
            df[col] = df[col].clip(Q1 - 3*IQR, Q3 + 3*IQR)
            if outliers_before > 0: print(f"   [CLIP] تقييد {col}: {outliers_before} قيمة متطرفة")
    min_features = max(1, int(len(df.columns) * 0.3))
    df = df.dropna(thresh=min_features)
    print(f"   [OK] البيانات النظيفة: {len(df)} صف x {len(df.columns)} عمود")
    return df

def _calculate_healing_weeks(row):
    """Calculate healing weeks target from clinical features"""
    wagner = row.get('wagner_grade', 0) if not pd.isna(row.get('wagner_grade', np.nan)) else 0
    hba1c = row.get('hba1c_value', 7) if not pd.isna(row.get('hba1c_value', np.nan)) else 7
    wound_depth = row.get('wound_depth_num', 0) if not pd.isna(row.get('wound_depth_num', np.nan)) else 0
    wound_cond = row.get('wound_condition_num', 0) if not pd.isna(row.get('wound_condition_num', np.nan)) else 0
    abpi = row.get('abpi_right', 1) if not pd.isna(row.get('abpi_right', np.nan)) else 1
    improvement = row.get('improvement_percentage', 50) if not pd.isna(row.get('improvement_percentage', np.nan)) else 50
    
    # Base: Wagner grade is primary driver
    base = 2 + wagner * 2.5
    # HbA1c above 7 adds time
    hba1c_penalty = max(0, (hba1c - 7) * 0.8)
    # Wound depth (0=superficial, 3=bone)
    depth_penalty = wound_depth * 1.5
    # Wound condition (0=clean, 3=necrotic)
    cond_penalty = wound_cond * 1.2
    # ABPI below 1.0 slows healing
    circulation_penalty = max(0, (1.0 - abpi) * 8)
    # Improvement (higher = faster healing)
    improvement_bonus = (improvement / 100) * 3
    
    weeks = base + hba1c_penalty + depth_penalty + cond_penalty + circulation_penalty - improvement_bonus
    return round(max(1, weeks), 1)


def prepare_ml_dataset(df):
    df = df.copy()
    # Calculate healing_weeks for every row
    df['healing_weeks'] = df.apply(_calculate_healing_weeks, axis=1)
    # Save the healing_weeks column BEFORE filtering rows
    healing_weeks_full = df[['healing_weeks']].copy()
    
    numeric_df = df.select_dtypes(include=[np.number])
    cols_to_exclude = ['patient_id', 'visit_id', 'reading_id', 'assessment_id', 'ulcer_id',
        'treatment_id', 'outcome_id', 'lab_id', 'vital_id', 'session_id', 'complication_id', 'care_id', 'history_id']
    cols_to_exclude = [c for c in cols_to_exclude if c in numeric_df.columns]
    numeric_df = numeric_df.drop(columns=cols_to_exclude)
    numeric_df = numeric_df.dropna()
    
    # Align healing_weeks with surviving rows after dropna
    healing_weeks = healing_weeks_full.loc[numeric_df.index, 'healing_weeks']
    
    if 'high_risk' in numeric_df.columns:
        y = numeric_df['high_risk']
        X = numeric_df.drop(columns=['high_risk'])
    else:
        X = numeric_df
        y = None
    
    print(f"[ML] مجموعة البيانات النهائية: {X.shape[0]} عينة x {X.shape[1]} ميزة")
    if y is not None: print(f"   التوزيع: {y.sum()} عالية الخطورة / {(1-y).sum()} منخفضة الخطورة")
    print(f"   مدة الشفاء: {healing_weeks.mean():.1f} +- {healing_weeks.std():.1f} أسبوع (متوسط)")
    return X, y, healing_weeks
