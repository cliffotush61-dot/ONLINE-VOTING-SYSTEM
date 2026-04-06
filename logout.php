<?php
require_once __DIR__ . '/includes/app_config.php';
evote_boot_session();
include 'db.php';
require_once __DIR__ . '/includes/system_helpers.php';

// Determine if admin or student logged in
$is_admin = isset($_SESSION['admin_id']);
$is_student = isset($_SESSION['student_id']);

if ($is_admin) {
    logAuditAction($conn, 'admin', (int) $_SESSION['admin_id'], (string) ($_SESSION['admin_username'] ?? 'admin'), 'LOGOUT', 'Administrator logged out.', 'admins', (int) $_SESSION['admin_id']);
}

if ($is_student) {
    logAuditAction($conn, 'student', (int) $_SESSION['student_id'], (string) ($_SESSION['reg_number'] ?? ''), 'LOGOUT', 'Student logged out.', 'students', (int) $_SESSION['student_id']);
}

// Destroy session
evote_clear_auth_session();
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect to appropriate login page
if ($is_admin) {
    header("Location: admin_login.php");
} else {
    header("Location: login.php");
}
exit();
?>
