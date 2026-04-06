<?php
require_once __DIR__ . '/includes/app_config.php';
evote_boot_session();
$admin_nav_href = (($_SESSION['role'] ?? '') === 'admin' && isset($_SESSION['admin_id']))
    ? 'admin_dashboard.php'
    : 'admin_login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Secure Voting System</title>

<style>

/* RESET */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: "Segoe UI", sans-serif;
    background: #f4f7fb;
}

/* HEADER */
header {
    background: #ffffff;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 25px 50px;
    border-bottom: 1px solid #e5e7eb;
}

.logo h1 {
    font-size: 26px;
    font-weight: 800;
    color: #1e293b;
}

.logo p {
    font-size: 13px;
    color: #6b7280;
}

/* NAV */
nav {
    display: flex;
    align-items: center;
    gap: 25px;
}

nav a {
    text-decoration: none;
    color: #111827;
    font-weight: 500;
}

nav a:hover {
    color: #2563eb;
}

/* LOGIN BUTTON */
.login-btn {
    background: linear-gradient(135deg,#1d4ed8,#1e40af);
    color: white;
    padding: 10px 25px;
    border-radius: 10px;
}

/* HERO */
.hero {
    background: linear-gradient(135deg,#4f8ef7,#3b6fdc);
    padding: 80px 50px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* LEFT */
.hero-left {
    max-width: 600px;
}

.hero-left h2 {
    font-size: 48px;
    color: white;
    font-weight: 800;
    margin-bottom: 20px;
}

.hero-left p {
    font-size: 18px;
    color: #e5edff;
    margin-bottom: 30px;
}

/* BUTTON */
.cta-btn {
    background: #ef4444;
    color: white;
    padding: 15px 30px;
    border-radius: 10px;
    font-weight: bold;
    text-decoration: none;
}

/* RIGHT CARD */
.hero-card {
    background: #e5eaf2;
    width: 400px;
    height: 300px;
    border-radius: 25px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* SIMPLE ICON */
.vote-icon {
    font-size: 80px;
}

/* RESPONSIVE */
@media (max-width: 900px) {
    .hero {
        flex-direction: column;
        gap: 30px;
    }

    .hero-left h2 {
        font-size: 34px;
    }

    header {
        flex-direction: column;
        gap: 10px;
    }
}

</style>

</head>
<body>

<header>
    <div class="logo">
        <h1>Secure Voting System</h1>
        <p>University Election Portal</p>
    </div>

    <nav>
        <a href="index.php">Home</a>
        <a href="register.php">Register</a>
        <a href="view_results.php">Results</a>
        <a href="login.php" class="login-btn">Login</a>
        <a href="<?php echo htmlspecialchars($admin_nav_href); ?>">Admin</a>
    </nav>
</header>

<section class="hero">

    <div class="hero-left">
        <h2>Secure campus elections with a live voting platform.</h2>
        <p>
            Register, sign in, and cast your vote from one secure election portal built for campus voting.
        </p>

        <a href="register.php" class="cta-btn">Create Account</a>
    </div>

    <div class="hero-card">
        <div class="vote-icon">🗳️</div>
    </div>

</section>

</body>
</html>
