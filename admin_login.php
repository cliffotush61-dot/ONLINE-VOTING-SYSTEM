<?php
session_start();
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $result = mysqli_query($conn, "SELECT * FROM admin WHERE username='$username'");
    $admin = mysqli_fetch_assoc($result);

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin'] = $admin['username'];
        header("Location: admin_dashboard.php");
        exit();
    } else {
        echo "Invalid admin login.";
    }
}
?>

<form method="POST">
    <h2>Admin Login</h2>
    <input type="text" name="username" placeholder="Username" required><br><br>
    <input type="password" name="password" placeholder="Password" required><br><br>
    <button type="submit">Login</button>
</form>