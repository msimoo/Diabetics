"""
main.py — تشغيل الـ Pipeline بالكامل
Runs the complete data pipeline: extract > transform > clean > save
"""
import os
import sys

try:
    sys.stdout.reconfigure(encoding='utf-8')
except:
    pass

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import pandas as pd
from data_pipeline.config import OUTPUT_DIR
from data_pipeline.extractor import get_engine, extract_all, get_latest_per_patient
from data_pipeline.transformer import transform_patient_data, create_target_variable
from data_pipeline.cleaner import clean_data, prepare_ml_dataset


def ensure_output_dir():
    if not os.path.exists(OUTPUT_DIR):
        os.makedirs(OUTPUT_DIR)
        print(f"[DIR] تم إنشاء مجلد {OUTPUT_DIR}")


def run_pipeline():
    print("=" * 60)
    print("[HOSP] نظام عيادة السكري - Data Pipeline")
    print("Diabetes Clinic Data Pipeline")
    print("=" * 60)

    ensure_output_dir()

    # Phase 1: Extract
    print("\n[PHASE 1] استخراج البيانات")
    engine = get_engine()
    try:
        with engine.connect() as conn:
            print("[OK] اتصال بقاعدة البيانات ناجح")
    except Exception as e:
        print(f"[ERR] فشل الاتصال: {e}")
        sys.exit(1)

    all_data = extract_all(engine)
    raw_path = os.path.join(OUTPUT_DIR, 'raw_data.csv')
    combined_raw = pd.concat(
        [v.assign(source_table=k) for k, v in all_data.items() if not v.empty],
        ignore_index=True
    )
    combined_raw.to_csv(raw_path, index=False, encoding='utf-8-sig')
    print(f"[SAVE] البيانات الخام: {raw_path}")

    # Phase 2: Latest patient data
    print("\n[PHASE 2] تجميع أحدث البيانات لكل مريض")
    latest_df = get_latest_per_patient(engine)
    latest_path = os.path.join(OUTPUT_DIR, 'latest_patients.csv')
    latest_df.to_csv(latest_path, index=False, encoding='utf-8-sig')
    print(f"[SAVE] أحدث البيانات: {latest_path}")
    print(f"   العدد: {len(latest_df)} مريض")

    # Phase 3: Transform
    print("\n[PHASE 3] تحويل البيانات")
    transformed = transform_patient_data(latest_df)
    processed_path = os.path.join(OUTPUT_DIR, 'processed_data.csv')
    transformed.to_csv(processed_path, index=False, encoding='utf-8-sig')
    print(f"[SAVE] البيانات المحولة: {processed_path}")

    # Phase 4: Target variable
    print("\n[PHASE 4] إنشاء المتغير المستهدف")
    df_with_target = create_target_variable(transformed)

    # Phase 5: Clean
    print("\n[PHASE 5] تنظيف البيانات")
    cleaned = clean_data(df_with_target)
    cleaned_path = os.path.join(OUTPUT_DIR, 'cleaned_data.csv')
    cleaned.to_csv(cleaned_path, index=False, encoding='utf-8-sig')
    print(f"[SAVE] البيانات النظيفة: {cleaned_path}")

    # Phase 6: ML dataset
    print("\n[PHASE 6] تحضير مجموعة التعلم الآلي")
    X, y, healing_weeks = prepare_ml_dataset(cleaned)

    if X is not None:
        ml_path = os.path.join(OUTPUT_DIR, 'ml_dataset.csv')
        ml_df = X.copy()
        if y is not None:
            ml_df['high_risk'] = y.values
        ml_df['healing_weeks'] = healing_weeks.values
        ml_df.to_csv(ml_path, index=False, encoding='utf-8-sig')
        print(f"[SAVE] بيانات التعلم الآلي: {ml_path}")
        print(f"   الأعمدة: {list(ml_df.columns)}")

        ml_train_path = os.path.join(OUTPUT_DIR, 'ml_training_data.csv')
        X.to_csv(ml_train_path, index=False, encoding='utf-8-sig')
        if y is not None:
            y.to_frame('high_risk').to_csv(
                os.path.join(OUTPUT_DIR, 'ml_target.csv'),
                index=False, encoding='utf-8-sig'
            )
        print(f"[SAVE] بيانات التدريب: {ml_train_path}")

    print("\n" + "=" * 60)
    print("[DONE] تم تشغيل الـ Pipeline بنجاح!")
    print("=" * 60)
    return X, y


if __name__ == '__main__':
    X, y = run_pipeline()
