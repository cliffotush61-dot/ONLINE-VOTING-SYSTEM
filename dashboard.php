<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="topbar">
        <div class="brand">Evoting</div>
        <div class="topbar-links">
            <a href="logout.php" class="btn-logout">Sign Out</a>
        </div>
    </div>

    <div class="container">
        <h1 class="page-title">Student Dashboard</h1>
        <p class="page-subtitle">Welcome to the departmental voting portal.</p>

        <div class="preview-box">
            <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($_SESSION['reg_number']); ?></p>
            <p><strong>Department:</strong> <?php echo htmlspecialchars($_SESSION['department']); ?></p>

            <br>

            <?php if ($_SESSION['has_voted'] == 1): ?>
                <div class="notice">
                    You have already voted. Your vote is final and cannot be changed.
                </div>
            <?php else: ?>
                <div class="dashboard-links">
                    <a href="vote.php">Go to Voting Page</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>