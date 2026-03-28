<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db.php';

if (!isset($_GET['id'])) {
    die("Candidate not found.");
}

$id = (int)$_GET['id'];
$result = mysqli_query($conn, "SELECT * FROM candidates WHERE id = $id");
$candidate = mysqli_fetch_assoc($result);

if (!$candidate) {
    die("Candidate not found.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Candidate Manifesto</title>
</head>
<body>
    <h2><?php echo htmlspecialchars($candidate['full_name']); ?></h2>
    <p><strong>Department:</strong> <?php echo htmlspecialchars($candidate['department']); ?></p>
    <p><strong>Position:</strong> <?php echo htmlspecialchars($candidate['position']); ?></p>
    <p><strong>Manifesto:</strong></p>
    <p><?php echo nl2br(htmlspecialchars($candidate['manifesto'])); ?></p>

    <a href="vote.php">Back to Voting Page</a>
</body>
</html>