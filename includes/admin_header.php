<?php
require_once __DIR__ . '/app_config.php';
evote_boot_session();
include_once __DIR__ . '/../db.php';

if (empty($allow_public_results) && (($_SESSION['role'] ?? '') !== 'admin' || !isset($_SESSION['admin_id']))) {
    header("Location: admin_login.php");
    exit();
}

$admin_username = $_SESSION['admin_username'] ?? ($_SESSION['username'] ?? 'Admin');
$current_page = basename($_SERVER['PHP_SELF']);
$compact_admin_layout = $compact_admin_layout ?? false;
$allow_public_results = !empty($allow_public_results);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Admin Panel | Secure Voting System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary-color: #667eea;
            --primary-dark: #764ba2;
            --secondary-color: #f093fb;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --background: #f5f7fa;
            --surface: #ffffff;
            --text-primary: #1f2937;
            --text-secondary: #6b7280;
            --border-color: #e5e7eb;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, "Helvetica Neue", sans-serif;
            background: var(--background);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* ===== ADMIN HEADER ===== */
        .admin-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .admin-header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-logo {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .compact-layout .admin-logo {
            display: none;
        }

        .compact-layout .admin-header-left::before {
            content: "Election Results";
            font-size: 24px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .admin-header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .user-badge::before {
            content: "👤";
        }

        .header-actions a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .header-actions a:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* ===== MAIN CONTAINER ===== */
        .admin-container {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        .admin-container.compact-layout {
            display: block;
            min-height: auto;
        }

        .compact-layout .admin-container {
            display: block;
            min-height: auto;
        }

        /* ===== SIDEBAR ===== */
        .admin-sidebar {
            width: 260px;
            background: var(--surface);
            border-right: 1px solid var(--border-color);
            padding: 0;
            overflow-y: auto;
            box-shadow: 1px 0 3px rgba(0, 0, 0, 0.05);
        }

        .admin-container.compact-layout .admin-sidebar {
            display: none;
        }

        .compact-layout .admin-sidebar {
            display: none;
        }

        .sidebar-section {
            padding: 20px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .sidebar-section:last-child {
            border-bottom: none;
        }

        .sidebar-title {
            padding: 15px 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-secondary);
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            border: none;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            margin: 0 10px;
            border-radius: 0 6px 6px 0;
        }

        .sidebar-menu a:hover {
            background: #f3f4f6;
            border-left-color: var(--primary-color);
            color: var(--primary-color);
            transform: translateX(4px);
        }

        .sidebar-menu a.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-left-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 600;
        }

        .sidebar-menu a .icon {
            font-size: 18px;
            min-width: 24px;
        }

        /* ===== MAIN CONTENT ===== */
        .admin-main {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
        }

        .admin-container.compact-layout .admin-main {
            width: 100%;
        }

        .compact-layout .admin-main {
            width: 100%;
        }

        .page-header {
            margin-bottom: 40px;
        }

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .page-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
        }

        /* ===== CONTENT CARD ===== */
        .content-card {
            background: var(--surface);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
        }

        /* ===== FORM STYLES ===== */
        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s ease;
            background: var(--surface);
            color: var(--text-primary);
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
        }

        /* ===== BUTTON STYLES ===== */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: var(--background);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: #f3f4f6;
        }

        .btn-danger {
            background: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success-color);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
            transform: translateY(-2px);
        }

        /* ===== MESSAGE STYLES ===== */
        .message-box {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success-color);
        }

        .message-success::before {
            content: "✓";
            font-weight: bold;
            font-size: 16px;
        }

        .message-error {
            background: #fee2e2;
            color: #7f1d1d;
            border-left: 4px solid var(--danger-color);
        }

        .message-error::before {
            content: "✕";
            font-weight: bold;
            font-size: 16px;
        }

        .message-warning {
            background: #fef3c7;
            color: #78350f;
            border-left: 4px solid var(--warning-color);
        }

        .message-warning::before {
            content: "⚠";
            font-weight: bold;
            font-size: 16px;
        }

        /* ===== TABLE STYLES ===== */
        .table-responsive {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .table thead {
            background: var(--background);
            border-bottom: 2px solid var(--border-color);
        }

        .table th {
            padding: 16px;
            text-align: left;
            font-weight: 700;
            color: var(--text-primary);
        }

        .table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody tr:hover {
            background: #f9fafb;
        }

        .table-actions {
            display: flex;
            gap: 8px;
        }

        .table-actions .btn {
            padding: 6px 12px;
            font-size: 12px;
        }

        /* ===== RESPONSIVE ===== */
        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }

            .admin-sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
                max-height: 200px;
            }

            .sidebar-menu {
                display: flex;
                flex-wrap: wrap;
            }

            .admin-main {
                padding: 25px;
            }

            .page-title {
                font-size: 24px;
            }

            .content-card {
                padding: 20px;
            }

            .admin-header {
                flex-direction: column;
                gap: 15px;
            }

            .admin-header-right {
                width: 100%;
                flex-direction: column;
            }

            .admin-container.compact-layout .admin-sidebar {
                display: none;
            }

            .compact-layout .admin-sidebar {
                display: none;
            }
        }

        /* ===== UTILITIES ===== */
        .text-center {
            text-align: center;
        }

        .mt-20 {
            margin-top: 20px;
        }

        .mb-20 {
            margin-bottom: 20px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .hidden {
            display: none;
        }
    </style>
</head>
<body class="<?php echo $compact_admin_layout ? 'compact-layout' : ''; ?>">

<header class="admin-header">
    <div class="admin-header-left">
        <div class="admin-logo">📊 Admin Panel</div>
    </div>
    <div class="admin-header-right">
        <div class="header-actions">
            <a href="index.php">Home</a>
            <?php if ($allow_public_results): ?>
                <a href="login.php">Login</a>
                <a href="admin_login.php">Admin</a>
            <?php else: ?>
                <div class="user-badge"><?php echo htmlspecialchars($admin_username); ?></div>
                <a href="admin_dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="admin-container">
    <aside class="admin-sidebar">
        <div class="sidebar-section">
            <div class="sidebar-title">👥 Student Management</div>
            <ul class="sidebar-menu">
                <li><a href="add_student.php" class="<?php echo $current_page === 'add_student.php' ? 'active' : ''; ?>"><span class="icon">➕</span>Add Student</a></li>
                <li><a href="manage_students.php" class="<?php echo $current_page === 'manage_students.php' ? 'active' : ''; ?>"><span class="icon">📝</span>Manage Students</a></li>
                <li><a href="import_students.php" class="<?php echo $current_page === 'import_students.php' ? 'active' : ''; ?>"><span class="icon">📥</span>Import Students</a></li>
                <li><a href="delete_student.php" class="<?php echo $current_page === 'delete_student.php' ? 'active' : ''; ?>"><span class="icon">🗑️</span>Delete Student</a></li>
            </ul>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-title">🎯 Candidate Management</div>
            <ul class="sidebar-menu">
                <li><a href="add_candidate.php" class="<?php echo $current_page === 'add_candidate.php' ? 'active' : ''; ?>"><span class="icon">➕</span>Add Candidate</a></li>
                <li><a href="manage_candidate.php" class="<?php echo $current_page === 'manage_candidate.php' ? 'active' : ''; ?>"><span class="icon">📝</span>Manage Candidates</a></li>
                <li><a href="delete_candidate.php" class="<?php echo $current_page === 'delete_candidate.php' ? 'active' : ''; ?>"><span class="icon">🗑️</span>Delete Candidate</a></li>
            </ul>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-title">🗳️ Voting</div>
            <ul class="sidebar-menu">
                <li><a href="view_results.php" class="<?php echo $current_page === 'view_results.php' ? 'active' : ''; ?>"><span class="icon">📊</span>View Results</a></li>
            </ul>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-title">🔒 Security</div>
            <ul class="sidebar-menu">
                <li><a href="tamper_check.php" class="<?php echo $current_page === 'tamper_check.php' ? 'active' : ''; ?>"><span class="icon">🔍</span>Tamper Check</a></li>
                <li><a href="view_audit.php" class="<?php echo $current_page === 'view_audit.php' ? 'active' : ''; ?>"><span class="icon">📋</span>Audit Log</a></li>
            </ul>
        </div>
    </aside>

    <main class="admin-main">
