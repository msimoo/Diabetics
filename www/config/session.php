<?php
/**
 * Session Management
 * Handles login, logout, and session validation
 */
require_once __DIR__ . '/constants.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Require login - redirects if not authenticated
 */
function require_login(): void {
    if (!is_logged_in()) {
        $_SESSION['redirect_after'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

/**
 * Log in a user
 */
function login_user(int $user_id, string $role, string $full_name): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['role'] = $role;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
}

/**
 * Log out and destroy session
 */
function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

/**
 * Check user role — accepts string or array of roles
 */
function has_role($roles): bool {
    if (!is_logged_in() || !isset($_SESSION['role'])) return false;
    if (is_string($roles)) $roles = [$roles];
    return in_array($_SESSION['role'], $roles);
}

/**
 * Require specific role(s) — accepts string or array
 */
function require_role($roles): void {
    require_login();
    if (!has_role($roles)) {
        die('❌ لا تملك الصلاحية للوصول إلى هذه الصفحة - Access denied');
    }
}

// Auto-logout on session timeout
if (is_logged_in() && (time() - $_SESSION['login_time'] > SESSION_TIMEOUT)) {
    logout_user();
}
?>
