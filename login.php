<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/app_config.php';
evote_boot_session();
include 'db.php';

$admin_nav_href = (($_SESSION['role'] ?? '') === 'admin' && isset($_SESSION['admin_id']))
    ? 'admin_dashboard.php'
    : 'admin_login.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (($_SESSION['role'] ?? '') === 'student' && isset($_SESSION['student_id'])) {
        header("Location: vote.php");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reg = trim($_POST['reg_number'] ?? '');
    $pass = (string) ($_POST['password'] ?? '');

    if ($reg === "" || $pass === "") {
        $error = "Invalid username or password.";
        logAuditAction($conn, 'student', null, $reg, 'LOGIN_FAILED', 'Missing login credentials.', 'students', null);
    } else {
        $students = evote_fetch_students_by_reg_number($conn, $reg);
        if (count($students) === 0) {
            logAuditAction($conn, 'student', null, $reg, 'LOGIN_FAILED', 'Student not found or account inactive.', 'students', null);
            $error = "Invalid username or password.";
        } else {
            $matchedUser = null;
            foreach ($students as $candidate) {
                if (!evote_student_is_active($candidate)) {
                    continue;
                }

                $storedPassword = evote_student_password_from_row($candidate);
                if (evote_password_matches($pass, $storedPassword)) {
                    $matchedUser = $candidate;
                    $matchedUser['stored_password'] = $storedPassword;
                    break;
                }
            }

            if ($matchedUser) {
                session_regenerate_id(true);
                evote_clear_auth_session();
                $_SESSION['student_id'] = (int) $matchedUser['id'];
                $_SESSION['reg_number'] = (string) $matchedUser['reg_number'];
                $_SESSION['username'] = (string) $matchedUser['reg_number'];
                $_SESSION['user_id'] = (int) $matchedUser['id'];
                $_SESSION['role'] = 'student';
                $_SESSION['department'] = (string) ($matchedUser['department'] ?? '');
                $_SESSION['has_voted'] = (int) ($matchedUser['has_voted'] ?? 0);
                $_SESSION['student_name'] = (string) ($matchedUser['full_name'] ?? '');

                if (count($students) > 1) {
                    logAuditAction($conn, 'student', (int) $matchedUser['id'], $matchedUser['reg_number'], 'LOGIN_SUCCESS_DUPLICATE_RECORDS', 'Student logged in from a duplicate registration set.', 'students', (int) $matchedUser['id']);
                } else {
                    logAuditAction($conn, 'student', (int) $matchedUser['id'], $matchedUser['reg_number'], 'LOGIN_SUCCESS', 'Student logged in successfully.', 'students', (int) $matchedUser['id']);
                }

                $_SESSION['post_login_notice'] = 'Redirecting to ballot...';
                session_write_close();
                header("Location: vote.php");
                exit();
            }

            logAuditAction($conn, 'student', null, $reg, 'LOGIN_FAILED', 'Password did not match any active student record.', 'students', null);
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login - Secure Voting System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f4f7fb;
            color: #111827;
        }

        .topbar {
            background: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 25px 40px;
            border-bottom: 1px solid #e5e7eb;
            flex-wrap: wrap;
        }

        .brand-wrap h1 {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 6px;
        }

        .brand-wrap p {
            font-size: 14px;
            color: #6b7280;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            margin-top: 8px;
        }

        .nav-links a {
            text-decoration: none;
            color: #111827;
            font-weight: 500;
        }

        .nav-links a:hover {
            color: #2563eb;
        }

        .nav-login {
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            color: #ffffff !important;
            padding: 10px 24px;
            border-radius: 10px;
        }

        .login-section {
            min-height: calc(100vh - 90px);
            background: linear-gradient(135deg, #4f8ef7, #3b6fdc);
            padding: 60px 40px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-wrapper {
            width: 100%;
            max-width: 1250px;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 30px;
            align-items: center;
        }

        .login-left {
            color: #ffffff;
            padding-right: 20px;
        }

        .login-left .eyebrow {
            display: inline-block;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.18);
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 22px;
        }

        .login-left h2 {
            font-size: 54px;
            line-height: 1.12;
            margin-bottom: 18px;
            font-weight: 800;
        }

        .login-left p {
            font-size: 18px;
            line-height: 1.7;
            color: #e7efff;
            max-width: 620px;
            margin-bottom: 20px;
        }

        .feature-list {
            margin-top: 18px;
            display: grid;
            gap: 12px;
        }

        .feature-item {
            background: rgba(255,255,255,0.10);
            border: 1px solid rgba(255,255,255,0.12);
            padding: 14px 16px;
            border-radius: 14px;
            color: #f8fbff;
        }

        .feature-item strong {
            display: block;
            margin-bottom: 4px;
        }

        .login-card {
            background: #ffffff;
            border-radius: 28px;
            padding: 34px;
            box-shadow: 0 24px 50px rgba(0, 0, 0, 0.16);
        }

        .login-card h3 {
            font-size: 32px;
            color: #111827;
            margin-bottom: 8px;
        }

        .login-card .subtext {
            color: #6b7280;
            margin-bottom: 22px;
            line-height: 1.6;
        }

        .error-box {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 8px;
        }

        .input-wrap {
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #d1d5db;
            font-size: 15px;
            outline: none;
            transition: 0.2s ease;
            background: #f9fafb;
        }

        .form-control:focus {
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.10);
        }

        .password-row {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .password-row .form-control {
            flex: 1;
        }

        .toggle-btn {
            border: none;
            background: #e5e7eb;
            color: #111827;
            padding: 14px 16px;
            border-radius: 14px;
            cursor: pointer;
            font-weight: 600;
        }

        .helper-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
            margin: 8px 0 24px;
            font-size: 14px;
            color: #6b7280;
        }

        .helper-row a {
            color: #1d4ed8;
            text-decoration: none;
            font-weight: 600;
        }

        .login-btn {
            width: 100%;
            border: none;
            background: linear-gradient(135deg, #1d4ed8, #1e40af);
            color: #ffffff;
            padding: 16px 22px;
            border-radius: 16px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 12px 22px rgba(29, 78, 216, 0.18);
        }

        .login-btn:hover {
            opacity: 0.96;
        }

        .bottom-note {
            margin-top: 18px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }

        .bottom-note a {
            color: #1d4ed8;
            text-decoration: none;
            font-weight: 700;
        }

        .footer-note {
            margin-top: 26px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }

        @media (max-width: 980px) {
            .login-wrapper {
                grid-template-columns: 1fr;
            }

            .login-left h2 {
                font-size: 40px;
            }
        }

        @media (max-width: 640px) {
            .topbar {
                padding: 20px;
            }

            .login-section {
                padding: 30px 18px;
            }

            .login-card {
                padding: 24px;
                border-radius: 22px;
            }

            .login-left h2 {
                font-size: 32px;
            }

            .nav-links {
                gap: 16px;
            }
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="brand-wrap">
        <h1>Secure Voting System</h1>
        <p>Student Election Portal</p>
    </div>

    <nav class="nav-links">
        <a href="index.php">Home</a>
        <a href="register.php">Register</a>
        <a href="view_results.php">Results</a>
        <a href="<?php echo htmlspecialchars($admin_nav_href); ?>">Admin</a>
    </nav>
</header>

<section class="login-section">
    <div class="login-wrapper">

        <div class="login-left">
            <span class="eyebrow">Student Access</span>
            <h2>Sign in and vote with confidence.</h2>
            <p>
                Enter your registration number and password to continue into the secure campus election portal.
            </p>

            <div class="feature-list">
                <div class="feature-item">
                    <strong>Fast entry</strong>
                    Login only takes a few seconds once your account is active.
                </div>
                <div class="feature-item">
                    <strong>Private session</strong>
                    Your vote journey stays inside your secure student account.
                </div>
                <div class="feature-item">
                    <strong>Clean results</strong>
                    Review the election flow without visual clutter or distraction.
                </div>
            </div>
        </div>

        <div class="login-card">
            <h3>Welcome back</h3>
            <p class="subtext">Use your registration number and password to continue.</p>

            <?php if (!empty($error)) : ?>
                <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="reg_number">Registration Number</label>
                    <div class="input-wrap">
                        <input type="text" id="reg_number" name="reg_number" class="form-control" placeholder="Enter registration number" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-row">
                        <input type="password" id="password" name="password" class="form-control" placeholder="Enter password" required>
                        <button type="button" class="toggle-btn" onclick="togglePassword()">Show</button>
                    </div>
                </div>

                <div class="helper-row">
                    <span>Secure student authentication</span>
                    <a href="register.php">Need an account?</a>
                </div>

                <button type="submit" class="login-btn">Login</button>
            </form>

            <div class="bottom-note">
                <a href="register.php">Create account</a>
            </div>

            <div class="footer-note">
                © 2026 University Election Portal. Secure login for all campus voters.
            </div>
        </div>

    </div>
</section>

<script>
function togglePassword() {
    const passwordInput = document.getElementById("password");
    const toggleBtn = document.querySelector(".toggle-btn");

    if (passwordInput.type === "password") {
        passwordInput.type = "text";
        toggleBtn.textContent = "Hide";
    } else {
        passwordInput.type = "password";
        toggleBtn.textContent = "Show";
    }
}
</script>

</body>
</html>
