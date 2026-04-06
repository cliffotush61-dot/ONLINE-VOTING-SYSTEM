 <?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

$error = "";
$success = "";

$departments = [];
$dept_query = mysqli_query($conn, "SELECT * FROM departments ORDER BY department_name ASC");
if ($dept_query) {
    while ($row = mysqli_fetch_assoc($dept_query)) {
        $departments[] = $row['department_name'];
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reg = trim($_POST['reg_number']);
    $full_name = trim($_POST['full_name']);
    $password_raw = $_POST['password'];
    $department = trim($_POST['department']);

    if ($reg === "" || $full_name === "" || $password_raw === "" || $department === "") {
        $error = "All fields are required.";
    } else {
        $check_stmt = mysqli_prepare($conn, "SELECT id FROM students WHERE reg_number = ? LIMIT 1");
        mysqli_stmt_bind_param($check_stmt, 's', $reg);
        mysqli_stmt_execute($check_stmt);
        $check_result = mysqli_stmt_get_result($check_stmt);
        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $error = "A student with that registration number already exists.";
        } else {
            $password = password_hash($password_raw, PASSWORD_DEFAULT);
            $password_column = evote_student_password_column($conn);

            $record_hash = evote_compute_entity_hash([
                'reg_number' => $reg,
                'full_name' => $full_name,
                'password' => $password,
                'department' => $department,
                'email' => '',
                'has_voted' => 0,
                'is_active' => 1
            ]);

            $sql = "INSERT INTO students (reg_number, full_name, {$password_column}, department, has_voted, is_active, record_hash)
                    VALUES (?, ?, ?, ?, 0, 1, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'sssss', $reg, $full_name, $password, $department, $record_hash);

            if (mysqli_stmt_execute($stmt)) {
                $student_id = mysqli_insert_id($conn);
                logAuditAction($conn, 'student', $student_id, $reg, 'STUDENT_CREATED', 'Student registered account created.', 'students', $student_id);
                $success = "Account created successfully. You can now log in.";
            } else {
                $error = mysqli_errno($conn) === 1062 ? "A student with this registration number already exists." : "Error: " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_stmt_close($check_stmt);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Secure Voting System</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
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
        .nav-links a:hover { color: #2563eb; }

        .register-section {
            min-height: calc(100vh - 90px);
            background: linear-gradient(135deg, #4f8ef7, #3b6fdc);
            padding: 60px 40px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .register-wrapper {
            width: 100%;
            max-width: 1250px;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
            gap: 30px;
            align-items: center;
        }
        .register-left {
            color: #ffffff;
            padding-right: 20px;
        }
        .register-left .eyebrow {
            display: inline-block;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.18);
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 22px;
        }
        .register-left h2 {
            font-size: 52px;
            line-height: 1.12;
            margin-bottom: 18px;
            font-weight: 800;
        }
        .register-left p {
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
        .register-card {
            background: #ffffff;
            border-radius: 28px;
            padding: 34px;
            box-shadow: 0 24px 50px rgba(0, 0, 0, 0.16);
        }
        .register-card h3 {
            font-size: 32px;
            color: #111827;
            margin-bottom: 8px;
        }
        .register-card .subtext {
            color: #6b7280;
            margin-bottom: 22px;
            line-height: 1.6;
        }
        .error-box, .success-box {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 18px;
            font-size: 14px;
        }
        .error-box {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .success-box {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #86efac;
        }
        .form-group { margin-bottom: 18px; }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #374151;
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #d1d5db;
            font-size: 15px;
            outline: none;
            background: #f9fafb;
            transition: 0.2s ease;
        }
        .form-control:focus {
            border-color: #2563eb;
            background: #ffffff;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.10);
        }
        .register-btn {
            width: 100%;
            border: none;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: #ffffff;
            padding: 16px 22px;
            border-radius: 16px;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 12px 22px rgba(239, 68, 68, 0.18);
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
        @media (max-width: 980px) {
            .register-wrapper { grid-template-columns: 1fr; }
            .register-left h2 { font-size: 40px; }
        }
        @media (max-width: 640px) {
            .topbar { padding: 20px; }
            .register-section { padding: 30px 18px; }
            .register-card { padding: 24px; border-radius: 22px; }
            .register-left h2 { font-size: 32px; }
            .nav-links { gap: 16px; }
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
        <a href="login.php">Login</a>
        <a href="view_results.php">Results</a>
        <a href="admin_login.php">Admin</a>
    </nav>
</header>

<section class="register-section">
    <div class="register-wrapper">
        <div class="register-left">
            <span class="eyebrow">Student Registration</span>
            <h2>Create your secure voter account.</h2>
            <p>
                Register with your institutional details to access the election portal and vote within your assigned department.
            </p>

            <div class="feature-list">
                <div class="feature-item">
                    <strong>Department-based access</strong>
                    Students can only vote within their registered department.
                </div>
                <div class="feature-item">
                    <strong>Secure account creation</strong>
                    Each account is linked to a unique registration number.
                </div>
                <div class="feature-item">
                    <strong>Election-ready access</strong>
                    Once registered, you can sign in and participate in the ballot process.
                </div>
            </div>
        </div>

        <div class="register-card">
            <h3>Create Account</h3>
            <p class="subtext">Enter your official academic details to join the portal.</p>

            <?php if ($error): ?>
                <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-box"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="reg_number">Registration Number</label>
                    <input type="text" id="reg_number" name="reg_number" class="form-control" placeholder="Enter registration number" required>
                </div>

                <div class="form-group">
                    <label for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" placeholder="Enter full name" required>
                </div>

                <div class="form-group">
                    <label for="department">Department</label>
                    <select id="department" name="department" class="form-control" required>
                        <option value="">Select department</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?php echo htmlspecialchars($department); ?>">
                                <?php echo htmlspecialchars($department); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Create password" required>
                </div>

                <button type="submit" class="register-btn">Create Account</button>
            </form>

            <div class="bottom-note">
                Already registered? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
</section>

</body>
</html>
