<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

include_once __DIR__ . '/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary-color: #667eea;
            --primary-dark: #764ba2;
            --background: #f5f7fa;
            --surface: #ffffff;
            --text-primary: #1f2937;
            --border-color: #e5e7eb;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
        }

        body {
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--background);
            color: var(--text-primary);
        }

        .admin-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .admin-header h1 {
            font-size: 24px;
            font-weight: 800;
        }

        .admin-header .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .admin-header a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 5px;
            transition: background 0.3s;
        }

        .admin-header a:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .dashboard-container {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        .sidebar {
            width: 260px;
            background: var(--surface);
            border-right: 1px solid var(--border-color);
            padding: 0;
            position: sticky;
            top: 70px;
            height: calc(100vh - 70px);
            overflow-y: auto;
        }

        .sidebar h3 {
            background: var(--background);
            padding: 15px 20px;
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            margin: 0 10px;
            border-radius: 0 6px 6px 0;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: var(--background);
            border-left-color: var(--primary-color);
            color: var(--primary-color);
        }

        .main-content {
            flex: 1;
            padding: 40px;
            overflow-y: auto;
        }

        .page-title {
            font-size: 28px;
            margin-bottom: 30px;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="date"],
        input[type="file"],
        select,
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="number"]:focus,
        input[type="date"]:focus,
        input[type="file"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        button, .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        .btn-secondary {
            background: #6b7280;
            color: white;
        }

        .btn-secondary:hover {
            background: #4b5563;
        }

        .message {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .message.error {
            background: #fee2e2;
            color: #7f1d1d;
            border: 1px solid #fecaca;
        }

        .message.warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .table thead {
            background: var(--background);
        }

        .table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid var(--border-color);
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
        }

        .table tbody tr:hover {
            background: var(--background);
        }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #7f1d1d;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        @media (max-width: 768px) {
            .dashboard-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                top: 0;
            }

            .main-content {
                padding: 20px;
            }

            .page-title {
                font-size: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="admin-header">
    <h1>📊 Admin Panel</h1>
    <div class="user-info">
        <span>Welcome, <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong></span>
        <a href="index.php">Home</a>
        <a href="logout.php">Logout</a>
    </div>
</div>

<div class="dashboard-container">
    <div class="sidebar">
        <h3>👥 Students</h3>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php">📊 Dashboard</a></li>
            <li><a href="add_student.php">➕ Add Student</a></li>
            <li><a href="manage_students.php">📝 Manage Students</a></li>
            <li><a href="import_students.php">📥 Import Students</a></li>
            <li><a href="delete_student.php">🗑️ Delete Student</a></li>
        </ul>

        <h3>🎯 Candidates</h3>
        <ul class="sidebar-menu">
            <li><a href="add_candidate.php">➕ Add Candidate</a></li>
            <li><a href="manage_candidate.php">📝 Manage Candidates</a></li>
            <li><a href="delete_candidate.php">🗑️ Delete Candidate</a></li>
        </ul>

        <h3>🗳️ Voting</h3>
        <ul class="sidebar-menu">
            <li><a href="view_results.php">📊 View Results</a></li>
        </ul>

        <h3>🔒 Security</h3>
        <ul class="sidebar-menu">
            <li><a href="tamper_check.php">🔍 Tamper Check</a></li>
            <li><a href="view_audit.php">📋 Audit Log</a></li>
        </ul>
    </div>

    <div class="main-content">
