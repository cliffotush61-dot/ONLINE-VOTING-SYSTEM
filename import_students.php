<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

if (isset($_POST["import"])) {

    $file = $_FILES['file']['tmp_name'];

    if (($handle = fopen($file, "r")) !== FALSE) {

        $row = 0;

        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

            if ($row == 0) { // skip header
                $row++;
                continue;
            }

            $reg = trim($data[0]);
            $name = trim($data[1]);
            $password = password_hash(trim($data[2]), PASSWORD_DEFAULT);
            $dept = trim($data[3]);

            if (!empty($reg) && !empty($name)) {

                $check = mysqli_query($conn, "SELECT id FROM students WHERE reg_number='$reg'");

                if (mysqli_num_rows($check) == 0) {
                    mysqli_query($conn, "INSERT INTO students 
                        (reg_number, full_name, password, department, has_voted, is_active)
                        VALUES ('$reg', '$name', '$password', '$dept', 0, 1)");
                }
            }
        }

        fclose($handle);
        $success = "Students imported successfully.";
    } else {
        $error = "Unable to read file.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Import Students</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="topbar">
    <div class="brand">Evoting Admin</div>
    <div class="topbar-links">
        <a href="admin_dashboard.php" class="btn-home">Dashboard</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</div>

<div class="form-card">
    <h2>Import Students (CSV)</h2>

    <?php if (!empty($success)) echo "<p class='success-message'>$success</p>"; ?>
    <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>

    <form method="POST" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <button type="submit" name="import" class="primary-btn">Upload CSV</button>
    </form>

    <br>
    <p><strong>Format:</strong> reg_number, full_name, password, department</p>
</div>

</body>
</html>