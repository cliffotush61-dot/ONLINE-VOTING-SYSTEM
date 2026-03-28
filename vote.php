<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'db.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$department = $_SESSION['department'];

$check_vote = mysqli_query($conn, "SELECT * FROM votes WHERE student_id = '$student_id'");
if (mysqli_num_rows($check_vote) > 0) {
    echo "<h2 style='padding:30px;'>You have already voted. Voting is irreversible.</h2>";
    exit();
}

$male_candidates = mysqli_query($conn, "SELECT * FROM candidates WHERE department = '$department' AND position = 'Male Delegate'");
$female_candidates = mysqli_query($conn, "SELECT * FROM candidates WHERE department = '$department' AND position = 'Female Delegate'");
$dept_candidates = mysqli_query($conn, "SELECT * FROM candidates WHERE department = '$department' AND position = 'Departmental Delegate'");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Vote</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="topbar">
        <div class="brand">Evoting</div>
        <div class="topbar-links">
            <a href="dashboard.php" class="btn-home">Go Home</a>
            <a href="logout.php" class="btn-logout">Sign Out</a>
        </div>
    </div>

    <div class="container">
        <h1 class="page-title">Voting Portal</h1>
        <p class="page-subtitle"><?php echo htmlspecialchars($department); ?></p>

        <form method="POST" action="preview_vote.php">

            <div class="section-title">Male Delegate</div>
            <div class="cards">
                <?php while($candidate = mysqli_fetch_assoc($male_candidates)): ?>
                    <div class="card">
                        <img src="<?php echo !empty($candidate['photo']) ? htmlspecialchars($candidate['photo']) : 'https://via.placeholder.com/280x240'; ?>" alt="Candidate Photo">
                        <div class="card-content">
                            <h3><?php echo htmlspecialchars($candidate['full_name']); ?></h3>
                            <p><?php echo htmlspecialchars($candidate['department']); ?></p>
                            <a class="btn-manifesto" href="manifesto.php?id=<?php echo $candidate['id']; ?>">View Manifesto</a>
                            <div class="radio-box">
                                <input type="radio" name="male_delegate" value="<?php echo $candidate['id']; ?>" required> Select
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="section-title">Female Delegate</div>
            <div class="cards">
                <?php while($candidate = mysqli_fetch_assoc($female_candidates)): ?>
                    <div class="card">
                        <img src="<?php echo !empty($candidate['photo']) ? htmlspecialchars($candidate['photo']) : 'https://via.placeholder.com/280x240'; ?>" alt="Candidate Photo">
                        <div class="card-content">
                            <h3><?php echo htmlspecialchars($candidate['full_name']); ?></h3>
                            <p><?php echo htmlspecialchars($candidate['department']); ?></p>
                            <a class="btn-manifesto" href="manifesto.php?id=<?php echo $candidate['id']; ?>">View Manifesto</a>
                            <div class="radio-box">
                                <input type="radio" name="female_delegate" value="<?php echo $candidate['id']; ?>" required> Select
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="section-title">Departmental Delegate</div>
            <div class="cards">
                <?php while($candidate = mysqli_fetch_assoc($dept_candidates)): ?>
                    <div class="card">
                        <img src="<?php echo !empty($candidate['photo']) ? htmlspecialchars($candidate['photo']) : 'https://via.placeholder.com/280x240'; ?>" alt="Candidate Photo">
                        <div class="card-content">
                            <h3><?php echo htmlspecialchars($candidate['full_name']); ?></h3>
                            <p><?php echo htmlspecialchars($candidate['department']); ?></p>
                            <a class="btn-manifesto" href="manifesto.php?id=<?php echo $candidate['id']; ?>">View Manifesto</a>
                            <div class="radio-box">
                                <input type="radio" name="departmental_delegate" value="<?php echo $candidate['id']; ?>" required> Select
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <br><br>
            <button type="submit" class="primary-btn">Preview Selection</button>
        </form>
    </div>
</body>
</html>