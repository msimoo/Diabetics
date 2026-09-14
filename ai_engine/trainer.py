"""
trainer.py — تدريب جميع النماذج V2
Trains upgraded AI models with hyperparameter tuning
"""
import sys
try:
    sys.stdout.reconfigure(encoding='utf-8')
except:
    pass

import os
import numpy as np
import pandas as pd
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from ai_engine.risk_predictor import RiskPredictor
from ai_engine.healing_estimator import HealingEstimator

MODELS_DIR = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'ai_api', 'models')


def train_all(data_path=None, tune=True):
    if data_path is None:
        possible_paths = [
            './output/ml_dataset.csv',
            './output/cleaned_data.csv',
            './output/ml_training_data.csv',
            '../output/ml_dataset.csv',
            '../output/cleaned_data.csv',
            '../output/ml_training_data.csv',
            os.path.join(os.path.dirname(MODELS_DIR), 'output', 'ml_dataset.csv'),
        ]
        for p in possible_paths:
            if os.path.exists(p):
                data_path = p
                break

    if not data_path or not os.path.exists(data_path):
        print("[WARN] No training data found. Run: python -m data_pipeline.main")
        return False

    print(f"[LOAD] Training data: {data_path}")
    df = pd.read_csv(data_path)
    print(f"       {len(df)} samples x {len(df.columns)} columns")

    # Target column
    target_col = None
    for col in ['high_risk', 'risk_label', 'target']:
        if col in df.columns:
            target_col = col
            break

    if target_col is None:
        print("[WARN] No target column (high_risk) found")
        return False

    y = df[target_col]
    X = df.drop(columns=[target_col])
    # Drop healing target from features (prevent leakage)
    for hc in ['healing_weeks', 'healing_days', 'estimated_healing']:
        if hc in X.columns:
            X = X.drop(columns=[hc])
    X = X.select_dtypes(include=[np.number])
    print(f"[ML] Features: {len(X.columns)}")

    if not os.path.exists(MODELS_DIR):
        os.makedirs(MODELS_DIR)

    # Train risk predictor V2
    risk_predictor = RiskPredictor(MODELS_DIR)
    risk_predictor.train(X, y, tune_hyperparams=tune)

    # Train healing estimator V2
    healing_col = None
    for col in ['healing_weeks', 'healing_days', 'estimated_healing']:
        if col in df.columns:
            healing_col = col
            break

    if healing_col:
        healing_y = df[healing_col]
        healing_estimator = HealingEstimator(MODELS_DIR)
        healing_estimator.train(X, healing_y, tune_hyperparams=tune)
    else:
        print("\n[WARN] No healing data found")

    print("\n" + "=" * 55)
    print("[DONE] All V2 models trained successfully!")
    print(f"[SAVE] Models in: {MODELS_DIR}")
    print("=" * 55)
    return True


if __name__ == '__main__':
    import argparse
    parser = argparse.ArgumentParser(description='AI Model Training V2')
    parser.add_argument('--data', type=str, help='CSV data path')
    parser.add_argument('--no-tune', action='store_true', help='Skip hyperparameter tuning')
    args = parser.parse_args()
    train_all(args.data, tune=not args.no_tune)
