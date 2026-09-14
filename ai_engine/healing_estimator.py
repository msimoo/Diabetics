"""
healing_estimator.py — نموذج تقدير مدة الشفاء V2
Upgraded: RandomizedSearchCV, LightGBM Regressor, dynamic weighting
"""
import sys
try:
    sys.stdout.reconfigure(encoding='utf-8')
except:
    pass

import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestRegressor, GradientBoostingRegressor
from sklearn.model_selection import train_test_split, RandomizedSearchCV, KFold, cross_val_score
from sklearn.metrics import mean_absolute_error, mean_squared_error, r2_score
from sklearn.preprocessing import StandardScaler
import joblib
import os
import warnings
warnings.filterwarnings('ignore')

try:
    from lightgbm import LGBMRegressor
    LGBM_AVAILABLE = True
except ImportError:
    LGBM_AVAILABLE = False


class HealingEstimator:
    """
    V2: نموذج تقدير مدة الشفاء — RF + GB + LGBM مع تحسين Hyperparameters
    """

    def __init__(self, models_dir='./models'):
        self.models_dir = models_dir
        self.models = {}
        self.weights = {}
        self.scaler = StandardScaler()
        self.feature_names = None
        self._is_trained = False

        if not os.path.exists(models_dir):
            os.makedirs(models_dir)

    def _get_param_grids(self, n_features):
        n_est = min(500, max(100, n_features * 10))
        return {
            'rf': {
                'n_estimators': [n_est//2, n_est, n_est*2],
                'max_depth': [8, 12, 18],
                'min_samples_split': [2, 5, 10],
                'min_samples_leaf': [1, 2, 4],
            },
            'gb': {
                'n_estimators': [n_est//2, n_est, n_est*2],
                'max_depth': [4, 6, 10],
                'learning_rate': [0.02, 0.05, 0.1, 0.15],
                'subsample': [0.7, 0.8, 1.0],
                'min_samples_split': [2, 5],
            },
        }

    def _get_lgbm_grid(self, n_features):
        n_est = min(500, max(100, n_features * 10))
        return {
            'n_estimators': [n_est//2, n_est, n_est*2],
            'max_depth': [-1, 8, 12, 20],
            'learning_rate': [0.02, 0.05, 0.1],
            'num_leaves': [15, 31, 50],
            'subsample': [0.7, 0.8, 1.0],
            'colsample_bytree': [0.7, 0.8, 1.0],
            'reg_alpha': [0, 0.1, 0.5],
            'reg_lambda': [0, 0.1, 0.5],
        }

    def train(self, X, y, test_size=0.2, tune_hyperparams=True):
        print("\n" + "=" * 55)
        print("HEAL V2: Training upgraded healing estimation model")
        print("=" * 55)

        self.feature_names = X.columns.tolist()
        n_features = len(self.feature_names)

        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=test_size, random_state=42
        )

        X_train_scaled = self.scaler.fit_transform(X_train)
        X_test_scaled = self.scaler.transform(X_test)

        candidates = {}

        # 1. Random Forest
        rf_base = RandomForestRegressor(random_state=42, n_jobs=-1)
        if tune_hyperparams:
            rf_grid = self._get_param_grids(n_features)['rf']
            rf_search = RandomizedSearchCV(
                rf_base, rf_grid, n_iter=10, cv=KFold(3),
                scoring='r2', random_state=42, n_jobs=-1, verbose=0
            )
            rf_search.fit(X_train_scaled, y_train)
            candidates['rf'] = rf_search.best_estimator_
            print(f"  RF best params: {rf_search.best_params_}")
        else:
            rf_base.fit(X_train_scaled, y_train)
            candidates['rf'] = rf_base

        # 2. Gradient Boosting
        gb_base = GradientBoostingRegressor(random_state=42)
        if tune_hyperparams:
            gb_grid = self._get_param_grids(n_features)['gb']
            gb_search = RandomizedSearchCV(
                gb_base, gb_grid, n_iter=10, cv=KFold(3),
                scoring='r2', random_state=42, n_jobs=-1, verbose=0
            )
            gb_search.fit(X_train_scaled, y_train)
            candidates['gb'] = gb_search.best_estimator_
            print(f"  GB best params: {gb_search.best_params_}")
        else:
            gb_base.fit(X_train_scaled, y_train)
            candidates['gb'] = gb_base

        # 3. LightGBM
        if LGBM_AVAILABLE:
            lgbm_base = LGBMRegressor(random_state=42, verbose=-1, force_row_wise=True)
            if tune_hyperparams:
                lgbm_grid = self._get_lgbm_grid(n_features)
                lgbm_search = RandomizedSearchCV(
                    lgbm_base, lgbm_grid, n_iter=8, cv=KFold(3),
                    scoring='r2', random_state=42, n_jobs=-1, verbose=0
                )
                lgbm_search.fit(X_train_scaled, y_train)
                candidates['lgbm'] = lgbm_search.best_estimator_
                print(f"  LGBM best params: {lgbm_search.best_params_}")
            else:
                candidates['lgbm'] = lgbm_base.fit(X_train_scaled, y_train)
        else:
            print("  LightGBM not available for regression, skipping")

        # Evaluate and weight
        self.models = {}
        self.weights = {}
        r2_scores = {}

        for name, model in candidates.items():
            model.fit(X_train_scaled, y_train)
            y_pred = model.predict(X_test_scaled)
            r2 = r2_score(y_test, y_pred)
            r2_scores[name] = r2
            self.models[name] = model
            print(f"  {name.upper():5s} R2: {r2:.4f}, MAE: {mean_absolute_error(y_test, y_pred):.2f}wk")

        # Softmax weighting by R2
        if r2_scores:
            scores = np.array(list(r2_scores.values()))
            exp_scores = np.exp(np.clip(scores * 3, -10, 10))
            soft_weights = exp_scores / exp_scores.sum()
            for i, name in enumerate(r2_scores.keys()):
                self.weights[name] = soft_weights[i]
            print(f"  Weights: {dict((k, f'{v:.3f}') for k, v in self.weights.items())}")

        self._is_trained = True
        self.save()

    def predict_healing_time(self, patient_data):
        if not self._is_trained:
            raise ValueError("Model not trained!")

        if isinstance(patient_data, dict):
            data_df = pd.DataFrame([patient_data])
        else:
            data_df = patient_data

        for col in self.feature_names:
            if col not in data_df.columns:
                data_df[col] = 0
        data_df = data_df[self.feature_names]
        scaled = self.scaler.transform(data_df)

        # Weighted ensemble
        all_ests = []
        weight_sum = 0
        for name, model in self.models.items():
            est = model.predict(scaled)[0]
            w = self.weights.get(name, 1.0 / max(1, len(self.models)))
            all_ests.append(est * w)
            weight_sum += w

        avg_est = sum(all_ests) / weight_sum if weight_sum > 0 else 0

        # Confidence from model disagreement
        estimates = [model.predict(scaled)[0] for model in self.models.values()]
        std_est = np.std(estimates) if len(estimates) > 1 else abs(estimates[0]) * 0.1

        return {
            'estimated_weeks': round(float(avg_est), 1),
            'confidence_interval': [
                round(max(0, float(avg_est - std_est * 1.5)), 1),
                round(float(avg_est + std_est * 1.5), 1)
            ],
            'model_details': {
                name: round(float(model.predict(scaled)[0]), 1)
                for name, model in self.models.items()
            },
            'ensemble_method': 'weighted_softmax_r2',
        }

    def save(self):
        for name, model in self.models.items():
            joblib.dump(model, os.path.join(self.models_dir, f'healing_{name}.pkl'))
        joblib.dump(self.scaler, os.path.join(self.models_dir, 'healing_scaler.pkl'))
        joblib.dump(self.feature_names, os.path.join(self.models_dir, 'healing_features.pkl'))
        joblib.dump(self.weights, os.path.join(self.models_dir, 'healing_weights.pkl'))
        print(f"  Healing models saved to {self.models_dir}/")

    def load(self):
        self.models = {}
        self.weights = {}

        for model_type in ['rf', 'gb', 'lgbm']:
            path = os.path.join(self.models_dir, f'healing_{model_type}.pkl')
            if os.path.exists(path):
                try:
                    self.models[model_type] = joblib.load(path)
                except:
                    pass

        if not self.models:
            return False

        scaler_path = os.path.join(self.models_dir, 'healing_scaler.pkl')
        features_path = os.path.join(self.models_dir, 'healing_features.pkl')
        weights_path = os.path.join(self.models_dir, 'healing_weights.pkl')

        if os.path.exists(scaler_path):
            self.scaler = joblib.load(scaler_path)
        if os.path.exists(features_path):
            self.feature_names = joblib.load(features_path)
        if os.path.exists(weights_path):
            self.weights = joblib.load(weights_path)

        self._is_trained = True
        print(f"  Loaded {len(self.models)} healing models: {list(self.models.keys())}")
        return True
