"""
app.py — خادم Flask API للذكاء الاصطناعي
Flask server that serves AI predictions to the PHP frontend

النقاط النهائية (Endpoints):
  POST /api/predict/risk     → توقع الخطر
  POST /api/predict/healing  → توقع مدة الشفاء
  POST /api/recommend        → توصيات العلاج والتغذية
  GET  /api/health           → فحص حالة الخادم

التشغيل:
  python app.py
  # يعمل على http://localhost:5000
"""

import os
import sys
import json
import logging
try:
    sys.stdout.reconfigure(encoding='utf-8')
    sys.stderr.reconfigure(encoding='utf-8')
except:
    pass

# إضافة المجلد الرئيسي
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from flask import Flask, request, jsonify
from flask_cors import CORS

from ai_engine.ensemble_model import EnsembleModel

# إعداد السجلات
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

app = Flask(__name__)
CORS(app)  # السماح بالاتصال من PHP

# مسار النماذج
MODELS_DIR = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'models')

# إنشاء النموذج المدمج
ensemble = EnsembleModel(MODELS_DIR)
ensemble.load_all()  # Try loading all models
risk_loaded = ensemble.risk_predictor._is_trained
healing_loaded = ensemble.healing_estimator._is_trained

if risk_loaded:
    logger.info("[OK] Risk prediction models loaded")
else:
    logger.warning("[WARN] Risk models not found - using rule-based fallback")

if healing_loaded:
    logger.info("[OK] Healing estimation models loaded")
else:
    logger.warning("[WARN] Healing models not found - using rule-based fallback")


# ===== نقطة فحص الصحة =====
@app.route('/api/health', methods=['GET', 'POST'])
def health_check():
    """فحص حالة خادم AI"""
    return jsonify({
        'status': 'ok',
        'risk_loaded': risk_loaded,
        'healing_loaded': healing_loaded,
        'version': '1.0.0',
        'message': 'AI Engine is running. Diabetes Clinic Intelligence System.',
    })


# ===== نقطة التنبؤ بالخطر =====
@app.route('/api/predict/risk', methods=['POST'])
def predict_risk():
    """
    التنبؤ بخطر تدهور القرحة أو الحاجة إلى البتر
    
    المدخلات (JSON):
        {
            "hba1c_value": 9.5,
            "wagner_grade": 3,
            "bmi": 32,
            "age": 60,
            "smoking_status": "مدخن",
            "right_sensation": "معدوم",
            "left_sensation": "منخفض",
            "right_pulse": "ضعيف",
            "left_pulse": "طبيعي متوسط",
            "blood_pressure_systolic": 145,
            "wound_condition": "صديد",
            "wound_depth": "العضلات",
            "duration_years": 15,
            "improvement_percentage": 25,
            "diabetes_type": "النوع الثاني (Type 2)"
        }
    
    المخرجات (JSON):
        {
            "risk_score": 85.3,
            "risk_class": "شديد جداً",
            "probability": 85.3,
            "rf_probability": 82.0,
            "xgb_probability": 88.5,
            "factors": [...]
        }
    """
    try:
        data = request.get_json()
        if not data:
            return jsonify({'error': 'Missing data. Send JSON in request body.'}), 400
        
        logger.info(f"[REQ] Risk prediction for: {data.get('full_name', 'Unknown')}")
        
        # تحويل البيانات إلى أرقام
        patient_data = _prepare_patient_data(data)
        
        if risk_loaded:
            result = ensemble.risk_predictor.predict_risk(patient_data)
        else:
            # استخدام الخوارزمية المبسطة
            result = _simple_risk_prediction(patient_data)
        
        result = _convert_numpy(result)
        logger.info(f"[RESULT] Risk score: {result.get('risk_score', 0)}% - {result.get('risk_class', 'N/A')}")
        return jsonify(result)
    
    except Exception as e:
        logger.error(f"❌ خطأ في التنبؤ بالخطر: {e}")
        return jsonify({'error': f'❌ خطأ: {str(e)}'}), 500


