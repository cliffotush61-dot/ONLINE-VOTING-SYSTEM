<?php
include 'db.php';

$message = "";
$message_class = "";
$student = null;

if (isset($_POST['search'])) {
    $reg = trim($_POST['reg_number']);
    $result = mysqli_query($conn, "SELECT * FROM students WHERE reg_number='$reg'");

    if (mysqli_num_rows($result) > 0) {
        $student = mysqli_fetch_assoc($result);
    } else {
        $message = "Student not found.";
        $message_class = "message-error";
    }
}

if (isset($_POST['add_candidate'])) {

    $reg = trim($_POST['reg_number']);
    $position = $_POST['position'];
    $gender = $_POST['gender'];
    $manifesto = $_POST['manifesto'];

    $student_query = mysqli_query($conn, "SELECT * FROM students WHERE reg_number='$reg'");
    $student = mysqli_fetch_assoc($student_query);

    if (!$student) {
        $message = "Invalid student.";
        $message_class = "message-error";
    } else {

        $name = $student['full_name'];
        $department = $student['department'];

        $check = mysqli_query($conn, "SELECT * FROM candidates WHERE reg_number='$reg'");

        if (mysqli_num_rows($check) > 0) {
            $message = "This student is already a candidate.";
            $message_class = "message-error";
        } else {

            if ($position == "Male Delegate" && $gender != "Male") {
                $message = "A Male Delegate candidate must be Male.";
                $message_class = "message-error";
            } elseif ($position == "Female Delegate" && $gender != "Female") {
                $message = "A Female Delegate candidate must be Female.";
                $message_class = "message-error";
            } else {

                $photo = "";

                if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
                    $filename = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "_", $_FILES["photo"]["name"]);
                    $target = "assets/images/" . $filename;

                    if (!is_dir("assets/images")) {
                        mkdir("assets/images", 0777, true);
                    }

                    if (move_uploaded_file($_FILES["photo"]["tmp_name"], $target)) {
                        $photo = $target;
                    }
                }

                $sql = "INSERT INTO candidates (reg_number, full_name, department, position, gender, photo, manifesto)
                        VALUES ('$reg', '$name', '$department', '$position', '$gender', '$photo', '$manifesto')";

                if (mysqli_query($conn, $sql)) {
                    $message = "Candidate added successfully.";
                    $message_class = "message-success";
                    $student = null;
                } else {
                    $message = "Error: " . mysqli_error($conn);
                    $message_class = "message-error";
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Candidate</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="topbar">
    <div class="brand">Evoting Admin</div>
    <div class="topbar-links">
        <a href="admin_dashboard.php" class="btn-home">Dashboard</a>
        <a href="manage_candidate.php" class="btn-home">Manage Candidates</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</div>

<div class="search-card">
    <h2>Add Candidate</h2>

    <?php if (!empty($message)): ?>
        <div class="message-box <?php echo $message_class; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <label>Enter Registration Number</label><br><br>
        <input type="text" name="reg_number" required>
        <button type="submit" name="search" class="secondary-btn">Search Student</button>
    </form>

    <br>

    <?php if ($student): ?>
        <div class="info-box">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
            <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($student['reg_number']); ?></p>
            <p><strong>Department:</strong> <?php echo htmlspecialchars($student['department']); ?></p>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="reg_number" value="<?php echo htmlspecialchars($student['reg_number']); ?>">

            <label>Position</label><br><br>
            <select name="position" required>
                <option value="Male Delegate">Male Delegate</option>
                <option value="Female Delegate">Female Delegate</option>
                <option value="Departmental Delegate">Departmental Delegate</option>
            </select><br><br>

            <label>Gender</label><br><br>
            <select name="gender" required>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select><br><br>

            <label>Manifesto</label><br><br>
            <textarea name="manifesto" rows="5"></textarea><br><br>

            <label>Photo</label><br><br>
            <input type="file" name="photo"><br><br>

            <button type="submit" name="add_candidate" class="primary-btn">Add Candidate</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>