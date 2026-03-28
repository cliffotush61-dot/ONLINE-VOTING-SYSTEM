<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reg = trim($_POST['reg_number']);
    $pass = $_POST['password'];

    $sql = "SELECT * FROM students WHERE reg_number = '$reg' AND is_active = 1";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);

        if (password_verify($pass, $user['password'])) {
            $_SESSION['student_id'] = $user['id'];
            $_SESSION['reg_number'] = $user['reg_number'];
            $_SESSION['department'] = $user['department'];
            $_SESSION['has_voted'] = $user['has_voted'];

            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Student not found.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="topbar">
        <div class="brand">Evoting</div>
    </div>

    <div class="form-card">
        <h2>Student Login</h2>

        <?php if (!empty($error)) : ?>
            <p class="error-message"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="reg_number" placeholder="Registration Number" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit" class="primary-btn">Login</button>
        </form>
    </div>
</body>
</html>