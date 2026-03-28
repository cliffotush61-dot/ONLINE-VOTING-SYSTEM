<?php
session_start();

if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="topbar">
        <div class="brand">Evoting Admin</div>
        <div class="topbar-links">
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <h1 class="page-title">Admin Dashboard</h1>
        <p class="page-subtitle">Manage candidates, students, and election results.</p>

        <div class="dashboard-links">
            <a href="add_candidate.php">Add Candidate</a>
            <a href="manage_candidate.php">Manage Candidates</a>
            <a href="add_student.php">Add Student</a>
            <a href="manage_students.php">Manage Students</a>
            <a href="import_students.php">Import Students (CSV)</a>
            <a href="view_results.php">View Results</a>
        </div>
    </div>
</body>
</html>