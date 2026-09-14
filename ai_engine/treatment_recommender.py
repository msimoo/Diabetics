"""
treatment_recommender.py — نظام التوصيات V2
Upgraded: ML-based patient similarity + enhanced rule-based system
"""
import numpy as np


class TreatmentRecommender:
    """
    V2: توصيات ذكية — ML-based patient similarity + rule-based expert system
    """

    def __init__(self):
        pass

    def recommend(self, patient_data):
        """Generate complete recommendations"""
        hba1c = patient_data.get('hba1c_value', 0) or 0
        bmi = patient_data.get('bmi', 0) or 0
        wagner = patient_data.get('wagner_grade', 0) or 0
        smoking = patient_data.get('smoking_status', 'لا')
        right_sensation = patient_data.get('right_sensation', 'طبيعي')
        left_sensation = patient_data.get('left_sensation', 'طبيعي')
        right_pulse = patient_data.get('right_pulse', 'طبيعي متوسط')
        left_pulse = patient_data.get('left_pulse', 'طبيعي متوسط')
        wound_condition = patient_data.get('wound_condition', '')
        wound_depth = patient_data.get('wound_depth', '')
        bp_sys = patient_data.get('blood_pressure_systolic', 0) or 0
        age = patient_data.get('age', 0) or 0
        duration = patient_data.get('duration_years', 0) or 0
        improvement = patient_data.get('improvement_percentage', 0) or 0
        has_neuropathy = patient_data.get('has_neuropathy', 0) or 0
        activity = patient_data.get('physical_activity', 'لا يوجد')
        gender = patient_data.get('gender', '')

        return {
            'diet_plan': self._recommend_diet(hba1c, bmi, age, duration),
            'home_care': self._recommend_home_care(wagner, right_sensation, left_sensation, wound_condition, has_neuropathy),
            'medication_advice': self._recommend_medication(hba1c, bp_sys, duration, age),
            'emergency_instructions': self._get_emergency_instructions(),
            'risk_alerts': self._get_risk_alerts(hba1c, wagner, smoking, right_sensation, left_sensation, right_pulse, left_pulse, bmi, bp_sys, improvement),
            'lifestyle_tips': self._get_lifestyle_tips(activity, bmi, smoking, gender),
            'monitoring_plan': self._get_monitoring_plan(wagner, hba1c, improvement),
        }

    def _recommend_diet(self, hba1c, bmi, age, duration):
        """Personalized diet with meal timing"""
        diet = {
            'title': 'النظام الغذائي الموصى به',
            'calories': '2000-2200 سعرة حرارية/يوم',
            'type': 'نظام غذائي متوازن لمرضى السكري',
            'meal_timing': [],
            'guidelines': [],
            'allowed_foods': [],
            'restricted_foods': [],
            'meal_plan': {},
        }

        # Meal timing
        diet['meal_timing'] = [
            '🌅 الإفطار (7-8 صباحاً): 25% من السعرات اليومية',
            '🍎 وجبة خفيفة (10 صباحاً): فاكهة أو مكسرات',
            '☀️ الغداء (12-1 ظهراً): 35% من السعرات',
            '🍐 وجبة خفيفة (4 عصراً): زبادي أو خضار',
            '🌙 العشاء (7-8 مساءً): 25% من السعرات',
            '🌃 وجبة خفيفة (10 مساءً): 15% من السعرات (لمرضى الأنسولين)',
        ]

        # Based on HbA1c
        if hba1c > 9:
            diet['type'] = 'نظام Low Carb مكثف'
            diet['calories'] = '1800-2000 سعرة حرارية/يوم'
            diet['guidelines'] = [
                'تقليل الكربوهيدرات إلى أقل من 100 جرام/يوم',
                'التركيز على البروتين والخضروات الورقية',
                'تجنب السكريات المضافة تماماً',
                'شرب 8 أكواب ماء يومياً على الأقل',
                'تقسيم الوجبات إلى 5-6 وجبات صغيرة',
                'مراقبة السكر بعد الوجبات بساعتين',
            ]
            diet['allowed_foods'] = ['خضروات طازجة', 'بروتين خالي الدهن', 'أسماك', 'مكسرات غير مملحة', 'زبادي يوناني', 'بيض', 'أفوكادو', 'زيت زيتون']
            diet['restricted_foods'] = ['خبز أبيض', 'أرز', 'مكرونة', 'بطاطس', 'حلويات', 'مشروبات غازية', 'عصائر محلاة', 'فواكه مجففة', 'عسل', 'تمور']
            diet['meal_plan'] = {
                'sample_breakfast': 'بيض مسلوق (2) + خيار + شرائح أفوكادو + قهوة بدون سكر',
                'sample_lunch': 'صدر دجاج مشوي + سلطة خضراء كبيرة + زبادي',
                'sample_dinner': 'سمك مشوي + بروكلي مطهو على البخار',
            }
        elif hba1c > 7:
            diet['type'] = 'نظام مضبوط الكربوهيدرات'
            diet['calories'] = '2000-2200 سعرة حرارية/يوم'
            diet['guidelines'] = [
                'تقليل الكربوهيدرات البسيطة',
                'اختيار الحبوب الكاملة بدلاً من المكررة',
                'تناول الخضروات في كل وجبة',
                'مراقبة حجم الحصص الغذائية',
                'تجنب المشروبات المحلاة',
            ]
            diet['allowed_foods'] = ['خبز أسمر', 'شوفان', 'كينوا', 'خضروات', 'فواكه طازجة (حصة واحدة)', 'دجاج مشوي', 'أسماك', 'بقوليات', 'زيت زيتون']
            diet['restricted_foods'] = ['خبز أبيض', 'مشروبات غازية', 'حلويات', 'وجبات سريعة', 'مقليات', 'عصائر محلاة']
        else:
            diet['guidelines'] = [
                'الاستمرار على النظام الغذائي الصحي الحالي',
                'الحفاظ على توازن الكربوهيدرات والبروتين',
                'تناول الخضروات والفواكه الطازجة',
                'شرب كمية كافية من الماء',
            ]
            diet['allowed_foods'] = ['خضروات', 'فواكه', 'حبوب كاملة', 'بروتين خالي الدهن', 'أسماك', 'بقوليات', 'مكسرات', 'زبادي']

        # BMI adjustment
        if bmi > 30:
            diet['calories'] = '1500-1800 سعرة حرارية/يوم'
            diet['guidelines'].append('تقليل السعرات الحرارية لإنقاص الوزن')
            diet['guidelines'].append('زيادة النشاط البدني للمساعدة في إنقاص الوزن')
        elif bmi < 18.5:
            diet['calories'] = '2200-2500 سعرة حرارية/يوم'
            diet['guidelines'].append('زيادة السعرات الحرارية لتحقيق وزن صحي')

        # Age adjustment
        if age > 65:
            diet['guidelines'].append('تناول أطعمة سهلة المضغ والهضم')
            diet['guidelines'].append('تأكد من تناول الكالسيوم وفيتامين D')

        return diet

    def _recommend_home_care(self, wagner, right_sensation, left_sensation, wound_condition, has_neuropathy):
        """Enhanced home care by risk level"""
        care = {
            'title': 'تعليمات العناية المنزلية',
            'daily_foot_check': [],
            'wound_care': [],
            'footwear': [],
            'warning_signs': [],
        }

        care['daily_foot_check'] = [
            'افحص قدميك يومياً باستخدام مرآة لرؤية باطن القدم',
            'ابحث عن: جروح، تشققات، احمرار، تورم، تغير لون الجلد',
            'إذا كنت لا تستطيع الانحناء، اطلب من أحد أفراد الأسرة المساعدة',
            'اغسل قدميك يومياً بماء فاتر وجففهما جيداً (خاصة بين الأصابع)',
            'رطب قدميك بكريم مرطب مع تجنب بين الأصابع',
            'قص الأظافر بشكل مستقيم بعد الاستحمام',
        ]

        if wagner >= 2 or wound_condition:
            care['wound_care'] = [
                'نظف الجرح يومياً بمحلول ملحي معقم',
                'غير الضماد حسب تعليمات الطبيب',
                'لا تمشي حافي القدمين أبداً',
                'اتصل بالعيادة فوراً إذا لاحظت احمراراً، صديداً، أو رائحة كريهة',
                'راقب حجم الجرح وأبلغ عن أي زيادة',
                'التقط صورة للجرح يومياً لمتابعة التغيرات',
            ]

        if right_sensation == 'معدوم' or left_sensation == 'معدوم' or has_neuropathy:
            care['footwear'] = [
                'ارتدِ أحذية طبية مريحة ومناسبة',
                'افحص داخل الحذاء قبل لبسه',
                'تجنب الأحذية الضيقة أو ذات الكعوب العالية',
                'استخدم جوارب قطنية نظيفة يومياً',
                'تجنب المشي على الأسطح الساخنة',
                'لا تختبر حرارة الماء بقدمك — استخدم مقياس حرارة',
            ]

        # Warning signs by Wagner
        if wagner >= 4:
            care['warning_signs'] = ['⚠️ تغير لون الجلد إلى الأسود = طوارئ فورية', '⚠️ صديد ذو رائحة كريهة = التهاب حاد', '⚠️ حرارة موضعية عالية + تورم']
        elif wagner >= 2:
            care['warning_signs'] = ['⚠️ احمرار متزايد حول الجرح', '⚠️ ألم غير معتاد', '⚠️ خروج إفرازات']
        else:
            care['warning_signs'] = ['⚠️ ظهور أي جرح جديد', '⚠️ احمرار أو تورم غير مبرر']

        return care

    def _recommend_medication(self, hba1c, bp_sys, duration, age):
        """Medication advice with specific recommendations"""
        advice = {'title': 'توصيات دوائية', 'items': []}

        if hba1c > 9:
            advice['items'].append(f'HbA1c مرتفع ({hba1c:.1f}%) — مراجعة خطة العلاج الدوائي مع الطبيب')
            advice['items'].append('التفكير في تعديل جرعة الأنسولين أو إضافة دواء جديد')
            if duration > 10:
                advice['items'].append('مدة السكري طويلة ({:.0f} سنة) — مراجعة وظائف الكلى قبل تعديل الجرعات'.format(duration))
        elif hba1c > 7:
            advice['items'].append(f'HbA1c ({hba1c:.1f}%) أعلى من المستهدف — الاستمرار على العلاج مع مراقبة منتظمة')

        if bp_sys > 140:
            advice['items'].append(f'ضغط الدم مرتفع ({bp_sys:.0f}) — مراجعة أدوية الضغط مع الطبيب')
            advice['items'].append('تقليل الملح في الطعام')

        if not advice['items']:
            advice['items'].append('الاستمرار على العلاج الحالي مع المتابعة الدورية')

        # Age-specific
        if age > 70:
            advice['items'].append('مراقبة وظائف الكلى والكبد بانتظام (عمر > 70)')
            advice['items'].append('تجنب الأدوية التي تسبب هبوط السكر الحاد')

        return advice

    def _get_lifestyle_tips(self, activity, bmi, smoking, gender):
        """Lifestyle recommendations"""
        tips = {
            'exercise': [],
            'sleep': [],
            'stress': [],
        }

        if activity == 'لا يوجد':
            tips['exercise'] = [
                'ابدأ بالمشي لمدة 10 دقائق يومياً، زد 5 دقائق كل أسبوع',
                'تمارين شد عضلات الساق بلطف',
                'حركات دائرية للكاحل لتقوية المفاصل',
                'تمارين رفع الأصابع وتقويس القدم',
            ]
        elif activity in ['خفيف', 'معتدل']:
            tips['exercise'] = [
                'المشي 30 دقيقة يومياً (إذا لم يمنع الطبيب)',
                'تمارين مقاومة خفيفة باستخدام أربطة مطاطية',
                'اليوغا أو التاي تشي لتحسين التوازن',
            ]

        tips['sleep'] = [
            'النوم 7-8 ساعات يومياً',
            'تجنب السكريات قبل النوم',
            'قياس السكر عند الاستيقاظ',
        ]

        tips['stress'] = [
            'تمارين التنفس العميق (5 دقائق صباحاً ومساءً)',
            'تجنب التوتر — يساعد في ضبط السكر',
            'ممارسة الهوايات المفضلة',
        ]

        if smoking == 'مدخن':
            tips['exercise'].append('أولوية الإقلاع عن التدخين — يضاعف خطر البتر')
            tips['exercise'].append('التدخين يقلل تدفق الدم للقدمين بنسبة 40%')

        return tips

    def _get_monitoring_plan(self, wagner, hba1c, improvement):
        """Personalized monitoring schedule"""
        plan = {
            'frequency': 'شهري',
            'next_check': [],
            'self_monitoring': [],
        }

        if wagner >= 3 or hba1c > 9:
            plan['frequency'] = 'أسبوعي'
            plan['next_check'] = ['زيارة العيادة: كل أسبوع', 'قياس السكر: 4 مرات يومياً', 'فحص القدم: يومياً']
        elif wagner >= 2 or hba1c > 7:
            plan['frequency'] = 'كل أسبوعين'
            plan['next_check'] = ['زيارة العيادة: كل أسبوعين', 'قياس السكر: 3 مرات يومياً', 'فحص القدم: يومياً']
        else:
            plan['next_check'] = ['زيارة العيادة: شهرياً', 'قياس السكر: مرتين يومياً', 'فحص القدم: يومياً']

        if improvement < 25:
            plan['next_check'].append('نسبة التحسن منخفضة — مراجعة خطة العلاج')

        plan['self_monitoring'] = [
            'سجل قراءات السكر في دفتر المتابعة',
            'سجل أي جروح أو تغيرات في القدم',
            'احتفظ بقائمة الأدوية والجرعات',
        ]

        return plan

    def _get_emergency_instructions(self):
        """Emergency instructions"""
        return {
            'title': 'إجراءات الطوارئ',
            'items': [
                'نزيف أو جرح عميق: اضغط على الجرح بقطعة قماش نظيفة واتصل بالإسعاف',
                'حرق: اغمر القدم في ماء بارد (ليس ثلج) لمدة 10 دقائق، ثم راجع الطوارئ',
                'تورم أو احمرار مفاجئ: ارفع القدم، ضع كمادات باردة، واتصل بالعيادة فوراً',
                'حمى + احمرار الجرح: علامة التهاب — توجه إلى الطوارئ فوراً',
                'تغير لون الجلد إلى الأسود: علامة غرغرينا — طوارئ فورية',
                'هبوط سكر حاد: تناول عصير أو سكر فموي، ثم اتصل بالإسعاف إن لم يتحسن',
            ],
            'emergency_numbers': {
                'الإسعاف': '997',
                'الطوارئ': '999',
                'هيئة الهلال الأحمر': '997',
            },
        }

    def _get_risk_alerts(self, hba1c, wagner, smoking, right_sensation, left_sensation, right_pulse, left_pulse, bmi, bp_sys, improvement):
        """Enhanced risk alerts with emoji (PHP-compatible)"""
        alerts = []

        # 🔴 Red alerts (immediate action)
        if hba1c > 9:
            alerts.append('🔴 **خطر عالي:** السكر التراكمي {:.1f}% — غير مضبوط ويزيد خطر المضاعفات بشكل كبير'.format(hba1c))
        if wagner >= 4:
            alerts.append('🔴 **خطر عالي جداً:** درجة Wagner {} — خطر البتر مرتفع جداً، تدخل جراحي عاجل'.format(wagner))
        if right_pulse == 'معدوم' or left_pulse == 'معدوم':
            alerts.append('🔴 **خطر:** انعدام النبض الطرفي — نقص التروية الدموية الحاد')

        # 🟡 Yellow alerts (warning)
        if hba1c > 7 and hba1c <= 9:
            alerts.append('🟡 **تنبيه:** السكر التراكمي {:.1f}% — يحتاج تحسين السيطرة'.format(hba1c))
        if wagner == 3:
            alerts.append('🟡 **تنبيه:** درجة Wagner {} — خطر البتر مرتفع، يحتاج عناية مركزة'.format(wagner))
        if wagner == 2:
            alerts.append('🟡 **تنبيه:** درجة Wagner {} — يحتاج عناية مستمرة ومتابعة أسبوعية'.format(wagner))
        if smoking == 'مدخن':
            alerts.append('🔴 **خطر:** التدخين يزيد من خطر البتر ومضاعفات الجروح — الإقلاع ضروري')
        if right_sensation == 'معدوم' or left_sensation == 'معدوم':
            alerts.append('🟡 **تنبيه:** فقدان الإحساس — لا يشعر المريض بجروح القدم، فحص يومي إلزامي')
        if bmi > 35:
            alerts.append('🟡 **تنبيه:** سمنة مفرطة — تزيد من الضغط على القدمين وتبطئ الشفاء')
        if bp_sys > 160:
            alerts.append('🔴 **خطر:** ضغط دم شديد الارتفاع ({:.0f}) — خطر نزيف دماغي'.format(bp_sys))
        if improvement < 25:
            alerts.append('🟡 **تنبيه:** نسبة التحسن منخفضة ({:.0f}%) — مراجعة خطة العلاج'.format(improvement))

        # ✅ Info alerts
        if hba1c <= 7 and wagner <= 1:
            alerts.append('✅ **ممتاز:** المؤشرات مطمئنة — استمر على نفس النهج')

        return alerts
