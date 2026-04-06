<?php
require_once __DIR__ . '/includes/app_config.php';
evote_boot_session();
include 'db.php';

$error = "";
$post_login_notice = "";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (($_SESSION['role'] ?? '') === 'admin' && isset($_SESSION['admin_id'])) {
        header("Location: admin_dashboard.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if ($username === "" || $password === "") {
        $error = "Invalid username or password.";
        logAuditAction($conn, 'admin', null, $username, 'LOGIN_FAILED', 'Missing admin login credentials.', 'admins', null);
    } else {
        $query = "SELECT * FROM admins WHERE username = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $query);
        if (!$stmt) {
            $error = "Invalid username or password.";
        } else {
            mysqli_stmt_bind_param($stmt, "s", $username);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            if ($result && mysqli_num_rows($result) > 0) {
                $admin = mysqli_fetch_assoc($result);
                $stored_hash = $admin['password_hash'] ?? ($admin['password'] ?? '');

                if (evote_password_matches($password, (string) $stored_hash)) {
                    session_regenerate_id(true);
                    evote_clear_auth_session();
                    $_SESSION['admin_id'] = (int) $admin['id'];
                    $_SESSION['admin_username'] = $admin['username'];
                    $_SESSION['user_id'] = (int) $admin['id'];
                    $_SESSION['username'] = $admin['username'];
                    $_SESSION['role'] = 'admin';
                    logAuditAction($conn, 'admin', (int) $admin['id'], $admin['username'], 'LOGIN_SUCCESS', 'Administrator logged in successfully.', 'admins', (int) $admin['id']);
                    $_SESSION['post_admin_login_notice'] = 'Redirecting to the admin dashboard...';
                    session_write_close();
                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    logAuditAction($conn, 'admin', (int) $admin['id'], $admin['username'], 'LOGIN_FAILED', 'Invalid password.', 'admins', (int) $admin['id']);
                    $error = "Invalid username or password.";
                }
            } else {
                logAuditAction($conn, 'admin', null, $username, 'LOGIN_FAILED', 'Administrator account not found.', 'admins', null);
                $error = "Invalid username or password.";
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Secure Voting System</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 50px;
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .login-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 10px;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        .error-message.show {
            display: block;
        }
        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .login-btn:hover {
            transform: translateY(-2px);
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
        }
        .login-footer a {
            color: #667eea;
            text-decoration: none;
        }
        .login-footer a:hover {
            text-decoration: underline;
        }
        .dashboard-link {
            display: block;
            width: 100%;
            margin-top: 12px;
            padding: 12px;
            border-radius: 5px;
            border: 1px solid #667eea;
            color: #667eea;
            background: #eef2ff;
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
        }
        .dashboard-link:hover {
            background: #e0e7ff;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>Admin Portal</h1>
            <p>Secure Voting System Management</p>
        </div>

        <?php if ($error): ?>
            <div class="error-message show">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required placeholder="Enter admin username">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required placeholder="Enter admin password">
            </div>

            <button type="submit" class="login-btn">Login</button>
        </form>

        <a href="index.php" class="dashboard-link">Back Home</a>

        <div class="login-footer">
            <p>Authorized administrative access only.</p>
        </div>
    </div>
</body>
</html>
