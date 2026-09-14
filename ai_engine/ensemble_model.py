"""
ensemble_model.py — النموذج المدمج V2
Supports dynamic weighting, calibrated ensemble, and explainability
"""
import os
import numpy as np
from .risk_predictor import RiskPredictor
from .healing_estimator import HealingEstimator
from .treatment_recommender import TreatmentRecommender


class EnsembleModel:
    """
    V2: النموذج المدمج — يربط RF + XGB + LGBM + Recommender في واجهة واحدة
    """

    def __init__(self, models_dir='./models'):
        self.models_dir = models_dir
        self.risk_predictor = RiskPredictor(models_dir)
        self.healing_estimator = HealingEstimator(models_dir)
        self.recommender = TreatmentRecommender()
        self._is_loaded = False

    def load_all(self):
        """Load all models"""
        risk_loaded = self.risk_predictor.load()
        self.healing_estimator.load()
        self._is_loaded = risk_loaded
        return self._is_loaded

    def analyze_patient(self, patient_data):
        """
        Full analysis with explainability
        """
        results = {
            'patient_info': {
                'name': patient_data.get('full_name', ''),
                'age': patient_data.get('age', ''),
                'gender': patient_data.get('gender', ''),
            },
            'risk': {
                'error': True, 'message': 'Model not available'
            },
            'healing': {
                'error': True, 'message': 'Model not available'
            },
            'recommendations': self.recommender.recommend(patient_data),
            'ensemble_score': None,
        }

        if self.risk_predictor._is_trained:
            results['risk'] = self.risk_predictor.predict_risk(patient_data)

        if self.healing_estimator._is_trained:
            results['healing'] = self.healing_estimator.predict_healing_time(patient_data)

        # Calculate ensemble score
        risk_score_val = None
        if not results['risk'].get('error'):
            risk_score_val = results['risk'].get('risk_score', 0)

        healing_score_val = None
        if not results['healing'].get('error'):
            healing_weeks = results['healing'].get('estimated_weeks', 0)
            healing_score_val = max(0, (healing_weeks - 4) * 5)

        if risk_score_val is not None and healing_score_val is not None:
            results['ensemble_score'] = min(100, round((risk_score_val * 0.7 + healing_score_val * 0.3), 1))
        elif risk_score_val is not None:
            results['ensemble_score'] = min(100, round(risk_score_val, 1))
        elif healing_score_val is not None:
            results['ensemble_score'] = min(100, round(healing_score_val, 1))

        if results.get('ensemble_score') is not None:
            if results['ensemble_score'] >= 80:
                results['overall_class'] = 'شديد جداً'
                results['overall_color'] = '#dc2626'
            elif results['ensemble_score'] >= 60:
                results['overall_class'] = 'مرتفع'
                results['overall_color'] = '#ef4444'
            elif results['ensemble_score'] >= 40:
                results['overall_class'] = 'متوسط'
                results['overall_color'] = '#f59e0b'
            else:
                results['overall_class'] = 'منخفض'
                results['overall_color'] = '#10b981'

        return results

    def explain_prediction(self, patient_data):
        """
        Return detailed explanation of predictions with feature importance
        """
        explanation = {
            'patient_info': {
                'name': patient_data.get('full_name', ''),
            },
        }

        if self.risk_predictor._is_trained:
            risk_result = self.risk_predictor.predict_risk(patient_data)
            explanation['risk'] = {
                'score': risk_result.get('risk_score'),
                'class': risk_result.get('risk_class'),
                'calibrated_probability': risk_result.get('calibrated_probability'),
                'weights': risk_result.get('weights'),
                'top_factors': risk_result.get('top_factors', []),
                'model_contributions': risk_result.get('model_contributions'),
            }

        if self.healing_estimator._is_trained:
            healing_result = self.healing_estimator.predict_healing_time(patient_data)
            explanation['healing'] = {
                'estimated_weeks': healing_result.get('estimated_weeks'),
                'confidence_interval': healing_result.get('confidence_interval'),
                'model_details': healing_result.get('model_details'),
            }

        return explanation
