<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $reg = $_POST['reg_number'];
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $dept = $_POST['department'];

    $sql = "INSERT INTO students (reg_number, password, department)
            VALUES ('$reg', '$pass', '$dept')";

    $result = mysqli_query($conn, $sql);

    if ($result) {
        echo "Student registered successfully";
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
</head>
<body>

<h2>Student Registration</h2>

<form method="POST">
    <input type="text" name="reg_number" placeholder="Registration Number" required><br><br>

    <input type="password" name="password" placeholder="Password" required><br><br>

    <select name="department" required>
        <option value="">Select Department</option>
        <option>Department of Education</option>
        <option>Department of Commercial Law</option>
        <option>Department of Community Health</option>
        <option>Department of Computing and Technology</option>
    </select><br><br>

    <button type="submit">Register</button>
</form>

</body>
</html>