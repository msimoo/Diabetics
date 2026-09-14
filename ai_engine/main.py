"""
main.py — الواجهة الرئيسية لمحرك الذكاء الاصطناعي
Main interface for running all AI models
"""
import os
import sys
import json
try:
    sys.stdout.reconfigure(encoding='utf-8')
except:
    pass

# إضافة المجلد الرئيسي
sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from ai_engine.ensemble_model import EnsembleModel
from ai_engine.trainer import train_all


def run_training():
    """تشغيل تدريب النماذج"""
    print("🏋️ بدء تدريب نماذج الذكاء الاصطناعي...")
    success = train_all()
    if success:
        print("\n✅ التدريب اكتمل. يمكنك الآن تشغيل خادم Flask:")
        print("   python ai_api/app.py")
    return success


def run_inference(patient_data_file=None):
    """
    تشغيل الاستدلال (Inference) على بيانات مريض
    
    المدخلات:
        patient_data_file: مسار ملف JSON ببيانات المريض
    """
    if patient_data_file and os.path.exists(patient_data_file):
        with open(patient_data_file, 'r', encoding='utf-8') as f:
            patient_data = json.load(f)
    else:
        # بيانات تجريبية للاختبار
        patient_data = {
            'full_name': 'مريض تجريبي',
            'age': 60,
            'gender': 'ذكر',
            'hba1c_value': 9.5,
            'bmi': 32,
            'wagner_grade': 3,
            'smoking_status': 'مدخن',
            'right_sensation': 'معدوم',
            'left_sensation': 'منخفض',
            'right_pulse': 'ضعيف',
            'left_pulse': 'طبيعي متوسط',
            'blood_pressure_systolic': 145,
            'wound_condition': 'صديد',
            'wound_depth': 'العضلات',
            'improvement_percentage': 25,
            'duration_years': 15,
            'diabetes_type': 'النوع الثاني (Type 2)',
        }
        print("📝 استخدام بيانات تجريبية للاختبار")
    
    print(f"🔍 تحليل بيانات المريض: {patient_data.get('full_name', 'غير معروف')}")
    
    # تحميل النماذج
    models_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'ai_api', 'models')
    ensemble = EnsembleModel(models_dir)
    loaded = ensemble.load_all()
    
    if not loaded:
        print("\n⚠️ لم يتم العثور على نماذج مدربة!")
        print("هل قمت بتشغيل التدريب أولاً؟ قم بتشغيل:")
        print("   python -m ai_engine.main --train")
        print("\nسيتم استخدام نظام التوصيات المبني على القواعد فقط.")
    
    # تحليل المريض
    results = ensemble.analyze_patient(patient_data)
    
    # عرض النتائج
    print("\n" + "=" * 60)
    print("📊 نتائج التحليل")
    print("=" * 60)
    
    risk = results.get('risk', {})
    healing = results.get('healing', {})
    recommendations = results.get('recommendations', {})
    
    print(f"\n🔮 **مخاطر المريض:**")
    if not risk.get('error'):
        print(f"   درجة الخطر: {risk.get('risk_score', 'N/A')}%")
        print(f"   التصنيف: {risk.get('risk_class', 'N/A')}")
        print(f"   Random Forest: {risk.get('rf_probability', 'N/A')}%")
        print(f"   XGBoost: {risk.get('xgb_probability', 'N/A')}%")
    
    if not healing.get('error'):
        print(f"\n⏱️ **مدة الشفاء المتوقعة:**")
        print(f"   {healing.get('estimated_weeks', 'N/A')} أسبوع")
        print(f"   فترة الثقة: {healing.get('confidence_interval', ['N/A'])}")
    
    if results.get('ensemble_score') is not None:
        print(f"\n🎯 **النتيجة المدمجة:** {results['ensemble_score']}/100")
        print(f"   التصنيف: {results.get('overall_class', 'N/A')}")
    
    if recommendations:
        diet = recommendations.get('diet_plan', {})
        if diet:
            print(f"\n🥗 **النظام الغذائي:**")
            print(f"   {diet.get('type', 'N/A')} — {diet.get('calories', '')}")
        
        alerts = recommendations.get('risk_alerts', [])
        if alerts:
            print(f"\n⚠️ **التنبيهات ({len(alerts)}):**")
            for a in alerts:
                print(f"   • {a}")
    
    return results


def main():
    """الواجهة الرئيسية"""
    import argparse
    parser = argparse.ArgumentParser(description='محرك الذكاء الاصطناعي لعيادة السكري')
    parser.add_argument('--train', action='store_true', help='تدريب النماذج')
    parser.add_argument('--predict', type=str, help='التنبؤ على ملف JSON', metavar='patient.json')
    parser.add_argument('--all', action='store_true', help='تشغيل التدريب ثم التنبؤ')
    
    args = parser.parse_args()
    
    if args.train or args.all:
        run_training()
    
    if args.predict or args.all:
        run_inference(args.predict if not args.all else None)
    
    if not args.train and not args.predict and not args.all:
        print("🏥 Diabetes Clinic AI Engine")
        print("=" * 50)
        print("الخيارات:")
        print("  --train          تدريب النماذج")
        print("  --predict FILE   التنبؤ على مريض")
        print("  --all            تشغيل التدريب والتنبؤ")
        print("\nأمثلة:")
        print("  python -m ai_engine.main --all")
        print("  python -m ai_engine.main --train")
        print("  python -m ai_engine.main --predict patient.json")


if __name__ == '__main__':
    main()
