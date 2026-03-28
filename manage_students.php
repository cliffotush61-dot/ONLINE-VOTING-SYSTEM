<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}

$result = mysqli_query($conn, "SELECT * FROM students ORDER BY department, full_name");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Students</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="topbar">
    <div class="brand">Evoting Admin</div>
    <div class="topbar-links">
        <a href="admin_dashboard.php" class="btn-home">Dashboard</a>
        <a href="add_student.php" class="btn-home">Add Student</a>
        <a href="logout.php" class="btn-logout">Logout</a>
    </div>
</div>

<div class="container">
    <h1 class="page-title">Manage Students</h1>
    <p class="page-subtitle">This is the official voter register for the election.</p>

    <div class="table-wrap">
        <table>
            <tr>
                <th>Registration Number</th>
                <th>Full Name</th>
                <th>Department</th>
                <th>Has Voted</th>
                <th>Active</th>
            </tr>

            <?php while ($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['reg_number']); ?></td>
                <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                <td><?php echo htmlspecialchars($row['department']); ?></td>
                <td class="<?php echo $row['has_voted'] ? 'status-yes' : 'status-no'; ?>">
                    <?php echo $row['has_voted'] ? 'Yes' : 'No'; ?>
                </td>
                <td class="<?php echo $row['is_active'] ? 'status-yes' : 'status-no'; ?>">
                    <?php echo $row['is_active'] ? 'Yes' : 'No'; ?>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>

</body>
</html>