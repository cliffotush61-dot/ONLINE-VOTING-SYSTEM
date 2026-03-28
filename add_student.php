<?php
include 'db.php';

$message = "";

$departments = [
    "Department of Education",
    "Department of Commercial Law",
    "Department of Community Health",
    "Department of Computing and Technology",
    "Department of Nursing",
    "Department of Business Administration",
    "Department of Accountancy",
    "Department of Human Resource Management",
    "Department of Public Health",
    "Department of Agriculture",
    "Department of Environmental Studies",
    "Department of Criminology",
    "Department of Social Work",
    "Department of Procurement and Logistics"
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reg_number = trim($_POST['reg_number']);
    $full_name = trim($_POST['full_name']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $department = trim($_POST['department']);

    if (empty($reg_number) || empty($full_name) || empty($department)) {
        $message = "All fields are required.";
    } else {
        $safe_reg = mysqli_real_escape_string($conn, $reg_number);
        $safe_name = mysqli_real_escape_string($conn, $full_name);
        $safe_department = mysqli_real_escape_string($conn, $department);

        $check = mysqli_query($conn, "SELECT * FROM students WHERE reg_number = '$safe_reg'");

        if (mysqli_num_rows($check) > 0) {
            $message = "A student with that registration number already exists.";
        } else {
            $sql = "INSERT INTO students (reg_number, full_name, password, department, has_voted, is_active)
                    VALUES ('$safe_reg', '$safe_name', '$password', '$safe_department', 0, 1)";

            if (mysqli_query($conn, $sql)) {
                $message = "Student added successfully.";
            } else {
                $message = "Error: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Student</title>
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
    <h2>Add Student</h2>

    <p><?php echo $message; ?></p>

    <form method="POST">
        <label>Registration Number:</label><br>
        <input type="text" name="reg_number" required><br><br>

        <label>Full Name:</label><br>
        <input type="text" name="full_name" required><br><br>

        <label>Temporary Password:</label><br>
        <input type="password" name="password" required><br><br>

        <label>Department:</label><br>
        <select name="department" required>
            <option value="">Select Department</option>
            <?php foreach ($departments as $dept): ?>
                <option value="<?php echo htmlspecialchars($dept); ?>">
                    <?php echo htmlspecialchars($dept); ?>
                </option>
            <?php endforeach; ?>
        </select><br><br>

        <button type="submit" class="primary-btn">Add Student</button>
    </form>
</div>

</body>
</html>