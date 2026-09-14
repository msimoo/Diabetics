<?php
/**
 * Login Page
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/helpers.php';

// If already logged in, redirect to dashboard
if (is_logged_in()) {
    redirect(BASE_URL . '/modules/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    // Verify CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($token)) {
        $error = '❌ طلب غير مصرح به - Invalid token';
    } else {
        $username = sanitize_input($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = '❌ يرجى إدخال اسم المستخدم وكلمة المرور';
        } else {
            $stmt = $mysqli->prepare("SELECT user_id, username, password_hash, full_name, role, is_active FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

            if ($user && password_verify($password, $user['password_hash'])) {
                if (!$user['is_active']) {
                    $error = '❌ هذا الحساب غير نشط - Contact admin';
                } else {
                    login_user($user['user_id'], $user['role'], $user['full_name']);
                    
                    // Update last login
                    $stmt = $mysqli->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $stmt->bind_param('i', $user['user_id']);
                    $stmt->execute();

                    // Redirect
                    $redirect = $_SESSION['redirect_after'] ?? (BASE_URL . '/modules/dashboard.php');
                    unset($_SESSION['redirect_after']);
                    redirect($redirect);
                }
            } else {
                $error = '❌ اسم المستخدم أو كلمة المرور غير صحيحة';
            }
        }
    }
}

// Generate fresh CSRF token for the form
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول | <?php echo SITE_NAME_EN; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Tajawal', 'Cairo', sans-serif;
            background: linear-gradient(145deg, #080e1c 0%, #10162e 35%, #1a1538 65%, #0b0f1f 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
        }

        /* Animated aurora background */
        body::before {
            content: '';
            position: absolute;
            top: -60%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: 
                radial-gradient(ellipse at 25% 30%, rgba(56, 189, 248, 0.07) 0%, transparent 45%),
                radial-gradient(ellipse at 75% 60%, rgba(168, 85, 247, 0.07) 0%, transparent 45%),
                radial-gradient(ellipse at 50% 80%, rgba(59, 130, 246, 0.04) 0%, transparent 50%);
            animation: auroraDrift 25s ease-in-out infinite alternate;
            z-index: 0;
        }

        @keyframes auroraDrift {
            0% { transform: translate(-2%, -1%) rotate(0deg); opacity: 0.7; }
            33% { transform: translate(0%, 2%) rotate(1deg); opacity: 1; }
            66% { transform: translate(2%, -1%) rotate(-1deg); opacity: 0.8; }
            100% { transform: translate(-1%, 0%) rotate(0.5deg); opacity: 0.9; }
        }

        /* Grid pattern */
        body::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: 
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 48px 48px;
            z-index: 0;
        }

        /* Floating orbs */
        .floating-shapes {
            position: absolute;
            inset: 0;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .floating-shapes span {
            position: absolute;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,255,255,0.04) 0%, transparent 70%);
            animation: orbFloat 18s ease-in-out infinite;
        }
        .floating-shapes span:nth-child(1) { width: 320px; height: 320px; top: -8%; right: -6%; animation-delay: 0s; animation-duration: 22s; }
        .floating-shapes span:nth-child(2) { width: 240px; height: 240px; bottom: -5%; left: -5%; animation-delay: -4s; animation-duration: 18s; }
        .floating-shapes span:nth-child(3) { width: 160px; height: 160px; top: 35%; right: 8%; animation-delay: -8s; animation-duration: 15s; }
        .floating-shapes span:nth-child(4) { width: 120px; height: 120px; bottom: 25%; left: 15%; animation-delay: -12s; animation-duration: 20s; }
        .floating-shapes span:nth-child(5) { width: 80px; height: 80px; top: 15%; left: 25%; animation-delay: -6s; animation-duration: 16s; }

        @keyframes orbFloat {
            0%, 100% { transform: translateY(0) scale(1); opacity: 0.2; }
            50% { transform: translateY(-25px) scale(1.15); opacity: 0.5; }
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 420px;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-radius: 24px;
            box-shadow: 0 0 0 1px rgba(255,255,255,0.08), 0 30px 80px rgba(0,0,0,0.35), 0 10px 24px rgba(0,0,0,0.15);
            padding: 48px 40px 40px;
            position: relative;
            animation: cardAppear 0.8s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #0ea5e9, #3b82f6, #8b5cf6, #0a7e6e, #0ea5e9);
            background-size: 300% 100%;
            animation: gradientBar 4s ease infinite;
            z-index: 2;
        }

        .login-container::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 24px;
            padding: 1px;
            background: linear-gradient(135deg, rgba(10,126,110,0.12), rgba(201,168,76,0.08));
            -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            -webkit-mask-composite: xor;
            mask-composite: exclude;
            pointer-events: none;
            z-index: 1;
        }

        @keyframes gradientBar {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        @keyframes cardAppear {
            from { opacity: 0; transform: translateY(40px) scale(0.96); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .logo { 
            text-align: center; 
            margin-bottom: 32px;
            animation: fadeSlideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) 0.15s both;
        }
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo-icon {
            width: 76px;
            height: 76px;
            margin: 0 auto 18px;
            background: linear-gradient(135deg, #0a7e6e 0%, #13a896 50%, #c9a84c 100%);
            border-radius: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 12px 32px rgba(10, 126, 110, 0.3);
            animation: logoPulse 3s ease-in-out infinite;
        }
        @keyframes logoPulse {
            0%, 100% { box-shadow: 0 12px 32px rgba(10, 126, 110, 0.3); transform: translateY(0); }
            50% { box-shadow: 0 16px 48px rgba(10, 126, 110, 0.45); transform: translateY(-2px); }
        }

        .logo h1 {
            color: #0f172a;
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 4px;
        }
        .logo p {
            color: #64748b;
            font-size: 14px;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-error {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            color: #dc2626;
            border-right: 4px solid #ef4444;
            animation: shake 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-6px); }
            40% { transform: translateX(6px); }
            60% { transform: translateX(-4px); }
            80% { transform: translateX(4px); }
        }

        .form-group {
            position: relative;
            margin-bottom: 20px;
        }
        .form-group input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 15px;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
            background: #f8fafc;
            color: #0f172a;
            outline: none;
        }
        .form-group input:focus {
            border-color: #0a7e6e;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(10, 126, 110, 0.1);
        }
        .form-group input::placeholder { color: #94a3b8; }
        .form-group .input-icon {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 16px;
            transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .form-group:focus-within .input-icon {
            color: #0a7e6e;
            transform: translateY(-50%) scale(1.1);
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #0a7e6e 0%, #13a896 50%, #0a7e6e 100%);
            background-size: 200% 100%;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-family: 'Cairo', sans-serif;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 16px rgba(10, 126, 110, 0.25);
        }
        .login-btn:hover {
            background-position: 100% 0;
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(10, 126, 110, 0.4);
        }
        .login-btn:active {
            transform: translateY(-1px) scale(0.99);
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }
        .login-footer .brand-text {
            color: #94a3b8;
            font-size: 12px;
        }

        @media (max-width: 480px) {
            .login-wrapper { padding: 12px; }
            .login-container { padding: 32px 24px 28px; }
            .logo h1 { font-size: 20px; }
            .logo-icon { width: 64px; height: 64px; }
        }
    </style>
</head>
<body>
    <div class="floating-shapes">
        <span></span><span></span><span></span><span></span><span></span>
    </div>

    <div class="login-wrapper">
        <div class="login-container">
            <div class="logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 48 48" fill="none" width="32" height="32">
                        <rect x="20" y="8" width="8" height="32" rx="4" fill="#fff"/>
                        <rect x="8" y="20" width="32" height="8" rx="4" fill="#fff"/>
                        <circle cx="24" cy="24" r="4" fill="rgba(255,255,255,0.6)"/>
                    </svg>
                </div>
                <h1>مركز سري للغدد الصماء والسكري</h1>
                <p>Sari Endocrinology & Diabetes Center</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="post" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <div class="form-group">
                    <span class="input-icon">👤</span>
                    <input type="text" name="username" placeholder="اسم المستخدم" required autocomplete="username">
                </div>
                <div class="form-group">
                    <span class="input-icon">🔒</span>
                    <input type="password" name="password" id="passwordField" placeholder="كلمة المرور" required autocomplete="current-password">
                </div>
                <button type="submit" name="login" class="login-btn">
                    تسجيل الدخول
                </button>
            </form>

            <div class="login-footer">
                <div class="brand-text">© <?php echo date('Y'); ?> Sari Endocrinology & Diabetes Center</div>
            </div>
        </div>
    </div>
</body>
</html>
