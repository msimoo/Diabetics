<?php
/**
 * Diet Plan Module
 * Generates personalized diet plans and meal suggestions for diabetic patients
 */
$page_title = 'النظام الغذائي | Diet Plan';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/navbar.php';
require_once __DIR__ . '/../../includes/sidebar.php';

$page_title = 'النظام الغذائي';
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

$patient = null;
if ($patient_id) {
    $stmt = $mysqli->prepare("SELECT *, TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) AS age FROM patients WHERE patient_id = ? AND is_active = 1");
    $stmt->bind_param('i', $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result->fetch_assoc();
}

$page_title = 'النظام الغذائي | Diet Plan';

// Meal plan data
$meals = [
    'الإفطار' => [
        'time' => '7:00 - 8:00 صباحاً',
        'icon' => '🌅',
        'options' => [
            ['name' => 'شوفان بالحليب', 'ingredients' => 'شوفان كامل، حليب قليل الدسم، قرفة، موزة صغيرة', 'calories' => '350 سعرة'],
            ['name' => 'بيض مسلوق مع توست', 'ingredients' => 'بيضتان مسلوقتان، شريحة توست أسمر، جبنة قليلة الدسم', 'calories' => '320 سعرة'],
            ['name' => 'زبادي بالفواكه', 'ingredients' => 'زبادي يوناني، توت طازج، ملعقة صغيرة عسل', 'calories' => '280 سعرة'],
        ]
    ],
    'وجبة خفيفة' => [
        'time' => '10:00 صباحاً',
        'icon' => '🥜',
        'options' => [
            ['name' => 'مكسرات', 'ingredients' => 'حفنة من اللوز أو الجوز (غير مملح)', 'calories' => '150 سعرة'],
            ['name' => 'فاكهة', 'ingredients' => 'تفاحة أو إجاصة أو حبة برتقال', 'calories' => '80 سعرة'],
            ['name' => 'جزر', 'ingredients' => 'أصابع جزر طازج مع حمص', 'calories' => '100 سعرة'],
        ]
    ],
    'الغداء' => [
        'time' => '1:00 - 2:00 ظهراً',
        'icon' => '🍛',
        'options' => [
            ['name' => 'دجاج مشوي مع خضار', 'ingredients' => 'صدر دجاج مشوي، خضروات سوتيه، أرز بني', 'calories' => '480 سعرة'],
            ['name' => 'سمك سلمون مع سلطة', 'ingredients' => 'شريحة سلمون مشوي، سلطة خضراء مع زيت زيتون', 'calories' => '420 سعرة'],
            ['name' => 'شوربة عدس مع خبز', 'ingredients' => 'شوربة عدس كاملة، شريحة خبز أسمر', 'calories' => '400 سعرة'],
        ]
    ],
    'وجبة خفيفة' => [
        'time' => '4:00 - 5:00 مساءً',
        'icon' => '🥤',
        'options' => [
            ['name' => 'عصير طبيعي', 'ingredients' => 'عصير خضروات طازج (كرفس، خيار، سبانخ)', 'calories' => '90 سعرة'],
            ['name' => 'زبادي', 'ingredients' => 'علبة زبادي قليل الدسم', 'calories' => '100 سعرة'],
            ['name' => 'فشار', 'ingredients' => 'فشار بدون زبدة وزيت', 'calories' => '120 سعرة'],
        ]
    ],
    'العشاء' => [
        'time' => '7:00 - 8:00 مساءً',
        'icon' => '🥗',
        'options' => [
            ['name' => 'سلطة تونة', 'ingredients' => 'تونة مصفاة، خس، طماطم، خيار، زيت زيتون', 'calories' => '350 سعرة'],
            ['name' => 'شوربة خضار', 'ingredients' => 'شوربة خضروات مشكلة مع قطع دجاج', 'calories' => '300 سعرة'],
            ['name' => 'جبنة مع خبز', 'ingredients' => 'جبنة قريش، خبز أسمر، خيار وطماطم', 'calories' => '280 سعرة'],
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape_output($page_title); ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/animations.css">
    <style>
        .diet-header {
            background: linear-gradient(135deg, #e65100, #bf360c);
            color: white;
            padding: 2rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .diet-header::before {
            content: '🥗';
            position: absolute;
            right: -20px;
            top: -20px;
            font-size: 100px;
            opacity: 0.15;
        }
        .diet-header h2 { font-size: 1.8rem; margin-bottom: 0.3rem; }
        .diet-header p { opacity: 0.9; }

        .meal-section { margin-bottom: 2rem; }
        .meal-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s;
            border-right: 4px solid #e65100;
        }
        .meal-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .meal-header {
            background: linear-gradient(135deg, #fff3e0, #ffe0b2);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .meal-header h3 { font-size: 1.2rem; color: #333; margin: 0; }
        .meal-header .meal-time { font-size: 0.9rem; color: #e65100; }
        .meal-body { padding: 1.5rem; }

        .meal-options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        .meal-option {
            background: #fafafa;
            border-radius: 10px;
            padding: 1rem;
            border: 1px solid #eee;
            transition: all 0.3s;
        }
        .meal-option:hover {
            background: #f5f5f5;
            border-color: #e65100;
        }
        .meal-option h4 { font-size: 1rem; margin-bottom: 0.5rem; color: #333; }
        .meal-option .ingredients {
            font-size: 0.85rem;
            color: #666;
            margin-bottom: 0.5rem;
            line-height: 1.6;
        }
        .meal-option .calories {
            display: inline-block;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 0.2rem 0.6rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .tips-section {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 2rem 0;
        }
        .tips-section h3 { color: #2e7d32; margin-bottom: 1rem; }
        .tips-section ul {
            list-style: none;
            padding: 0;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.5rem;
        }
        .tips-section ul li {
            padding: 0.4rem 0;
            padding-right: 1.5rem;
            position: relative;
            font-size: 0.95rem;
            color: #333;
        }
        .tips-section ul li::before {
            content: '✓';
            position: absolute;
            right: 0;
            color: #2e7d32;
            font-weight: 700;
        }

        .water-tracker {
            background: linear-gradient(135deg, #e3f2fd, #bbdefb);
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            margin: 2rem 0;
        }
        .water-tracker h3 { color: #1565c0; margin-bottom: 1rem; }
        .water-glasses {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .water-glass {
            width: 40px;
            height: 50px;
            background: rgba(255,255,255,0.5);
            border: 2px solid #90caf9;
            border-radius: 0 0 8px 8px;
            position: relative;
            transition: all 0.3s;
            cursor: pointer;
        }
        .water-glass.filled {
            background: #42a5f5;
            border-color: #1976d2;
        }
        .water-glass span {
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.7rem;
            color: #666;
        }

        @media print {
            .no-print { display: none !important; }
            .meal-card { break-inside: avoid; }
        }

        @media (max-width: 768px) {
            .meal-options-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
    <div class="main-content">
        <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
    <div class="page-content page-entrance">
            <div class="page-header">
                <h1 class="page-title">🥗 <?php echo $page_title; ?></h1>
                <p class="page-subtitle">Dietary Plan for Diabetes Patients</p>
            </div>

                <?php if (!$patient): ?>
                    <div class="empty-state">
                        <div class="empty-icon">👤</div>
                        <h3>اختر مريضاً</h3>
                        <p>يرجى اختيار مريض من قائمة المرضى لعرض خطة غذائية مخصصة</p>
                        <a href="<?php echo BASE_URL; ?>/modules/patients/index.php" class="btn btn-primary">قائمة المرضى</a>
                    </div>
                <?php else: ?>
                    <!-- Diet Header -->
                    <div class="diet-header fade-in">
                        <h2>🥗 خطة غذائية متوازنة</h2>
                        <p>نظام غذائي صحي ومناسب لمريض السكري — مخصص لـ <?php echo escape_output($patient['full_name']); ?></p>
                        <div style="margin-top: 0.8rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                            <span style="background: rgba(255,255,255,0.2); padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.85rem;">
                                👤 <?= $patient['age'] ?> سنة
                            </span>
                            <span style="background: rgba(255,255,255,0.2); padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.85rem;">
                                📅 تاريخ النظام: <?= date('Y-m-d') ?>
                            </span>
                        </div>
                    </div>

                    <!-- Patient Info -->
                    <div class="patient-info-bar" style="background:white; border-radius:12px; padding:1rem 1.5rem; margin-bottom:1.5rem; display:flex; flex-wrap:wrap; gap:1.5rem; align-items:center; box-shadow:0 2px 8px rgba(0,0,0,0.06);">
                        <span><strong>رقم الملف:</strong> <?= escape_output($patient['file_number']) ?></span>
                        <span><strong>الاسم:</strong> <?= escape_output($patient['full_name']) ?></span>
                        <span><strong>الجنس:</strong> <?php echo $patient['gender'] === 'ذكر' ? 'ذكر' : 'أنثى'; ?></span>
                        <span><strong>المدينة:</strong> <?php echo escape_output($patient['city'] ?? ''); ?></span>
                    </div>

                    <!-- Meals -->
                    <?php foreach ($meals as $meal_name => $meal_data): ?>
                    <div class="meal-section fade-in">
                        <div class="meal-card">
                            <div class="meal-header">
                                <div>
                                    <h3><?= $meal_data['icon'] ?> <?= $meal_name ?></h3>
                                </div>
                                <span class="meal-time">⏰ <?= $meal_data['time'] ?></span>
                            </div>
                            <div class="meal-body">
                                <div class="meal-options-grid">
                                    <?php foreach ($meal_data['options'] as $option): ?>
                                    <div class="meal-option">
                                        <h4><?php echo escape_output($option['name']); ?></h4>
                                        <div class="ingredients"><?php echo escape_output($option['ingredients']); ?></div>
                                        <span class="calories">🔥 <?= $option['calories'] ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Tips -->
                    <div class="tips-section fade-in">
                        <h3>💡 نصائح غذائية مهمة</h3>
                        <ul>
                            <li>تناول وجباتك في أوقات منتظمة ولا تتفوّت أي وجبة</li>
                            <li>اشرب الماء قبل الوجبات بنصف ساعة لتحسين الهضم</li>
                            <li>امضغ الطعام جيداً وتناول الطعام ببطء</li>
                            <li>قلل من استخدام الملح واستبدله بالبهارات والأعشاب</li>
                            <li>تجنب الأطعمة المقلية واختر المشوي أو المسلوق</li>
                            <li>راقب كمية الكربوهيدرات في كل وجبة</li>
                            <li>استشر أخصائي التغذية قبل تغيير نظامك الغذائي</li>
                        </ul>
                    </div>

                    <!-- Water Tracker -->
                    <div class="water-tracker fade-in">
                        <h3>💧 تتبع شرب الماء</h3>
                        <p style="margin-bottom: 1rem; color: #444;">
                            احرص على شرب 8 أكواب من الماء يومياً
                        </p>
                        <div class="water-glasses" id="waterTracker">
                            <div class="water-glass" data-index="0"><span>1</span></div>
                            <div class="water-glass" data-index="1"><span>2</span></div>
                            <div class="water-glass" data-index="2"><span>3</span></div>
                            <div class="water-glass" data-index="3"><span>4</span></div>
                            <div class="water-glass" data-index="4"><span>5</span></div>
                            <div class="water-glass" data-index="5"><span>6</span></div>
                            <div class="water-glass" data-index="6"><span>7</span></div>
                            <div class="water-glass" data-index="7"><span>8</span></div>
                        </div>
                        <p style="margin-top: 2rem; font-size: 0.85rem; color: #777;">
                            اضغط على الكأس لتسجيل شرب الماء (يُحفظ في المتصفح)
                        </p>
                    </div>
                <?php endif; ?>
            </div>

            <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
        </main>
    </div>

    <script>
    // Water tracker with localStorage
    (function() {
        const tracker = document.getElementById('waterTracker');
        if (!tracker) return;
        const saved = localStorage.getItem('waterGlasses_' + <?= $patient_id ?: 0 ?>);
        const filled = saved ? JSON.parse(saved) : [];
        const glasses = tracker.querySelectorAll('.water-glass');
        
        function updateGlasses() {
            glasses.forEach((glass, index) => {
                glass.classList.toggle('filled', filled.includes(index));
            });
            localStorage.setItem('waterGlasses_' + <?= $patient_id ?: 0 ?>, JSON.stringify(filled));
        }

        glasses.forEach((glass) => {
            const idx = parseInt(glass.dataset.index);
            glass.addEventListener('click', function() {
                const pos = filled.indexOf(idx);
                if (pos > -1) {
                    filled.splice(pos, 1);
                } else {
                    filled.push(idx);
                }
                updateGlasses();
            });
        });

        updateGlasses();
    })();
    </script>
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
</body>
</html>
