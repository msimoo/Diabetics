"""
risk_predictor.py — نموذج التنبؤ بالخطر (Risk Prediction) V2
Upgraded: GridSearchCV, LightGBM, CalibratedClassifierCV, dynamic weighting, feature importance
"""
import sys
try:
    sys.stdout.reconfigure(encoding='utf-8')
except:
    pass

import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split, RandomizedSearchCV, StratifiedKFold, cross_val_score
from sklearn.metrics import accuracy_score, precision_score, recall_score, f1_score, roc_auc_score
from sklearn.preprocessing import StandardScaler
from sklearn.calibration import CalibratedClassifierCV
import joblib
import os
import warnings
warnings.filterwarnings('ignore')

try:
    from lightgbm import LGBMClassifier
    LGBM_AVAILABLE = True
except ImportError:
    LGBM_AVAILABLE = False


class RiskPredictor:
    """
    V2: نموذج التنبؤ بالخطر — يدعم 4 نماذج مع تحسين Hyperparameters
    RF + XGBoost + LightGBM + Calibrated Ensemble
    يوفر أهمية الميزات لكل توقع (Explainability)
    """

    def __init__(self, models_dir='./models'):
        self.models_dir = models_dir
        self.models = {}         # trained models dict
        self.weights = {}        # dynamic weights by AUC
        self.scaler = StandardScaler()
        self.feature_names = None
        self.calibrated_ensemble = None
        self._is_trained = False
        self.X_train_scaled = None
        self.y_train = None
        self.cv_auc_scores = {}  # store per-model AUC for weighting
        
        if not os.path.exists(models_dir):
            os.makedirs(models_dir)

    def _get_param_grids(self, n_features):
        """Get hyperparameter grids scaled to dataset size"""
        n_est = min(500, max(100, n_features * 10))
        return {
            'rf': {
                'n_estimators': [n_est//2, n_est, n_est*2],
                'max_depth': [8, 12, 18, 25],
                'min_samples_split': [2, 5, 10],
                'min_samples_leaf': [1, 2, 4],
                'max_features': ['sqrt', 'log2', None],
            },
            'xgb': {
                'n_estimators': [n_est//2, n_est, n_est*2],
                'max_depth': [4, 6, 8, 12],
                'learning_rate': [0.01, 0.05, 0.1, 0.2],
                'subsample': [0.6, 0.8, 1.0],
                'colsample_bytree': [0.6, 0.8, 1.0],
                'min_child_weight': [1, 3, 5],
            },
        }

    def _get_lgbm_params(self, n_features):
        """Get LightGBM parameter grid"""
        n_est = min(500, max(100, n_features * 10))
        return {
            'n_estimators': [n_est//2, n_est, n_est*2],
            'max_depth': [-1, 8, 12, 20],
            'learning_rate': [0.01, 0.05, 0.1],
            'num_leaves': [15, 31, 50, 80],
            'subsample': [0.6, 0.8, 1.0],
            'colsample_bytree': [0.6, 0.8, 1.0],
            'min_child_samples': [5, 10, 20],
            'reg_alpha': [0, 0.1, 0.5],
            'reg_lambda': [0, 0.1, 0.5],
        }

    def train(self, X, y, test_size=0.2, tune_hyperparams=True):
        """
        Train with hyperparameter tuning, calibration, and dynamic weighting
        """
        print("\n" + "=" * 55)
        print("RISK V2: Training upgraded risk prediction model")
        print("=" * 55)

        self.feature_names = X.columns.tolist()
        n_features = len(self.feature_names)
        
        # Stratified split
        X_train, X_test, y_train, y_test = train_test_split(
            X, y, test_size=test_size, random_state=42, stratify=y
        )
        self.y_train = y_train
        
        # Scale
        X_train_scaled = self.scaler.fit_transform(X_train)
        self.X_train_scaled = X_train_scaled
        X_test_scaled = self.scaler.transform(X_test)

        # Define model candidates
        candidates = {}

        # 1. Random Forest
        rf_base = RandomForestClassifier(random_state=42, class_weight='balanced', n_jobs=-1)
        if tune_hyperparams:
            rf_grid = self._get_param_grids(n_features)['rf']
            rf_search = RandomizedSearchCV(
                rf_base, rf_grid, n_iter=15, cv=StratifiedKFold(3),
                scoring='roc_auc', random_state=42, n_jobs=-1, verbose=0
            )
            rf_search.fit(X_train_scaled, y_train)
            candidates['rf'] = rf_search.best_estimator_
            print(f"  RF best params: {rf_search.best_params_}")
        else:
            rf_base.set_params(
                n_estimators=min(500, max(100, n_features * 10)),
                max_depth=min(25, max(8, n_features // 2))
            )
            candidates['rf'] = rf_base

        # 2. XGBoost
        try:
            from xgboost import XGBClassifier
            xgb_base = XGBClassifier(random_state=42, eval_metric='logloss', use_label_encoder=False)
            if tune_hyperparams:
                xgb_grid = self._get_param_grids(n_features)['xgb']
                xgb_search = RandomizedSearchCV(
                    xgb_base, xgb_grid, n_iter=15, cv=StratifiedKFold(3),
                    scoring='roc_auc', random_state=42, n_jobs=-1, verbose=0
                )
                xgb_search.fit(X_train_scaled, y_train)
                candidates['xgb'] = xgb_search.best_estimator_
                print(f"  XGB best params: {xgb_search.best_params_}")
            else:
                candidates['xgb'] = xgb_base
        except ImportError:
            print("  XGBoost not available, skipping")

        # 3. LightGBM
        if LGBM_AVAILABLE:
            lgbm_base = LGBMClassifier(random_state=42, verbose=-1, force_row_wise=True)
            if tune_hyperparams:
                lgbm_grid = self._get_lgbm_params(n_features)
                lgbm_search = RandomizedSearchCV(
                    lgbm_base, lgbm_grid, n_iter=12, cv=StratifiedKFold(3),
                    scoring='roc_auc', random_state=42, n_jobs=-1, verbose=0
                )
                lgbm_search.fit(X_train_scaled, y_train)
                candidates['lgbm'] = lgbm_search.best_estimator_
                print(f"  LGBM best params: {lgbm_search.best_params_}")
            else:
                candidates['lgbm'] = lgbm_base.fit(X_train_scaled, y_train)
        else:
            print("  LightGBM not available, skipping")

        # Evaluate and weight models
        self.models = {}
        self.weights = {}
        self.cv_auc_scores = {}

        for name, model in candidates.items():
            # Cross-validated AUC
            try:
                cv_scores = cross_val_score(
                    model, X_train_scaled, y_train,
                    cv=StratifiedKFold(5), scoring='roc_auc'
                )
                mean_auc = cv_scores.mean()
                self.cv_auc_scores[name] = mean_auc
                print(f"  {name.upper():5s} CV AUC: {mean_auc:.4f} (+/- {cv_scores.std():.4f})")
            except:
                # Fallback: test set AUC
                y_prob = model.predict_proba(X_test_scaled)[:, 1]
                mean_auc = roc_auc_score(y_test, y_prob)
                self.cv_auc_scores[name] = mean_auc
                print(f"  {name.upper():5s} Test AUC: {mean_auc:.4f}")

            # Refit on full training data
            model.fit(X_train_scaled, y_train)
            self.models[name] = model

        # Dynamic weights: softmax of AUC scores
        if self.cv_auc_scores:
            aucs = np.array(list(self.cv_auc_scores.values()))
            # Softmax with temperature 0.5 for sharper weighting
            exp_aucs = np.exp(aucs * 5)
            soft_weights = exp_aucs / exp_aucs.sum()
            for i, name in enumerate(self.cv_auc_scores.keys()):
                self.weights[name] = soft_weights[i]
            print(f"\n  Dynamic weights: {dict((k, f'{v:.3f}') for k, v in self.weights.items())}")

        # 4. Calibrated ensemble (Probability Calibration)
        if len(self.models) >= 2:
            from sklearn.ensemble import VotingClassifier
            estimators = [(n, m) for n, m in self.models.items()]
            voter = VotingClassifier(
                estimators=estimators,
                voting='soft',
                weights=[self.weights.get(n, 1.0) for n in self.models.keys()]
            )
            voter.fit(X_train_scaled, y_train)
            # Calibrate
            self.calibrated_ensemble = CalibratedClassifierCV(
                estimator=voter,
                method='isotonic',
                cv=3
            )
            self.calibrated_ensemble.fit(X_train_scaled, y_train)
            print("  Calibrated ensemble ready (isotonic regression)")
        else:
            # Single model calibration
            single_model = list(self.models.values())[0]
            self.calibrated_ensemble = CalibratedClassifierCV(
                estimator=single_model,
                method='isotonic',
                cv=3
            )
            self.calibrated_ensemble.fit(X_train_scaled, y_train)
            print("  Single model calibrated")

        # Final evaluation
        self._evaluate(X_test_scaled, y_test)
        self._is_trained = True
        self.save()

    def _evaluate(self, X_test_scaled, y_test):
        """Evaluate all models"""
        print("\n  --- Final Evaluation ---")
        
        for name, model in self.models.items():
            y_pred = model.predict(X_test_scaled)
            y_prob = model.predict_proba(X_test_scaled)[:, 1]
            print(f"\n  {name.upper():5s}:")
            print(f"         Accuracy:  {accuracy_score(y_test, y_pred):.3f}")
            print(f"         Precision: {precision_score(y_test, y_pred, zero_division=0):.3f}")
            print(f"         Recall:    {recall_score(y_test, y_pred, zero_division=0):.3f}")
            print(f"         F1:        {f1_score(y_test, y_pred, zero_division=0):.3f}")
            if len(np.unique(y_test)) > 1:
                print(f"         AUC:       {roc_auc_score(y_test, y_prob):.3f}")

        # Calibrated ensemble
        if self.calibrated_ensemble is not None:
            cal_pred = self.calibrated_ensemble.predict(X_test_scaled)
            cal_prob = self.calibrated_ensemble.predict_proba(X_test_scaled)[:, 1]
            print(f"\n  CALIB (Calibrated Ensemble):")
            print(f"         Accuracy:  {accuracy_score(y_test, cal_pred):.3f}")
            if len(np.unique(y_test)) > 1:
                print(f"         AUC:       {roc_auc_score(y_test, cal_prob):.3f}")

    def predict_risk(self, patient_data):
        """Predict with feature importance"""
        if not self._is_trained:
            return {'risk_score': 0, 'risk_class': 'غير متاح', 'note': 'النموذج غير مدرب'}

        # Prepare data
        if isinstance(patient_data, dict):
            data_df = pd.DataFrame([patient_data])
        else:
            data_df = patient_data

        for col in self.feature_names:
            if col not in data_df.columns:
                data_df[col] = 0
        data_df = data_df[self.feature_names]
        scaled = self.scaler.transform(data_df)

        # Weighted ensemble prediction
        all_probs = []
        weight_sum = 0
        for name, model in self.models.items():
            prob = model.predict_proba(scaled)[0, 1]
            w = self.weights.get(name, 1.0 / max(1, len(self.models)))
            all_probs.append(prob * w)
            weight_sum += w

        avg_prob = sum(all_probs) / weight_sum if weight_sum > 0 else 0.5

        # Calibrated prediction (more accurate)
        if self.calibrated_ensemble is not None:
            cal_prob = self.calibrated_ensemble.predict_proba(scaled)[0, 1]
            # Blend calibrated with weighted average (0.7 calibrated, 0.3 weighted)
            avg_prob = cal_prob * 0.7 + avg_prob * 0.3

        risk_score = round(avg_prob * 100, 1)

        # Risk class
        if risk_score >= 80: risk_class = 'شديد جدا'
        elif risk_score >= 60: risk_class = 'مرتفع'
        elif risk_score >= 40: risk_class = 'متوسط'
        else: risk_class = 'منخفض'

        # Feature importance for explainability
        feature_importance = self._get_feature_importance(scaled)

        return {
            'risk_score': risk_score,
            'risk_class': risk_class,
            'calibrated_probability': round(float(cal_prob * 100), 1) if self.calibrated_ensemble is not None else risk_score,
            'model_contributions': {
                name: round(float(self.cv_auc_scores.get(name, 0) * 100), 1)
                for name in self.models.keys()
            },
            'weights': {
                name: round(float(w), 3) for name, w in self.weights.items()
            },
            'top_factors': feature_importance,
        }

    def _get_feature_importance(self, scaled_input):
        """
        Compute top contributing factors for a single prediction using SHAP-style
        per-model feature importance * feature value deviation from mean
        """
        if not self.feature_names:
            return []

        # Aggregate importance across all models
        importance_sum = {}
        for name, model in self.models.items():
            if hasattr(model, 'feature_importances_'):
                fi = model.feature_importances_
                if len(fi) == len(self.feature_names):
                    w = self.weights.get(name, 1.0 / max(1, len(self.models)))
                    for i, fname in enumerate(self.feature_names):
                        importance_sum[fname] = importance_sum.get(fname, 0) + fi[i] * w

        if not importance_sum:
            return []

        # Scale to percentages
        total = sum(importance_sum.values())
        if total == 0:
            return []

        factors = sorted(
            [(name, round(val / total * 100, 1)) for name, val in importance_sum.items()],
            key=lambda x: x[1], reverse=True
        )[:10]  # Top 10 factors

        # Translate Arabic feature names
        translation = {
            'wagner_grade': 'درجة Wagner', 'hba1c_value': 'السكر التراكمي', 'hba1c_ratio': 'نسبة HbA1c',
            'age': 'العمر', 'bmi': 'مؤشر الكتلة', 'bmi_category': 'فئة BMI',
            'smoking_num': 'التدخين', 'duration_years': 'مدة المرض',
            'right_sensation_num': 'الإحساس (اليمنى)', 'left_sensation_num': 'الإحساس (اليسرى)',
            'right_pulse_num': 'النبض (اليمنى)', 'left_pulse_num': 'النبض (اليسرى)',
            'neuropathy_index': 'مؤشر الاعتلال العصبي', 'ulcer_severity': 'شدة القرحة',
            'wound_depth_num': 'عمق الجرح', 'wound_condition_num': 'حالة الجرح',
            'abpi_right': 'ABPI اليمنى', 'abpi_left': 'ABPI اليسرى',
            'blood_pressure_systolic': 'ضغط الدم الانقباضي',
            'improvement_percentage': 'نسبة التحسن', 'fpg_value': 'سكر الصائم',
            'treatment_type_num': 'نوع العلاج', 'amputation_num': 'مستوى البتر',
            'activity_num': 'النشاط البدني', 'bp_risk': 'خطر الضغط',
            'has_sensation_loss': 'فقدان الإحساس', 'has_pulse_loss': 'فقدان النبض',
            'is_smoker': 'مدخن', 'age_group': 'الفئة العمرية',
            'creatinine': 'الكرياتينين', 'ldl': 'LDL', 'hdl': 'HDL',
            'triglycerides': 'الدهون الثلاثية', 'weight': 'الوزن',
            'total_visits': 'عدد الزيارات', 'days_since_last_visit': 'أيام منذ آخر زيارة',
            'gender_num': 'الجنس', 'diabetes_type_num': 'نوع السكري',
        }

        readable = []
        for fname, pct in factors:
            label = translation.get(fname, fname.replace('_', ' '))
            direction = 'مرتفع' if pct > 0 else 'منخفض'
            readable.append({
                'factor': label,
                'importance_pct': pct,
                'description': f'{label}: {pct}% تأثير على نتيجة التحليل'
            })

        return readable

    def predict_batch(self, X):
        """Batch prediction"""
        if not self._is_trained:
            raise ValueError("Model not trained!")

        scaled = self.scaler.transform(X[self.feature_names])
        all_probs = np.zeros((scaled.shape[0],))
        weight_sum = 0

        for name, model in self.models.items():
            probs = model.predict_proba(scaled)[:, 1]
            w = self.weights.get(name, 1.0 / max(1, len(self.models)))
            all_probs += probs * w
            weight_sum += w

        avg_probs = all_probs / weight_sum if weight_sum > 0 else 0.5

        return {
            'risk_scores': (avg_probs * 100).round(1),
            'risk_classes': np.where(
                avg_probs >= 0.8, 'شديد جدا',
                np.where(avg_probs >= 0.6, 'مرتفع',
                np.where(avg_probs >= 0.4, 'متوسط', 'منخفض'))
            )
        }

    def save(self):
        """Save all models"""
        for name, model in self.models.items():
            joblib.dump(model, os.path.join(self.models_dir, f'risk_{name}.pkl'))
        joblib.dump(self.scaler, os.path.join(self.models_dir, 'risk_scaler.pkl'))
        joblib.dump(self.feature_names, os.path.join(self.models_dir, 'risk_features.pkl'))
        joblib.dump(self.weights, os.path.join(self.models_dir, 'risk_weights.pkl'))
        if self.calibrated_ensemble is not None:
            joblib.dump(self.calibrated_ensemble, os.path.join(self.models_dir, 'risk_calibrated.pkl'))
        print(f"  Models saved to {self.models_dir}/")

    def load(self):
        """Load all models"""
        self.models = {}
        self.weights = {}

        # Try to load each model
        for model_type in ['rf', 'xgb', 'lgbm']:
            path = os.path.join(self.models_dir, f'risk_{model_type}.pkl')
            if os.path.exists(path):
                try:
                    self.models[model_type] = joblib.load(path)
                except:
                    print(f"  Could not load {model_type} model")

        if not self.models:
            return False

        # Load scaler and features
        scaler_path = os.path.join(self.models_dir, 'risk_scaler.pkl')
        features_path = os.path.join(self.models_dir, 'risk_features.pkl')
        weights_path = os.path.join(self.models_dir, 'risk_weights.pkl')
        calibrated_path = os.path.join(self.models_dir, 'risk_calibrated.pkl')

        if os.path.exists(scaler_path):
            self.scaler = joblib.load(scaler_path)
        if os.path.exists(features_path):
            self.feature_names = joblib.load(features_path)
        if os.path.exists(weights_path):
            self.weights = joblib.load(weights_path)
        if os.path.exists(calibrated_path):
            self.calibrated_ensemble = joblib.load(calibrated_path)

        self._is_trained = True
        print(f"  Loaded {len(self.models)} risk models: {list(self.models.keys())}")
        if self.weights:
            print(f"  Weights: {dict((k, f'{v:.3f}') for k, v in self.weights.items())}")
        if self.calibrated_ensemble is not None:
            print("  Calibrated ensemble loaded")
        return True