# ===== نقطة تقدير مدة الشفاء =====
@app.route('/api/predict/healing', methods=['POST'])
def predict_healing():
    """
    تقدير عدد الأسابيع اللازمة للشفاء
    
    المدخلات (JSON): نفس بيانات risk
    
    المخرجات (JSON):
        {
            "estimated_weeks": 8.5,
            "confidence_interval": [6.5, 10.5],
            "rf_estimate": 8.2,
            "gb_estimate": 8.8
        }
    """
    try:
        data = request.get_json()
        if not data:
            return jsonify({'error': 'Missing data'}), 400
        
        logger.info(f"[REQ] Healing estimate for: {data.get('full_name', 'Unknown')}")
        
        patient_data = _prepare_patient_data(data)
        
        if healing_loaded:
            result = ensemble.healing_estimator.predict_healing_time(patient_data)
        else:
            result = _simple_healing_estimate(patient_data)
        
        result = _convert_numpy(result)
        logger.info(f"[RESULT] Healing estimate: {result.get('estimated_weeks', 0)} weeks")
        return jsonify(result)
    
    except Exception as e:
        logger.error(f"❌ خطأ في تقدير الشفاء: {e}")
        return jsonify({'error': f'❌ خطأ: {str(e)}'}), 500


# ===== نقطة التوصيات =====
@app.route('/api/recommend', methods=['POST'])
def recommend():
    """
    توصيات العلاج والتغذية والعناية المنزلية
    
    المدخلات (JSON): نفس البيانات
    
    المخرجات (JSON):
        {
            "diet_plan": {...},
            "home_care": {...},
            "medication_advice": {...},
            "emergency_instructions": {...},
            "risk_alerts": [...]
        }
    """
    try:
        data = request.get_json()
        if not data:
            return jsonify({'error': 'Missing data. Send JSON in request body.'}), 400
        
        logger.info(f"[REQ] Recommendations for: {data.get('full_name', 'Unknown')}")
        
        recommendations = ensemble.recommender.recommend(data)
        
        logger.info(f"[RESULT] Recommendations generated")
        return jsonify(recommendations)
    
    except Exception as e:
        logger.error(f"[ERR] Recommendations error: {e}")
        return jsonify({'error': str(e)}), 500


# ===== نقطة التحليل الكامل =====
@app.route('/api/analyze', methods=['POST'])
def analyze_full():
    """
    تحليل كامل لمريض (جميع النماذج)
    
    المخرجات: risk + healing + recommendations في استجابة واحدة
    """
    try:
        data = request.get_json()
        if not data:
            return jsonify({'error': 'Missing data. Send JSON in request body.'}), 400
        
        logger.info(f"[REQ] Full analysis for: {data.get('full_name', 'Unknown')}")
        
        patient_data = _prepare_patient_data(data)
        results = ensemble.analyze_patient(patient_data)
        results = _convert_numpy(results)
        
        logger.info(f"[RESULT] Full analysis complete")
        return jsonify(results)
    
    except Exception as e:
        logger.error(f"[ERR] Analysis error: {e}")
        return jsonify({'error': str(e)}), 500


# ===== نقطة شرح التوقعات (Explainability) =====
@app.route('/api/explain', methods=['POST'])
def explain_prediction():
    """
    شرح تفصيلي لتوقعات الذكاء الاصطناعي مع أهم العوامل المؤثرة
    
    المدخلات: نفس بيانات /api/analyze
    
    المخرجات:
        {
            "risk": {
                "score": 85.3,
                "top_factors": [
                    {"factor": "درجة Wagner", "importance_pct": 35.0, "description": "Wagner 3: 35% تأثير"},
                    ...
                ],
                "weights": {"rf": 0.42, "xgb": 0.33, "lgbm": 0.25},
                "model_contributions": {"rf": 85.2, "xgb": 90.1}
            },
            "healing": {
                "estimated_weeks": 8.5,
                "model_details": {"rf": 8.2, "gb": 8.8}
            }
        }
    """
    try:
        data = request.get_json()
        if not data:
            return jsonify({'error': 'Missing data'}), 400

        patient_data = _prepare_patient_data(data)
        explanation = ensemble.explain_prediction(patient_data)
        explanation = _convert_numpy(explanation)

        return jsonify(explanation)
    except Exception as e:
        logger.error(f"[ERR] Explain error: {e}")
        return jsonify({'error': str(e)}), 500


# ===== دوال مساعدة =====
def _convert_numpy(obj):
    """Convert numpy types to native Python types for JSON serialization"""
    import numpy as np
    if isinstance(obj, dict):
        return {k: _convert_numpy(v) for k, v in obj.items()}
    elif isinstance(obj, list):
        return [_convert_numpy(v) for v in obj]
    elif isinstance(obj, np.integer):
        return int(obj)
    elif isinstance(obj, np.floating):
        return float(obj)
    elif isinstance(obj, np.ndarray):
        return _convert_numpy(obj.tolist())
    else:
        return obj


