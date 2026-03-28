<?php
// Safe session start — prevents duplicate session warnings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Secure Voting System</title>

    <!-- Correct CSS path -->
    <link rel="stylesheet" type="text/css" href="/voting_system/assets/css/style.css">
<img src="https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQKjG9DpS_XhtP_Km-LpGJXoWESlsIwz07Uo0sHbFYC_w&s" alt="Voting System Logo" style="height:50px;">

</head>

<body>
    <header>
        <h1>Secure Online Voting System</h1>

        <nav>
            <a href="/voting_system/index.php">Home</a> |
            <a href="/voting_system/frontend/register.html">Register</a> |
            <a href="/voting_system/frontend/login.html">Login</a> |
            <a href="/voting_system/frontend/vote.html">Vote</a> |
            <a href="/voting_system/frontend/results.html">Results</a> |
            <a href="/voting_system/frontend/admin_login.html">Admin</a>

            <?php
            // Show logout only when logged in
            if (isset($_SESSION['student_id']) || isset($_SESSION['admin'])) {
                echo ' | <a href="/voting_system/backend/logout.php">Logout</a>';
            }
            ?>
        </nav>
    </header>

    <main>