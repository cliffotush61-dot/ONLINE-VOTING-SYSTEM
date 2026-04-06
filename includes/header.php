<?php
// Safe session start — prevents duplicate session warnings
require_once __DIR__ . '/app_config.php';
evote_boot_session();

$admin_nav_href = (($_SESSION['role'] ?? '') === 'admin' && isset($_SESSION['admin_id']))
    ? 'admin_dashboard.php'
    : 'admin_login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Voting System</title>
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
</head>
<body>
    <header class="topbar">
        <div class="brand">Secure Voting System</div>
        <div class="topbar-links">
            <a href="index.php">Home</a>
            <a href="index.php#elections">Elections</a>
            <a href="index.php#how-it-works">How It Works</a>
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
            <a href="<?php echo htmlspecialchars($admin_nav_href); ?>">Admin</a>
            <?php
            if (isset($_SESSION['student_id']) || isset($_SESSION['admin_id'])) {
                echo '<a href="logout.php" class="btn-logout">Logout</a>';
            }
            ?>
        </div>
    </header>
    <main>
