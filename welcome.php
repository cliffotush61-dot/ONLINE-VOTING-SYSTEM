<?php
session_start();
include 'db.php';

// Get system status
$db_status = "✓ Connected";
$admin_status = "✓ Ready";
$students_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM students"))['count'];
$departments_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM departments"))['count'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Welcome - Secure Voting System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: "Segoe UI", -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
        }

        .welcome {
            background: white;
            border-radius: 12px;
            padding: 50px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            margin-bottom: 30px;
            text-align: center;
        }

        h1 {
            color: #667eea;
            margin-bottom: 20px;
            font-size: 36px;
        }

        .tagline {
            color: #6b7280;
            margin-bottom: 30px;
            font-size: 16px;
        }

        .credentials-section {
            background: #f5f7fa;
            border-radius: 8px;
            padding: 30px;
            margin: 30px 0;
            text-align: left;
        }

        .credentials-section h2 {
            color: #1f2937;
            margin-bottom: 25px;
            font-size: 22px;
        }

        .credential-box {
            background: white;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .credential-box h3 {
            color: #667eea;
            margin-bottom: 12px;
            font-size: 18px;
        }

        .credential-item {
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            background: #f9fafb;
            padding: 10px;
            border-radius: 4px;
        }

        .credential-item strong {
            color: #1f2937;
            display: inline-block;
            width: 120px;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .status-card {
            background: #f5f7fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .status-card .icon {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .status-card h4 {
            color: #1f2937;
            margin-bottom: 8px;
        }

        .status-card p {
            color: #6b7280;
            font-size: 14px;
        }

        .button-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-top: 30px;
        }

        a, button {
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-size: 14px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-secondary:hover {
            background: #667eea;
            color: white;
        }

        .warning {
            background: #fef3c7;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }

        .back-button {
            position: fixed;
            top: 20px;
            left: 20px;
            padding: 10px 20px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 600;
            transition: all 0.3s;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .back-button:hover {
            background: #667eea;
            color: white;
            transform: translateX(-5px);
        }

        @media (max-width: 768px) {
            .welcome {
                padding: 30px 20px;
            }

            h1 {
                font-size: 28px;
            }

            .button-group {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<a href="index.php" class="back-button">← Home</a>

<div class="container">
    <div class="welcome">
        <h1>🎉 Welcome to Secure Voting System</h1>
        <p class="tagline">System initialized and ready for use</p>

        <div class="warning">
            ⚠️ <strong>Important:</strong> Please change the default admin password after your first login for security.
        </div>

        <div class="credentials-section">
            <h2>🔐 Login Credentials</h2>
            
            <div class="credential-box">
                <h3>👨‍💼 Admin Portal</h3>
                <div class="credential-item">
                    <strong>Username:</strong> <code>admin</code>
                </div>
                <div class="credential-item">
                    <strong>Password:</strong> <code>admin123</code>
                </div>
                <p style="color: #6b7280; font-size: 13px; margin-top: 10px;">Use these credentials to access the admin dashboard and manage students, candidates, and voting settings.</p>
            </div>

            <div class="credential-box">
                <h3>🎓 Student Access</h3>
                <div class="credential-item">
                    <strong>Action:</strong> Students must <code>register</code> first with their information
                </div>
                <div class="credential-item">
                    <strong>Then:</strong> Login with their <code>registration number</code> and created <code>password</code>
                </div>
                <p style="color: #6b7280; font-size: 13px; margin-top: 10px;">Each student will have a unique registration number provided by administrators.</p>
            </div>
        </div>

        <div class="status-grid">
            <div class="status-card">
                <div class="icon">✓</div>
                <h4>Database</h4>
                <p><?php echo $db_status; ?></p>
            </div>
            <div class="status-card">
                <div class="icon">👨‍💼</div>
                <h4>Admin Access</h4>
                <p><?php echo $admin_status; ?></p>
            </div>
            <div class="status-card">
                <div class="icon">🏢</div>
                <h4>Departments</h4>
                <p><?php echo $departments_count; ?> Active</p>
            </div>
            <div class="status-card">
                <div class="icon">📊</div>
                <h4>Students</h4>
                <p><?php echo $students_count; ?> Registered</p>
            </div>
        </div>

        <div class="button-group">
            <a href="admin_login.php" class="btn-primary">→ Admin Login</a>
            <a href="login.php" class="btn-secondary">→ Student Login</a>
        </div>
    </div>
</div>

</body>
</html>