def _prepare_patient_data(data):
    """تحويل البيانات النصية إلى أرقام للنماذج"""
    patient_data = {}
    
    # تعيين القيم النصية مباشرة (سيتم تحويلها بواسطة transformer)
    for key in ['full_name', 'gender', 'smoking_status', 'diabetes_type',
                'right_sensation', 'left_sensation', 'right_pulse', 'left_pulse',
                'wound_condition', 'wound_depth', 'initial_cause', 'physical_activity']:
        patient_data[key] = data.get(key, '')
    
    # تعيين القيم الرقمية
    numeric_fields = {
        'age': 0, 'bmi': 0, 'hba1c_value': 0, 'fpg_value': 0,
        'wagner_grade': 0, 'abpi_right': 1, 'abpi_left': 1,
        'blood_pressure_systolic': 0, 'blood_pressure_diastolic': 0,
        'improvement_percentage': 0, 'wound_size_cm2': 0,
        'duration_years': 0, 'weight': 0, 'height': 0,
        'total_visits': 0, 'days_since_last_visit': 0,
        'ldl': 0, 'hdl': 0, 'creatinine': 0, 'triglycerides': 0,
    }
    
    for field, default in numeric_fields.items():
        val = data.get(field, default)
        try:
            patient_data[field] = float(val) if val else default
        except (ValueError, TypeError):
            patient_data[field] = default
    
    # حساب المتغيرات المشتقة
    patient_data['has_sensation_loss'] = 1 if (
        patient_data.get('right_sensation', '') == 'معدوم' or 
        patient_data.get('left_sensation', '') == 'معدوم'
    ) else 0
    
    patient_data['has_pulse_loss'] = 1 if (
        patient_data.get('right_pulse', '') == 'معدوم' or 
        patient_data.get('left_pulse', '') == 'معدوم'
    ) else 0
    
    patient_data['is_smoker'] = 1 if patient_data.get('smoking_status', '') == 'مدخن' else 0
    
    return patient_data


def _simple_risk_prediction(patient_data):
    """خوارزمية مبسطة للتنبؤ بالخطر (في حال عدم وجود نماذج مدربة)"""
    score = 0
    
    hba1c = float(patient_data.get('hba1c_value', 0))
    wagner = int(patient_data.get('wagner_grade', 0))
    bmi = float(patient_data.get('bmi', 0))
    age = int(patient_data.get('age', 0))
    
    if hba1c > 10: score += 25
    elif hba1c > 8: score += 15
    elif hba1c > 7: score += 8
    
    if wagner >= 4: score += 25
    elif wagner >= 3: score += 18
    elif wagner >= 2: score += 10
    
    if patient_data.get('is_smoker'): score += 10
    if patient_data.get('has_sensation_loss'): score += 10
    if patient_data.get('has_pulse_loss'): score += 10
    if bmi > 30: score += 5
    if age > 65: score += 5
    
    score = min(score, 100)
    
    if score >= 80: risk_class = 'شديد جداً'
    elif score >= 60: risk_class = 'مرتفع'
    elif score >= 40: risk_class = 'متوسط'
    else: risk_class = 'منخفض'
    
    return {
        'risk_score': float(score),
        'risk_class': risk_class,
        'probability': float(score),
        'rf_probability': float(score),
        'xgb_probability': float(score),
        'note': 'تم استخدام الخوارزمية المبسطة (النماذج غير مدربة)',
    }


def _simple_healing_estimate(patient_data):
    """تقدير مبسط لمدة الشفاء (في حال عدم وجود نماذج مدربة)"""
    wagner = int(patient_data.get('wagner_grade', 0))
    hba1c = float(patient_data.get('hba1c_value', 0))
    
    # قاعدة تقريبية: Wagner * 2 + HbA1c * 0.5
    weeks = max(2, wagner * 2 + hba1c * 0.5)
    
    return {
        'estimated_weeks': round(weeks, 1),
        'confidence_interval': [round(max(1, weeks - 2), 1), round(weeks + 2, 1)],
        'rf_estimate': round(weeks, 1),
        'gb_estimate': round(weeks + 0.5, 1),
        'note': 'تقدير مبني على القواعد (النماذج غير مدربة)',
    }


# ===== نقطة البداية =====
if __name__ == '__main__':
    print("=" * 60)
    print("Diabetes Clinic AI Engine -- Flask API Server")
    print("=" * 60)
    print(f"Models: {MODELS_DIR}")
    print(f"Server: http://localhost:5000")
    print(f"Endpoints:")
    print(f"   GET  /api/health")
    print(f"   POST /api/predict/risk")
    print(f"   POST /api/predict/healing")
    print(f"   POST /api/recommend")
    print(f"   POST /api/analyze")
    print()
    print("Test: open http://localhost:5000/api/health in browser")
    print("=" * 60)
    
    app.run(host='0.0.0.0', port=5000, debug=False)
